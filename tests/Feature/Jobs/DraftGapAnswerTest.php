<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\DTO\KnowledgeEntryData;
use App\Jobs\DraftGapAnswer;
use App\Llm\Contracts\LlmClient;
use App\Models\Tenant;
use App\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use App\Services\GapDraftStatus;
use App\Tenancy\TenantInitializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DraftGapAnswerTest extends TestCase
{
    use RefreshDatabase;

    public function test_drafts_answer_into_entry_and_marks_ready(): void
    {
        $this->app->bind(LlmClient::class, fn (): LlmClient => new class implements LlmClient
        {
            public function generate(string $systemPrompt, array $messages): string
            {
                return 'Да, рядом есть бесплатная парковка.';
            }
        });

        $tenant = Tenant::factory()->create();
        $entry = app(TenantInitializer::class)->run(
            $tenant->id,
            fn () => app(KnowledgeEntryRepositoryInterface::class)->create(
                new KnowledgeEntryData('Есть ли парковка рядом?', '', false),
            ),
        );

        app(GapDraftStatus::class)->begin($entry->id);

        DraftGapAnswer::dispatchSync($tenant->id, $entry->id, 'Есть ли парковка рядом?');

        // Фоновая джоба вписала AI-черновик в content и сняла статус «пишется».
        $this->assertDatabaseHas('knowledge_entries', [
            'id' => $entry->id,
            'content' => 'Да, рядом есть бесплатная парковка.',
        ]);
        $this->assertFalse(app(GapDraftStatus::class)->isDrafting($entry->id));
    }
}
