<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Modules\Conversations\Models\Conversation;
use App\Shared\Enums\ConversationOutcome;
use App\Shared\Enums\ConversationStatus;
use App\Shared\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CloseStaleConversationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_closes_stale_open_dialogs_across_tenants(): void
    {
        $t1 = Tenant::factory()->create();
        $stale = Conversation::factory()->create([
            'tenant_id' => $t1->id,
            'status' => ConversationStatus::Open,
            'last_message_at' => now()->subHour(),
        ]);

        $t2 = Tenant::factory()->create();
        $fresh = Conversation::factory()->create([
            'tenant_id' => $t2->id,
            'status' => ConversationStatus::Open,
            'last_message_at' => now()->subMinutes(5),
        ]);

        $this->artisan('conversations:close-stale')->assertSuccessful();

        // Потерянный лид: закрыт без записи.
        $this->assertSame(ConversationStatus::Closed, $stale->fresh()->status);
        $this->assertNull($stale->fresh()->booked_at);
        // Свежий — остаётся в работе.
        $this->assertSame(ConversationStatus::Open, $fresh->fresh()->status);
    }

    public function test_closes_needs_human_dialogs_idle_over_a_day_as_lost(): void
    {
        $tenant = Tenant::factory()->create();

        // Висит «нужен человек» больше суток — оператор не разобрал → потерянный лид.
        $stale = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => ConversationStatus::NeedsHuman,
            'last_message_at' => now()->subHours(25),
        ]);

        // Недавняя эскалация — ещё ждёт оператора, не трогаем.
        $fresh = Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => ConversationStatus::NeedsHuman,
            'last_message_at' => now()->subHours(2),
        ]);

        $this->artisan('conversations:close-stale')->assertSuccessful();

        $this->assertSame(ConversationStatus::Closed, $stale->fresh()->status);
        $this->assertSame(ConversationOutcome::Lost, $stale->fresh()->outcome());
        $this->assertSame(ConversationStatus::NeedsHuman, $fresh->fresh()->status);
    }
}
