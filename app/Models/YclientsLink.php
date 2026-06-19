<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\YclientsMarketplaceService;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Привязка подключения из маркетплейса YClients (salon_id ↔ тенант ↔ user-токен).
 * Не тенант-модель: вебхук YClients приходит без тенант-контекста, поэтому строка
 * живёт вне изоляции и наполняется в два шага (см. миграцию и
 * {@see YclientsMarketplaceService}). user-токен шифруется в БД.
 *
 * @property string $id
 * @property string $salon_id
 * @property ?string $tenant_id
 * @property ?string $user_token
 * @property array<string, mixed> $raw
 * @property ?Carbon $connected_at
 */
class YclientsLink extends Model
{
    use HasUuids;

    protected $fillable = [
        'salon_id',
        'tenant_id',
        'user_token',
        'raw',
        'connected_at',
    ];

    protected function casts(): array
    {
        return [
            'user_token' => 'encrypted',
            'raw' => 'array',
            'connected_at' => 'datetime',
        ];
    }
}
