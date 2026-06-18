<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\OwnerNotification;
use App\Enums\OwnerEvent;
use App\Models\Tenant;
use App\Notifications\NotifierResolver;
use App\Repositories\Contracts\NotificationRecipientRepositoryInterface;
use Throwable;

/**
 * Рассылает уведомление о событии всем готовым получателям бизнеса через
 * соответствующие нотификаторы (email/telegram/…). Падение одного получателя
 * не срывает рассылку остальным. Вызывается из очереди (SendOwnerNotification).
 */
final readonly class NotificationService
{
    public function __construct(
        private NotificationRecipientRepositoryInterface $recipients,
        private NotifierResolver $notifiers,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     */
    public function send(Tenant $tenant, OwnerEvent $event, array $context = []): void
    {
        $recipients = $this->recipients->deliverableForCurrentTenant();

        if ($recipients->isEmpty()) {
            return;
        }

        $notification = $this->compose($tenant, $event, $context);

        foreach ($recipients as $recipient) {
            $notifier = $this->notifiers->for($recipient->type);

            if ($notifier === null) {
                continue;
            }

            try {
                $notifier->send($tenant, $recipient, $notification);
            } catch (Throwable $e) {
                report($e);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function compose(Tenant $tenant, OwnerEvent $event, array $context): OwnerNotification
    {
        $lines = [$event->title().'.'];

        $map = [
            'contact' => 'Клиент',
            'phone' => 'Телефон',
            'channel' => 'Источник',
            // Ссылка на аккаунт клиента в мессенджере (VK — vk.com/id…, Telegram —
            // t.me/…), чтобы владелец мог сразу написать клиенту в его канал.
            'profile' => 'Профиль',
            'snippet' => 'Сообщение',
        ];
        foreach ($map as $key => $label) {
            $value = trim((string) ($context[$key] ?? ''));
            if ($value !== '') {
                $lines[] = "{$label}: {$value}";
            }
        }

        $conversationId = (string) ($context['conversationId'] ?? '');
        if ($conversationId !== '') {
            $lines[] = 'Диалог: '.$this->conversationLink($conversationId);
        }

        return new OwnerNotification("«{$tenant->name}»: {$event->title()}", implode("\n", $lines));
    }

    private function conversationLink(string $conversationId): string
    {
        $domain = (string) config('app.business_domain');
        $base = $domain !== '' ? "https://{$domain}" : rtrim((string) config('app.url'), '/');

        return "{$base}/cabinet/conversations/{$conversationId}";
    }
}
