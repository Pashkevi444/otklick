<?php

declare(strict_types=1);

namespace App\Modules\Flows\Models;

use App\Shared\Models\Concerns\MarksSandbox;
use App\Shared\Models\TenantOwnedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Назначение A/B-варианта диалогу в сценарии (липкое: один вариант на диалог в
 * пределах сценария). Конверсия выводится стыковкой с `conversations.booked_at`.
 * Тенант-модель (строгий RLS).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $flow_id
 * @property string $conversation_id
 * @property string $variant
 */
class FlowAbAssignment extends TenantOwnedModel
{
    use MarksSandbox;

    protected $fillable = [
        'tenant_id',
        'flow_id',
        'conversation_id',
        'variant',
    ];

    /**
     * @return BelongsTo<Flow, $this>
     */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }
}
