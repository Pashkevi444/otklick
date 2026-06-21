<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Enums\NotificationChannelType;
use App\Enums\TenantPlan;
use App\Models\Channel;
use App\Models\CrmConnection;
use App\Models\NotificationRecipient;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class NotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function owner(?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::factory()->create(['plan' => TenantPlan::Standard]);

        return [$tenant, User::factory()->owner($tenant)->create()];
    }

    public function test_page_exposes_limits(): void
    {
        [, $owner] = $this->owner();

        $this->actingAs($owner)->get('/cabinet/notifications')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Cabinet/Notifications')
                ->where('limits.email', 1)
                ->where('limits.telegram', 4)
                ->has('recipients'));
    }

    public function test_booking_events_hidden_without_active_yclients(): void
    {
        // Без подключённого YClients события про запись (booked/cancelled) не
        // возникают — их не предлагаем в настройках уведомлений.
        [, $owner] = $this->owner();

        $this->actingAs($owner)->get('/cabinet/notifications')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('eventOptions', fn (Collection $opts): bool => $opts->pluck('value')->contains('new_lead')
                    && $opts->pluck('value')->contains('needs_human')
                    && $opts->pluck('value')->doesntContain('booked')
                    && $opts->pluck('value')->doesntContain('cancelled')));
    }

    public function test_booking_events_shown_with_active_yclients(): void
    {
        [$tenant, $owner] = $this->owner();
        CrmConnection::factory()->create(['tenant_id' => $tenant->id, 'is_active' => true]);

        $this->actingAs($owner)->get('/cabinet/notifications')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('eventOptions', fn (Collection $opts): bool => $opts->pluck('value')->contains('booked')
                    && $opts->pluck('value')->contains('cancelled')));
    }

    public function test_owner_adds_email_recipient(): void
    {
        [$tenant, $owner] = $this->owner();

        $this->actingAs($owner)->post('/cabinet/notifications/email', ['email' => 'boss@example.com'])
            ->assertRedirect();

        $this->assertDatabaseHas('notification_recipients', [
            'tenant_id' => $tenant->id,
            'type' => 'email',
            'value' => 'boss@example.com',
        ]);
    }

    public function test_email_limit_enforced(): void
    {
        [$tenant, $owner] = $this->owner();
        NotificationRecipient::factory()->create(['tenant_id' => $tenant->id]); // уже 1 (лимит Standard)

        $this->actingAs($owner)->post('/cabinet/notifications/email', ['email' => 'second@example.com'])
            ->assertSessionHasErrors('limit');

        $this->assertSame(1, NotificationRecipient::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)->where('type', 'email')->count());
    }

    public function test_override_raises_limit(): void
    {
        $tenant = Tenant::factory()->create(['plan' => TenantPlan::Standard, 'settings' => ['overrides' => ['maxNotifyEmail' => 3]]]);
        [, $owner] = $this->owner($tenant);
        NotificationRecipient::factory()->create(['tenant_id' => $tenant->id]);

        // С оверрайдом лимит 3 — второй проходит.
        $this->actingAs($owner)->post('/cabinet/notifications/email', ['email' => 'second@example.com'])
            ->assertSessionDoesntHaveErrors('limit');
    }

    public function test_connect_telegram_generates_link(): void
    {
        Http::fake(['*/getMe' => Http::response(['ok' => true, 'result' => ['username' => 'TestBot']])]);

        [$tenant, $owner] = $this->owner();
        Channel::factory()->create(['tenant_id' => $tenant->id]); // активный Telegram-бот

        $this->actingAs($owner)->post('/cabinet/notifications/telegram', [])
            ->assertRedirect()
            ->assertSessionHas('telegram_link');

        $this->assertDatabaseHas('notification_recipients', [
            'tenant_id' => $tenant->id,
            'type' => 'telegram',
            'value' => null,
            'is_active' => false,
        ]);
        $pending = NotificationRecipient::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('type', 'telegram')->firstOrFail();
        $this->assertNotNull($pending->link_token);
    }

    public function test_connect_telegram_requires_bot(): void
    {
        [, $owner] = $this->owner();

        $this->actingAs($owner)->post('/cabinet/notifications/telegram', [])
            ->assertSessionHasErrors('telegram');
    }

    public function test_owner_deletes_recipient(): void
    {
        [$tenant, $owner] = $this->owner();
        $recipient = NotificationRecipient::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($owner)->delete("/cabinet/notifications/{$recipient->id}")->assertRedirect();

        $this->assertDatabaseMissing('notification_recipients', ['id' => $recipient->id]);
    }

    public function test_owner_cannot_touch_other_tenant_recipient(): void
    {
        [, $owner] = $this->owner();
        $other = NotificationRecipient::factory()->create(['tenant_id' => Tenant::factory()->create()->id]);

        $this->actingAs($owner)->delete("/cabinet/notifications/{$other->id}")->assertNotFound();
    }

    public function test_recipient_type_enum(): void
    {
        $this->assertSame('email', NotificationChannelType::Email->value);
    }
}
