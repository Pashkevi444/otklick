<?php

declare(strict_types=1);

namespace Tests\Feature\WhatsApp;

use App\Modules\Channels\Jobs\ProcessWhatsAppUpdate;
use App\Modules\Channels\Models\Channel;
use App\Shared\Enums\ChannelType;
use App\Shared\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class PollWhatsAppUpdatesTest extends TestCase
{
    use RefreshDatabase;

    private function channel(Tenant $tenant, bool $active = true): Channel
    {
        return Channel::factory()->create([
            'tenant_id' => $tenant->id,
            'type' => ChannelType::WhatsApp,
            'is_active' => $active,
            'credentials' => ['id_instance' => '1101', 'api_token' => 'tok'],
        ]);
    }

    /**
     * @return array{receiptId: int, body: array<string, mixed>}
     */
    private function incoming(int $receiptId): array
    {
        return [
            'receiptId' => $receiptId,
            'body' => [
                'typeWebhook' => 'incomingMessageReceived',
                'idMessage' => 'W'.$receiptId,
                'senderData' => ['chatId' => '79991234567@c.us'],
                'messageData' => ['typeMessage' => 'textMessage', 'textMessageData' => ['textMessage' => 'привет']],
            ],
        ];
    }

    public function test_poll_fetches_updates_and_dispatches_jobs(): void
    {
        Bus::fake([ProcessWhatsAppUpdate::class]);

        $tenant = Tenant::factory()->create();
        $channel = $this->channel($tenant);

        Http::fake([
            // Очередь Green API: одно входящее, затем пусто (тело null) — дренаж стоп.
            '*/receiveNotification/*' => Http::sequence()
                ->push($this->incoming(7))
                ->whenEmpty(Http::response(null)),
            '*/deleteNotification/*' => Http::response([]),
        ]);

        $this->artisan('whatsapp:poll', ['--once' => true])->assertExitCode(0);

        Bus::assertDispatched(ProcessWhatsAppUpdate::class, fn (ProcessWhatsAppUpdate $job): bool => $job->channelId === $channel->id
            && ($job->update['typeWebhook'] ?? null) === 'incomingMessageReceived');

        // Уведомление подтверждено (deleteNotification вызван с его receiptId).
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/deleteNotification/tok/7'));
    }

    public function test_poll_fetches_all_active_channels_in_one_round(): void
    {
        // Конкурентный приём: первое уведомление обоих каналов тянется за один круг
        // (раньше — по очереди), джоба ставится на каждый канал.
        Bus::fake([ProcessWhatsAppUpdate::class]);

        $tenant = Tenant::factory()->create();
        $ch1 = $this->channel($tenant);
        $ch2 = $this->channel($tenant);

        Http::fake([
            '*/receiveNotification/*' => Http::sequence()
                ->push($this->incoming(1))
                ->push($this->incoming(2))
                ->whenEmpty(Http::response(null)),
            '*/deleteNotification/*' => Http::response([]),
        ]);

        $this->artisan('whatsapp:poll', ['--once' => true])->assertExitCode(0);

        Bus::assertDispatched(ProcessWhatsAppUpdate::class, fn (ProcessWhatsAppUpdate $job): bool => $job->channelId === $ch1->id);
        Bus::assertDispatched(ProcessWhatsAppUpdate::class, fn (ProcessWhatsAppUpdate $job): bool => $job->channelId === $ch2->id);
    }

    public function test_poll_ignores_inactive_channels(): void
    {
        Bus::fake([ProcessWhatsAppUpdate::class]);

        $tenant = Tenant::factory()->create();
        $this->channel($tenant, active: false);

        Http::fake();

        $this->artisan('whatsapp:poll', ['--once' => true])->assertExitCode(0);

        Http::assertNothingSent();
        Bus::assertNotDispatched(ProcessWhatsAppUpdate::class);
    }
}
