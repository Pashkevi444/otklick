<?php

declare(strict_types=1);

namespace Tests\Feature\Max;

use App\Jobs\ProcessMaxUpdate;
use App\Models\Channel;
use App\Models\KnowledgeEntry;
use App\Models\Message;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ProcessMaxUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $messageOverrides
     * @return array<string, mixed>
     */
    private function update(array $messageOverrides = []): array
    {
        return [
            'update_type' => 'message_created',
            'message' => array_merge([
                'recipient' => ['chat_id' => 555],
                'sender' => ['user_id' => 777],
                'body' => ['text' => 'есть ли доставка?', 'mid' => 'm10'],
            ], $messageOverrides),
        ];
    }

    private function process(Tenant $tenant, Channel $channel, array $update): void
    {
        $this->app->call([new ProcessMaxUpdate($tenant->id, $channel->id, $update), 'handle']);
    }

    public function test_bot_answers_from_knowledge_and_sends_to_max(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->max()->create(['tenant_id' => $tenant->id]);
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

        // Ответ ушёл именно в MAX (POST /messages с chat_id), а не в Telegram/VK.
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/messages')
            && str_contains($request->url(), 'chat_id=555'));
    }

    public function test_duplicate_update_is_idempotent(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->max()->create(['tenant_id' => $tenant->id]);

        $this->process($tenant, $channel, $this->update());
        $this->process($tenant, $channel, $this->update());

        $this->assertDatabaseCount('messages', 2);
        Http::assertSentCount(1);
    }

    public function test_blocked_tenant_bot_does_not_respond(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create(['is_blocked' => true]);
        $channel = Channel::factory()->max()->create(['tenant_id' => $tenant->id]);

        $this->process($tenant, $channel, $this->update());

        $this->assertDatabaseCount('messages', 0);
        Http::assertNothingSent();
    }

    public function test_non_message_event_is_ignored(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->max()->create(['tenant_id' => $tenant->id]);

        $this->process($tenant, $channel, ['update_type' => 'bot_started', 'chat_id' => 555]);

        $this->assertDatabaseCount('messages', 0);
        Http::assertNothingSent();
    }
}
