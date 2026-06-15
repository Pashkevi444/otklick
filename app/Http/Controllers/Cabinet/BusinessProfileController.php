<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cabinet;

use App\DTO\BusinessProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Cabinet\UpdateBusinessProfileRequest;
use App\Services\TenantService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Профиль бизнеса («контекст работы») в кабинете тенанта.
 */
final class BusinessProfileController extends Controller
{
    public function __construct(
        private readonly TenantService $tenants,
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
        $this->tenants->updateProfile(
            $request->user()->tenant,
            (string) $request->string('name'),
            new BusinessProfile(
                phone: $request->input('phone'),
                address: $request->input('address'),
                workingHours: $request->input('working_hours'),
                escalationNote: $request->input('escalation_note'),
            ),
        );

        return redirect()
            ->route('cabinet.profile.edit')
            ->with('success', 'Профиль сохранён.');
    }
}
