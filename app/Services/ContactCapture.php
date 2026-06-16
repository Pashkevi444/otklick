<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Conversation;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Support\PhoneExtractor;

/**
 * Достаёт из входящего сообщения контактные данные клиента и сохраняет их по
 * диалогу: телефон — регуляркой (он однозначен), имя — нейросетью и только если
 * бот в прошлой реплике спрашивал имя (люди отвечают просто «Павел»).
 *
 * Не readonly/final намеренно — это оркестратор, который мокается в юнит-тестах
 * сервисов-вызывателей.
 */
class ContactCapture
{
    /** Значения contact_name, которые считаем «именем ещё нет». */
    private const array PLACEHOLDER_NAMES = [null, '', 'Гость сайта', 'Гость'];

    public function __construct(
        private ConversationRepositoryInterface $conversations,
        private MessageRepositoryInterface $messages,
        private NameDetector $names,
    ) {}

    public function fromInbound(Conversation $conversation, string $text): void
    {
        if ($conversation->contact_phone === null) {
            $phone = PhoneExtractor::fromText($text);
            if ($phone !== null) {
                $this->conversations->setContactPhone($conversation, $phone);
            }
        }

        if (in_array($conversation->contact_name, self::PLACEHOLDER_NAMES, true)) {
            // Сначала явное представление в самом сообщении («меня зовут …»),
            // затем — короткий ответ на вопрос бота «Как вас зовут?» (через LLM).
            $name = $this->names->fromText($text)
                ?? $this->names->fromReply($this->messages->latestOutboundText($conversation), $text);

            if ($name !== null) {
                $this->conversations->setContactName($conversation, $name);
            }
        }
    }
}
