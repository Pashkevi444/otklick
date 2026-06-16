<?php

declare(strict_types=1);

namespace Tests\Integration\Repositories;

use App\Enums\ChannelType;
use App\Enums\MessageDirection;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Repositories\Contracts\LeadAnalyticsRepositoryInterface;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class LeadAnalyticsRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private LeadAnalyticsRepositoryInterface $repo;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = $this->app->make(LeadAnalyticsRepositoryInterface::class);
        $this->tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($this->tenant->id);
    }

    public function test_leads_in_range_with_inbound_count_and_tenant_isolation(): void
    {
        $channel = Channel::factory()->create(['tenant_id' => $this->tenant->id, 'type' => ChannelType::Telegram]);

        $inRange = Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $channel->id,
            'created_at' => now()->subDays(2),
        ]);
        Message::factory()->create(['tenant_id' => $this->tenant->id, 'conversation_id' => $inRange->id, 'direction' => MessageDirection::Inbound]);
        Message::factory()->create(['tenant_id' => $this->tenant->id, 'conversation_id' => $inRange->id, 'direction' => MessageDirection::Inbound]);
        Message::factory()->create(['tenant_id' => $this->tenant->id, 'conversation_id' => $inRange->id, 'direction' => MessageDirection::Outbound]);

        // Вне окна.
        Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $channel->id,
            'created_at' => now()->subDays(200),
        ]);

        // Другой тенант — не должен попасть (RLS/scope).
        $other = Tenant::factory()->create();
        Conversation::factory()->create(['tenant_id' => $other->id, 'created_at' => now()->subDay()]);

        $leads = $this->repo->leadsForAnalytics(Carbon::now()->subDays(30), Carbon::now());

        $this->assertCount(1, $leads);
        $this->assertSame($inRange->id, $leads->first()->id);
        $this->assertSame(2, (int) $leads->first()->getAttribute('inbound_count'));
    }

    public function test_connected_channel_types_only_active(): void
    {
        Channel::factory()->create(['tenant_id' => $this->tenant->id, 'type' => ChannelType::Telegram, 'is_active' => true]);
        Channel::factory()->create(['tenant_id' => $this->tenant->id, 'type' => ChannelType::Web, 'is_active' => false]);

        $types = $this->repo->connectedChannelTypes();

        $this->assertContains('telegram', $types);
        $this->assertNotContains('web', $types);
    }

    public function test_recent_leads_are_limited_and_newest_first(): void
    {
        $channel = Channel::factory()->create(['tenant_id' => $this->tenant->id]);
        Conversation::factory()->count(10)->create(['tenant_id' => $this->tenant->id, 'channel_id' => $channel->id, 'last_message_at' => now()]);

        $recent = $this->repo->recentLeads(5);

        $this->assertCount(5, $recent);
    }
}
