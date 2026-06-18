<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Доступ к базе клиентов тенанта. Скоупится текущим тенантом (RLS + scope).
 */
interface ClientRepositoryInterface
{
    public function findByPhone(string $phone): ?Client;

    public function find(string $id): ?Client;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Client;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Client $client, array $attributes): void;

    public function delete(Client $client): void;

    /**
     * Клиенты тенанта с поиском, фильтром по первому каналу, сортировкой и
     * пагинацией (для грид-журнала). С числом диалогов клиента.
     *
     * @param  'last'|'name'|'first'  $sort
     * @param  'asc'|'desc'  $direction
     * @return LengthAwarePaginator<int, Client>
     */
    public function paginateForCurrentTenant(
        ?string $search,
        ?string $channel,
        string $sort,
        string $direction,
        int $perPage,
    ): LengthAwarePaginator;

    /**
     * Типы каналов, через которые клиенты приходили впервые — для фильтра.
     *
     * @return list<string>
     */
    public function channelsForCurrentTenant(): array;
}
