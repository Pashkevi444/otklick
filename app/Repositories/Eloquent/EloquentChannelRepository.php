<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTO\NewChannelData;
use App\Models\Channel;
use App\Repositories\Contracts\ChannelRepositoryInterface;
use Illuminate\Support\Collection;

/**
 * @extends EloquentRepository<Channel>
 */
final class EloquentChannelRepository extends EloquentRepository implements ChannelRepositoryInterface
{
    protected function model(): string
    {
        return Channel::class;
    }

    public function create(NewChannelData $data): Channel
    {
        $credentials = array_filter([
            'bot_token' => $data->botToken,
            'secret_token' => $data->secretToken,
        ], static fn (?string $v): bool => $v !== null);

        return Channel::create([
            'tenant_id' => $data->tenantId,
            'type' => $data->type,
            'external_id' => $data->externalId,
            'credentials' => $credentials,
            'is_active' => true,
            'settings' => $data->settings,
        ]);
    }

    public function find(string $id): ?Channel
    {
        return $this->findById($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Channel $channel, array $attributes): void
    {
        $channel->update($attributes);
    }

    public function forCurrentTenant(): Collection
    {
        return Channel::query()->latest()->get();
    }

    public function delete(Channel $channel): void
    {
        $this->remove($channel);
    }
}
