<?php

declare(strict_types=1);

namespace App\Modules\Platform\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Repositories\Contracts\TenantRepositoryInterface;
use App\Modules\Identity\Services\BusinessProvisioningService;
use App\Modules\Identity\Services\TenantService;
use App\Modules\Identity\Services\UserService;
use App\Modules\Platform\Http\Requests\StoreTenantRequest;
use App\Modules\Platform\Http\Requests\UpdateTenantOverridesRequest;
use App\Modules\Platform\Http\Requests\UpdateTenantOwnerPasswordRequest;
use App\Modules\Platform\Http\Requests\UpdateTenantRequest;
use App\Shared\Enums\TenantPlan;
use App\Shared\Models\BusinessType;
use App\Shared\Models\Tenant;
use App\Shared\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Управление тенантами супер-админом: создание, тариф, срок доступа, блокировка.
 * Таблица tenants — реестр, не скоупится.
 */
final class TenantController extends Controller
{
    public function __construct(
        private readonly TenantRepositoryInterface $tenants,
        private readonly TenantService $tenantService,
        private readonly UserService $users,
        private readonly BusinessProvisioningService $provisioning,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Tenants/Index', [
            'tenants' => $this->tenants->all()->map($this->present(...))->all(),
            'plans' => $this->plans(),
        ]);
    }

    public function store(StoreTenantRequest $request): RedirectResponse
    {
        // Тенант + владелец создаются атомарно (см. BusinessProvisioningService).
        $tenant = $this->provisioning->createWithOwner(
            (string) $request->string('name'),
            TenantPlan::from((string) $request->string('plan')),
            $request->input('access_expires_at'),
            (string) $request->string('owner_name'),
            (string) $request->string('owner_email'),
            (string) $request->string('owner_password'),
        );

        return redirect()
            ->route('admin.tenants.show', $tenant->id)
            ->with('success', "Бизнес «{$tenant->name}» создан.");
    }

    public function show(string $tenant): Response
    {
        $model = $this->findOrFail($tenant);

        return Inertia::render('Admin/Tenants/Show', [
            'tenant' => $this->present($model),
            'plans' => $this->plans(),
            'businessTypes' => BusinessType::options(),
            // Дефолтные возможности каждого тарифа — чтобы при выборе тарифа в СУ
            // галочки/лимиты подставлялись по тарифу (а правка — только на «Индивидуальном»).
            'planDefaults' => collect(TenantPlan::cases())
                ->mapWithKeys(fn (TenantPlan $p): array => [$p->value => $p->features()->toArray()])
                ->all(),
            'users' => $this->users->listForTenant($model)->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->label(),
            ])->all(),
        ]);
    }

    public function update(UpdateTenantRequest $request, string $tenant): RedirectResponse
    {
        $this->tenantService->updateSubscription(
            $this->findOrFail($tenant),
            TenantPlan::from((string) $request->string('plan')),
            $request->input('access_expires_at'),
        );

        return redirect()->route('admin.tenants.show', $tenant)->with('success', 'Подписка обновлена.');
    }

    /**
     * Супер-админ задаёт тип бизнеса тенанту (пока вручную; в будущем — на
     * регистрации). Влияет на подбор шаблонов сценариев и базы знаний.
     */
    public function updateBusinessType(Request $request, string $tenant): RedirectResponse
    {
        $data = $request->validate([
            'business_type' => ['nullable', 'string', 'exists:business_types,key'],
        ]);

        $this->tenantService->setBusinessType($this->findOrFail($tenant), $data['business_type'] ?? null);

        return redirect()->route('admin.tenants.show', $tenant)->with('success', 'Тип бизнеса обновлён.');
    }

    public function updateOwnerPassword(UpdateTenantOwnerPasswordRequest $request, string $tenant): RedirectResponse
    {
        $ok = $this->users->setOwnerPassword(
            $this->findOrFail($tenant),
            (string) $request->string('password'),
        );

        abort_if(! $ok, 404, 'У бизнеса нет владельца.');

        return redirect()->route('admin.tenants.show', $tenant)->with('success', 'Пароль владельца обновлён.');
    }

    /**
     * Индивидуальные права/лимиты бизнеса (по договорённости) — поверх тарифа.
     */
    public function updateOverrides(UpdateTenantOverridesRequest $request, string $tenant): RedirectResponse
    {
        $this->tenantService->setOverrides($this->findOrFail($tenant), $request->overrides());

        return redirect()->route('admin.tenants.show', $tenant)->with('success', 'Права и лимиты обновлены.');
    }

    /**
     * Сброс индивидуальных оверрайдов — бизнес возвращается к возможностям тарифа.
     */
    public function resetOverrides(string $tenant): RedirectResponse
    {
        $this->tenantService->setOverrides($this->findOrFail($tenant), []);

        return redirect()->route('admin.tenants.show', $tenant)->with('success', 'Права сброшены к тарифу.');
    }

    public function block(string $tenant): RedirectResponse
    {
        $this->tenantService->block($this->findOrFail($tenant));

        return redirect()->route('admin.tenants.show', $tenant)->with('success', 'Бизнес заблокирован.');
    }

    public function unblock(string $tenant): RedirectResponse
    {
        $this->tenantService->unblock($this->findOrFail($tenant));

        return redirect()->route('admin.tenants.show', $tenant)->with('success', 'Бизнес разблокирован.');
    }

    private function findOrFail(string $id): Tenant
    {
        $tenant = $this->tenants->find($id);

        abort_if($tenant === null, 404);

        return $tenant;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function plans(): array
    {
        return array_map(
            fn (TenantPlan $p): array => ['value' => $p->value, 'label' => $p->label()],
            TenantPlan::cases(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'plan' => $tenant->plan->value,
            'plan_label' => $tenant->plan->label(),
            'business_type' => $tenant->business_type,
            'business_type_label' => $tenant->businessType?->label,
            'access_expires_at' => $tenant->access_expires_at?->toDateString(),
            'is_blocked' => $tenant->is_blocked,
            'has_active_access' => $tenant->hasActiveAccess(),
            'created_at' => $tenant->created_at?->toDateString(),
            // Права/лимиты: эффективные (тариф + оверрайды), дефолты тарифа и факт оверрайда.
            'features' => $tenant->features()->toArray(),
            'planDefaults' => $tenant->plan->features()->toArray(),
            'hasOverrides' => isset($tenant->settings['overrides']) && $tenant->settings['overrides'] !== [],
        ];
    }
}
