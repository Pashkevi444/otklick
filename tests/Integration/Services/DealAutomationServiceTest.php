<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Enums\DealStageAutomation;
use App\Enums\PipelineEvent;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\DealStage;
use App\Models\Tenant;
use App\Services\DealAutomationService;
use App\Services\LeadService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DealAutomationServiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->max()->create();
        $this->app->make(TenantContext::class)->set($this->tenant->id);
    }

    private function service(): DealAutomationService
    {
        return $this->app->make(DealAutomationService::class);
    }

    private function leadOnConversation(): array
    {
        $conversation = Conversation::factory()->withClient('Иван', '+79990001122')->create(['tenant_id' => $this->tenant->id]);
        $lead = $this->app->make(LeadService::class)->createFromConversation($conversation);

        return [$conversation, $lead];
    }

    private function stageOf(DealStageAutomation $role): DealStage
    {
        return DealStage::query()->where('automation', $role->value)->firstOrFail();
    }

    public function test_event_without_lead_does_nothing(): void
    {
        $conversation = Conversation::factory()->create(['tenant_id' => $this->tenant->id]); // контактов нет → лида нет

        $this->service()->onEvent($conversation, PipelineEvent::Booked);

        $this->assertSame(0, Deal::query()->count());
    }

    public function test_first_event_converts_lead_and_moves_deal_to_working(): void
    {
        [$conversation, $lead] = $this->leadOnConversation();

        $this->service()->onEvent($conversation, PipelineEvent::Booked);

        $this->assertSame(1, Deal::query()->count());
        $deal = Deal::query()->firstOrFail();
        $this->assertSame($this->stageOf(DealStageAutomation::Working)->id, $deal->stage_id);
        $this->assertSame($deal->id, $lead->fresh()->deal_id); // лид связан со сделкой
    }

    public function test_needs_human_then_won_moves_existing_deal(): void
    {
        [$conversation] = $this->leadOnConversation();

        $this->service()->onEvent($conversation, PipelineEvent::NeedsHuman);
        $this->assertSame($this->stageOf(DealStageAutomation::NeedsHuman)->id, Deal::query()->firstOrFail()->stage_id);

        $this->service()->onEvent($conversation, PipelineEvent::Won);

        $this->assertSame(1, Deal::query()->count()); // та же сделка, не новая
        $this->assertSame($this->stageOf(DealStageAutomation::Won)->id, Deal::query()->firstOrFail()->stage_id);
    }

    public function test_cancelled_moves_deal_to_lost(): void
    {
        [$conversation] = $this->leadOnConversation();

        $this->service()->onEvent($conversation, PipelineEvent::Cancelled);

        $this->assertSame($this->stageOf(DealStageAutomation::Lost)->id, Deal::query()->firstOrFail()->stage_id);
    }
}
