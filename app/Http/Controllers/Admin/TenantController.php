<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\TenantPlan;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTenantRequest;
use App\Http\Requests\Admin\UpdateTenantRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\TenantService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
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
        $tenant = $this->tenantService->register(
            (string) $request->string('name'),
            TenantPlan::from((string) $request->string('plan')),
            $request->input('access_expires_at'),
        );

        $this->users->createOwner(
            $tenant,
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
            'access_expires_at' => $tenant->access_expires_at?->toDateString(),
            'is_blocked' => $tenant->is_blocked,
            'has_active_access' => $tenant->hasActiveAccess(),
            'created_at' => $tenant->created_at?->toDateString(),
        ];
    }
}
