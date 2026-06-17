<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Llm\Contracts\LlmClient;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\CrmKnowledgeRepositoryInterface;
use App\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\KnowledgeRetriever;
use App\Services\PromptBuilder;
use App\Services\ReplyComposer;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use Tests\TestCase;

final class ReplyComposerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function composer(LlmClient $llm, ?ConversationRepositoryInterface $conversations = null): ReplyComposer
    {
        $knowledge = Mockery::mock(KnowledgeEntryRepositoryInterface::class);
        $knowledge->shouldReceive('publishedForCurrentTenant')->andReturn(new Collection);

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recentForChat')->andReturn(new Collection);

        $crmKnowledge = Mockery::mock(CrmKnowledgeRepositoryInterface::class);
        $crmKnowledge->shouldReceive('forCurrentTenant')->andReturn(new Collection);

        // По умолчанию ретривер не находит индекс → фолбэк на всю базу (текущее поведение).
        $retriever = Mockery::mock(KnowledgeRetriever::class);
        $retriever->shouldReceive('retrieve')->andReturn(null)->byDefault();

        return new ReplyComposer($llm, new PromptBuilder, $knowledge, $messages, $conversations ?? $this->conversations(), $crmKnowledge, $retriever);
    }

    /**
     * Репозиторий диалогов по умолчанию терпит любые вызовы счётчика уточнений.
     */
    private function conversations(): ConversationRepositoryInterface&MockInterface
    {
        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('bumpClarificationAttempts')->andReturn(1)->byDefault();
        $conversations->shouldReceive('resetClarificationAttempts')->byDefault();

        return $conversations;
    }

    public function test_returns_model_answer_when_available(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()->andReturn('Работаем с 9 до 21.');

        $reply = $this->composer($llm)->compose(new Tenant(['name' => 'Бизнес']), new Conversation);

        $this->assertSame('Работаем с 9 до 21.', $reply->text);
        $this->assertFalse($reply->escalate);
    }

    public function test_escalates_with_fallback_including_phone(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()->andReturn(PromptBuilder::ESCALATE);

        $tenant = new Tenant(['name' => 'Бизнес', 'settings' => ['profile' => ['phone' => '+7 900']]]);

        $reply = $this->composer($llm)->compose($tenant, new Conversation);

        $this->assertTrue($reply->escalate);
        $this->assertStringContainsString('администратору', $reply->text);
        $this->assertStringContainsString('+7 900', $reply->text);
    }

    public function test_clarifies_instead_of_escalating_below_limit(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()
            ->andReturn(PromptBuilder::CLARIFY.' Подскажите, какая услуга вас интересует?');

        $conversation = new Conversation;

        $conversations = $this->conversations();
        $conversations->shouldReceive('bumpClarificationAttempts')->once()->with($conversation)->andReturn(1);
        $conversations->shouldNotReceive('resetClarificationAttempts');

        $reply = $this->composer($llm, $conversations)->compose(new Tenant(['name' => 'Бизнес']), $conversation);

        $this->assertFalse($reply->escalate);
        $this->assertSame('Подскажите, какая услуга вас интересует?', $reply->text);
    }

    public function test_escalates_after_third_clarification(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()
            ->andReturn(PromptBuilder::CLARIFY.' Не совсем понял вопрос.');

        $conversation = new Conversation(['clarification_attempts' => 2]);

        $conversations = $this->conversations();
        // Третья подряд непонятка → счётчик доходит до лимита.
        $conversations->shouldReceive('bumpClarificationAttempts')->once()->with($conversation)->andReturn(3);
        $conversations->shouldReceive('resetClarificationAttempts')->once()->with($conversation);

        $reply = $this->composer($llm, $conversations)->compose(new Tenant(['name' => 'Бизнес']), $conversation);

        $this->assertTrue($reply->escalate);
        $this->assertStringContainsString('администратору', $reply->text);
    }

    public function test_booked_sentinel_closes_dialog(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()
            ->andReturn(PromptBuilder::BOOKED.' Записал вас на завтра в 15:00, ждём!');

        $reply = $this->composer($llm)->compose(new Tenant(['name' => 'Бизнес']), new Conversation);

        $this->assertTrue($reply->booked);
        $this->assertFalse($reply->escalate);
        $this->assertSame('Записал вас на завтра в 15:00, ждём!', $reply->text);
    }

    public function test_booked_sentinel_in_the_middle_is_stripped(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()
            ->andReturn('Хорошо, Паша, записываю на 14:00. '.PromptBuilder::BOOKED.' Подтверждаю запись. Ждём!');

        $reply = $this->composer($llm)->compose(new Tenant(['name' => 'Бизнес']), new Conversation);

        $this->assertTrue($reply->booked);
        $this->assertFalse($reply->escalate);
        $this->assertStringNotContainsString('[[BOOKED]]', $reply->text);
        $this->assertStringContainsString('Подтверждаю запись', $reply->text);
        $this->assertStringContainsString('Хорошо, Паша', $reply->text);
    }

    public function test_book_sentinel_starts_booking(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()->andReturn(PromptBuilder::BOOK);

        $reply = $this->composer($llm)->compose(new Tenant(['name' => 'Бизнес']), new Conversation, bookingEnabled: true);

        $this->assertTrue($reply->startBooking);
        $this->assertFalse($reply->escalate);
        $this->assertFalse($reply->booked);
        $this->assertStringNotContainsString('[[BOOK]]', $reply->text);
    }

    public function test_cancellation_sentinel_marks_cancelled(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()
            ->andReturn('Отменил вашу запись на завтра. '.PromptBuilder::CANCELLED.' Ждём вас снова!');

        $reply = $this->composer($llm)->compose(new Tenant(['name' => 'Бизнес']), new Conversation);

        $this->assertTrue($reply->cancelled);
        $this->assertFalse($reply->booked);
        $this->assertFalse($reply->escalate);
        $this->assertStringNotContainsString('[[CANCELLED]]', $reply->text);
        $this->assertStringContainsString('Отменил вашу запись', $reply->text);
    }

    public function test_resets_streak_when_model_answers(): void
    {
        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()->andReturn('Да, конечно, записать вас?');

        $conversation = new Conversation(['clarification_attempts' => 2]);

        $conversations = $this->conversations();
        $conversations->shouldReceive('resetClarificationAttempts')->once()->with($conversation);
        $conversations->shouldNotReceive('bumpClarificationAttempts');

        $reply = $this->composer($llm, $conversations)->compose(new Tenant(['name' => 'Бизнес']), $conversation);

        $this->assertFalse($reply->escalate);
        $this->assertSame('Да, конечно, записать вас?', $reply->text);
    }
}
