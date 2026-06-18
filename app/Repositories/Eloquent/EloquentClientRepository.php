<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Client;
use App\Repositories\Contracts\ClientRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class EloquentClientRepository implements ClientRepositoryInterface
{
    public function findByPhone(string $phone): ?Client
    {
        return Client::query()->where('phone', $phone)->first();
    }

    public function find(string $id): ?Client
    {
        return Client::query()->whereKey($id)->first();
    }

    public function create(array $attributes): Client
    {
        return Client::create($attributes);
    }

    public function update(Client $client, array $attributes): void
    {
        $client->forceFill($attributes)->save();
    }

    public function delete(Client $client): void
    {
        $client->delete();
    }

    public function paginateForCurrentTenant(
        ?string $search,
        ?string $channel,
        string $sort,
        string $direction,
        int $perPage,
    ): LengthAwarePaginator {
        $query = Client::query()->withCount('conversations');

        if ($search !== null && $search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $w) use ($needle): void {
                $w->whereRaw('lower(name) like ?', [$needle])
                    ->orWhereRaw('lower(phone) like ?', [$needle])
                    ->orWhereRaw('lower(email) like ?', [$needle])
                    ->orWhereRaw('lower(telegram_username) like ?', [$needle]);
            });
        }

        if ($channel !== null && $channel !== '') {
            $query->where('first_channel_type', $channel);
        }

        $column = match ($sort) {
            'name' => 'name',
            'first' => 'first_seen_at',
            default => 'last_seen_at',
        };
        $dir = $direction === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($column, $dir)->orderByDesc('created_at')->paginate($perPage)->withQueryString();
    }

    public function channelsForCurrentTenant(): array
    {
        return Client::query()
            ->whereNotNull('first_channel_type')
            ->distinct()
            ->orderBy('first_channel_type')
            ->pluck('first_channel_type')
            ->map(fn ($v): string => (string) $v)
            ->all();
    }
}
