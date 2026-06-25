<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Shared\Models\Tenant;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Ссылка на трекер ошибок (GlitchTip/Sentry) в меню — только супер-админу и
 * только когда задан ERROR_TRACKING_URL (`services.error_tracking_url`).
 */
final class ErrorTrackingLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_gets_error_tracking_url_when_configured(): void
    {
        config(['services.error_tracking_url' => 'https://errors.otcl1ck.ru']);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)
            ->get('/admin/tenants')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('errorTrackingUrl', 'https://errors.otcl1ck.ru'));
    }

    public function test_owner_does_not_get_error_tracking_url(): void
    {
        config(['services.error_tracking_url' => 'https://errors.otcl1ck.ru']);
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)
            ->get('/cabinet')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('errorTrackingUrl', null));
    }
}
