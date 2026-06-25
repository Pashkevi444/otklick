<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Http\Requests\UpdateBusinessProfileRequest;
use App\Modules\Identity\Services\TenantService;
use App\Shared\DTO\BusinessProfile;
use App\Shared\Http\Controller;
use App\Shared\Models\BusinessType;
use App\Shared\Support\BusinessAvatarStorage;
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
                // Почта бизнеса по умолчанию — почта владельца (если своя не задана).
                'email' => $profile->email ?: $tenant->ownerEmail(),
            ],
            // Тип бизнеса (ниша): плашка + смена. Влияет на подбор шаблонов и БЗ.
            'businessType' => $tenant->business_type,
            'businessTypes' => BusinessType::options(),
        ]);
    }

    /**
     * Смена типа бизнеса тенантом (ниша из справочника или сброс в «не задан»).
     */
    public function updateBusinessType(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'business_type' => ['nullable', 'string', 'exists:business_types,key'],
        ]);

        $this->tenants->setBusinessType($request->user()->tenant, $data['business_type'] ?? null);

        return redirect()->route('cabinet.profile.edit')->with('success', 'Тип бизнеса обновлён.');
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
                email: $request->input('email'),
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
