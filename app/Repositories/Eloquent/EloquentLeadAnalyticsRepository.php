<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\MessageDirection;
use App\Models\Channel;
use App\Models\Conversation;
use App\Repositories\Contracts\LeadAnalyticsRepositoryInterface;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class EloquentLeadAnalyticsRepository implements LeadAnalyticsRepositoryInterface
{
    public function leadsForAnalytics(Carbon $from, Carbon $to): Collection
    {
        return Conversation::query()
            ->whereBetween('created_at', [$from, $to])
            ->with('channel:id,type')
            ->withCount(['messages as inbound_count' => fn (BuilderContract $q): BuilderContract => $q
                ->where('direction', MessageDirection::Inbound->value)])
            ->get();
    }

    public function connectedChannelTypes(): array
    {
        return Channel::query()
            ->where('is_active', true)
            ->distinct()
            ->pluck('type')
            ->map(fn ($type): string => $type instanceof \BackedEnum ? (string) $type->value : (string) $type)
            ->all();
    }

    public function recentLeads(int $limit): Collection
    {
        return Conversation::query()
            ->with(['channel:id,type', 'latestMessage'])
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
