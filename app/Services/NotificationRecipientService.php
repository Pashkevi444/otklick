<?php

declare(strict_types=1);

namespace App\Services;

use App\Channels\Telegram\TelegramGateway;
use App\Enums\ChannelType;
use App\Enums\NotificationChannelType;
use App\Models\Channel;
use App\Models\NotificationRecipient;
use App\Models\Tenant;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use App\Repositories\Contracts\NotificationRecipientRepositoryInterface;
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
        private ChannelRepositoryInterface $channels,
        private TelegramGateway $telegram,
    ) {}

    public function addEmail(Tenant $tenant, string $email, ?string $label): NotificationRecipient
    {
        $this->assertWithinLimit($tenant, NotificationChannelType::Email);

        return $this->recipients->create([
            'tenant_id' => $tenant->id,
            'type' => NotificationChannelType::Email->value,
            'value' => $email,
            'label' => $label,
            'is_active' => true,
            'verified_at' => now(),
        ]);
    }

    /**
     * Заводит ожидающего Telegram-получателя и возвращает диплинк для подключения.
     */
    public function startTelegramLink(Tenant $tenant, ?string $label): string
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
