<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Identity\Http\Requests\StoreTeamMemberRequest;
use App\Modules\Identity\Http\Requests\UpdateTeamMemberRequest;
use App\Modules\Identity\Services\UserService;
use App\Shared\Enums\CabinetSection;
use App\Shared\Enums\MemberPermission;
use App\Shared\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Команда бизнеса: владелец добавляет сотрудников (операторов) и ограничивает им
 * доступные разделы кабинета. Доступно только владельцу.
 */
final class TeamController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function index(Request $request): Response
    {
        $this->assertOwner($request);

        $tenant = $request->user()->tenant;
        $features = $tenant->features();

        // Матрица прав, которую владелец вправе раздавать — ограничена тарифом
        // (матрица мемберов ⊆ тенантной матрицы). Группируем по разделам:
        // доступ к разделу + права-действия внутри него.
        $grantable = MemberPermission::grantableWith($features);
        $permissionGroups = [];
        foreach (CabinetSection::cases() as $section) {
            $inSection = array_filter($grantable, fn (MemberPermission $p): bool => $p->section() === $section);
            if ($inSection === []) {
                continue; // раздела нет в тарифе
            }

            $access = null;
            $actions = [];
            foreach ($inSection as $p) {
                if ($p->isAction()) {
                    $actions[] = ['key' => $p->value, 'label' => $p->label()];
                } else {
                    $access = ['key' => $p->value, 'label' => $section->label()];
                }
            }

            $permissionGroups[] = ['access' => $access, 'actions' => $actions];
        }

        return Inertia::render('Cabinet/Team/Index', [
            'permissionGroups' => $permissionGroups,
            'maxUsers' => $features->maxOperators,
            'usedUsers' => $this->users->listForTenant($tenant)->count(),
            'members' => $this->users->listForTenant($tenant)->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role->value,
                'roleLabel' => $u->role->label(),
                'isOwner' => $u->isOwner(),
                'permissions' => $u->isOwner() ? MemberPermission::values() : ($u->permissions ?? []),
            ])->values()->all(),
        ]);
    }

    public function store(StoreTeamMemberRequest $request): RedirectResponse
    {
        $this->users->addMember(
            $request->user()->tenant,
            (string) $request->string('name'),
            (string) $request->string('email'),
            (string) $request->string('password'),
            $request->input('permissions', []),
        );

        return redirect()->route('cabinet.team.index')->with('success', 'Сотрудник добавлен.');
    }

    public function update(UpdateTeamMemberRequest $request, string $member): RedirectResponse
    {
        $updated = $this->users->updateMember(
            $request->user()->tenant,
            $member,
            $request->input('name'),
            $request->input('permissions', []),
        );

        abort_if($updated === null, HttpResponse::HTTP_NOT_FOUND);

        return redirect()->route('cabinet.team.index')->with('success', 'Права обновлены.');
    }

    public function destroy(Request $request, string $member): RedirectResponse
    {
        $this->assertOwner($request);

        abort_unless($this->users->removeMember($request->user()->tenant, $member), HttpResponse::HTTP_NOT_FOUND);

        return redirect()->route('cabinet.team.index')->with('success', 'Сотрудник удалён.');
    }

    private function assertOwner(Request $request): void
    {
        abort_unless($request->user()?->isOwner() === true, HttpResponse::HTTP_FORBIDDEN);
    }
}
