<?php

declare(strict_types=1);

namespace Tests\Feature\Widget;

use App\Models\Channel;
use App\Models\Conversation;
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
        // Главное меню бота — кнопки-подсказки, которые бот показывает после приветствия.
        $tenant->update(['settings' => [...$tenant->settings, 'bot_menu' => ['Записаться', 'Цены и услуги', 'Адрес и часы']]]);

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
            ->assertJsonStructure(['reply', 'needsHuman', 'options']);
    }

    public function test_message_exposes_clickable_options_from_keyboard(): void
    {
        $channel = $this->webChannel();
        $token = $this->postJson($this->url($channel, 'session'))->json('token');

        // Первое сообщение — вопрос (имя из него НЕ берём), бот приветствует и
        // просит представиться. Уже СЛЕДУЮЩЕЕ сообщение с именем+телефоном
        // завершает форму и отдаёт кнопки-варианты — виджет рендерит их
        // кликабельными чипами (как в мессенджерах).
        $this->postJson($this->url($channel, 'message'), ['token' => $token, 'text' => 'Здравствуйте, есть доставка?'])
            ->assertOk();

        $this->postJson($this->url($channel, 'message'), ['token' => $token, 'text' => 'Иван, +7 999 123-45-67'])
            ->assertOk()
            ->assertJsonPath('options', fn (array $o): bool => in_array('Записаться', $o, true));
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

    public function test_session_does_not_create_conversation_until_first_message(): void
    {
        $channel = $this->webChannel();

        $token = $this->postJson($this->url($channel, 'session'))->json('token');

        // Открыли виджет, но ничего не написали — диалога в БД быть не должно.
        $this->assertSame(0, Conversation::withoutGlobalScopes()->where('channel_id', $channel->id)->count());

        $this->postJson($this->url($channel, 'message'), ['token' => $token, 'text' => 'привет'])->assertOk();

        // Первое сообщение — диалог создаётся.
        $this->assertSame(1, Conversation::withoutGlobalScopes()->where('channel_id', $channel->id)->count());
    }

    public function test_widget_captures_client_phone(): void
    {
        $channel = $this->webChannel();

        $token = $this->postJson($this->url($channel, 'session'))->json('token');
        $this->postJson($this->url($channel, 'message'), [
            'token' => $token,
            'text' => 'Запишите меня, мой телефон +7 999 123-45-67',
        ])->assertOk();

        $conv = Conversation::withoutGlobalScopes()->where('channel_id', $channel->id)->firstOrFail();
        // Телефон — в карточке клиента (нормализация), не в буфере лида.
        $this->assertSame('+79991234567', $conv->client()->withoutGlobalScopes()->first()?->phone);
    }

    public function test_widget_stores_visitor_ip_as_contact_ref(): void
    {
        $channel = $this->webChannel();

        $token = $this->postJson($this->url($channel, 'session'))->json('token');
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])
            ->postJson($this->url($channel, 'message'), ['token' => $token, 'text' => 'привет'])
            ->assertOk();

        $conv = Conversation::withoutGlobalScopes()->where('channel_id', $channel->id)->firstOrFail();
        $this->assertSame('203.0.113.7', $conv->contact_ref);
    }

    public function test_widget_script_is_served_as_javascript(): void
    {
        $this->get('/widget/v1/widget.js')
            ->assertOk()
            ->assertHeader('content-type', 'application/javascript; charset=utf-8');
    }
}
