<?php

declare(strict_types=1);

namespace Tests\Integration\Services;

use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Conversations\Services\SpamDetector;
use App\Shared\Enums\MessageDirection;
use App\Shared\Models\Tenant;
use App\Shared\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SpamDetectorTest extends TestCase
{
    use RefreshDatabase;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = Tenant::factory()->create();
        $this->app->make(TenantContext::class)->set($tenant->id);
        $this->conversation = Conversation::factory()->create(['tenant_id' => $tenant->id]);
    }

    private function detector(): SpamDetector
    {
        return $this->app->make(SpamDetector::class);
    }

    private function inbound(string $text): void
    {
        Message::factory()->create([
            'tenant_id' => $this->conversation->tenant_id,
            'conversation_id' => $this->conversation->id,
            'direction' => MessageDirection::Inbound,
            'text' => $text,
        ]);
    }

    public function test_stopwords_are_spam(): void
    {
        $this->assertTrue($this->detector()->isSpam($this->conversation, 'Лучшее КАЗИНО — заходи и выигрывай!'));
    }

    public function test_telegram_invite_is_spam(): void
    {
        $this->assertTrue($this->detector()->isSpam($this->conversation, 'переходи в канал t.me/+AbCdEfGh12'));
    }

    public function test_many_links_are_spam(): void
    {
        $this->assertTrue($this->detector()->isSpam($this->conversation, 'http://a.ru http://b.ru http://c.ru'));
    }

    public function test_normal_message_is_not_spam(): void
    {
        $this->assertFalse($this->detector()->isSpam($this->conversation, 'Здравствуйте! Сколько стоит мужская стрижка и есть ли окно сегодня?'));
    }

    public function test_single_link_is_not_spam(): void
    {
        // Живой клиент часто кидает одну ссылку (свой профиль/референс) — не спам.
        $this->assertFalse($this->detector()->isSpam($this->conversation, 'Вот пример которую хочу: https://instagram.com/p/123'));
    }

    public function test_flooding_is_spam(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->inbound("сообщение {$i}");
        }

        $this->assertTrue($this->detector()->isSpam($this->conversation, 'ещё одно'));
    }

    public function test_repeated_identical_messages_are_spam(): void
    {
        $this->inbound('тут был я');
        $this->inbound('тут был я');
        $this->inbound('тут был я');

        $this->assertTrue($this->detector()->isSpam($this->conversation, 'тут был я'));
    }
}
