<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Jobs\ProcessTelegramAlbum;
use App\Jobs\ProcessTelegramUpdate;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\KnowledgeEntry;
use App\Models\Message;
use App\Models\Tenant;
use App\Speech\Contracts\SpeechToText;
use App\Speech\FakeSpeechToText;
use App\Vision\Contracts\ImageToText;
use App\Vision\FakeImageToText;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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

    public function test_photo_with_caption_is_recognized_and_answered(): void
    {
        Http::fake([
            '*/getFile*' => Http::response(['result' => ['file_path' => 'photos/p1.jpg']]),
            '*/file/bot*' => Http::response("\xFF\xD8\xFF\xE0JPEG"),
            '*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
        ]);
        // Vision «видит» фото — возвращаем описание (вместо реальной модели).
        $this->app->instance(ImageToText::class, new FakeImageToText('мужская стрижка андеркат'));

        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $this->seedGateDone($tenant, $channel);

        // Фото с подписью: текста нет, есть photo[] и caption.
        $this->process($tenant, $channel, [
            'update_id' => 201,
            'message' => [
                'message_id' => 21,
                'chat' => ['id' => 555],
                'from' => ['username' => 'ivan'],
                'caption' => 'хочу такую же',
                'photo' => [
                    ['file_id' => 'small', 'width' => 90],
                    ['file_id' => 'big', 'width' => 1280],
                ],
            ],
        ]);

        // Входящее записано: подпись + описание фото → бот ответил (не молчит).
        $inbound = Message::query()->where('direction', 'inbound')->firstOrFail();
        $this->assertStringContainsString('хочу такую же', (string) $inbound->text);
        $this->assertStringContainsString('фото', (string) $inbound->text);
        $this->assertSame(1, Message::query()->where('direction', 'outbound')->count());
        Http::assertSent(fn ($r): bool => str_contains($r->url(), '/sendMessage'));
    }

    public function test_album_buffered_and_answered_once(): void
    {
        // Альбом → один ответ: фото копятся в буфере, склейка планируется один раз,
        // отложенный джоб отдаёт боту одно объединённое сообщение.
        Queue::fake();
        Http::fake([
            '*/getFile*' => Http::response(['result' => ['file_path' => 'photos/p.jpg']]),
            '*/file/bot*' => Http::response("\xFF\xD8\xFF\xE0JPEG"),
            '*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
        ]);
        $this->app->instance(ImageToText::class, new FakeImageToText('пример работы'));

        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $this->seedGateDone($tenant, $channel);

        // Два фото одного альбома приходят ОТДЕЛЬНЫМИ апдейтами (общий media_group_id).
        foreach ([41, 42] as $i => $messageId) {
            $this->process($tenant, $channel, [
                'update_id' => 400 + $i,
                'message' => [
                    'message_id' => $messageId,
                    'media_group_id' => 'GROUP1',
                    'chat' => ['id' => 555],
                    'from' => ['username' => 'ivan'],
                    'caption' => $i === 0 ? 'оба нравятся' : null,
                    'photo' => [['file_id' => "f{$messageId}", 'width' => 1000]],
                ],
            ]);
        }

        // Пока ничего не обработано — фото в буфере; склейка запланирована РОВНО раз.
        $this->assertSame(0, Message::query()->where('direction', 'inbound')->count());
        Queue::assertPushed(ProcessTelegramAlbum::class, 1);

        // Запускаем склейку (в проде — отложенный воркер через ~2с).
        $this->app->call([new ProcessTelegramAlbum($tenant->id, $channel->id, 'GROUP1'), 'handle']);

        // Один объединённый ввод (с подписью альбома) и один ответ бота на весь альбом.
        $inbound = Message::query()->where('direction', 'inbound')->get();
        $this->assertCount(1, $inbound);
        $this->assertStringContainsString('оба нравятся', (string) $inbound->first()->text);
        $this->assertSame(1, Message::query()->where('direction', 'outbound')->count());
    }

    public function test_separate_photos_without_group_each_processed(): void
    {
        // Отдельные фото (БЕЗ media_group_id) — каждое обрабатывается само по себе.
        Http::fake([
            '*/getFile*' => Http::response(['result' => ['file_path' => 'photos/p.jpg']]),
            '*/file/bot*' => Http::response("\xFF\xD8\xFF\xE0JPEG"),
            '*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]]),
        ]);
        $this->app->instance(ImageToText::class, new FakeImageToText('пример работы'));

        $tenant = Tenant::factory()->create();
        $channel = Channel::factory()->create(['tenant_id' => $tenant->id]);
        $this->seedGateDone($tenant, $channel);

        foreach ([31, 32] as $i => $messageId) {
            $this->process($tenant, $channel, [
                'update_id' => 300 + $i,
                'message' => [
                    'message_id' => $messageId,
                    'chat' => ['id' => 555],
                    'from' => ['username' => 'ivan'],
                    'photo' => [['file_id' => "f{$messageId}", 'width' => 1000]],
                ],
            ]);
        }

        // Оба фото обработаны: 2 входящих, бот ответил на каждое.
        $this->assertSame(2, Message::query()->where('direction', 'inbound')->count());
        $this->assertSame(2, Message::query()->where('direction', 'outbound')->count());
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
