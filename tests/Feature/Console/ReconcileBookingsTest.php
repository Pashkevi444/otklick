<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Modules\Booking\Models\CrmConnection;
use App\Modules\Channels\Models\Channel;
use App\Modules\Conversations\Models\Conversation;
use App\Shared\Enums\ConversationOutcome;
use App\Shared\Enums\ConversationStatus;
use App\Shared\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class ReconcileBookingsTest extends TestCase
{
    use RefreshDatabase;

    private function bookedConversation(Tenant $tenant, Carbon $visitAt): Conversation
    {
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);

        return Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'channel_id' => $channel->id,
            'status' => 'open',
            'booked_at' => now(),
            'crm_record_id' => 'rec-x',
            'booked_for' => $visitAt,
        ]);
    }

    public function test_closes_completed_bookings_only_for_crm_tenants(): void
    {
        // Тенант С CRM — завершённую запись (визит прошёл) закрываем → «Успешный лид».
        $crmTenant = Tenant::factory()->create();
        CrmConnection::factory()->create(['tenant_id' => $crmTenant->id]);
        $crmConv = $this->bookedConversation($crmTenant, now()->subHour());

        // Тенант БЕЗ CRM — даже завершённую не трогаем (лишний код не крутится).
        $noCrmTenant = Tenant::factory()->create();
        $noCrmConv = $this->bookedConversation($noCrmTenant, now()->subHour());

        $this->artisan('bookings:reconcile')->assertSuccessful();

        $this->assertSame(ConversationStatus::Closed, $crmConv->fresh()->status);
        $this->assertSame(ConversationOutcome::Booked, $crmConv->fresh()->outcome());

        $this->assertSame(ConversationStatus::Open, $noCrmConv->fresh()->status);
    }

    public function test_does_not_close_future_bookings(): void
    {
        $tenant = Tenant::factory()->create();
        CrmConnection::factory()->create(['tenant_id' => $tenant->id]);
        $future = $this->bookedConversation($tenant, now()->addDays(2));

        $this->artisan('bookings:reconcile')->assertSuccessful();

        $this->assertSame(ConversationStatus::Open, $future->fresh()->status);
        $this->assertSame(ConversationOutcome::Open, $future->fresh()->outcome()); // ещё «в работе»
    }
}
