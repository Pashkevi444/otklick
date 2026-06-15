<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Jobs\ProcessTelegramUpdate;
use App\Models\Channel;
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
                'text' => 'привет',
                'from' => ['first_name' => 'Иван'],
            ], $overrides),
        ];
    }

    public function test_processing_records_messages_and_sends_echo(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);

        $this->app->call([new ProcessTelegramUpdate($tenant->id, $channel->id, $this->update()), 'handle']);

        $this->assertDatabaseHas('messages', [
            'tenant_id' => $tenant->id,
            'direction' => 'inbound',
            'external_message_id' => '10',
            'text' => 'привет',
        ]);
        $this->assertDatabaseHas('messages', [
            'tenant_id' => $tenant->id,
            'direction' => 'outbound',
            'text' => 'Вы написали: привет',
            'status' => 'sent',
        ]);
        $this->assertDatabaseHas('conversations', [
            'tenant_id' => $tenant->id,
            'external_chat_id' => '555',
            'contact_name' => 'Иван',
        ]);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/sendMessage')
            && $request['chat_id'] === '555'
            && $request['text'] === 'Вы написали: привет');
    }

    public function test_duplicate_update_is_idempotent(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);

        $this->app->call([new ProcessTelegramUpdate($tenant->id, $channel->id, $this->update()), 'handle']);
        $this->app->call([new ProcessTelegramUpdate($tenant->id, $channel->id, $this->update()), 'handle']);

        // 1 входящее + 1 исходящее, без дублей.
        $this->assertDatabaseCount('messages', 2);
        Http::assertSentCount(1);
    }

    public function test_non_text_update_is_ignored(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);

        $update = ['update_id' => 101, 'message' => ['message_id' => 11, 'chat' => ['id' => 555], 'photo' => []]];
        $this->app->call([new ProcessTelegramUpdate($tenant->id, $channel->id, $update), 'handle']);

        $this->assertDatabaseCount('messages', 0);
        Http::assertNothingSent();
    }
}
