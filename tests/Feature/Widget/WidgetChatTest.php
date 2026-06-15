<?php

declare(strict_types=1);

namespace Tests\Feature\Widget;

use App\Models\Channel;
use App\Models\Tenant;
use App\Services\ChannelService;
use App\Tenancy\TenantInitializer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class WidgetChatTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<string>  $origins
     */
    private function webChannel(array $origins = []): Channel
    {
        $tenant = Tenant::factory()->create();

        return app(TenantInitializer::class)->run($tenant->id, function () use ($tenant, $origins): Channel {
            $channel = app(ChannelService::class)->connectWeb($tenant->id);

            if ($origins !== []) {
                app(ChannelService::class)->setWidgetOrigins($channel, $origins);
            }

            return $channel->refresh();
        });
    }

    private function url(Channel $channel, string $action): string
    {
        return "/widget/v1/{$channel->tenant_id}/{$channel->id}/{$action}";
    }

    public function test_session_then_message_returns_a_reply(): void
    {
        $channel = $this->webChannel();

        $session = $this->postJson($this->url($channel, 'session'));
        $session->assertOk()->assertJsonStructure(['token', 'greeting']);

        $token = $session->json('token');

        $this->postJson($this->url($channel, 'message'), ['token' => $token, 'text' => 'Здравствуйте, у вас есть доставка?'])
            ->assertOk()
            ->assertJsonStructure(['reply', 'needsHuman']);
    }

    public function test_origin_not_in_allow_list_is_forbidden(): void
    {
        $channel = $this->webChannel(['https://shop.ru']);

        $this->withHeaders(['Origin' => 'https://evil.ru'])
            ->postJson($this->url($channel, 'session'))
            ->assertForbidden();

        $this->withHeaders(['Origin' => 'https://shop.ru'])
            ->postJson($this->url($channel, 'session'))
            ->assertOk();
    }

    public function test_forged_session_token_is_rejected(): void
    {
        $channel = $this->webChannel();

        $this->postJson($this->url($channel, 'message'), ['token' => 'not-a-real-token', 'text' => 'привет'])
            ->assertForbidden();
    }

    public function test_token_from_one_widget_does_not_work_on_another(): void
    {
        $a = $this->webChannel();
        $b = $this->webChannel();

        $token = $this->postJson($this->url($a, 'session'))->json('token');

        // Токен канала A не должен приниматься каналом B.
        $this->postJson($this->url($b, 'message'), ['token' => $token, 'text' => 'привет'])
            ->assertForbidden();
    }

    public function test_non_web_channel_returns_404(): void
    {
        $tenant = Tenant::factory()->create();
        // Несуществующий/чужой канал.
        $this->postJson("/widget/v1/{$tenant->id}/00000000-0000-0000-0000-000000000000/session")
            ->assertNotFound();
    }

    public function test_widget_script_is_served_as_javascript(): void
    {
        $this->get('/widget/v1/widget.js')
            ->assertOk()
            ->assertHeader('content-type', 'application/javascript; charset=utf-8');
    }
}
