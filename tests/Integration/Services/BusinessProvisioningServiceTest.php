<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Modules\Identity\Services\BusinessProvisioningService;
use App\Shared\Enums\TenantPlan;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Throwable;

final class BusinessProvisioningServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): BusinessProvisioningService
    {
        return $this->app->make(BusinessProvisioningService::class);
    }

    public function test_creates_tenant_with_owner(): void
    {
        $tenant = $this->service()->createWithOwner(
            'Барбершоп', TenantPlan::default(), null, 'Иван', 'owner@biz.ru', 'secret-pass',
        );

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'name' => 'Барбершоп']);
        $this->assertDatabaseHas('users', ['email' => 'owner@biz.ru', 'tenant_id' => $tenant->id, 'role' => 'owner']);
    }

    public function test_rolls_back_tenant_when_owner_creation_fails(): void
    {
        // Email уже занят — вставка владельца упадёт (unique), тенант не должен остаться.
        User::factory()->create(['email' => 'taken@biz.ru']);

        try {
            $this->service()->createWithOwner(
                'Бизнес-сирота', TenantPlan::default(), null, 'Пётр', 'taken@biz.ru', 'secret-pass',
            );
            $this->fail('Ожидалось исключение при дубле email.');
        } catch (Throwable) {
            // ожидаемо
        }

        $this->assertDatabaseMissing('tenants', ['name' => 'Бизнес-сирота']);
    }
}
