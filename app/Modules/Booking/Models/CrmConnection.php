<?php

declare(strict_types=1);

namespace App\Modules\Booking\Models;

use App\Shared\Enums\CrmProvider;
use App\Shared\Models\TenantOwnedModel;
use Database\Factories\CrmConnectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Подключение тенанта к CRM. Креды (company_id, токен) шифруются в БД.
 *
 * @property string $id
 * @property string $tenant_id
 * @property CrmProvider $provider
 * @property array<string, mixed> $credentials
 * @property bool $is_active
 * @property array<string, mixed> $settings
 */
class CrmConnection extends TenantOwnedModel
{
    /** @use HasFactory<CrmConnectionFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'provider',
        'credentials',
        'is_active',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'provider' => CrmProvider::class,
            'credentials' => 'encrypted:array',
            'is_active' => 'boolean',
            'settings' => 'array',
        ];
    }

    /**
     * Значение креда по ключу. Какие ключи существуют — знает только
     * CRM-стратегия (provider-специфика не утекает в модель).
     */
    public function credential(string $key): ?string
    {
        $value = $this->credentials[$key] ?? null;

        return $value !== null ? (string) $value : null;
    }
}
