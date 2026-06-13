<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\NewTenantData;
use App\Enums\TenantPlan;
use App\Events\TenantRegistered;
use App\Models\Tenant;
use App\Repositories\Contracts\TenantRepositoryInterface;
use Illuminate\Support\Str;

/**
 * Бизнес-логика жизненного цикла тенанта. Работает с БД только через репозиторий.
 */
final readonly class TenantService
{
    public function __construct(
        private TenantRepositoryInterface $tenants,
    ) {}

    /**
     * Регистрирует нового тенанта: генерирует уникальный slug, ставит план и
     * поднимает домен-событие для побочных эффектов онбординга.
     *
     * @param  array<string, mixed>  $settings
     */
    public function register(string $name, ?TenantPlan $plan = null, array $settings = []): Tenant
    {
        $data = new NewTenantData(
            name: $name,
            slug: $this->uniqueSlug($name),
            plan: $plan ?? TenantPlan::default(),
            settings: $settings,
        );

        $tenant = $this->tenants->create($data);

        TenantRegistered::dispatch($tenant);

        return $tenant;
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while ($this->tenants->slugExists($slug)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
