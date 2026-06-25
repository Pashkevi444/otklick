<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Modules\Identity\Mail\PasswordResetCodeMail;
use App\Shared\Models\Tenant;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_screen_renders(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/ForgotPassword'));
    }

    public function test_reset_password_screen_renders(): void
    {
        $this->get('/reset-password?email=owner@biz.ru')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/ResetPassword')->where('email', 'owner@biz.ru'));
    }

    public function test_requesting_code_stores_hash_emails_and_redirects(): void
    {
        Mail::fake();
        $tenant = Tenant::factory()->create();
        $user = User::factory()->owner($tenant)->create(['email' => 'owner@biz.ru']);

        $this->post('/forgot-password', ['email' => 'owner@biz.ru'])
            ->assertRedirect(route('password.reset', ['email' => 'owner@biz.ru']));

        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'owner@biz.ru']);
        Mail::assertQueued(PasswordResetCodeMail::class);
    }

    public function test_requesting_code_for_unknown_email_is_silent_but_redirects(): void
    {
        Mail::fake();

        $this->post('/forgot-password', ['email' => 'nobody@biz.ru'])
            ->assertRedirect(route('password.reset', ['email' => 'nobody@biz.ru']));

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'nobody@biz.ru']);
        Mail::assertNothingQueued();
    }

    public function test_reset_with_valid_code_changes_password_and_allows_login(): void
    {
        Mail::fake();
        $tenant = Tenant::factory()->create();
        $user = User::factory()->owner($tenant)->create(['email' => 'owner@biz.ru']);

        $this->post('/forgot-password', ['email' => 'owner@biz.ru']);

        $code = null;
        Mail::assertQueued(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });

        $this->post('/reset-password', [
            'email' => 'owner@biz.ru',
            'code' => $code,
            'password' => 'brand-new-pass-1',
            'password_confirmation' => 'brand-new-pass-1',
        ])->assertRedirect(route('login'));

        // Код одноразовый — запись удалена.
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'owner@biz.ru']);

        // Новый пароль работает.
        $this->post('/login', ['email' => 'owner@biz.ru', 'password' => 'brand-new-pass-1'])
            ->assertRedirect(route('cabinet.dashboard'));
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_reset_with_wrong_code_fails(): void
    {
        Mail::fake();
        $tenant = Tenant::factory()->create();
        User::factory()->owner($tenant)->create(['email' => 'owner@biz.ru']);

        $this->post('/forgot-password', ['email' => 'owner@biz.ru']);

        $this->from('/reset-password')->post('/reset-password', [
            'email' => 'owner@biz.ru',
            'code' => '000000',
            'password' => 'brand-new-pass-1',
            'password_confirmation' => 'brand-new-pass-1',
        ])->assertSessionHasErrors('code');

        // Код не сожжён, пароль не сменён.
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'owner@biz.ru']);
        $this->assertGuest();
        $this->post('/login', ['email' => 'owner@biz.ru', 'password' => 'password'])
            ->assertRedirect(route('cabinet.dashboard'));
    }

    public function test_reset_with_expired_code_fails(): void
    {
        Mail::fake();
        $tenant = Tenant::factory()->create();
        User::factory()->owner($tenant)->create(['email' => 'owner@biz.ru']);

        $this->post('/forgot-password', ['email' => 'owner@biz.ru']);
        $code = null;
        Mail::assertQueued(PasswordResetCodeMail::class, function (PasswordResetCodeMail $mail) use (&$code): bool {
            $code = $mail->code;

            return true;
        });

        // Состарим код за пределы TTL (6 минут).
        DB::table('password_reset_tokens')->where('email', 'owner@biz.ru')
            ->update(['created_at' => now()->subMinutes(7)]);

        $this->from('/reset-password')->post('/reset-password', [
            'email' => 'owner@biz.ru',
            'code' => $code,
            'password' => 'brand-new-pass-1',
            'password_confirmation' => 'brand-new-pass-1',
        ])->assertSessionHasErrors('code');
    }
}
