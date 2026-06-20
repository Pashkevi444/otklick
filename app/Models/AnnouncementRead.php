<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Факт прочтения анонса бизнесом (пер-тенант). Тенант-модель — строгий RLS.
 *
 * @property string $id
 * @property string $announcement_id
 * @property string $tenant_id
 * @property Carbon $read_at
 */
class AnnouncementRead extends TenantOwnedModel
{
    protected $fillable = [
        'announcement_id',
        'tenant_id',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Announcement, $this>
     */
    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }
}
