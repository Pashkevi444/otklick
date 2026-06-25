<?php

declare(strict_types=1);

namespace App\Modules\Knowledge\Repositories\Eloquent;

use App\Modules\Knowledge\Models\KnowledgeGap;
use App\Modules\Knowledge\Repositories\Contracts\KnowledgeGapRepositoryInterface;
use App\Shared\Enums\KnowledgeGapStatus;
use Illuminate\Support\Collection;

final class EloquentKnowledgeGapRepository implements KnowledgeGapRepositoryInterface
{
    public function record(string $question, ?string $conversationId, ?string $channelType): KnowledgeGap
    {
        $normalized = $this->normalize($question);

        // Дедуп в пределах тенанта (глобальный scope) среди открытых пробелов.
        $existing = KnowledgeGap::query()
            ->where('normalized', $normalized)
            ->where('status', KnowledgeGapStatus::Open->value)
            ->first();

        if ($existing instanceof KnowledgeGap) {
            $existing->forceFill([
                'occurrences' => $existing->occurrences + 1,
                'conversation_id' => $conversationId,
                'channel_type' => $channelType,
                'last_seen_at' => now(),
            ])->save();

            return $existing;
        }

        return KnowledgeGap::create([
            'question' => $question,
            'normalized' => $normalized,
            'occurrences' => 1,
            'conversation_id' => $conversationId,
            'channel_type' => $channelType,
            'status' => KnowledgeGapStatus::Open,
            'last_seen_at' => now(),
        ]);
    }

    public function openForCurrentTenant(): Collection
    {
        return KnowledgeGap::query()
            ->where('status', KnowledgeGapStatus::Open->value)
            ->orderByDesc('occurrences')
            ->orderByDesc('last_seen_at')
            ->get();
    }

    public function countOpenForCurrentTenant(): int
    {
        return KnowledgeGap::query()->where('status', KnowledgeGapStatus::Open->value)->count();
    }

    public function find(string $id): ?KnowledgeGap
    {
        return KnowledgeGap::query()->whereKey($id)->first();
    }

    public function updateStatus(KnowledgeGap $gap, KnowledgeGapStatus $status): void
    {
        $gap->forceFill(['status' => $status])->save();
    }

    public function delete(KnowledgeGap $gap): void
    {
        $gap->delete();
    }

    /** Нормализация для дедупа: нижний регистр, без хвостовых пробелов, до 255 символов. */
    private function normalize(string $question): string
    {
        return mb_substr(trim(mb_strtolower($question)), 0, 255);
    }
}
