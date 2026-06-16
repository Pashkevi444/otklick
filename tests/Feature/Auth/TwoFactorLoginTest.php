<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FAQRCode\Google2FA;
use Tests\TestCase;

final class TwoFactorLoginTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: User, 1: string}
     */
    private function userWith2fa(): array
    {
        $secret = app(Google2FA::class)->generateSecretKey();
        $user = User::factory()->owner(Tenant::factory()->create())->create();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

        return [$user, $secret];
    }

    public function test_login_with_2fa_redirects_to_challenge_without_logging_in(): void
    {
        [$user] = $this->userWith2fa();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route('two-factor.login'));

        $this->assertGuest();
    }

    public function test_valid_code_completes_login(): void
    {
        [$user, $secret] = $this->userWith2fa();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->post('/two-factor-challenge', ['code' => app(Google2FA::class)->getCurrentOtp($secret)])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_code_rejected(): void
    {
        [$user] = $this->userWith2fa();
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->post('/two-factor-challenge', ['code' => '000000'])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_recovery_code_completes_login_and_is_consumed(): void
    {
        $secret = app(Google2FA::class)->generateSecretKey();
        $user = User::factory()->owner(Tenant::factory()->create())->create();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => ['abcde-fghij', 'kkkkk-lllll'],
        ])->save();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->post('/two-factor-challenge', ['code' => 'abcde-fghij'])->assertRedirect();

        $this->assertAuthenticatedAs($user);
        $this->assertNotContains('abcde-fghij', $user->fresh()->two_factor_recovery_codes ?? []);
    }

    public function test_login_without_2fa_logs_in_directly(): void
    {
        $user = User::factory()->owner(Tenant::factory()->create())->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
    }
}
