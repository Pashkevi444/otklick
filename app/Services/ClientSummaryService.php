<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MessageDirection;
use App\Llm\Contracts\LlmClient;
use App\Models\Client;
use App\Repositories\Contracts\MessageRepositoryInterface;
use Throwable;

/**
 * Краткое резюме клиента по переписке (LLM): что хотел, чем интересовался, важные
 * детали и статус. Используется в карточке клиента; пересобирается по записи и
 * вручную кнопкой. При сбое LLM возвращает null (не затираем прежнее резюме).
 */
final readonly class ClientSummaryService
{
    /** Сколько сообщений (суммарно по последним диалогам) подаём в LLM. */
    private const int MESSAGE_LIMIT = 40;

    private const int CONVERSATIONS_LIMIT = 5;

    private const string SYSTEM_PROMPT =
        'Ты — ассистент CRM локального бизнеса. По переписке бизнеса с клиентом составь краткое резюме о КЛИЕНТЕ: '.
        '2–4 предложения — что он хотел, какие услуги/вопросы его интересовали, важные детали и предпочтения, '.
        'текущий статус (записан, думает, отказался и т.п.). Пиши по-русски, по делу, без воды и без markdown. '.
        'Не оценивай работу бота или сервиса. Верни ТОЛЬКО текст резюме.';

    public function __construct(
        private LlmClient $llm,
        private MessageRepositoryInterface $messages,
    ) {}

    public function summarize(Client $client): ?string
    {
        $transcript = $this->transcript($client);

        if ($transcript === '') {
            return null;
        }

        try {
            $summary = trim($this->llm->generate(self::SYSTEM_PROMPT, [['role' => 'user', 'content' => $transcript]]));

            return $summary !== '' ? $summary : null;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    private function transcript(Client $client): string
    {
        $lines = [];

        foreach ($client->conversations()->latest()->limit(self::CONVERSATIONS_LIMIT)->get() as $conversation) {
            foreach ($this->messages->recentForConversation($conversation, self::MESSAGE_LIMIT) as $message) {
                $text = trim((string) $message->text);
                if ($text === '') {
                    continue;
                }

                $role = $message->direction === MessageDirection::Inbound ? 'Клиент' : 'Бот';
                $lines[] = "{$role}: {$text}";
            }
        }

        return implode("\n", array_slice($lines, -self::MESSAGE_LIMIT));
    }
}
