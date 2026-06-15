<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\DTO\BusinessProfile;
use App\Models\Tenant;
use App\Services\TenantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class TenantProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_update_profile_persists_name_and_settings(): void
    {
        $tenant = Tenant::factory()->create(['name' => 'Старое имя']);

        $updated = $this->app->make(TenantService::class)->updateProfile(
            $tenant,
            'Новое имя',
            new BusinessProfile(phone: '+7 900', workingHours: 'Пн–Пт 9:00–18:00'),
        );

        $this->assertSame('Новое имя', $updated->name);
        $this->assertSame('+7 900', $updated->settings['profile']['phone']);
        $this->assertSame('Пн–Пт 9:00–18:00', $updated->settings['profile']['working_hours']);
        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'name' => 'Новое имя']);
    }
}
