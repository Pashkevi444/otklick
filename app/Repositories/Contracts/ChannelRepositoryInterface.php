<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTO\NewChannelData;
use App\Models\Channel;
use Illuminate\Support\Collection;

/**
 * Контракт доступа к данным каналов. Единственный слой, работающий с БД для
 * сущности Channel.
 */
interface ChannelRepositoryInterface
{
    public function create(NewChannelData $data): Channel;

    public function find(string $id): ?Channel;

    /**
     * Каналы текущего тенант-контекста (scoped/RLS), новые сверху.
     *
     * @return Collection<int, Channel>
     */
    public function forCurrentTenant(): Collection;

    public function delete(Channel $channel): void;
}
