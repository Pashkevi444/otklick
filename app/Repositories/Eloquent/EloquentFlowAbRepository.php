<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\FlowAbAssignment;
use App\Repositories\Contracts\FlowAbRepositoryInterface;

final class EloquentFlowAbRepository implements FlowAbRepositoryInterface
{
    public function variantFor(string $flowId, string $conversationId): ?string
    {
        $variant = FlowAbAssignment::query()
            ->where('flow_id', $flowId)
            ->where('conversation_id', $conversationId)
            ->value('variant');

        return $variant !== null ? (string) $variant : null;
    }

    public function assign(string $flowId, string $conversationId, string $variant): void
    {
        // tenant_id проставит BelongsToTenant; уникум (conversation_id, flow_id).
        FlowAbAssignment::query()->firstOrCreate(
            ['conversation_id' => $conversationId, 'flow_id' => $flowId],
            ['variant' => $variant],
        );
    }

    public function statsForCurrentTenant(): array
    {
        return FlowAbAssignment::query()
            ->join('conversations', 'conversations.id', '=', 'flow_ab_assignments.conversation_id')
            ->selectRaw('flow_ab_assignments.flow_id as flow_id, flow_ab_assignments.variant as variant, count(*) as total, count(conversations.booked_at) as booked')
            ->groupBy('flow_ab_assignments.flow_id', 'flow_ab_assignments.variant')
            ->get()
            ->map(static fn (FlowAbAssignment $r): array => [
                'flow_id' => (string) $r->flow_id,
                'variant' => (string) $r->variant,
                'total' => (int) $r->getAttribute('total'),
                'booked' => (int) $r->getAttribute('booked'),
            ])
            ->all();
    }
}
