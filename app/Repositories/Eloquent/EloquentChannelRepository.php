<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTO\NewChannelData;
use App\Models\Channel;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use Illuminate\Support\Collection;

final class EloquentChannelRepository implements ChannelRepositoryInterface
{
    public function create(NewChannelData $data): Channel
    {
        return Channel::create([
            'tenant_id' => $data->tenantId,
            'type' => $data->type,
            'external_id' => $data->externalId,
            'credentials' => [
                'bot_token' => $data->botToken,
                'secret_token' => $data->secretToken,
            ],
            'is_active' => true,
            'settings' => $data->settings,
        ]);
    }

    public function find(string $id): ?Channel
    {
        return Channel::find($id);
    }

    public function forCurrentTenant(): Collection
    {
        return Channel::query()->latest()->get();
    }

    public function delete(Channel $channel): void
    {
        $channel->delete();
    }
}
