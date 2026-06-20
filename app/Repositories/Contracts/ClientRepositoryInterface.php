<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Client;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Доступ к базе клиентов тенанта. Скоупится текущим тенантом (RLS + scope).
 */
interface ClientRepositoryInterface
{
    public function findByPhone(string $phone): ?Client;

    /** Карточка по нику Telegram (легаси-дедуп: клиент без записанного chat_id). */
    public function findByTelegramUsername(string $username): ?Client;

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

    /**
     * Аудитория рассылки: клиенты тенанта без отписки (marketing_opt_out=false),
     * с подгруженными диалогами и каналами — чтобы по ним вычислить достижимые
     * цели (мессенджеры + email). Если задан $clientIds — только эти клиенты
     * (отписка всё равно исключает).
     *
     * @param  list<string>|null  $clientIds  null/пусто — вся база
     * @return Collection<int, Client>
     */
    public function marketingAudienceForCurrentTenant(?array $clientIds = null): Collection;

    /**
     * Число клиентов в аудитории рассылки (без отписки) — для предпросмотра.
     */
    public function marketingAudienceCountForCurrentTenant(): int;

    /**
     * Лёгкий список клиентов для выбора получателей (id, имя, телефон, отписка).
     *
     * @return list<array{id: string, name: string, phone: string|null, opted_out: bool}>
     */
    public function pickerListForCurrentTenant(): array;
}
