<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\Enums\CabinetSection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cabinet\StoreTeamMemberRequest;
use App\Http\Requests\Cabinet\UpdateTeamMemberRequest;
use App\Models\User;
use App\Services\UserService;
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

        return Inertia::render('Cabinet/Team/Index', [
            'sections' => array_map(
                fn (CabinetSection $s): array => ['key' => $s->value, 'label' => $s->label()],
                CabinetSection::cases(),
            ),
            'maxUsers' => $features->maxOperators,
            'usedUsers' => $this->users->listForTenant($tenant)->count(),
            'members' => $this->users->listForTenant($tenant)->map(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $u->role->value,
                'roleLabel' => $u->role->label(),
                'isOwner' => $u->isOwner(),
                'permissions' => $u->isOwner() ? CabinetSection::values() : ($u->permissions ?? []),
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
