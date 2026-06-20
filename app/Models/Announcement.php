<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnnouncementType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Анонс площадки (новость или обновление). Публикует супер-админ; виден всем
 * бизнесам — глобальный, без tenant_id.
 *
 * @property string $id
 * @property AnnouncementType $type
 * @property string $title
 * @property string $body
 * @property bool $is_published
 * @property Carbon|null $published_at
 */
class Announcement extends Model
{
    use HasUuids;

    protected $fillable = [
        'type',
        'title',
        'body',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => AnnouncementType::class,
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<AnnouncementRead, $this>
     */
    public function reads(): HasMany
    {
        return $this->hasMany(AnnouncementRead::class);
    }
}
