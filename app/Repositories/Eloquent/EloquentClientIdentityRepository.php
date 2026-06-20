<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\ChannelType;
use App\Models\ClientIdentity;
use App\Repositories\Contracts\ClientIdentityRepositoryInterface;

final class EloquentClientIdentityRepository implements ClientIdentityRepositoryInterface
{
    public function findClientId(ChannelType $type, string $identity): ?string
    {
        $clientId = ClientIdentity::query()
            ->where('channel_type', $type->value)
            ->where('identity', $identity)
            ->value('client_id');

        return $clientId !== null ? (string) $clientId : null;
    }

    public function link(string $clientId, ChannelType $type, string $identity): void
    {
        // Уникум (tenant_id, channel_type, identity) — tenant_id проставит BelongsToTenant.
        ClientIdentity::query()->updateOrCreate(
            ['channel_type' => $type->value, 'identity' => $identity],
            ['client_id' => $clientId],
        );
    }
}
