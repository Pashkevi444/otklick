<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\IncomingMessage;
use App\DTO\SandboxReply;
use App\Enums\ConversationStatus;
use App\Enums\MessageStatus;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Repositories\Contracts\SandboxRepositoryInterface;
use App\Support\ImageMime;
use App\Tenancy\TestContext;
use App\Vision\Contracts\ImageToText;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
        private ImageToText $vision,
    ) {}

    /**
     * Обработать реплику тестирующего и вернуть ответ бота.
     */
    public function send(Tenant $tenant, string $chatId, string $text): SandboxReply
    {
        return $this->test->run(function () use ($tenant, $chatId, $text): SandboxReply {
            $conversation = $this->conversations->firstOrCreateForChat($this->sandbox->channel()->id, $chatId, null, null);

            $this->messages->recordInbound($conversation, new IncomingMessage(
                externalChatId: $chatId,
                externalMessageId: (string) Str::uuid(),
                text: $text,
            ));

            return $this->respondTo($tenant, $conversation, $text);
        });
    }

    /**
     * Прикреплённое в тесте фото: распознаём (vision) и прогоняем как ввод клиента —
     * чтобы проверить ответ бота по картинке. Vision выключен/не сработал → бот
     * передал бы фото администратору (с пояснением тестирующему).
     *
     * @param  list<array{path: string, url: string}>  $images  сохранённые на диск файлы
     */
    public function sendImage(Tenant $tenant, string $chatId, array $images, string $caption): SandboxReply
    {
        return $this->test->run(function () use ($tenant, $chatId, $images, $caption): SandboxReply {
            $conversation = $this->conversations->firstOrCreateForChat($this->sandbox->channel()->id, $chatId, null, null);
            $urls = array_map(static fn (array $i): string => $i['url'], $images);

            $this->messages->recordInbound($conversation, new IncomingMessage(
                externalChatId: $chatId,
                externalMessageId: (string) Str::uuid(),
                text: $caption,
                raw: ['images' => $images],
            ));

            $recognized = $this->describeImages($images, $caption);

            if ($recognized === null) {
                $ack = 'Спасибо! Получили ваше фото и передали администратору — он скоро ответит.';
                $this->messages->recordOutbound($conversation, $ack, MessageStatus::Sent);
                $this->conversations->updateStatus($conversation, ConversationStatus::NeedsHuman);
                $this->conversations->touchLastMessage($conversation);

                return new SandboxReply(
                    text: $ack,
                    escalate: true,
                    note: 'Распознавание фото выключено или не сработало — в реальном диалоге фото ушло бы администратору. Включите vision (VISION_DRIVER=yandex).',
                    images: $urls,
                );
            }

            return $this->respondTo($tenant, $conversation, $recognized);
        });
    }

    /**
     * Прогоняет ввод через настоящий пайплайн ответа бота и зеркалит переходы
     * статуса/пояснения для тестирующего. Ошибку пайплайна не роняем 500-й.
     */
    private function respondTo(Tenant $tenant, Conversation $conversation, string $input): SandboxReply
    {
        // Пайплайн ответа может бросить (LLM/эмбеддер/CRM/сеть). В тестовом чате
        // это не должно ронять запрос 500-й — ловим, логируем для разбора и
        // показываем понятный ответ (реальных клиентов/CRM это не затрагивает).
        try {
            // Контакты клиента (как в проде, до ответа) — в тестовую карточку.
            $this->contacts->fromInbound($conversation, $input);

            $reply = $this->responder->respond($tenant, $conversation, $input);
        } catch (Throwable $e) {
            Log::error('sandbox.respond_failed', [
                'tenant_id' => (string) $tenant->id,
                'conversation_id' => (string) $conversation->id,
                'text' => $input,
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
            images: $reply->images,
        );
    }

    /**
     * Описывает прикреплённые фото через vision и складывает подпись + описание
     * в ввод клиента. null — vision выключен/не распознал.
     *
     * @param  list<array{path: string, url: string}>  $images
     */
    private function describeImages(array $images, string $caption): ?string
    {
        $descriptions = [];

        foreach ($images as $image) {
            if (! Storage::disk('public')->exists($image['path'])) {
                continue;
            }

            $bytes = (string) Storage::disk('public')->get($image['path']);
            if ($bytes === '') {
                continue;
            }

            $description = $this->vision->describe($bytes, ImageMime::sniff($bytes), $caption);
            if ($description !== null && trim($description) !== '') {
                $descriptions[] = trim($description);
            }
        }

        return $descriptions === [] ? null : ImageRecognitionService::compose($caption, $descriptions);
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
