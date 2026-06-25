<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Services;

use App\Modules\Bot\Repositories\Contracts\PromptTemplateRepositoryInterface;
use App\Modules\Knowledge\Models\KnowledgeEntry;
use App\Modules\Knowledge\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use App\Shared\DTO\BusinessProfile;
use App\Shared\Llm\Contracts\LlmClient;
use App\Shared\Models\Tenant;
use Throwable;

/**
 * AI-черновик ответа на «пробел бота» (вопрос клиента без ответа в базе знаний).
 * Строит ответ на ДАННЫХ бизнеса (профиль + опубликованная БЗ) и НИШЕВОМ промпте
 * (та же роль/экспертиза, что у бота) — владелец потом проверяет, правит и
 * публикует. Это снимает главный затык маховика пробелов: черновик не пустой.
 *
 * Конкретные факты (цены/адрес/услуги) модель НЕ выдумывает — оставляет
 * плейсхолдеры `[уточните ...]`. При сбое/пустом ответе LLM возвращает '' (тогда
 * черновик создаётся пустым, как раньше — поток не ломается).
 */
final class KnowledgeGapDrafter
{
    public function __construct(
        private readonly LlmClient $llm,
        private readonly PromptTemplateRepositoryInterface $promptTemplates,
        private readonly KnowledgeEntryRepositoryInterface $knowledge,
    ) {}

    public function draft(Tenant $tenant, string $question): string
    {
        $question = trim($question);

        if ($question === '') {
            return '';
        }

        try {
            $answer = trim($this->llm->generate(
                $this->systemPrompt($tenant),
                [['role' => 'user', 'content' => $question]],
            ));
        } catch (Throwable $e) {
            report($e);

            return '';
        }

        // Это запись базы знаний, а не реплика бота — убираем возможные служебные
        // метки [[...]], если модель их добавила.
        return trim((string) preg_replace('/\[\[[A-Z_]+\]\]/u', '', $answer));
    }

    private function systemPrompt(Tenant $tenant): string
    {
        $profile = BusinessProfile::fromArray($tenant->settings['profile'] ?? []);

        $about = $this->about($profile);
        $kb = $this->knowledge->publishedForCurrentTenant()
            ->map(fn (KnowledgeEntry $e): string => '• '.$e->title.': '.$e->content)
            ->implode("\n");

        $niche = $this->promptTemplates->behaviorFor($tenant->business_type);
        $niche = $niche !== null
            ? trim(str_replace(['{{business_name}}', '{{photos_marker}}', '{{clarify_marker}}'], [$tenant->name, '', ''], $niche))
            : '';

        $sections = [];

        $sections[] = "Ты помогаешь администратору бизнеса «{$tenant->name}» заполнить базу знаний бота. ".
            'Клиент задал вопрос, на который у бизнеса пока нет готового ответа. Напиши КОРОТКИЙ понятный '.
            "черновик ответа (2–6 предложений) на русском — владелец потом проверит, поправит и опубликует.\n".
            "Требования:\n".
            '— Это ЗАПИСЬ базы знаний, а не реплика в чате: без приветствий, подписей, призывов «запишитесь» '.
            "и без служебных меток в квадратных скобках вида [[...]].\n".
            '— Опирайся на данные бизнеса ниже. КОНКРЕТНЫЕ факты (цены, адрес, телефон, услуги, акции, часы, '.
            'имена мастеров) НЕ выдумывай: если их нет в данных — оставь явный плейсхолдер в квадратных скобках, '.
            "например «[уточните цену]» или «[укажите адрес]», чтобы владелец вписал.\n".
            '— Можешь добавить полезное общее знание по нише, если оно не противоречит данным.';

        if ($niche !== '') {
            $sections[] = "Ниша и экспертиза бизнеса (используй ту же роль и знание ниши, но пиши именно запись для базы знаний):\n".$niche;
        }

        $sections[] = "Данные бизнеса:\n".($about !== '' ? $about : 'не указаны (используй плейсхолдеры для конкретики)');
        $sections[] = "База знаний бизнеса (не противоречь ей):\n".($kb !== '' ? $kb : 'пока пуста');

        return implode("\n\n", $sections);
    }

    private function about(BusinessProfile $profile): string
    {
        $lines = array_values(array_filter([
            $profile->description !== null && $profile->description !== '' ? "Описание: {$profile->description}" : null,
            $profile->phone !== null && $profile->phone !== '' ? "Телефон: {$profile->phone}" : null,
            $profile->address !== null && $profile->address !== '' ? "Адрес: {$profile->address}" : null,
            $profile->workingHours !== null && $profile->workingHours !== '' ? "Часы работы: {$profile->workingHours}" : null,
            $profile->website !== null && $profile->website !== '' ? "Сайт: {$profile->website}" : null,
        ], fn (?string $l): bool => $l !== null));

        return implode("\n", array_map(fn (string $l): string => "- {$l}", $lines));
    }
}
