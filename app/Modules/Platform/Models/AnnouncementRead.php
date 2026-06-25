<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use App\Shared\Models\TenantOwnedModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Факт прочтения анонса КОНКРЕТНЫМ пользователем (пер-юзер). Тенант-модель —
 * строгий RLS; уникальность по (анонс, пользователь).
 *
 * @property string $id
 * @property string $announcement_id
 * @property string $tenant_id
 * @property string|null $user_id
 * @property Carbon $read_at
 */
class AnnouncementRead extends TenantOwnedModel
{
    protected $fillable = [
        'announcement_id',
        'tenant_id',
        'user_id',
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
