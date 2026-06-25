<?php

declare(strict_types=1);

namespace Tests\Feature\Vk;

use App\Modules\Channels\Jobs\ProcessVkUpdate;
use App\Modules\Channels\Models\Channel;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Knowledge\Models\KnowledgeEntry;
use App\Shared\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ProcessVkUpdateTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $messageOverrides
     * @return array<string, mixed>
     */
    private function update(array $messageOverrides = []): array
    {
        return [
            'type' => 'message_new',
            'object' => ['message' => array_merge([
                'peer_id' => 555,
                'from_id' => 555,
                'text' => 'есть ли доставка?',
                'conversation_message_id' => 10,
            ], $messageOverrides)],
        ];
    }

    private function process(Tenant $tenant, Channel $channel, array $update): void
    {
        $this->app->call([new ProcessVkUpdate($tenant->id, $channel->id, $update), 'handle']);
    }

    public function test_bot_answers_from_knowledge_and_sends_to_vk(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->vk()->create(['tenant_id' => $tenant->id]);
        KnowledgeEntry::factory()->create([
            'tenant_id' => $tenant->id,
            'is_published' => true,
            'title' => 'Доставка',
            'content' => 'Доставка бесплатно от 1000 рублей',
        ]);
        // Контактная форма уже пройдена — проверяем ответ по базе знаний.
        Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'channel_id' => $channel->id,
            'external_chat_id' => '555',
            'contacts_gate_done' => true,
            'consent_agreed' => true,
            'status' => 'open',
        ]);

        $this->process($tenant, $channel, $this->update());

        $this->assertDatabaseHas('messages', [
            'tenant_id' => $tenant->id,
            'direction' => 'inbound',
            'text' => 'есть ли доставка?',
        ]);

        $outbound = Message::query()->where('direction', 'outbound')->firstOrFail();
        $this->assertStringContainsString('бесплатно', (string) $outbound->text);

        // Ответ ушёл именно во VK (messages.send), а не в Telegram.
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/messages.send')
            && $request['peer_id'] === '555');
    }

    public function test_account_link_points_to_vk_profile(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->vk()->create(['tenant_id' => $tenant->id]);

        $this->process($tenant, $channel, $this->update());

        $this->assertDatabaseHas('conversations', [
            'tenant_id' => $tenant->id,
            'contact_ref' => 'https://vk.com/id555',
        ]);
    }

    public function test_chat_peer_id_does_not_get_a_broken_profile_link(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->vk()->create(['tenant_id' => $tenant->id]);

        // peer_id беседы (≥ 2_000_000_000) — это id чата, не пользователя: ссылки нет.
        $this->process($tenant, $channel, $this->update(['peer_id' => 2000000005, 'from_id' => 777]));

        $this->assertDatabaseHas('conversations', ['tenant_id' => $tenant->id, 'contact_ref' => null]);
    }

    public function test_duplicate_update_is_idempotent(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->vk()->create(['tenant_id' => $tenant->id]);

        $this->process($tenant, $channel, $this->update());
        $this->process($tenant, $channel, $this->update());

        $this->assertDatabaseCount('messages', 2);
        Http::assertSentCount(1);
    }

    public function test_blocked_tenant_bot_does_not_respond(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create(['is_blocked' => true]);
        $channel = Channel::factory()->vk()->create(['tenant_id' => $tenant->id]);

        $this->process($tenant, $channel, $this->update());

        $this->assertDatabaseCount('messages', 0);
        Http::assertNothingSent();
    }

    public function test_non_message_event_is_ignored(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->vk()->create(['tenant_id' => $tenant->id]);

        $this->process($tenant, $channel, ['type' => 'group_join', 'object' => ['user_id' => 555]]);

        $this->assertDatabaseCount('messages', 0);
        Http::assertNothingSent();
    }
}
