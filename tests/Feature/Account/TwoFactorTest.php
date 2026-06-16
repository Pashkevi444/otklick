<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PragmaRX\Google2FAQRCode\Google2FA;
use Tests\TestCase;

final class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->owner(Tenant::factory()->create())->create();
    }

    private function otp(string $secret): string
    {
        return app(Google2FA::class)->getCurrentOtp($secret);
    }

    public function test_enable_requires_current_password(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->post('/account/two-factor', ['current_password' => 'wrong'])
            ->assertSessionHasErrors('current_password');

        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_enable_generates_pending_secret(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->post('/account/two-factor', ['current_password' => 'password'])->assertRedirect();

        $user->refresh();
        $this->assertNotNull($user->two_factor_secret);
        $this->assertFalse($user->hasTwoFactorEnabled()); // ещё не подтверждено
    }

    public function test_show_renders_qr_when_pending(): void
    {
        $user = $this->owner();
        $this->actingAs($user)->post('/account/two-factor', ['current_password' => 'password']);

        $this->actingAs($user)->get('/account/two-factor')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Account/TwoFactor')
                ->where('pending', true)
                ->whereNot('qr', null)
                ->has('recoveryCodes', 8));
    }

    public function test_confirm_enables_with_valid_code(): void
    {
        $user = $this->owner();
        $this->actingAs($user)->post('/account/two-factor', ['current_password' => 'password']);
        $secret = (string) $user->fresh()->two_factor_secret;

        $this->actingAs($user)->post('/account/two-factor/confirm', ['code' => $this->otp($secret)])
            ->assertSessionHas('success');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_confirm_rejects_wrong_code(): void
    {
        $user = $this->owner();
        $this->actingAs($user)->post('/account/two-factor', ['current_password' => 'password']);

        $this->actingAs($user)->post('/account/two-factor/confirm', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_disable_clears_two_factor(): void
    {
        $user = $this->owner();
        $this->actingAs($user)->post('/account/two-factor', ['current_password' => 'password']);
        $secret = (string) $user->fresh()->two_factor_secret;
        $this->actingAs($user)->post('/account/two-factor/confirm', ['code' => $this->otp($secret)]);

        $this->actingAs($user)->delete('/account/two-factor', ['current_password' => 'password'])
            ->assertSessionHas('success');

        $this->assertNull($user->fresh()->two_factor_secret);
        $this->assertFalse($user->fresh()->hasTwoFactorEnabled());
    }
}
