<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Services;

use App\Modules\Clients\Services\ClientService;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Repositories\Contracts\MessageRepositoryInterface;
use App\Shared\Support\NameValidator;
use App\Shared\Support\PhoneExtractor;

/**
 * Достаёт из входящего сообщения контактные данные клиента и пишет их в КАРТОЧКУ
 * клиента (источник правды): телефон — регуляркой (он однозначен), имя — нейросетью
 * и только если бот в прошлой реплике спрашивал имя (люди отвечают просто «Павел»).
 *
 * Не readonly/final намеренно — это оркестратор, который мокается в юнит-тестах
 * сервисов-вызывателей.
 */
class ContactCapture
{
    /** Значения имени, которые считаем «именем ещё нет». */
    private const array PLACEHOLDER_NAMES = ['Гость сайта', 'Гость'];

    public function __construct(
        private MessageRepositoryInterface $messages,
        private NameDetector $names,
        private ClientService $clients,
    ) {}

    public function fromInbound(Conversation $conversation, string $text): void
    {
        // Гарантируем карточку клиента (и узнаём вернувшегося) — до разбора сообщения.
        $this->clients->attachClient($conversation);

        if (! $this->hasPhone($conversation)) {
            $phone = PhoneExtractor::fromText($text);
            if ($phone !== null) {
                $this->clients->recordPhone($conversation, $phone);
            }
        }

        if (! $this->hasName($conversation)) {
            // Имя НЕ берём из ПЕРВОГО сообщения клиента (бот ещё не просил
            // представиться) — первое сообщение почти всегда вопрос, а не имя.
            // Начинаем ловить имя только ПОСЛЕ того, как бот ответил/спросил.
            $lastOutbound = $this->messages->latestOutboundText($conversation);

            // Сначала явное представление в самом сообщении («меня зовут …»),
            // затем — короткий ответ на вопрос бота «Как вас зовут?» (через LLM).
            $name = $lastOutbound === null
                ? null
                : $this->names->fromText($text) ?? $this->names->fromReply($lastOutbound, $text);

            // ВАЖНО: LLM-имя проверяем тем же `NameValidator`, что и гейт, иначе
            // вопрос/стоп-слово («а меня нет в базе?» → «Нет») обходит защиту гейта.
            if ($name !== null && NameValidator::isPlausible($name, $text)) {
                $this->clients->recordName($conversation, $name);
            }
        }
    }

    private function hasPhone(Conversation $conversation): bool
    {
        $phone = $conversation->displayPhone();

        return $phone !== null && $phone !== '';
    }

    private function hasName(Conversation $conversation): bool
    {
        $name = $conversation->displayName();

        return $name !== null && $name !== '' && ! in_array($name, self::PLACEHOLDER_NAMES, true);
    }
}
