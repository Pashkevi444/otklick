<?php

declare(strict_types=1);

namespace App\Modules\Channels\Repositories\Eloquent;

use App\Modules\Channels\DTO\NewChannelData;
use App\Modules\Channels\Models\Channel;
use App\Modules\Channels\Repositories\Contracts\ChannelRepositoryInterface;
use App\Shared\Repositories\EloquentRepository;
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
        $credentials = array_filter(array_merge([
            'bot_token' => $data->botToken,
        ], $data->credentials), static fn (?string $v): bool => $v !== null);

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
