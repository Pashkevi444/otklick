<?php

declare(strict_types=1);

namespace App\Modules\Bot\Services;

use App\Modules\Bot\Repositories\Contracts\PromptTemplateRepositoryInterface;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Conversations\Repositories\Contracts\ConversationRepositoryInterface;
use App\Modules\Conversations\Repositories\Contracts\MessageRepositoryInterface;
use App\Modules\Identity\DTO\BusinessProfile;
use App\Modules\Knowledge\Models\CrmKnowledgeEntry;
use App\Modules\Knowledge\Models\KnowledgeEntry;
use App\Modules\Knowledge\Repositories\Contracts\CrmKnowledgeRepositoryInterface;
use App\Modules\Knowledge\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use App\Modules\Knowledge\Services\KnowledgeRetriever;
use App\Shared\DTO\BotReply;
use App\Shared\Enums\MessageDirection;
use App\Shared\Llm\Contracts\LlmClient;
use App\Shared\Models\Tenant;
use App\Shared\Support\ImageUrls;
use App\Shared\Support\KnowledgeLinks;
use Illuminate\Support\Collection;

/**
 * Формирует ответ бота: системный промпт (профиль + опубликованная БЗ) + история
 * диалога → LLM.
 *
 * Различает сигналы модели: эскалацию ([[ESCALATE]] — клиент зовёт человека,
 * жалоба), непонятку ([[CLARIFY]] — вопрос неясен / нет ответа в базе) и
 * состоявшуюся запись ([[BOOKED]] — диалог закрывается). На непонятку бот
 * несколько раз переспрашивает (счётчик в диалоге), и только после лимита подряд
 * идущих уточнений диалог уходит на администратора.
 */
class ReplyComposer
{
    private const int HISTORY_LIMIT = 10;

    /** Сколько уточняющих вопросов подряд бот задаёт, прежде чем звать человека. */
    private const int MAX_CLARIFICATIONS = 3;

    /** Сколько релевантных записей знаний подмешивать в промпт (RAG). */
    private const int RAG_TOP_K = 6;

    /**
     * Фразы-маркеры «бот фактически не ответил»: явный отказ помочь или
     * переадресация к человеку. Если такой текст пришёл от LLM БЕЗ служебного
     * сентинела ([[ESCALATE]]/[[CLARIFY]]/…), это всё равно провал — клиент остался
     * без ответа. Список высокоточный: каждая фраза однозначно означает, что вопрос
     * не закрыт. Регистр и ё→е нормализуются перед сравнением.
     *
     * @var list<string>
     */
    private const array NON_ANSWER_MARKERS = [
        'не могу помочь',
        'не могу вам помочь',
        'ничем не могу помочь',
        'не могу ответить на этот вопрос',
        'не могу ответить на ваш вопрос',
        'не могу дать ответ',
        'не могу предоставить такую информацию',
        'не могу предоставить эту информацию',
        'не располагаю информацией',
        'не располагаю такой информацией',
        'не владею информацией',
        'у меня нет информации',
        'у меня нет данных',
        'нет такой информации',
        'затрудняюсь ответить',
        'я не знаю ответа',
        'этого я не знаю',
        'обратитесь к администратору',
        'обратитесь к нашему администратору',
        'обратитесь к менеджеру',
        'уточните у администратора',
        'свяжитесь с администратором',
    ];

    public function __construct(
        private readonly LlmClient $llm,
        private readonly PromptBuilder $prompt,
        private readonly KnowledgeEntryRepositoryInterface $knowledge,
        private readonly MessageRepositoryInterface $messages,
        private readonly ConversationRepositoryInterface $conversations,
        private readonly CrmKnowledgeRepositoryInterface $crmKnowledge,
        private readonly KnowledgeRetriever $retriever,
        private readonly PromptTemplateRepositoryInterface $promptTemplates,
    ) {}

    public function compose(Tenant $tenant, Conversation $conversation, bool $bookingEnabled = false): BotReply
    {
        $profile = BusinessProfile::fromArray($tenant->settings['profile'] ?? []);

        $history = $this->history($conversation);

        // RAG: по вопросу клиента достаём только релевантные записи знаний; если
        // индекс пуст или эмбеддер недоступен — отдаём всю базу (фолбэк).
        // $allPublished — ПОЛНАЯ опубликованная база (для подбора записи под медиа по
        // ответу модели: фото могут просить в follow-up «не дошли примеры», где RAG
        // нужную запись уже не отбирает). $published — то, что уходит в промпт.
        $allPublished = $this->knowledge->publishedForCurrentTenant();
        $published = $allPublished;
        $crm = $this->crmKnowledge->forCurrentTenant();

        // Семантический поиск (RAG) — только если возможность включена тарифом/оверрайдом;
        // иначе в промпт идёт вся база (как и было).
        $retrieved = $tenant->features()->rag
            ? $this->retriever->retrieve($this->lastUserText($history), self::RAG_TOP_K)
            : null;

        if ($retrieved !== null) {
            $published = $published->filter(fn (KnowledgeEntry $e): bool => in_array($e->id, $retrieved['manual'], true))->values();
            $crm = $crm->filter(fn (CrmKnowledgeEntry $e): bool => in_array($e->id, $retrieved['crm'], true))->values();
        }

        // Предварительный кандидат записи для медиа (фолбэк): верхний хит RAG; если
        // RAG недоступен (эмбеддер моргнул / вопрос без текста — голосовое, стикер) —
        // по вхождению заголовка в вопрос. Финально запись уточним ПО ОТВЕТУ модели
        // (ниже) — это устойчивее к транслиту и к промахам ранжира RAG.
        $ragEntry = $retrieved !== null
            ? ($retrieved['manual'] !== [] ? $published->firstWhere('id', $retrieved['manual'][0]) : null)
            : $this->bestTitleMatch($published, $this->lastUserText($history));

        // Если клиент уже известен (узнали по чату/телефону/нику и перенесли
        // контакты) — промпт скажет боту звать по имени и не переспрашивать.
        $knownName = $this->knownName($conversation);
        $phone = $conversation->displayPhone();
        $phoneKnown = $phone !== null && $phone !== '';

        // Диалог уже начат, если бот ранее отвечал (в истории есть реплики
        // ассистента) — тогда промпт запретит здороваться в каждом сообщении.
        $conversationStarted = in_array('assistant', array_column($history, 'role'), true);

        // «Голова» промпта под нишу тенанта (prompt_templates) — иначе дефолт.
        $behavior = $this->promptTemplates->behaviorFor($tenant->business_type);

        $systemPrompt = $this->prompt->build($tenant->name, $profile, $published, $bookingEnabled, $crm, $knownName, $phoneKnown, $conversationStarted, $behavior);

        $answer = trim($this->llm->generate($systemPrompt, $history));

        // Сентинелы ищем в ЛЮБОМ месте ответа (модель не всегда ставит их строго
        // в начало) и вырезаем из видимого текста.

        // Настоящая эскалация (или сбой модели = пустой ответ) — сразу на человека.
        if ($answer === '' || str_contains($answer, PromptBuilder::ESCALATE)) {
            $this->resetStreak($conversation);

            return new BotReply($this->fallback($profile), escalate: true, knowledgeGap: true);
        }

        // Клиент хочет записаться, а у тенанта подключена CRM — запускаем
        // пошаговый мастер записи (BookingFlow) через BotResponder. Текст здесь
        // не важен: BotResponder заменит его на первый шаг мастера.
        if (str_contains($answer, PromptBuilder::BOOK)) {
            $this->resetStreak($conversation);

            return new BotReply('Секунду, подберу для вас время для записи…', escalate: false, startBooking: true);
        }

        // Клиент отменил запись — подтверждаем отмену и закрываем диалог.
        if (str_contains($answer, PromptBuilder::CANCELLED)) {
            $this->resetStreak($conversation);

            $text = $this->stripSentinels($answer);

            return new BotReply(
                $text !== '' ? $text : 'Готово, отменил вашу запись. Если захотите записаться снова — напишите нам.',
                escalate: false,
                cancelled: true,
            );
        }

        // Запись оформлена — подтверждаем клиенту и закрываем диалог.
        if (str_contains($answer, PromptBuilder::BOOKED)) {
            $this->resetStreak($conversation);

            $confirmation = $this->stripSentinels($answer);

            return new BotReply(
                $confirmation !== '' ? $confirmation : $this->defaultBookingConfirmation(),
                escalate: false,
                booked: true,
            );
        }

        // Бот не понял / не нашёл ответ — переспрашиваем, пока не упрёмся в лимит.
        if (str_contains($answer, PromptBuilder::CLARIFY)) {
            $attempts = $this->conversations->bumpClarificationAttempts($conversation);

            if ($attempts >= self::MAX_CLARIFICATIONS) {
                $this->conversations->resetClarificationAttempts($conversation);

                return new BotReply($this->fallback($profile), escalate: true, knowledgeGap: true);
            }

            $question = $this->stripSentinels($answer);

            return new BotReply($question !== '' ? $question : $this->defaultClarification(), escalate: false);
        }

        // Контроль качества: LLM выдала отказ/переадресацию к человеку, но БЕЗ
        // сентинела (DeepSeek/gpt-oss так бывает). Клиент остался без ответа —
        // передаём администратору и фиксируем пробел, иначе такие промахи модели не
        // попадали в «развитие бота». Высокоточный список фраз (NON_ANSWER_MARKERS).
        if ($this->looksLikeNonAnswer($answer)) {
            $this->resetStreak($conversation);

            return new BotReply($this->fallback($profile), escalate: true, knowledgeGap: true);
        }

        // Бот ответил по делу — обнуляем серию непоняток.
        $this->resetStreak($conversation);

        // Целевая запись для медиа — по заголовку, НАЗВАННОМУ в ОТВЕТЕ модели: она
        // берёт каноничное имя записи из промпта («Warrior cut», «mod cut»), что
        // устойчивее к транслиту/опечаткам в вопросе и к промахам ранжира RAG. Ищем
        // по ВСЕЙ базе ($allPublished): на follow-up «не дошли примеры» нужной записи
        // в RAG-отборе уже нет, но модель её называет. Не назвала — кандидат RAG.
        // Фото и ссылки — ТОЛЬКО из неё, в полном объёме, без смешивания записей.
        $mediaEntry = $this->bestTitleMatch($allPublished, $answer) ?? $ragEntry;
        $availableImages = $mediaEntry !== null ? $this->imagesFrom(new Collection([$mediaEntry])) : [];
        $availableLinks = $mediaEntry !== null ? $this->linksFrom(new Collection([$mediaEntry])) : [];

        // Прикрепляем фото, если бот поставил метку [[PHOTOS]] ИЛИ в ответе прямо
        // предлагает примеры/работы (DeepSeek непостоянно ставит метку, но фразу
        // «вот примеры наших работ» пишет надёжно) и у целевой записи есть фото.
        // URL берём ТОЧНЫЕ из данных (LLM их искажает) + ловим пастнутые ботом.
        $wantsPhotos = str_contains($answer, PromptBuilder::PHOTOS)
            || ($availableImages !== [] && $this->answerOffersExamples($answer));
        [$text, $pasted] = ImageUrls::split($this->stripSentinels($answer));
        $images = array_values(array_unique(array_merge($wantsPhotos ? $availableImages : [], $pasted)));
        $finalText = $images === []
            ? $text
            : ($text !== '' ? $text : 'Вот примеры наших работ 👇');

        // Ссылки сработавших записей — отдельным блоком в конце ответа.
        $finalText = KnowledgeLinks::append($finalText, $availableLinks);

        return new BotReply($finalText, escalate: false, images: $images);
    }

    /**
     * Ответ модели — фактический «не ответ»: явный отказ помочь или переадресация к
     * человеку без служебного сентинела (см. {@see self::NON_ANSWER_MARKERS}).
     * Регистр и ё→е нормализуются.
     */
    private function looksLikeNonAnswer(string $answer): bool
    {
        $a = str_replace('ё', 'е', mb_strtolower($answer));

        foreach (self::NON_ANSWER_MARKERS as $marker) {
            if (mb_strpos($a, $marker) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Убирает служебные сентинелы из видимого клиенту текста и подчищает пробелы.
     */
    private function stripSentinels(string $text): string
    {
        $text = str_replace(
            [PromptBuilder::ESCALATE, PromptBuilder::BOOKED, PromptBuilder::BOOK, PromptBuilder::CANCELLED, PromptBuilder::CLARIFY, PromptBuilder::PHOTOS],
            '',
            $text,
        );

        return trim((string) preg_replace('/ {2,}/', ' ', $text));
    }

    /**
     * Точные URL фото примеров работ из записей знаний (до 10 — лимит альбома в
     * мессенджерах). Берём прямо из данных, минуя LLM (она искажает длинные ссылки).
     * Источник — одна целевая запись, поэтому отдаём её фото в полном объёме.
     *
     * @param  Collection<int, KnowledgeEntry>  $entries
     * @return list<string>
     */
    private function imagesFrom(Collection $entries): array
    {
        $urls = [];
        foreach ($entries as $entry) {
            foreach ($entry->images ?? [] as $img) {
                if ($img['url'] !== '') {
                    $urls[] = $img['url'];
                }
            }
        }

        return array_slice(array_values(array_unique($urls)), 0, 10);
    }

    /**
     * Запись, чей заголовок встречается в переданном тексте (вопросе клиента ИЛИ
     * ответе модели) — регистр и ё→е нормализованы. По ОТВЕТУ это надёжно: модель
     * пишет каноничное имя записи из промпта, устойчиво к транслиту/опечаткам в
     * вопросе. Берём запись с самым длинным совпавшим заголовком; не нашли — null.
     *
     * @param  Collection<int, KnowledgeEntry>  $published
     */
    private function bestTitleMatch(Collection $published, string $text): ?KnowledgeEntry
    {
        $q = str_replace('ё', 'е', mb_strtolower(trim($text)));

        if ($q === '') {
            return null;
        }

        $best = null;
        $bestLen = 0;

        foreach ($published as $entry) {
            $title = str_replace('ё', 'е', mb_strtolower(trim((string) $entry->title)));
            $len = mb_strlen($title);

            if ($len >= 3 && $len > $bestLen && mb_strpos($q, $title) !== false) {
                $best = $entry;
                $bestLen = $len;
            }
        }

        return $best;
    }

    /**
     * Ответ модели прямо предлагает показать примеры/работы (страховка на случай,
     * когда DeepSeek забыл метку [[PHOTOS]], но фразу «вот примеры наших работ»
     * написал). Срабатывает только если у целевой записи реально есть фото.
     */
    private function answerOffersExamples(string $answer): bool
    {
        $a = mb_strtolower($answer);

        foreach (['вот примеры', 'примеры наших работ', 'примеры работ', 'наших работ', 'еще раз примеры', 'ещё раз примеры', 'вот фото', 'вот несколько работ'] as $needle) {
            if (mb_strpos($a, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ссылки из найденных записей знаний ({label, url}) — дописываются в ответ.
     *
     * @param  Collection<int, KnowledgeEntry>  $entries
     * @return list<array{label?: string, url?: string}>
     */
    private function linksFrom(Collection $entries): array
    {
        $links = [];
        foreach ($entries as $entry) {
            foreach ($entry->links ?? [] as $link) {
                $links[] = $link;
            }
        }

        return $links;
    }

    /** Имя клиента, если он его называл (а не плейсхолдер «Гость»); иначе null. */
    private function knownName(Conversation $conversation): ?string
    {
        $name = $conversation->displayName();

        return $name !== null && $name !== '' && ! in_array($name, ['Гость', 'Гость сайта'], true)
            ? $name
            : null;
    }

    private function resetStreak(Conversation $conversation): void
    {
        if (($conversation->clarification_attempts ?? 0) > 0) {
            $this->conversations->resetClarificationAttempts($conversation);
        }
    }

    private function defaultClarification(): string
    {
        return 'Подскажите, пожалуйста, чуть подробнее, что именно вас интересует?';
    }

    private function defaultBookingConfirmation(): string
    {
        return 'Готово, записал вас! Будем рады видеть. Если что-то изменится — напишите нам.';
    }

    /**
     * @return list<array{role: 'user'|'assistant', content: string}>
     */
    private function history(Conversation $conversation): array
    {
        // История по ЧАТУ (через все диалоги клиента), чтобы бот помнил прошлое
        // общение — например, оформленную ранее запись — после закрытия диалога.
        return $this->messages->recentForChat((string) $conversation->channel_id, (string) $conversation->external_chat_id, self::HISTORY_LIMIT)
            ->map(fn (Message $m): array => [
                'role' => $m->direction === MessageDirection::Inbound ? 'user' : 'assistant',
                'content' => (string) $m->text,
            ])
            ->all();
    }

    /**
     * Последняя реплика клиента из истории — запрос для семантического поиска.
     *
     * @param  list<array{role: 'user'|'assistant', content: string}>  $history
     */
    private function lastUserText(array $history): string
    {
        foreach (array_reverse($history) as $message) {
            if ($message['role'] === 'user') {
                return $message['content'];
            }
        }

        return '';
    }

    private function fallback(BusinessProfile $profile): string
    {
        $text = 'Спасибо за обращение! Я передал ваш вопрос администратору — он скоро свяжется с вами.';

        if ($profile->phone !== null && $profile->phone !== '') {
            $text .= " Если вопрос срочный — позвоните: {$profile->phone}.";
        }

        return $text;
    }
}
