<?php

declare(strict_types=1);

namespace Tests\Feature\Widget;

use App\Enums\ConversationStatus;
use App\Enums\MessageDirection;
use App\Events\ClientTyping;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use App\Services\ChannelService;
use App\Services\ConversationHandoffService;
use App\Tenancy\TenantInitializer;
use App\Vision\Contracts\ImageToText;
use App\Vision\FakeImageToText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
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

    public function test_poll_delivers_operator_messages_and_status(): void
    {
        $channel = $this->webChannel();
        $token = $this->postJson($this->url($channel, 'session'))->json('token');
        $lastId = $this->postJson($this->url($channel, 'message'), ['token' => $token, 'text' => 'привет'])
            ->assertOk()->json('lastId');

        // Оператор перехватывает диалог и пишет посетителю (в контексте тенанта).
        $conv = Conversation::withoutGlobalScopes()->where('channel_id', $channel->id)->firstOrFail();
        app(TenantInitializer::class)->run((string) $channel->tenant_id, function () use ($conv): void {
            $handoff = app(ConversationHandoffService::class);
            $handoff->takeOver($conv, 1);
            $handoff->reply($conv, 'Это оператор, чем помочь?');
        });

        $poll = $this->postJson($this->url($channel, 'poll'), ['token' => $token, 'after' => $lastId]);

        $poll->assertOk()->assertJsonPath('operatorActive', true);
        $this->assertContains('Это оператор, чем помочь?', collect($poll->json('messages'))->pluck('text')->all());
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

    public function test_upload_attaches_image_and_hands_off_to_operator(): void
    {
        Storage::fake('public');
        $channel = $this->webChannel();
        $token = $this->postJson($this->url($channel, 'session'))->json('token');

        $res = $this->post($this->url($channel, 'upload'), [
            'token' => $token,
            'image' => UploadedFile::fake()->image('hairstyle.jpg', 600, 400),
            'caption' => 'Вот такую стрижку хочу',
        ], ['Accept' => 'application/json']);

        $res->assertOk()
            ->assertJsonStructure(['reply', 'needsHuman', 'images', 'lastId', 'operatorActive'])
            ->assertJsonPath('needsHuman', true);
        $this->assertNotEmpty($res->json('images'));

        $conv = Conversation::withoutGlobalScopes()->where('channel_id', $channel->id)->firstOrFail();
        app(TenantInitializer::class)->run((string) $channel->tenant_id, function () use ($conv): void {
            $inbound = Message::withoutGlobalScopes()
                ->where('conversation_id', $conv->id)
                ->where('direction', MessageDirection::Inbound)
                ->firstOrFail();
            $this->assertNotEmpty($inbound->payload['images'] ?? []);

            // Диалог передан администратору (бот фото не «видит»).
            $this->assertSame(ConversationStatus::NeedsHuman, $conv->fresh()->status);
        });
    }

    public function test_recognized_photo_with_caption_gets_bot_answer_no_handoff(): void
    {
        Storage::fake('public');
        // Vision «видит» фото — возвращаем описание (вместо реальной модели).
        $this->app->instance(ImageToText::class, new FakeImageToText('каре с чёлкой'));

        $channel = $this->webChannel();
        $token = $this->postJson($this->url($channel, 'session'))->json('token');

        $res = $this->post($this->url($channel, 'upload'), [
            'token' => $token,
            'image' => UploadedFile::fake()->image('hairstyle.jpg', 600, 400),
            'caption' => 'хочу такую стрижку',
        ], ['Accept' => 'application/json']);

        // Бот ответил, диалог НЕ ушёл администратору (раньше всегда уходил).
        $res->assertOk()->assertJsonPath('needsHuman', false);
        $this->assertNotEmpty($res->json('reply'));

        $conv = Conversation::withoutGlobalScopes()->where('channel_id', $channel->id)->firstOrFail();
        $this->assertNotSame(ConversationStatus::NeedsHuman, $conv->fresh()->status);
    }

    public function test_two_photos_each_processed_with_bot_reply(): void
    {
        Storage::fake('public');
        $this->app->instance(ImageToText::class, new FakeImageToText('пример стрижки'));

        $channel = $this->webChannel();
        $token = $this->postJson($this->url($channel, 'session'))->json('token');

        // В виджете фото уходят последовательными запросами (по одному).
        foreach (['cut1.jpg', 'cut2.jpg'] as $name) {
            $this->post($this->url($channel, 'upload'), [
                'token' => $token,
                'image' => UploadedFile::fake()->image($name, 500, 500),
            ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('needsHuman', false);
        }

        $conv = Conversation::withoutGlobalScopes()->where('channel_id', $channel->id)->firstOrFail();
        app(TenantInitializer::class)->run((string) $channel->tenant_id, function () use ($conv): void {
            // Оба фото записаны как отдельные входящие, на каждое — ответ бота.
            $this->assertSame(2, Message::where('conversation_id', $conv->id)->where('direction', MessageDirection::Inbound)->count());
            $this->assertSame(2, Message::where('conversation_id', $conv->id)->where('direction', MessageDirection::Outbound)->count());
        });
    }

    public function test_client_typing_broadcasts_to_cabinet(): void
    {
        Event::fake([ClientTyping::class]);
        $channel = $this->webChannel();
        $token = $this->postJson($this->url($channel, 'session'))->json('token');

        // Диалог появляется только после первого сообщения — создаём его.
        $this->postJson($this->url($channel, 'message'), ['token' => $token, 'text' => 'привет'])->assertOk();

        $this->postJson($this->url($channel, 'typing'), ['token' => $token])
            ->assertOk()->assertJsonPath('ok', true);

        Event::assertDispatched(ClientTyping::class);
    }

    public function test_client_typing_is_silent_without_conversation(): void
    {
        // Сессия открыта, но переписки ещё нет → диалога нет → событие не шлём.
        Event::fake([ClientTyping::class]);
        $channel = $this->webChannel();
        $token = $this->postJson($this->url($channel, 'session'))->json('token');

        $this->postJson($this->url($channel, 'typing'), ['token' => $token])->assertOk();

        Event::assertNotDispatched(ClientTyping::class);
    }

    public function test_session_exposes_realtime_channel(): void
    {
        $channel = $this->webChannel();

        // reverb=null в тестах (BROADCAST != reverb), но имя канала всегда есть —
        // виджет подпишется на него, когда WS включён.
        $this->postJson($this->url($channel, 'session'))
            ->assertOk()
            ->assertJsonPath('reverb', null)
            ->assertJsonStructure(['token', 'greeting', 'channel']);
    }

    public function test_upload_rejects_non_image_file(): void
    {
        Storage::fake('public');
        $channel = $this->webChannel();
        $token = $this->postJson($this->url($channel, 'session'))->json('token');

        // Не-картинку валидация отклоняет (роут stateless; в проде виджет шлёт
        // Accept: application/json → 422, здесь — редирект-отказ). Главное: файл НЕ
        // обработан и диалог не создан.
        $this->post($this->url($channel, 'upload'), [
            'token' => $token,
            'image' => UploadedFile::fake()->create('malware.exe', 50, 'application/octet-stream'),
        ])->assertStatus(302);

        $this->assertSame(0, Conversation::withoutGlobalScopes()->where('channel_id', $channel->id)->count());
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

    public function test_config_returns_widget_color(): void
    {
        $channel = $this->webChannel();
        app(TenantInitializer::class)->run((string) $channel->tenant_id, function () use ($channel): void {
            app(ChannelService::class)->setWidgetColor($channel, '#7c3aed');
        });

        $this->getJson($this->url($channel, 'config'))
            ->assertOk()
            ->assertJsonPath('color', '#7c3aed');
    }

    public function test_config_returns_null_color_by_default(): void
    {
        $channel = $this->webChannel();

        $this->getJson($this->url($channel, 'config'))
            ->assertOk()
            ->assertJsonPath('color', null);
    }

    public function test_widget_script_is_served_as_javascript(): void
    {
        $this->get('/widget/v1/widget.js')
            ->assertOk()
            ->assertHeader('content-type', 'application/javascript; charset=utf-8');
    }
}
