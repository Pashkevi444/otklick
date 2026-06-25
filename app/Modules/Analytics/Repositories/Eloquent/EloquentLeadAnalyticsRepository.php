<?php

declare(strict_types=1);

namespace App\Modules\Analytics\Repositories\Eloquent;

use App\Modules\Analytics\Repositories\Contracts\LeadAnalyticsRepositoryInterface;
use App\Modules\Channels\Models\Channel;
use App\Modules\Conversations\Models\Conversation;
use App\Shared\Enums\MessageDirection;
use Illuminate\Contracts\Database\Query\Builder as BuilderContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class EloquentLeadAnalyticsRepository implements LeadAnalyticsRepositoryInterface
{
    public function leadsForAnalytics(Carbon $from, Carbon $to): Collection
    {
        return Conversation::query()
            ->whereBetween('created_at', [$from, $to])
            ->with(['channel:id,type', 'client:id,name,phone,email'])
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
            ->with(['channel:id,type', 'latestMessage', 'client:id,name,phone,email'])
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function bookingsForCrm(string $crmConnectionId, Carbon $from, Carbon $to): Collection
    {
        return Conversation::query()
            ->where('crm_connection_id', $crmConnectionId)
            ->whereNotNull('booked_at')
            ->whereBetween('booked_at', [$from, $to])
            ->with(['channel:id,type', 'client:id,name,phone,email'])
            ->get();
    }

    public function cancelledCountForCrm(string $crmConnectionId, Carbon $from, Carbon $to): int
    {
        return Conversation::query()
            ->where('crm_connection_id', $crmConnectionId)
            ->whereNotNull('cancelled_at')
            ->whereBetween('cancelled_at', [$from, $to])
            ->count();
    }

    public function leadsCount(Carbon $from, Carbon $to): int
    {
        return Conversation::query()
            ->whereBetween('created_at', [$from, $to])
            ->count();
    }
}
