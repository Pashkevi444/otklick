<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Mail\EmailChangeCodeMail;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class EmailChangeTest extends TestCase
{
    use RefreshDatabase;

    private function owner(): User
    {
        return User::factory()->owner(Tenant::factory()->create())->create();
    }

    public function test_settings_and_email_pages_render(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->get('/account')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Account/Settings')->where('account.email', $user->email));

        $this->actingAs($user)->get('/account/email')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p->component('Account/Email')->where('currentEmail', $user->email));
    }

    public function test_request_sends_code_to_new_address(): void
    {
        Mail::fake();
        $user = $this->owner();

        $this->actingAs($user)
            ->post('/account/email', ['new_email' => 'new@example.com', 'current_password' => 'password'])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertQueued(EmailChangeCodeMail::class);
        $this->assertDatabaseHas('email_change_codes', ['user_id' => $user->id, 'new_email' => 'new@example.com']);
    }

    public function test_request_requires_correct_current_password(): void
    {
        $user = $this->owner();

        $this->actingAs($user)
            ->post('/account/email', ['new_email' => 'new@example.com', 'current_password' => 'wrong'])
            ->assertSessionHasErrors('current_password');

        $this->assertDatabaseMissing('email_change_codes', ['user_id' => $user->id]);
    }

    public function test_request_rejects_taken_email(): void
    {
        $user = $this->owner();
        User::factory()->create(['email' => 'taken@example.com']);

        $this->actingAs($user)
            ->post('/account/email', ['new_email' => 'taken@example.com', 'current_password' => 'password'])
            ->assertSessionHasErrors('new_email');
    }

    public function test_confirm_with_valid_code_changes_email(): void
    {
        $user = $this->owner();
        DB::table('email_change_codes')->insert([
            'user_id' => $user->id,
            'new_email' => 'new@example.com',
            'code_hash' => Hash::make('123456'),
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->post('/account/email/confirm', ['code' => '123456'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('new@example.com', $user->fresh()->email);
        $this->assertDatabaseMissing('email_change_codes', ['user_id' => $user->id]);
    }

    public function test_confirm_with_wrong_code_fails(): void
    {
        $user = $this->owner();
        $original = $user->email;
        DB::table('email_change_codes')->insert([
            'user_id' => $user->id,
            'new_email' => 'new@example.com',
            'code_hash' => Hash::make('123456'),
            'created_at' => now(),
        ]);

        $this->actingAs($user)
            ->post('/account/email/confirm', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertSame($original, $user->fresh()->email);
    }

    public function test_confirm_with_expired_code_fails(): void
    {
        $user = $this->owner();
        $original = $user->email;
        DB::table('email_change_codes')->insert([
            'user_id' => $user->id,
            'new_email' => 'new@example.com',
            'code_hash' => Hash::make('123456'),
            'created_at' => now()->subMinutes(20), // TTL 15 мин
        ]);

        $this->actingAs($user)
            ->post('/account/email/confirm', ['code' => '123456'])
            ->assertSessionHasErrors('code');

        $this->assertSame($original, $user->fresh()->email);
    }
}
