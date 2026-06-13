<?php

declare(strict_types=1);

namespace App\Providers;

use App\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;

final class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // scoped → Octane сбрасывает инстанс между запросами,
        // тенант не «протекает» между запросами в резидентном рантайме.
        $this->app->scoped(TenantContext::class);
    }
}
