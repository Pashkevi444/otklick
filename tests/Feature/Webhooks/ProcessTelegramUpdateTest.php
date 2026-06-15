<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Jobs\ProcessTelegramUpdate;
use App\Models\Channel;
use App\Models\KnowledgeEntry;
use App\Models\Message;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ProcessTelegramUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function update(array $overrides = []): array
    {
        return [
            'update_id' => 100,
            'message' => array_merge([
                'message_id' => 10,
                'chat' => ['id' => 555],
                'text' => 'есть ли доставка?',
                'from' => ['first_name' => 'Иван'],
            ], $overrides),
        ];
    }

    private function process(Tenant $tenant, Channel $channel, array $update): void
    {
        $this->app->call([new ProcessTelegramUpdate($tenant->id, $channel->id, $update), 'handle']);
    }

    public function test_bot_answers_from_published_knowledge(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        KnowledgeEntry::factory()->create([
            'tenant_id' => $tenant->id,
            'is_published' => true,
            'title' => 'Доставка',
            'content' => 'Доставка бесплатно от 1000 рублей',
        ]);

        $this->process($tenant, $channel, $this->update());

        $this->assertDatabaseHas('messages', [
            'tenant_id' => $tenant->id,
            'direction' => 'inbound',
            'text' => 'есть ли доставка?',
        ]);

        $outbound = Message::query()->where('direction', 'outbound')->firstOrFail();
        $this->assertStringContainsString('бесплатно', (string) $outbound->text);
        $this->assertDatabaseHas('conversations', ['tenant_id' => $tenant->id, 'status' => 'open']);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/sendMessage')
            && str_contains((string) $request['text'], 'бесплатно'));
    }

    public function test_unknown_question_falls_back_and_flags_needs_human(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);

        $this->process($tenant, $channel, $this->update(['text' => 'расскажи про квантовую физику']));

        $outbound = Message::query()->where('direction', 'outbound')->firstOrFail();
        $this->assertStringContainsString('администратору', (string) $outbound->text);
        $this->assertDatabaseHas('conversations', ['tenant_id' => $tenant->id, 'status' => 'needs_human']);
    }

    public function test_duplicate_update_is_idempotent(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);

        $this->process($tenant, $channel, $this->update());
        $this->process($tenant, $channel, $this->update());

        // 1 входящее + 1 исходящее, без дублей.
        $this->assertDatabaseCount('messages', 2);
        Http::assertSentCount(1);
    }

    public function test_non_text_update_is_ignored(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);

        $this->process($tenant, $channel, ['update_id' => 101, 'message' => ['message_id' => 11, 'chat' => ['id' => 555], 'photo' => []]]);

        $this->assertDatabaseCount('messages', 0);
        Http::assertNothingSent();
    }
}
