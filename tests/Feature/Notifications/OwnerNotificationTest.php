<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\DTO\OwnerNotification;
use App\Enums\NotificationChannelType;
use App\Enums\OwnerEvent;
use App\Jobs\ProcessTelegramUpdate;
use App\Jobs\SendOwnerNotification;
use App\Mail\OwnerEventMail;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\NotificationRecipient;
use App\Models\Tenant;
use App\Services\NotificationService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class OwnerNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_incoming_message_dispatches_owner_notification(): void
    {
        Http::fake();
        Bus::fake([SendOwnerNotification::class]);

        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);

        $update = [
            'update_id' => 100,
            'message' => ['message_id' => 10, 'chat' => ['id' => 555], 'text' => 'привет', 'from' => ['first_name' => 'Иван']],
        ];
        $this->app->call([new ProcessTelegramUpdate($tenant->id, $channel->id, $update), 'handle']);

        Bus::assertDispatched(SendOwnerNotification::class, fn (SendOwnerNotification $job): bool => $job->tenantId === $tenant->id && $job->event === OwnerEvent::NewLead->value);
    }

    public function test_service_sends_email_to_deliverable_recipients(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->id);
        NotificationRecipient::factory()->create(['tenant_id' => $tenant->id]); // email, активен, подтверждён

        $this->app->make(NotificationService::class)->send($tenant, OwnerEvent::NewLead, ['contact' => 'Иван']);

        Mail::assertQueued(OwnerEventMail::class);
    }

    public function test_notification_includes_client_profile_link(): void
    {
        Mail::fake();

        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->id);
        NotificationRecipient::factory()->create(['tenant_id' => $tenant->id]);

        // Ссылка на аккаунт клиента (VK/Telegram) должна попасть в уведомление,
        // чтобы владелец мог написать клиенту в его канал.
        $this->app->make(NotificationService::class)->send($tenant, OwnerEvent::NewLead, [
            'contact' => 'Гость',
            'profile' => 'https://vk.com/id777',
        ]);

        Mail::assertQueued(OwnerEventMail::class, fn (OwnerEventMail $mail): bool => str_contains($mail->render(), 'https://vk.com/id777'));
    }

    public function test_owner_event_mail_renders(): void
    {
        // Рендер markdown-шаблона не должен падать («No hint path for [mail]»).
        $mail = new OwnerEventMail(new OwnerNotification('Тема', "Строка 1\nСтрока 2"));

        $html = $mail->render();

        $this->assertStringContainsString('Строка 1', $html);
    }

    public function test_telegram_start_links_pending_recipient(): void
    {
        Http::fake();

        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $recipient = NotificationRecipient::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => NotificationChannelType::Telegram,
            'value' => null,
            'is_active' => false,
            'verified_at' => null,
            'link_token' => 'tok123ABC',
        ]);

        $update = [
            'update_id' => 101,
            'message' => ['message_id' => 11, 'chat' => ['id' => 999], 'text' => '/start notify_tok123ABC'],
        ];
        $this->app->call([new ProcessTelegramUpdate($tenant->id, $channel->id, $update), 'handle']);

        $fresh = $recipient->fresh();
        $this->assertSame('999', $fresh->value);
        $this->assertTrue($fresh->is_active);
        $this->assertNull($fresh->link_token);
        $this->assertNotNull($fresh->verified_at);

        // Это была команда привязки — обычный диалог не создаётся.
        $this->assertSame(0, Conversation::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
    }
}
