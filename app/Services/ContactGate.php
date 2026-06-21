<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\BotReply;
use App\DTO\ReplyKeyboard;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\CrmConnectionRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Support\BotMenu;
use App\Support\EmailExtractor;
use App\Support\NameValidator;
use App\Support\PhoneExtractor;

/**
 * Контактная форма в начале диалога (единая для всех каналов): нового клиента
 * приветствуем и сразу просим имя + телефон (обязательно) и email (по желанию),
 * со СТРОГОЙ валидацией телефона (короткий/длинный/мусор → просим исправить).
 * Когда контакты собраны — даём кликабельные варианты действий по услугам/CRM.
 *
 * Вернувшегося клиента узнаём (контакты перенеслись из прошлого диалога чата —
 * по чату/телефону/нику) и форму НЕ показываем — здороваемся по имени.
 *
 * Не final/readonly — мокается в юнит-тестах BotResponder.
 */
class ContactGate
{
    private const array PLACEHOLDER_NAMES = ['Гость', 'Гость сайта'];

    public function __construct(
        private readonly ConversationRepositoryInterface $conversations,
        private readonly MessageRepositoryInterface $messages,
        private readonly ClientService $clients,
        private readonly CrmConnectionRepositoryInterface $crm,
    ) {}

    /**
     * Ведёт контактную форму. Возвращает ответ бота, пока форма не отработала;
     * null — контакты есть/клиент узнан, диалог идёт обычным потоком.
     */
    public function handle(Tenant $tenant, Conversation $conversation, string $text): ?BotReply
    {
        if ($conversation->contacts_gate_done) {
            return null;
        }

        $firstContact = $this->messages->latestOutboundText($conversation) === null;
        $phone = PhoneExtractor::analyze($text);

        // Вернувшийся клиент: контакты известны ещё ДО диалога (перенеслись из
        // прошлого диалога чата) — присутствуют на ПЕРВОМ сообщении и НЕ пришли в
        // нём самом. Иначе это новичок — приветствуем по-другому (ниже).
        if ($firstContact && $this->isComplete($conversation) && $phone['status'] !== 'valid') {
            $this->conversations->markContactsGateDone($conversation);

            return $this->welcome($tenant, "С возвращением, {$conversation->displayName()}! 👋 Рады снова вас видеть. Чем могу помочь?");
        }

        // Собираем имя/телефон/email из сообщения. Безопасно даже на первом
        // сообщении: `NameValidator` не даст вопрос/стоп-слово принять за имя.
        $this->capture($conversation, $text, $phone);

        if ($this->isComplete($conversation)) {
            $this->conversations->markContactsGateDone($conversation);

            return $this->welcome($tenant, "Спасибо, {$conversation->displayName()}! 🙌 Чем могу помочь?");
        }

        // Первый контакт нового клиента → тёплое приветствие + запрос недостающего.
        if ($firstContact) {
            return $this->greeting($tenant, $conversation);
        }

        // Дальше — точечный переспрос недостающего.
        if (! $this->hasPhone($conversation) && $phone['status'] === 'invalid') {
            return $this->ask('Кажется, номер указан некорректно. 🙈 Пришлите, пожалуйста, российский номер в формате +7 999 123-45-67.');
        }

        if (! $this->hasPhone($conversation)) {
            return $this->ask('Оставьте, пожалуйста, номер телефона для записи — например, +7 999 123-45-67.');
        }

        return $this->ask('Подскажите, пожалуйста, как вас зовут — имя нужно для записи.');
    }

    /**
     * Собирает контакты из сообщения (телефон/имя/email), не затирая уже
     * заполненное.
     *
     * @param  array{status: string, phone: string|null}  $phone
     */
    private function capture(Conversation $conversation, string $text, array $phone): void
    {
        if (! $this->hasPhone($conversation) && $phone['status'] === 'valid') {
            $this->clients->recordPhone($conversation, (string) $phone['phone']);
        }

        if (! $this->hasName($conversation)) {
            $name = $this->extractName($text);
            if ($name !== null) {
                $this->clients->recordName($conversation, $name);
            }
        }

        if ($conversation->displayEmail() === null) {
            $email = EmailExtractor::fromText($text);
            if ($email !== null) {
                $this->clients->recordEmail($conversation, $email);
            }
        }
    }

    private function isComplete(Conversation $conversation): bool
    {
        return $this->hasName($conversation) && $this->hasPhone($conversation);
    }

    /** Тёплое приветствие нового клиента + запрос недостающего (имя и/или телефон). */
    private function greeting(Tenant $tenant, Conversation $conversation): BotReply
    {
        $ask = match (true) {
            ! $this->hasName($conversation) && ! $this->hasPhone($conversation) => 'подскажите, пожалуйста, ваше имя и телефон (email — по желанию). Например: Алексей, +7 999 123-45-67',
            ! $this->hasName($conversation) => 'подскажите, пожалуйста, как вас зовут.',
            default => 'оставьте, пожалуйста, номер телефона для связи — например, +7 999 123-45-67.',
        };

        return new BotReply("Здравствуйте! 👋 Рады видеть вас в «{$tenant->name}». Чтобы записать вас и помочь, {$ask}", escalate: false);
    }

    /**
     * Приветствие + главное меню бота (кнопки бизнеса + авто-«Записаться» при
     * подключённой записи). Меню пустое → приветствие без кнопок. Нажатие
     * отправляет подпись → обычный разбор (ReplyComposer/BookingFlow/воронки).
     */
    private function welcome(Tenant $tenant, string $text): BotReply
    {
        $menu = BotMenu::effective($tenant, $this->crm->activeForCurrentTenant() !== null);

        return $menu === []
            ? new BotReply($text, escalate: false)
            : new BotReply($text, escalate: false, keyboard: ReplyKeyboard::grid($menu, 2));
    }

    private function ask(string $text): BotReply
    {
        return new BotReply($text, escalate: false);
    }

    private function hasName(Conversation $conversation): bool
    {
        $name = $conversation->displayName();

        return $name !== null && $name !== '' && ! in_array($name, self::PLACEHOLDER_NAMES, true);
    }

    private function hasPhone(Conversation $conversation): bool
    {
        $phone = $conversation->displayPhone();

        return $phone !== null && $phone !== '';
    }

    /**
     * Детерминированно достаёт имя из ответа на форму: убираем email, телефон,
     * вводные слова и приветствия, берём оставшееся имя и валидируем единым
     * `NameValidator` (вопросы/стоп-слова/«Любой мастер» именем НЕ считаются).
     */
    private function extractName(string $text): ?string
    {
        // Слэш-команды мессенджера (/start, /help) — не имя.
        $t = (string) preg_replace('#(?<!\S)/[A-Za-z][\w]*#u', ' ', $text);
        $t = (string) preg_replace('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/u', ' ', $t);
        $t = (string) preg_replace('/[\d\-+()]{5,}/u', ' ', $t);
        // Убираем вводные и приветствия, чтобы «Привет, я Павел» дало «Павел».
        $t = (string) preg_replace('/\b(меня\s+зовут|зовут|это|имя|меня|я|привет|здравствуйте|здравствуй|добрый\s+день|добрый\s+вечер|доброе\s+утро)\b/iu', ' ', $t);
        $t = trim((string) preg_replace('/[^\p{L}\s-]+/u', ' ', $t));

        if (preg_match('/\p{L}[\p{L}-]+(?:\s+\p{L}[\p{L}-]+)?/u', $t, $m) !== 1) {
            return null;
        }

        $name = mb_convert_case(trim($m[0]), MB_CASE_TITLE, 'UTF-8');

        return NameValidator::isPlausible($name, $text) ? $name : null;
    }
}
