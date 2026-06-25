<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

use App\Modules\Identity\IdentityApiService;
use App\Shared\Enums\TenantPlan;
use App\Shared\Models\Tenant;
use App\Shared\Models\User;
use Illuminate\Support\Collection;

/**
 * Публичный контракт модуля «Идентичность» — единственная дверь для других модулей.
 * Снаружи доступны только эти методы; UserService/TenantService/
 * BusinessProvisioningService и репозитории — приватная кухня модуля.
 * Реализация — {@see IdentityApiService}.
 */
interface IdentityApi
{
    // --- Тенант: жизненный цикл (TenantService) ---

    /** Создаёт тенанта вместе с владельцем атомарно (транзакция). */
    public function createWithOwner(
        string $name,
        TenantPlan $plan,
        ?string $accessExpiresAt,
        string $ownerName,
        string $ownerEmail,
        string $ownerPassword,
    ): Tenant;

    /** Тариф и срок оплаченного доступа. */
    public function updateSubscription(Tenant $tenant, TenantPlan $plan, ?string $accessExpiresAt): Tenant;

    /** Тип бизнеса тенанта (ключ справочника business_types или null). */
    public function setBusinessType(Tenant $tenant, ?string $businessType): Tenant;

    /**
     * Индивидуальные оверрайды прав/лимитов (поверх тарифа); [] — сброс к тарифу.
     *
     * @param  array<string, mixed>  $overrides
     */
    public function setOverrides(Tenant $tenant, array $overrides): Tenant;

    public function block(Tenant $tenant): Tenant;

    public function unblock(Tenant $tenant): Tenant;

    /**
     * Главное меню бота — кнопки-подсказки бизнеса.
     *
     * @param  list<string>  $buttons
     */
    public function updateBotMenu(Tenant $tenant, array $buttons): Tenant;

    /** Включает/выключает недельный AI-дайджест владельцу. */
    public function setWeeklyDigest(Tenant $tenant, bool $enabled): Tenant;

    // --- Тенант: реестр (TenantRepository) ---

    /**
     * Все тенанты (реестр для супер-админа), новые сверху.
     *
     * @return Collection<int, Tenant>
     */
    public function all(): Collection;

    public function find(string $id): ?Tenant;

    // --- Пользователи (UserService + UserRepository) ---

    /** Владелец бизнеса по id тенанта (для входа супер-админа в кабинет). */
    public function ownerOf(string $tenantId): ?User;

    /**
     * Пользователи тенанта (в его тенант-контексте).
     *
     * @return Collection<int, User>
     */
    public function listForTenant(Tenant $tenant): Collection;

    /** Установить пароль владельцу бизнеса; false — у тенанта нет владельца. */
    public function setOwnerPassword(Tenant $tenant, string $password): bool;

    /**
     * Пользователи текущего тенант-контекста (scoped/RLS).
     *
     * @return Collection<int, User>
     */
    public function forCurrentTenant(): Collection;
}
