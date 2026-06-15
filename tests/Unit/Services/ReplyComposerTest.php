<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Llm\Contracts\LlmClient;
use App\Models\Conversation;
use App\Models\Tenant;
use App\Repositories\Contracts\KnowledgeEntryRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\PromptBuilder;
use App\Services\ReplyComposer;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

final class ReplyComposerTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function composer(LlmClient $llm): ReplyComposer
    {
        $knowledge = Mockery::mock(KnowledgeEntryRepositoryInterface::class);
        $knowledge->shouldReceive('publishedForCurrentTenant')->andReturn(new Collection);

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('recentForConversation')->andReturn(new Collection);

        return new ReplyComposer($llm, new PromptBuilder, $knowledge, $messages);
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
}
