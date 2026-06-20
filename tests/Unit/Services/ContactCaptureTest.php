<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Llm\Contracts\LlmClient;
use App\Models\Conversation;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\ClientService;
use App\Services\ContactCapture;
use App\Services\NameDetector;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

final class ContactCaptureTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    public function test_captures_phone_from_text(): void
    {
        $conversation = new Conversation(['contact_name' => 'Иван', 'contact_phone' => null]);

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('setContactPhone')->once()->with($conversation, '+79991234567');
        $conversations->shouldNotReceive('setContactName');

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $llm = Mockery::mock(LlmClient::class);

        $this->capture($conversations, $messages, $llm)
            ->fromInbound($conversation, 'Мой номер 8 999 123-45-67');
    }

    public function test_captures_name_via_model_when_bot_asked(): void
    {
        $conversation = new Conversation(['contact_name' => 'Гость сайта', 'contact_phone' => '+70000000000']);

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('setContactName')->once()->with($conversation, 'Павел');
        $conversations->shouldNotReceive('setContactPhone');

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('latestOutboundText')->once()->with($conversation)->andReturn('Как вас зовут?');

        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()->andReturn('Павел');

        $this->capture($conversations, $messages, $llm)->fromInbound($conversation, 'Павел');
    }

    public function test_captures_name_and_phone_from_one_message_without_bot_asking(): void
    {
        // Клиент сам представился в первом же сообщении — без предыдущего вопроса бота.
        $conversation = new Conversation(['contact_name' => null, 'contact_phone' => null]);

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('setContactPhone')->once()->with($conversation, '+79991234567');
        $conversations->shouldReceive('setContactName')->once()->with($conversation, 'Паша');

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        // Явное представление ловится без обращения к истории/модели.
        $messages->shouldNotReceive('latestOutboundText');

        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldNotReceive('generate');

        $this->capture($conversations, $messages, $llm)
            ->fromInbound($conversation, 'запиши меня к Савелию, меня зовут Паша, телефон 8 999 123-45-67');
    }

    public function test_does_not_touch_name_when_already_known(): void
    {
        $conversation = new Conversation(['contact_name' => 'Анна', 'contact_phone' => '+70000000000']);

        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldNotReceive('setContactName');
        $conversations->shouldNotReceive('setContactPhone');

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldNotReceive('latestOutboundText');

        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldNotReceive('generate');

        $this->capture($conversations, $messages, $llm)->fromInbound($conversation, 'Павел');
    }

    private function capture(
        ConversationRepositoryInterface $conversations,
        MessageRepositoryInterface $messages,
        LlmClient $llm,
    ): ContactCapture {
        $clients = Mockery::mock(ClientService::class);
        $clients->shouldReceive('recognizeReturning')->byDefault();
        $clients->shouldReceive('linkConversation')->byDefault();

        return new ContactCapture($conversations, $messages, new NameDetector($llm), $clients);
    }
}
