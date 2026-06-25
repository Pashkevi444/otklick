<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Modules\Channels\Jobs\ProcessWhatsAppUpdate;
use App\Modules\Channels\Models\Channel;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Knowledge\Models\KnowledgeEntry;
use App\Shared\Enums\ChannelType;
use App\Shared\Models\Tenant;
use App\Shared\Speech\Contracts\SpeechToText;
use App\Shared\Speech\FakeSpeechToText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ProcessWhatsAppUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function channel(Tenant $tenant): Channel
    {
        return Channel::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => ChannelType::WhatsApp->value,
            'credentials' => ['id_instance' => '1101', 'api_token' => 'tok'],
        ]);
    }

    private function seedGateDone(Tenant $tenant, Channel $channel, string $chatId): void
    {
        Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'channel_id' => $channel->id,
            'external_chat_id' => $chatId,
            'contacts_gate_done' => true,
            'status' => 'open',
        ]);
    }

    private function process(Tenant $tenant, Channel $channel, array $update): void
    {
        $this->app->call([new ProcessWhatsAppUpdate($tenant->id, $channel->id, $update), 'handle']);
    }

    public function test_text_message_is_processed_and_answered(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create();
        $channel = $this->channel($tenant);
        KnowledgeEntry::factory()->create([
            'tenant_id' => $tenant->id,
            'is_published' => true,
            'title' => 'Доставка',
            'content' => 'Доставка бесплатно от 1000 рублей',
        ]);
        $this->seedGateDone($tenant, $channel, '79991234567@c.us');

        $this->process($tenant, $channel, [
            'typeWebhook' => 'incomingMessageReceived',
            'idMessage' => 'W1',
            'senderData' => ['chatId' => '79991234567@c.us'],
            'messageData' => ['typeMessage' => 'textMessage', 'textMessageData' => ['textMessage' => 'есть ли доставка?']],
        ]);

        $this->assertDatabaseHas('messages', [
            'tenant_id' => $tenant->id,
            'direction' => 'inbound',
            'text' => 'есть ли доставка?',
        ]);
        Http::assertSent(fn ($r): bool => str_contains($r->url(), '/sendMessage/'));
    }

    public function test_voice_message_is_transcribed(): void
    {
        Http::fake([
            '*/a.ogg' => Http::response('OGG-OPUS-BYTES'),
            '*' => Http::response(['idMessage' => 'X']),
        ]);
        $this->app->instance(SpeechToText::class, new FakeSpeechToText('есть ли доставка?'));

        $tenant = Tenant::factory()->create();
        $channel = $this->channel($tenant);
        $this->seedGateDone($tenant, $channel, '79991234567@c.us');

        $this->process($tenant, $channel, [
            'typeWebhook' => 'incomingMessageReceived',
            'idMessage' => 'W2',
            'senderData' => ['chatId' => '79991234567@c.us'],
            'messageData' => ['typeMessage' => 'audioMessage', 'fileMessageData' => ['downloadUrl' => 'https://files.green-api.com/a.ogg']],
        ]);

        $this->assertDatabaseHas('messages', [
            'tenant_id' => $tenant->id,
            'direction' => 'inbound',
            'text' => 'есть ли доставка?',
        ]);
    }
}
