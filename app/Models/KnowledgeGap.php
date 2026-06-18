<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\KnowledgeGapStatus;
use Database\Factories\KnowledgeGapFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;

/**
 * «Пробел бота» — вопрос клиента, на который бот не нашёл ответа в базе знаний.
 * Дедупится по нормализованному тексту в пределах тенанта; строгий RLS.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $question
 * @property string $normalized
 * @property int $occurrences
 * @property string|null $conversation_id
 * @property string|null $channel_type
 * @property KnowledgeGapStatus $status
 * @property Carbon|null $last_seen_at
 */
class KnowledgeGap extends TenantOwnedModel
{
    /** @use HasFactory<KnowledgeGapFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'question',
        'normalized',
        'occurrences',
        'conversation_id',
        'channel_type',
        'status',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'occurrences' => 'integer',
            'status' => KnowledgeGapStatus::class,
            'last_seen_at' => 'datetime',
        ];
    }
}
