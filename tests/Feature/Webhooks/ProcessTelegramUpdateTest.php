<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Jobs\ProcessTelegramUpdate;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\KnowledgeEntry;
use App\Models\Message;
use App\Models\Tenant;
use App\Speech\Contracts\SpeechToText;
use App\Speech\FakeSpeechToText;
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

    /** Контактная форма уже пройдена — тестируем ответы бота по сути диалога. */
    private function seedGateDone(Tenant $tenant, Channel $channel, string $chatId = '555'): void
    {
        Conversation::factory()->create([
            'tenant_id' => $tenant->id,
            'channel_id' => $channel->id,
            'external_chat_id' => $chatId,
            'contacts_gate_done' => true,
            'status' => 'open',
        ]);
    }

    public function test_voice_message_is_transcribed_and_processed_as_text(): void
    {
        Http::fake([
            '*/getFile*' => Http::response(['result' => ['file_path' => 'voice/v1.oga']]),
            '*/file/bot*' => Http::response('OGG-OPUS-BYTES'),
            '*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
        ]);
        // Распознавание возвращает текст вопроса (вместо реального SpeechKit).
        $this->app->instance(SpeechToText::class, new FakeSpeechToText('есть ли доставка?'));

        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $this->seedGateDone($tenant, $channel);

        // Голосовое сообщение: текста нет, есть voice.file_id.
        $this->process($tenant, $channel, [
            'update_id' => 101,
            'message' => [
                'message_id' => 11,
                'chat' => ['id' => 555],
                'from' => ['username' => 'ivan'],
                'voice' => ['file_id' => 'AABB', 'duration' => 3, 'mime_type' => 'audio/ogg'],
            ],
        ]);

        // Входящее записано как распознанный текст — дальше обычный пайплайн бота.
        $this->assertDatabaseHas('messages', [
            'tenant_id' => $tenant->id,
            'direction' => 'inbound',
            'text' => 'есть ли доставка?',
        ]);
        Http::assertSent(fn ($r): bool => str_contains($r->url(), '/getFile'));
    }

    public function test_voice_message_ignored_when_transcription_fails(): void
    {
        Http::fake([
            '*/getFile*' => Http::response(['result' => ['file_path' => 'voice/v1.oga']]),
            '*' => Http::response('', 200),
        ]);
        // STT не распознал (null) — голос игнорируем, входящего нет.
        $this->app->instance(SpeechToText::class, new FakeSpeechToText(null));

        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $this->seedGateDone($tenant, $channel);

        $this->process($tenant, $channel, [
            'update_id' => 102,
            'message' => ['message_id' => 12, 'chat' => ['id' => 555], 'voice' => ['file_id' => 'CCDD']],
        ]);

        $this->assertDatabaseMissing('messages', ['tenant_id' => $tenant->id, 'direction' => 'inbound']);
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
        $this->seedGateDone($tenant, $channel);

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

    public function test_unknown_question_clarifies_and_keeps_conversation_open(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $this->seedGateDone($tenant, $channel);

        $this->process($tenant, $channel, $this->update(['text' => 'шо ты голова']));

        $outbound = Message::query()->where('direction', 'outbound')->firstOrFail();
        // Бот не зовёт сразу человека — переспрашивает и остаётся в диалоге.
        $this->assertStringNotContainsString('администратору', (string) $outbound->text);
        $this->assertDatabaseHas('conversations', ['tenant_id' => $tenant->id, 'status' => 'open']);
        $this->assertDatabaseHas('conversations', ['tenant_id' => $tenant->id, 'clarification_attempts' => 1]);
    }

    public function test_escalates_after_three_unanswerable_questions(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $this->seedGateDone($tenant, $channel);

        // Три подряд непонятных сообщения в одном чате → третье эскалирует на человека.
        $this->process($tenant, $channel, $this->update(['message_id' => 10, 'text' => 'шо ты голова']));
        $this->process($tenant, $channel, $this->update(['message_id' => 11, 'text' => 'а ты кто такой']));
        $this->process($tenant, $channel, $this->update(['message_id' => 12, 'text' => 'бла бла бла']));

        $this->assertDatabaseHas('conversations', ['tenant_id' => $tenant->id, 'status' => 'needs_human']);

        // Среди ответов есть фолбэк с передачей администратору.
        $escalated = Message::query()->where('direction', 'outbound')
            ->get()->contains(fn (Message $m): bool => str_contains((string) $m->text, 'администратору'));
        $this->assertTrue($escalated, 'Ожидали фолбэк-сообщение про администратора после третьей непонятки.');
    }

    public function test_stores_account_link_and_does_not_seed_name_from_account(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);

        $this->process($tenant, $channel, $this->update([
            'text' => 'есть ли доставка?',
            'from' => ['first_name' => 'Иван', 'username' => 'ivan_tg'],
        ]));

        $conv = Conversation::query()->where('tenant_id', $tenant->id)->firstOrFail();
        // Имя из аккаунта НЕ подставляем; ссылку на аккаунт — сохраняем.
        $this->assertNull($conv->contact_name);
        $this->assertSame('https://t.me/ivan_tg', $conv->contact_ref);
    }

    public function test_account_link_is_null_without_username(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);

        $this->process($tenant, $channel, $this->update([
            'text' => 'привет',
            'from' => ['first_name' => 'Иван'],
        ]));

        $conv = Conversation::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertNull($conv->contact_ref);
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

    public function test_blocked_tenant_bot_does_not_respond(): void
    {
        Http::fake();
        $tenant = Tenant::factory()->create(['is_blocked' => true]);
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);

        $this->process($tenant, $channel, $this->update());

        $this->assertDatabaseCount('messages', 0);
        Http::assertNothingSent();
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
