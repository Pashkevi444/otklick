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

    public function reassignClient(string $fromClientId, string $toClientId): void
    {
        ClientIdentity::query()->where('client_id', $fromClientId)->each(function (ClientIdentity $identity) use ($toClientId): void {
            $collision = ClientIdentity::query()
                ->where('client_id', $toClientId)
                ->where('channel_type', $identity->channel_type->value)
                ->where('identity', $identity->identity)
                ->exists();

            if ($collision) {
                $identity->delete(); // у $to уже есть эта идентичность — дубль отбрасываем
            } else {
                $identity->forceFill(['client_id' => $toClientId])->save();
            }
        });
    }
}
