<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreTenantRequest;
use App\Models\Tenant;
use App\Models\User;
use App\Repositories\Contracts\TenantRepositoryInterface;
use App\Services\TenantService;
use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Управление тенантами супер-админом. Таблица tenants — реестр, не скоупится.
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
        ]);
    }

    public function store(StoreTenantRequest $request): RedirectResponse
    {
        $tenant = $this->tenantService->register((string) $request->string('name'));

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
        $model = $this->tenants->find($tenant);

        abort_if($model === null, 404);

        return Inertia::render('Admin/Tenants/Show', [
            'tenant' => $this->present($model),
            'users' => $this->users->listForTenant($model)->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->label(),
            ])->all(),
        ]);
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
            'plan' => $tenant->plan->label(),
            'created_at' => $tenant->created_at?->toDateString(),
        ];
    }
}
