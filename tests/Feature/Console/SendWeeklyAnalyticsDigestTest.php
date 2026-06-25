<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Modules\Channels\Models\Channel;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Notifications\Mail\OwnerEventMail;
use App\Modules\Notifications\Models\NotificationRecipient;
use App\Shared\Models\Tenant;
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

    public function test_digest_goes_only_to_directors(): void
    {
        Mail::fake();
        $tenant = Tenant::factory()->max()->create();
        NotificationRecipient::factory()->create(['tenant_id' => $tenant->id, 'role' => 'director']);
        NotificationRecipient::factory()->create(['tenant_id' => $tenant->id, 'role' => 'staff']);
        $this->leadIn($tenant);

        Artisan::call('analytics:weekly-digest');

        // Только директор — 1 письмо (сотруднику дайджест не уходит).
        Mail::assertQueued(OwnerEventMail::class, 1);
    }

    public function test_skips_when_business_disabled_digest(): void
    {
        Mail::fake();
        $tenant = Tenant::factory()->max()->create(['settings' => ['weekly_digest' => false]]);
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
