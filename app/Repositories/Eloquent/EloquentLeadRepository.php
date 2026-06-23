<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTO\LeadData;
use App\Models\Lead;
use App\Repositories\Contracts\LeadRepositoryInterface;
use Illuminate\Support\Collection;

final class EloquentLeadRepository implements LeadRepositoryInterface
{
    public function forCurrentTenant(): Collection
    {
        return Lead::query()
            ->with(['client', 'deal'])
            ->orderByDesc('created_at')
            ->get();
    }

    public function find(string $id): ?Lead
    {
        return Lead::query()->with(['client', 'deal'])->whereKey($id)->first();
    }

    public function create(LeadData $data): Lead
    {
        return Lead::query()->create([
            'client_id' => $data->clientId,
            'conversation_id' => $data->conversationId,
            'title' => $data->title,
            'source' => $data->source,
            'notes' => $data->notes,
            'custom' => $data->custom,
        ]);
    }

    public function update(Lead $lead, array $attributes): void
    {
        $lead->forceFill($attributes)->save();
    }

    public function delete(Lead $lead): void
    {
        $lead->delete();
    }

    public function existsForConversation(string $conversationId): bool
    {
        return Lead::query()->where('conversation_id', $conversationId)->exists();
    }

    public function findByConversation(string $conversationId): ?Lead
    {
        return Lead::query()->where('conversation_id', $conversationId)->first();
    }
}
