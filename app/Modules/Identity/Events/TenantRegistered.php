<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use App\Shared\Models\Tenant;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Домен-событие: зарегистрирован новый тенант. Слушатели вешают побочные
 * эффекты (онбординг, дефолтная база знаний, биллинг) без связывания с сервисом.
 */
final class TenantRegistered
{
    use Dispatchable;

    public function __construct(public readonly Tenant $tenant) {}
}
