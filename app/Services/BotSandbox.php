<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\IncomingMessage;
use App\DTO\SandboxReply;
use App\Enums\ConversationStatus;
use App\Enums\MessageStatus;
use App\Models\Tenant;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Repositories\Contracts\SandboxRepositoryInterface;
use App\Tenancy\TestContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Тестовый прогон бота («песочница»): прогоняет настоящий пайплайн ответа
 * (контактная форма → запись → воронки → база знаний/LLM) по реальным настройкам
 * бизнеса, но в {@see TestContext} — диалог/клиент/сообщения помечаются тестовыми
 * (не попадают в лиды и базу клиентов), а запись в CRM имитируется.
 *
 * Зеркалит {@see IncomingMessageService}, но без отправки в канал и фоновых задач:
 * ответ возвращается прямо в кабинет.
 */
final readonly class BotSandbox
{
    public function __construct(
        private SandboxRepositoryInterface $sandbox,
        private ConversationRepositoryInterface $conversations,
        private MessageRepositoryInterface $messages,
        private ContactCapture $contacts,
        private BotResponder $responder,
        private TestContext $test,
    ) {}

    /**
     * Обработать реплику тестирующего и вернуть ответ бота.
     */
    public function send(Tenant $tenant, string $chatId, string $text): SandboxReply
    {
        return $this->test->run(function () use ($tenant, $chatId, $text): SandboxReply {
            $channel = $this->sandbox->channel();
            $conversation = $this->conversations->firstOrCreateForChat($channel->id, $chatId, null, null);

            $incoming = new IncomingMessage(
                externalChatId: $chatId,
                externalMessageId: (string) Str::uuid(),
                text: $text,
            );
            $this->messages->recordInbound($conversation, $incoming);

            // Пайплайн ответа может бросить (LLM/эмбеддер/CRM/сеть). В тестовом чате
            // это не должно ронять запрос 500-й — ловим, логируем для разбора и
            // показываем понятный ответ (реальных клиентов/CRM это не затрагивает).
            try {
                // Контакты клиента (как в проде, до ответа) — в тестовую карточку.
                $this->contacts->fromInbound($conversation, $text);

                $reply = $this->responder->respond($tenant, $conversation, $text);
            } catch (Throwable $e) {
                Log::error('sandbox.respond_failed', [
                    'tenant_id' => (string) $tenant->id,
                    'conversation_id' => (string) $conversation->id,
                    'text' => $text,
                    'error' => $e->getMessage(),
                    'exception' => $e::class,
                ]);

                $fallback = 'Бот не смог обработать сообщение — что-то пошло не так на стороне сервиса. Мы записали детали для разбора.';
                $this->messages->recordOutbound($conversation, $fallback, MessageStatus::Failed);

                return new SandboxReply(
                    $fallback,
                    note: 'Ошибка обработки (детали в логах). Это тестовый режим — реальных клиентов и записей в CRM не затронуло.',
                );
            }

            $this->messages->recordOutbound($conversation, $reply->text, MessageStatus::Sent);
            $this->conversations->touchLastMessage($conversation);

            // Зеркалим переходы статуса диалога (для многошаговости теста) и поясняем
            // тестирующему, что произошло бы в реальном диалоге.
            $note = null;
            if ($reply->escalate) {
                $this->conversations->updateStatus($conversation, ConversationStatus::NeedsHuman);
                $note = 'Бот передал бы диалог живому оператору (эскалация).';
            } elseif ($reply->booked) {
                $this->conversations->markBooked($conversation);
                $note = 'Запись оформлена в тестовом режиме — в YClients ничего не отправлено.';
            } elseif ($reply->cancelled) {
                $this->responder->cancelBookingInCrm($conversation);
                $this->conversations->markCancelled($conversation);
                $note = 'Запись отменена в тестовом режиме — в YClients ничего не менялось.';
            }

            return new SandboxReply(
                text: $reply->text,
                buttons: $reply->keyboard?->labels() ?? [],
                escalate: $reply->escalate,
                booked: $reply->booked,
                cancelled: $reply->cancelled,
                note: $note,
            );
        });
    }

    /**
     * История текущего тестового диалога (для восстановления при перезагрузке).
     *
     * @return list<array{direction: string, text: string}>
     */
    public function history(string $chatId): array
    {
        return $this->test->run(function () use ($chatId): array {
            $channel = $this->sandbox->channel();
            $conversation = $this->conversations->findActiveForChat($channel->id, $chatId);

            if ($conversation === null) {
                return [];
            }

            return $this->messages->allForConversation($conversation)
                ->map(static fn ($m): array => [
                    'direction' => $m->direction->value,
                    'text' => (string) $m->text,
                ])
                ->values()
                ->all();
        });
    }

    /**
     * Сбросить тестовый диалог — начать прогон заново.
     */
    public function reset(string $chatId): void
    {
        $this->test->run(fn () => $this->sandbox->resetChat($chatId));
    }
}
