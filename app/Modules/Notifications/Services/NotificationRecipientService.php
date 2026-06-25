<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Channels\Contracts\ChannelsApi;
use App\Modules\Channels\Models\Channel;
use App\Modules\Channels\Telegram\TelegramGateway;
use App\Modules\Notifications\Models\NotificationRecipient;
use App\Modules\Notifications\Repositories\Contracts\NotificationRecipientRepositoryInterface;
use App\Shared\Enums\ChannelType;
use App\Shared\Enums\NotificationChannelType;
use App\Shared\Enums\RecipientRole;
use App\Shared\Models\Tenant;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Управление получателями уведомлений с учётом лимитов тарифа (с оверрайдами).
 * Telegram подключается по диплинку через бот бизнеса.
 */
final readonly class NotificationRecipientService
{
    public function __construct(
        private NotificationRecipientRepositoryInterface $recipients,
        private ChannelsApi $channels,
        private TelegramGateway $telegram,
    ) {}

    /**
     * @param  list<string>  $events  типы событий; [] = все
     */
    public function addEmail(Tenant $tenant, string $email, ?string $label, RecipientRole $role = RecipientRole::Director, array $events = []): NotificationRecipient
    {
        $this->assertWithinLimit($tenant, NotificationChannelType::Email);

        return $this->recipients->create([
            'tenant_id' => $tenant->id,
            'type' => NotificationChannelType::Email->value,
            'value' => $email,
            'label' => $label,
            'is_active' => true,
            'verified_at' => now(),
            'role' => $role->value,
            'events' => $events,
        ]);
    }

    /**
     * Обновляет роль и подписку на типы событий у получателя.
     *
     * @param  list<string>  $events
     */
    public function updatePreferences(NotificationRecipient $recipient, RecipientRole $role, array $events): void
    {
        $this->recipients->update($recipient, ['role' => $role->value, 'events' => $events]);
    }

    /**
     * Заводит ожидающего Telegram-получателя и возвращает диплинк для подключения.
     *
     * @param  list<string>  $events  типы событий; [] = все
     */
    public function startTelegramLink(Tenant $tenant, ?string $label, RecipientRole $role = RecipientRole::Director, array $events = []): string
    {
        $this->assertWithinLimit($tenant, NotificationChannelType::Telegram);

        $channel = $this->channels->forCurrentTenant()
            ->first(fn (Channel $c): bool => $c->type === ChannelType::Telegram && $c->is_active);

        if (! $channel instanceof Channel) {
            throw ValidationException::withMessages([
                'telegram' => 'Сначала подключите Telegram-бота в разделе «Каналы».',
            ]);
        }

        $username = $this->telegram->getMe($channel)['username'] ?? null;

        if (! is_string($username) || $username === '') {
            throw ValidationException::withMessages([
                'telegram' => 'Не удалось получить имя бота. Проверьте подключение Telegram-канала.',
            ]);
        }

        $token = Str::random(24);

        $this->recipients->create([
            'tenant_id' => $tenant->id,
            'type' => NotificationChannelType::Telegram->value,
            'value' => null,
            'label' => $label,
            'is_active' => false,
            'link_token' => $token,
            'role' => $role->value,
            'events' => $events,
        ]);

        return "https://t.me/{$username}?start=notify_{$token}";
    }

    private function assertWithinLimit(Tenant $tenant, NotificationChannelType $type): void
    {
        $features = $tenant->features();
        $limit = $type === NotificationChannelType::Email
            ? $features->maxNotifyEmail
            : $features->maxNotifyTelegram;

        if ($this->recipients->countByType($type) >= $limit) {
            throw ValidationException::withMessages([
                'limit' => "Достигнут лимит получателей «{$type->label()}» для вашего тарифа ({$limit}).",
            ]);
        }
    }
}
