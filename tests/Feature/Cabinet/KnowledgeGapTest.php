<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Jobs\DraftGapAnswer;
use App\Jobs\ProcessTelegramUpdate;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\KnowledgeGap;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class KnowledgeGapTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function update(array $overrides = []): array
    {
        return [
            'update_id' => random_int(1, 1_000_000),
            'message' => array_merge([
                'message_id' => random_int(1, 1_000_000),
                'chat' => ['id' => 555],
                'text' => 'шо ты голова',
                'from' => ['first_name' => 'Иван'],
            ], $overrides),
        ];
    }

    public function test_unanswered_question_is_captured_as_gap(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        // Контактная форма уже пройдена — тестируем фиксацию пробела по сути вопроса.
        Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'channel_id' => $channel->id,
            'external_chat_id' => '555',
            'contacts_gate_done' => true,
            'consent_agreed' => true,
            'status' => 'open',
        ]);

        // Три подряд непонятных сообщения → бот эскалирует из-за пробела в базе.
        $this->app->call([new ProcessTelegramUpdate($tenant->id, $channel->id, $this->update(['text' => 'абракадабра один'])), 'handle']);
        $this->app->call([new ProcessTelegramUpdate($tenant->id, $channel->id, $this->update(['text' => 'абракадабра два'])), 'handle']);
        $this->app->call([new ProcessTelegramUpdate($tenant->id, $channel->id, $this->update(['text' => 'абракадабра три'])), 'handle']);

        // Вопрос, на котором бот сдался, зафиксирован как «пробел».
        $this->assertDatabaseHas('knowledge_gaps', [
            'tenant_id' => $tenant->id,
            'question' => 'абракадабра три',
            'status' => 'open',
            'channel_type' => 'telegram',
        ]);
    }

    public function test_gaps_tab_lists_open_gaps(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        KnowledgeGap::factory()->create(['tenant_id' => $tenant->id, 'question' => 'Есть парковка?', 'occurrences' => 4]);

        $this->actingAs($owner)
            ->get('/cabinet/knowledge')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Cabinet/KnowledgeBase/Index')
                ->has('gaps', 1)
                ->where('gaps.0.question', 'Есть парковка?')
                ->where('gaps.0.occurrences', 4));
    }

    public function test_promote_creates_draft_dispatches_ai_drafter_and_resolves_gap(): void
    {
        Queue::fake();

        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        $gap = KnowledgeGap::factory()->create(['tenant_id' => $tenant->id, 'question' => 'Есть ли парковка рядом?']);

        $this->actingAs($owner)
            ->post("/cabinet/knowledge-gaps/{$gap->id}/to-knowledge")
            ->assertRedirect();

        // Создан черновик-запись (текст допишет фоновая джоба AI-черновика).
        $this->assertDatabaseHas('knowledge_entries', [
            'tenant_id' => $tenant->id,
            'title' => 'Есть ли парковка рядом?',
            'is_published' => false,
        ]);
        Queue::assertPushed(DraftGapAnswer::class);
        // Пробел закрыт.
        $this->assertDatabaseHas('knowledge_gaps', ['id' => $gap->id, 'status' => 'resolved']);
    }

    public function test_dismiss_marks_gap_dismissed(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        $gap = KnowledgeGap::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($owner)
            ->post("/cabinet/knowledge-gaps/{$gap->id}/dismiss")
            ->assertRedirect();

        $this->assertDatabaseHas('knowledge_gaps', ['id' => $gap->id, 'status' => 'dismissed']);
    }

    public function test_destroy_deletes_gap(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        $gap = KnowledgeGap::factory()->create(['tenant_id' => $tenant->id]);

        $this->actingAs($owner)
            ->delete("/cabinet/knowledge-gaps/{$gap->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('knowledge_gaps', ['id' => $gap->id]);
    }

    public function test_cannot_touch_another_tenants_gap(): void
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();

        $other = Tenant::factory()->create();
        $otherGap = KnowledgeGap::factory()->create(['tenant_id' => $other->id]);

        $this->actingAs($owner)
            ->post("/cabinet/knowledge-gaps/{$otherGap->id}/dismiss")
            ->assertNotFound();

        $this->assertDatabaseHas('knowledge_gaps', ['id' => $otherGap->id, 'status' => 'open']);
    }
}
