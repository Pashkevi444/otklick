<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\DTO\LeadData;
use App\Enums\CrmSource;
use App\Enums\LeadStatus;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Tenant;
use App\Services\LeadService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LeadServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->max()->create();
        $this->app->make(TenantContext::class)->set($this->tenant->id);
    }

    private function service(): LeadService
    {
        return $this->app->make(LeadService::class);
    }

    private function conversationWithClient(): Conversation
    {
        return Conversation::factory()
            ->withClient('Алексей', '+79990001122')
            ->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_create_from_conversation_is_idempotent(): void
    {
        $conversation = $this->conversationWithClient();

        $lead = $this->service()->createFromConversation($conversation);

        $this->assertNotNull($lead);
        $this->assertSame(CrmSource::Bot, $lead->source);
        $this->assertSame((string) $conversation->client_id, (string) $lead->client_id);

        // Повторный вызов не плодит второй лид.
        $this->assertNull($this->service()->createFromConversation($conversation));
        $this->assertSame(1, Lead::query()->count());
    }

    public function test_create_from_conversation_skips_without_client(): void
    {
        $conversation = Conversation::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->assertNull($this->service()->createFromConversation($conversation));
        $this->assertSame(0, Lead::query()->count());
    }

    public function test_convert_to_deal_creates_linked_deal_in_first_stage(): void
    {
        $conversation = $this->conversationWithClient();
        $lead = $this->service()->createFromConversation($conversation);

        $deal = $this->service()->convertToDeal($lead);

        $this->assertNotNull($deal);
        $this->assertSame(1, Deal::query()->count());
        $this->assertSame((string) $lead->client_id, (string) $deal->client_id);

        $fresh = $lead->fresh();
        $this->assertSame($deal->id, $fresh->deal_id);
        $this->assertSame(LeadStatus::Converted, $fresh->status);
    }

    public function test_convert_to_deal_is_idempotent(): void
    {
        $lead = $this->service()->createManual(new LeadData(title: 'Заявка с сайта'));

        $first = $this->service()->convertToDeal($lead);
        $second = $this->service()->convertToDeal($lead->fresh());

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Deal::query()->count());
    }

    public function test_dismiss_marks_lead_dismissed(): void
    {
        $lead = $this->service()->createManual(new LeadData(title: 'Спам'));

        $this->service()->dismiss($lead);

        $this->assertSame(LeadStatus::Dismissed, $lead->fresh()->status);
    }
}
