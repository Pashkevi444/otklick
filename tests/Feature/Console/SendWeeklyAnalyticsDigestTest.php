<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Mail\OwnerEventMail;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\NotificationRecipient;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

final class SendWeeklyAnalyticsDigestTest extends TestCase
{
    use RefreshDatabase;

    private function recipient(Tenant $tenant): void
    {
        // Доставляемый получатель уведомлений владельца (email, активен).
        NotificationRecipient::factory()->create(['tenant_id' => $tenant->id]);
    }

    private function leadIn(Tenant $tenant): void
    {
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'channel_id' => $channel->id,
            'created_at' => now()->subDays(2),
        ]);
    }

    public function test_sends_digest_for_max_tenant_with_leads(): void
    {
        Mail::fake();
        $tenant = Tenant::factory()->max()->create();
        $this->recipient($tenant);
        $this->leadIn($tenant);

        Artisan::call('analytics:weekly-digest');

        Mail::assertQueued(OwnerEventMail::class, 1);
    }

    public function test_skips_tenant_without_ai_insights(): void
    {
        Mail::fake();
        $tenant = Tenant::factory()->create(['plan' => 'standard']);
        $this->recipient($tenant);
        $this->leadIn($tenant);

        Artisan::call('analytics:weekly-digest');

        Mail::assertNotQueued(OwnerEventMail::class);
    }

    public function test_skips_max_tenant_without_leads(): void
    {
        Mail::fake();
        $tenant = Tenant::factory()->max()->create();
        $this->recipient($tenant);

        Artisan::call('analytics:weekly-digest');

        Mail::assertNotQueued(OwnerEventMail::class);
    }
}
