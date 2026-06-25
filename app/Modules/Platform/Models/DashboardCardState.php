<?php

declare(strict_types=1);

namespace App\Modules\Platform\Models;

use App\Shared\Enums\CardState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Состояние плашки дашборда, заданное супер-админом глобально (для всех бизнесов).
 * Одна строка на ключ плашки; глобальная, без tenant_id.
 *
 * @property string $id
 * @property string $card_key
 * @property CardState $state
 */
class DashboardCardState extends Model
{
    use HasUuids;

    protected $fillable = [
        'card_key',
        'state',
    ];

    protected function casts(): array
    {
        return [
            'state' => CardState::class,
        ];
    }
}
