<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\NotificationChannelType;
use App\Models\NotificationRecipient;
use App\Repositories\Contracts\NotificationRecipientRepositoryInterface;
use Illuminate\Support\Collection;

final class EloquentNotificationRecipientRepository implements NotificationRecipientRepositoryInterface
{
    public function forCurrentTenant(): Collection
    {
        return NotificationRecipient::query()->latest()->get();
    }

    public function deliverableForCurrentTenant(): Collection
    {
        return NotificationRecipient::query()
            ->where('is_active', true)
            ->whereNotNull('value')
            ->get();
    }

    public function countByType(NotificationChannelType $type): int
    {
        return NotificationRecipient::query()->where('type', $type->value)->count();
    }

    public function create(array $attributes): NotificationRecipient
    {
        return NotificationRecipient::create($attributes);
    }

    public function findForCurrentTenant(string $id): ?NotificationRecipient
    {
        return NotificationRecipient::query()->find($id);
    }

    public function findByLinkToken(string $token): ?NotificationRecipient
    {
        return NotificationRecipient::query()->where('link_token', $token)->first();
    }

    public function update(NotificationRecipient $recipient, array $attributes): void
    {
        $recipient->forceFill($attributes)->save();
    }

    public function delete(NotificationRecipient $recipient): void
    {
        $recipient->delete();
    }
}
