<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Conversation;
use App\Models\Tenant;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Services\ConsentGate;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

final class ConsentGateTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function gate(ConversationRepositoryInterface $repo): ConsentGate
    {
        return new ConsentGate($repo);
    }

    public function test_first_message_shows_consent_form_with_buttons(): void
    {
        $repo = Mockery::mock(ConversationRepositoryInterface::class);
        $repo->shouldNotReceive('markConsentGiven');

        $reply = $this->gate($repo)->handle(new Tenant, new Conversation, 'здравствуйте');

        $this->assertNotNull($reply);
        $this->assertStringContainsString('персональных данных', $reply->text);
        $this->assertStringContainsString('/consent', $reply->text);
        $this->assertStringContainsString('/privacy', $reply->text);
        $this->assertSame(['Да', 'Нет'], $reply->keyboard?->labels());
        $this->assertFalse($reply->escalate);
    }

    public function test_yes_records_consent_and_proceeds(): void
    {
        $conversation = new Conversation;
        $repo = Mockery::mock(ConversationRepositoryInterface::class);
        $repo->shouldReceive('markConsentGiven')->once()->with($conversation);

        // null = рубеж пройден, диалог идёт дальше (контактная форма и т.д.).
        $this->assertNull($this->gate($repo)->handle(new Tenant, $conversation, 'Да'));
    }

    public function test_no_says_goodbye_without_recording(): void
    {
        $repo = Mockery::mock(ConversationRepositoryInterface::class);
        $repo->shouldNotReceive('markConsentGiven');

        $reply = $this->gate($repo)->handle(new Tenant, new Conversation, 'нет');

        $this->assertNotNull($reply);
        $this->assertStringContainsString('Без согласия', $reply->text);
        $this->assertFalse($reply->escalate);
    }

    public function test_already_consented_passes_through(): void
    {
        $conversation = new Conversation;
        $conversation->consent_agreed = true;
        $repo = Mockery::mock(ConversationRepositoryInterface::class);
        $repo->shouldNotReceive('markConsentGiven');

        $this->assertNull($this->gate($repo)->handle(new Tenant, $conversation, 'часы работы?'));
    }
}
