<?php

declare(strict_types=1);

namespace Tests\Feature\Cabinet;

use App\Modules\Channels\Models\Channel;
use App\Modules\Conversations\Events\OperatorTyping;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Shared\Enums\ChannelType;
use App\Shared\Enums\MessageDirection;
use App\Shared\Enums\UserRole;
use App\Shared\Models\Tenant;
use App\Shared\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Перехват диалога оператором (живой чат): перехват/ответ/возврат + лайв-поллинг
 * + авто-возврат боту по простою. На веб-канале (без пуша) — чтобы не ходить в сеть.
 */
final class ConversationHandoffTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Tenant, 1: User, 2: Conversation}
     */
    private function setup3(): array
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->owner($tenant)->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id, 'type' => ChannelType::Web]);
        $conversation = Conversation::factory()->create(['tenant_id' => $tenant->id, 'channel_id' => $channel->id]);

        return [$tenant, $owner, $conversation];
    }

    public function test_owner_takes_over_and_bot_is_marked_handling(): void
    {
        [, $owner, $conv] = $this->setup3();

        $this->actingAs($owner)->postJson("/cabinet/conversations/{$conv->id}/takeover")
            ->assertOk()
            ->assertJsonPath('operatorActive', true);

        $fresh = Conversation::withoutGlobalScopes()->findOrFail($conv->id);
        $this->assertNotNull($fresh->operator_active_at);
        $this->assertSame($owner->id, $fresh->operator_user_id);
        $this->assertTrue($fresh->isOperatorHandling());
    }

    public function test_operator_reply_records_outbound(): void
    {
        [$tenant, $owner, $conv] = $this->setup3();
        $this->actingAs($owner)->postJson("/cabinet/conversations/{$conv->id}/takeover")->assertOk();

        $this->actingAs($owner)->postJson("/cabinet/conversations/{$conv->id}/reply", ['text' => 'Здравствуйте, я оператор!'])
            ->assertOk()
            ->assertJsonPath('message.text', 'Здравствуйте, я оператор!');

        $this->assertDatabaseHas('messages', [
            'tenant_id' => $tenant->id,
            'conversation_id' => $conv->id,
            'direction' => MessageDirection::Outbound->value,
            'text' => 'Здравствуйте, я оператор!',
        ]);
    }

    public function test_operator_can_send_image_to_client(): void
    {
        Storage::fake('public');
        [$tenant, $owner, $conv] = $this->setup3();
        $this->actingAs($owner)->postJson("/cabinet/conversations/{$conv->id}/takeover")->assertOk();

        $res = $this->actingAs($owner)->post("/cabinet/conversations/{$conv->id}/reply", [
            'image' => UploadedFile::fake()->image('work.jpg', 500, 500),
        ], ['Accept' => 'application/json']);

        // Фото-ответ принят без текста; в сообщении есть URL картинки.
        $res->assertOk();
        $this->assertNotEmpty($res->json('message.images'));

        $msg = Message::withoutGlobalScopes()->where('conversation_id', $conv->id)
            ->where('direction', MessageDirection::Outbound)->firstOrFail();
        $this->assertNotEmpty($msg->payload['images'] ?? []);
    }

    public function test_operator_reply_requires_text_or_image(): void
    {
        [, $owner, $conv] = $this->setup3();
        $this->actingAs($owner)->postJson("/cabinet/conversations/{$conv->id}/takeover")->assertOk();

        // Пустой ответ (ни текста, ни фото) — валидация отклоняет. Кабинет на Inertia,
        // поэтому ошибка валидации приходит редиректом с ошибками в сессии, не 422.
        $this->actingAs($owner)->post("/cabinet/conversations/{$conv->id}/reply", [])
            ->assertSessionHasErrors('text');
    }

    public function test_reply_requires_takeover_first(): void
    {
        [, $owner, $conv] = $this->setup3();

        $this->actingAs($owner)->postJson("/cabinet/conversations/{$conv->id}/reply", ['text' => 'привет'])
            ->assertStatus(422);
    }

    public function test_messages_poll_returns_new_after_cursor(): void
    {
        [$tenant, $owner, $conv] = $this->setup3();
        $first = Message::factory()->create(['tenant_id' => $tenant->id, 'conversation_id' => $conv->id, 'direction' => MessageDirection::Inbound, 'text' => 'первое']);
        $second = Message::factory()->create(['tenant_id' => $tenant->id, 'conversation_id' => $conv->id, 'direction' => MessageDirection::Outbound, 'text' => 'второе']);

        $this->actingAs($owner)->getJson("/cabinet/conversations/{$conv->id}/messages?after={$first->id}")
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.id', $second->id)
            ->assertJsonPath('operatorActive', false);
    }

    public function test_release_returns_dialog_to_bot(): void
    {
        [, $owner, $conv] = $this->setup3();
        $this->actingAs($owner)->postJson("/cabinet/conversations/{$conv->id}/takeover")->assertOk();

        $this->actingAs($owner)->postJson("/cabinet/conversations/{$conv->id}/release")
            ->assertOk()
            ->assertJsonPath('operatorActive', false);

        $fresh = Conversation::withoutGlobalScopes()->findOrFail($conv->id);
        $this->assertNull($fresh->operator_active_at);
        $this->assertFalse($fresh->isOperatorHandling());
    }

    public function test_operator_typing_broadcasts_when_handling(): void
    {
        Event::fake([OperatorTyping::class]);
        [, $owner, $conv] = $this->setup3();
        $this->actingAs($owner)->postJson("/cabinet/conversations/{$conv->id}/takeover")->assertOk();

        $this->actingAs($owner)->postJson("/cabinet/conversations/{$conv->id}/typing")
            ->assertOk()->assertJsonPath('ok', true);

        Event::assertDispatched(OperatorTyping::class);
    }

    public function test_operator_typing_is_silent_without_takeover(): void
    {
        // Перехвата нет → индикатор «оператор печатает» бессмыслен, событие не шлём.
        Event::fake([OperatorTyping::class]);
        [, $owner, $conv] = $this->setup3();

        $this->actingAs($owner)->postJson("/cabinet/conversations/{$conv->id}/typing")->assertOk();

        Event::assertNotDispatched(OperatorTyping::class);
    }

    public function test_member_without_edit_permission_cannot_signal_typing(): void
    {
        [$tenant, , $conv] = $this->setup3();
        $member = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Member->value, 'permissions' => []]);

        $this->actingAs($member)->postJson("/cabinet/conversations/{$conv->id}/typing")->assertForbidden();
    }

    public function test_member_without_edit_permission_cannot_take_over(): void
    {
        [$tenant, , $conv] = $this->setup3();
        $member = User::factory()->create(['tenant_id' => $tenant->id, 'role' => UserRole::Member->value, 'permissions' => []]);

        $this->actingAs($member)->postJson("/cabinet/conversations/{$conv->id}/takeover")->assertForbidden();
    }

    public function test_idle_takeover_is_auto_released(): void
    {
        [$tenant, $owner, $conv] = $this->setup3();
        // Перехвачено давно (дольше порога) и без активности — авто-возврат боту.
        $conv->forceFill([
            'operator_active_at' => now()->subMinutes(Conversation::OPERATOR_IDLE_MINUTES + 20),
            'operator_user_id' => $owner->id,
        ])->save();

        Artisan::call('conversations:release-idle');

        $this->assertNull(Conversation::withoutGlobalScopes()->findOrFail($conv->id)->operator_active_at);
    }
}
