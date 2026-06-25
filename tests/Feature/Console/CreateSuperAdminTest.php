<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CreateSuperAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_super_admin(): void
    {
        $this->artisan('admin:create-super-admin', [
            'name' => 'Root',
            'email' => 'root@otklick.io',
            'password' => 'secret-pass',
        ])->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'email' => 'root@otklick.io',
            'role' => 'super_admin',
            'tenant_id' => null,
        ]);
    }

    public function test_rejects_duplicate_email(): void
    {
        User::factory()->superAdmin()->create(['email' => 'root@otklick.io']);

        $this->artisan('admin:create-super-admin', [
            'name' => 'Root',
            'email' => 'root@otklick.io',
            'password' => 'secret-pass',
        ])->assertFailed();
    }
}
