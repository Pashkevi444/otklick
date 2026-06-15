<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class AccountPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_page_renders(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $this->actingAs($owner)->get('/account/password')->assertOk();
    }

    public function test_user_changes_password(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->put('/account/password', [
            'current_password' => 'password',
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ])->assertSessionHasNoErrors()->assertSessionHas('success');

        $this->assertTrue(Hash::check('new-secret-123', $admin->fresh()->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->put('/account/password', [
            'current_password' => 'wrong',
            'password' => 'new-secret-123',
            'password_confirmation' => 'new-secret-123',
        ])->assertSessionHasErrors('current_password');
    }
}
