<?php

declare(strict_types=1);

namespace App\Modules\Identity\Services;

use App\Modules\Identity\DTO\BusinessProfile;
use App\Modules\Identity\DTO\NewTenantData;
use App\Modules\Identity\Events\TenantRegistered;
use App\Modules\Identity\Repositories\Contracts\TenantRepositoryInterface;
use App\Shared\Enums\TenantPlan;
use App\Shared\Models\Tenant;
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
    public function register(string $name, ?TenantPlan $plan = null, ?string $accessExpiresAt = null, array $settings = []): Tenant
    {
        $data = new NewTenantData(
            name: $name,
            slug: $this->uniqueSlug($name),
            plan: $plan ?? TenantPlan::default(),
            accessExpiresAt: $accessExpiresAt,
            settings: $settings,
        );

        $tenant = $this->tenants->create($data);

        TenantRegistered::dispatch($tenant);

        return $tenant;
    }

    /**
     * Тариф и срок оплаченного доступа (после даты кабинет блокируется).
     */
    public function updateSubscription(Tenant $tenant, TenantPlan $plan, ?string $accessExpiresAt): Tenant
    {
        return $this->tenants->update($tenant, [
            'plan' => $plan,
            'access_expires_at' => $accessExpiresAt,
        ]);
    }

    /**
     * Задаёт тип бизнеса тенанта (ключ справочника business_types или null —
     * «не задан»). Валидацию ключа делает вызывающий (по справочнику).
     */
    public function setBusinessType(Tenant $tenant, ?string $businessType): Tenant
    {
        return $this->tenants->update($tenant, ['business_type' => $businessType]);
    }

    public function block(Tenant $tenant): Tenant
    {
        return $this->tenants->update($tenant, ['is_blocked' => true]);
    }

    public function unblock(Tenant $tenant): Tenant
    {
        return $this->tenants->update($tenant, ['is_blocked' => false]);
    }

    /**
     * Обновляет название бизнеса и профиль («контекст работы»). Профиль
     * хранится в settings под ключом profile.
     */
    public function updateProfile(Tenant $tenant, string $name, BusinessProfile $profile): Tenant
    {
        $settings = $tenant->settings;
        $settings['profile'] = $profile->toArray();

        return $this->tenants->update($tenant, [
            'name' => $name,
            'settings' => $settings,
        ]);
    }

    /**
     * Главное меню бота — кнопки-подсказки бизнеса (settings['bot_menu']).
     * Пустые подписи отбрасываются; авто-«Записаться» добавляет бот при CRM.
     *
     * @param  list<string>  $buttons
     */
    public function updateBotMenu(Tenant $tenant, array $buttons): Tenant
    {
        $settings = $tenant->settings;
        $settings['bot_menu'] = array_values(array_filter(
            array_map(static fn (string $b): string => trim($b), $buttons),
            static fn (string $b): bool => $b !== '',
        ));

        return $this->tenants->update($tenant, ['settings' => $settings]);
    }

    /**
     * Индивидуальные оверрайды прав/лимитов бизнеса (поверх тарифа). Пустой
     * массив очищает оверрайды — бизнес возвращается к возможностям тарифа.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function setOverrides(Tenant $tenant, array $overrides): Tenant
    {
        $settings = $tenant->settings;

        if ($overrides === []) {
            unset($settings['overrides']);
        } else {
            $settings['overrides'] = $overrides;
        }

        return $this->tenants->update($tenant, ['settings' => $settings]);
    }

    /**
     * Включает/выключает недельный AI-дайджест владельцу (хранится в settings).
     */
    public function setWeeklyDigest(Tenant $tenant, bool $enabled): Tenant
    {
        $settings = $tenant->settings;
        $settings['weekly_digest'] = $enabled;

        return $this->tenants->update($tenant, ['settings' => $settings]);
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
