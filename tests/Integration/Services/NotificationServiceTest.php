<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Enums\OwnerEvent;
use App\Mail\OwnerEventMail;
use App\Models\NotificationRecipient;
use App\Models\Tenant;
use App\Services\NotificationService;
use App\Tenancy\TenantInitializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class NotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function send(Tenant $tenant, OwnerEvent $event): void
    {
        app(TenantInitializer::class)->run($tenant->id, fn () => app(NotificationService::class)->send($tenant, $event, []));
    }

    public function test_sends_only_to_recipients_subscribed_to_the_event(): void
    {
        Mail::fake();
        $tenant = Tenant::factory()->create();
        // Подписан только на эскалацию.
        NotificationRecipient::factory()->create(['tenant_id' => $tenant->id, 'events' => ['needs_human']]);
        // Подписан на все типы (events = []).
        NotificationRecipient::factory()->create(['tenant_id' => $tenant->id, 'events' => []]);

        $this->send($tenant, OwnerEvent::NewLead);

        // «Новый лид» получает только тот, кто подписан на все — 1 письмо.
        Mail::assertQueued(OwnerEventMail::class, 1);
    }

    public function test_sends_to_both_when_event_matches_subscription(): void
    {
        Mail::fake();
        $tenant = Tenant::factory()->create();
        NotificationRecipient::factory()->create(['tenant_id' => $tenant->id, 'events' => ['needs_human']]);
        NotificationRecipient::factory()->create(['tenant_id' => $tenant->id, 'events' => []]);

        $this->send($tenant, OwnerEvent::NeedsHuman);

        // Эскалацию получают оба (подписанный на неё + подписанный на всё).
        Mail::assertQueued(OwnerEventMail::class, 2);
    }
}
