<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Enums\CrmSource;
use App\Enums\LeadStatus;
use App\Models\Conversation;
use App\Models\Lead;
use App\Models\Tenant;
use App\Services\LeadBackfillService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class LeadBackfillServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): LeadBackfillService
    {
        return $this->app->make(LeadBackfillService::class);
    }

    public function test_backfills_leads_from_conversations_with_a_client(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();

        $ca1 = Conversation::factory()->withClient('Иван', '+79990000001')->create(['tenant_id' => $a->id]);
        $ca2 = Conversation::factory()->withClient('Пётр', '+79990000002')->create(['tenant_id' => $a->id]);
        Conversation::factory()->create(['tenant_id' => $a->id]); // без клиента — лид не создаётся
        $cb1 = Conversation::factory()->withClient('Анна', '+79990000003')->create(['tenant_id' => $b->id]);

        $created = $this->service()->run();

        $this->assertSame(3, $created);
        $this->assertSame(2, Lead::withoutGlobalScopes()->where('tenant_id', $a->id)->count());
        $this->assertSame(1, Lead::withoutGlobalScopes()->where('tenant_id', $b->id)->count());

        $lead = Lead::withoutGlobalScopes()->where('conversation_id', $ca1->id)->firstOrFail();
        $this->assertSame(CrmSource::Bot, $lead->source);
        $this->assertSame(LeadStatus::New, $lead->status);
        $this->assertSame((string) $a->id, (string) $lead->tenant_id);

        // Диалоги связаны со своими лидами, тенанты не перепутаны.
        $this->assertTrue(Lead::withoutGlobalScopes()->where('conversation_id', $ca2->id)->where('tenant_id', $a->id)->exists());
        $this->assertTrue(Lead::withoutGlobalScopes()->where('conversation_id', $cb1->id)->where('tenant_id', $b->id)->exists());
    }

    public function test_is_idempotent(): void
    {
        $a = Tenant::factory()->create();
        Conversation::factory()->withClient('Иван', '+79990000001')->create(['tenant_id' => $a->id]);

        $this->assertSame(1, $this->service()->run());
        $this->assertSame(0, $this->service()->run()); // повторно — ничего не создаёт
        $this->assertSame(1, Lead::withoutGlobalScopes()->count());
    }
}
