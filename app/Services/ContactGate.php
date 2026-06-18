<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\BotReply;
use App\DTO\ReplyKeyboard;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Support\EmailExtractor;
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

        // Узнанный вернувшийся клиент — форму не показываем, сразу здороваемся.
        if ($this->hasName($conversation) && $this->hasPhone($conversation)) {
            $this->conversations->markContactsGateDone($conversation);

            return $this->welcome($tenant, "С возвращением, {$conversation->contact_name}! 👋 Рады снова вас видеть. Чем могу помочь?");
        }

        // Самое первое сообщение — это запрос клиента, а не ответ на форму. Просто
        // приветствуем + показываем форму, НЕ вытаскивая «имя» из его вопроса.
        $firstContact = $this->messages->latestOutboundText($conversation) === null;
        if ($firstContact && ! $this->hasPhone($conversation)) {
            return new BotReply(
                "Здравствуйте! 👋 Рады видеть вас в «{$tenant->name}». Чтобы записать вас и помочь, ".
                'подскажите, пожалуйста, ваше имя и телефон (email — по желанию). '.
                'Например: Алексей, +7 999 123-45-67',
                escalate: false,
            );
        }

        // Это ответ на форму — собираем недостающее.
        $phone = PhoneExtractor::analyze($text);
        if (! $this->hasPhone($conversation) && $phone['status'] === 'valid') {
            $this->conversations->setContactPhone($conversation, (string) $phone['phone']);
        }
        if (! $this->hasName($conversation)) {
            $name = $this->extractName($text);
            if ($name !== null) {
                $this->conversations->setContactName($conversation, $name);
            }
        }
        if ($conversation->contact_email === null) {
            $email = EmailExtractor::fromText($text);
            if ($email !== null) {
                $this->conversations->setContactEmail($conversation, $email);
            }
        }

        // Собрали имя + телефон → форма отработала, предлагаем варианты.
        if ($this->hasName($conversation) && $this->hasPhone($conversation)) {
            $this->conversations->markContactsGateDone($conversation);
            $name = (string) $conversation->contact_name;

            return $this->welcome($tenant, "Спасибо, {$name}! 🙌 Чем могу помочь?");
        }

        // Чего-то не хватает — точечно переспрашиваем.
        if (! $this->hasPhone($conversation) && $phone['status'] === 'invalid') {
            return $this->ask('Кажется, номер указан некорректно. 🙈 Пришлите, пожалуйста, российский номер в формате +7 999 123-45-67.');
        }

        if (! $this->hasPhone($conversation)) {
            return $this->ask('Оставьте, пожалуйста, номер телефона для записи — например, +7 999 123-45-67.');
        }

        return $this->ask('Подскажите, пожалуйста, как вас зовут — имя нужно для записи.');
    }

    /** Приветствие + кликабельные варианты действий (на основе услуг/CRM/базы). */
    private function welcome(Tenant $tenant, string $text): BotReply
    {
        return new BotReply($text, escalate: false, keyboard: $this->suggestions());
    }

    /**
     * Варианты-кнопки: запись (если есть автозапись в CRM или ручная), цены и
     * адрес. Нажатие отправляет подпись → обычный разбор (ReplyComposer/BookingFlow).
     */
    private function suggestions(): ReplyKeyboard
    {
        return ReplyKeyboard::grid(['Записаться', 'Цены и услуги', 'Адрес и часы'], 2);
    }

    private function ask(string $text): BotReply
    {
        return new BotReply($text, escalate: false);
    }

    private function hasName(Conversation $conversation): bool
    {
        $name = $conversation->contact_name;

        return $name !== null && $name !== '' && ! in_array($name, self::PLACEHOLDER_NAMES, true);
    }

    private function hasPhone(Conversation $conversation): bool
    {
        return $conversation->contact_phone !== null && $conversation->contact_phone !== '';
    }

    /**
     * Детерминированно достаёт имя из ответа на форму: убираем email, телефон и
     * вводные слова, берём оставшееся имя.
     */
    private function extractName(string $text): ?string
    {
        $t = (string) preg_replace('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/u', ' ', $text);
        $t = (string) preg_replace('/[\d\-+()]{5,}/u', ' ', $t);
        $t = (string) preg_replace('/\b(меня\s+зовут|зовут|это|я|имя|меня)\b/iu', ' ', $t);
        $t = trim((string) preg_replace('/[^\p{L}\s-]+/u', ' ', $t));

        if (preg_match('/\p{L}[\p{L}-]+(?:\s+\p{L}[\p{L}-]+)?/u', $t, $m) !== 1) {
            return null;
        }

        return mb_convert_case(trim($m[0]), MB_CASE_TITLE, 'UTF-8');
    }
}
