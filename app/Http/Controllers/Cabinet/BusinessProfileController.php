<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\DTO\BusinessProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cabinet\UpdateBusinessProfileRequest;
use App\Services\TenantService;
use App\Support\BusinessAvatarStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Профиль бизнеса («контекст работы» + витрина-карточка) в кабинете тенанта.
 */
final class BusinessProfileController extends Controller
{
    public function __construct(
        private readonly TenantService $tenants,
        private readonly BusinessAvatarStorage $avatars,
    ) {}

    public function edit(Request $request): Response
    {
        $tenant = $request->user()->tenant;
        $profile = BusinessProfile::fromArray($tenant->settings['profile'] ?? []);

        return Inertia::render('Cabinet/Profile', [
            'profile' => [
                'name' => $tenant->name,
                ...$profile->toArray(),
            ],
        ]);
    }

    public function update(UpdateBusinessProfileRequest $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;
        $current = BusinessProfile::fromArray($tenant->settings['profile'] ?? []);

        [$avatarPath, $avatarUrl] = $this->resolveAvatar($request, $current);

        $this->tenants->updateProfile(
            $tenant,
            (string) $request->string('name'),
            new BusinessProfile(
                phone: $request->input('phone'),
                address: $request->input('address'),
                workingHours: $request->input('working_hours'),
                escalationNote: $request->input('escalation_note'),
                description: $request->input('description'),
                website: $request->input('website'),
                avatarPath: $avatarPath,
                avatarUrl: $avatarUrl,
            ),
        );

        return redirect()
            ->route('cabinet.profile.edit')
            ->with('success', 'Профиль сохранён.');
    }

    /**
     * Определяет аватар после сохранения: новый файл, удаление или прежний.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function resolveAvatar(Request $request, BusinessProfile $current): array
    {
        if ($request->boolean('remove_avatar')) {
            $this->avatars->delete($current->avatarPath);

            return [null, null];
        }

        $file = $request->file('avatar');
        if ($file instanceof UploadedFile) {
            $this->avatars->delete($current->avatarPath);
            $stored = $this->avatars->store((string) $request->user()->tenant_id, $file);

            return [$stored['path'], $stored['url']];
        }

        return [$current->avatarPath, $current->avatarUrl];
    }
}
