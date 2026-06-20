<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Llm\Contracts\LlmClient;
use App\Models\Conversation;
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

    public function test_captures_phone_from_text_into_client(): void
    {
        $conversation = new Conversation(['contact_name' => 'Иван', 'contact_phone' => null]);

        $clients = $this->clients();
        $clients->shouldReceive('recordPhone')->once()->with($conversation, '+79991234567');
        $clients->shouldNotReceive('recordName');

        $this->capture($clients)->fromInbound($conversation, 'Мой номер 8 999 123-45-67');
    }

    public function test_captures_name_via_model_when_bot_asked(): void
    {
        $conversation = new Conversation(['contact_name' => 'Гость сайта', 'contact_phone' => '+70000000000']);

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('latestOutboundText')->once()->with($conversation)->andReturn('Как вас зовут?');

        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()->andReturn('Павел');

        $clients = $this->clients();
        $clients->shouldReceive('recordName')->once()->with($conversation, 'Павел');
        $clients->shouldNotReceive('recordPhone');

        $this->capture($clients, $messages, $llm)->fromInbound($conversation, 'Павел');
    }

    public function test_captures_name_and_phone_from_one_message_without_bot_asking(): void
    {
        $conversation = new Conversation(['contact_name' => null, 'contact_phone' => null]);

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldNotReceive('latestOutboundText'); // явное представление — без истории

        $clients = $this->clients();
        $clients->shouldReceive('recordPhone')->once()->with($conversation, '+79991234567');
        $clients->shouldReceive('recordName')->once()->with($conversation, 'Паша');

        $this->capture($clients, $messages)->fromInbound($conversation, 'запиши меня к Савелию, меня зовут Паша, телефон 8 999 123-45-67');
    }

    public function test_does_not_touch_contacts_when_already_known(): void
    {
        $conversation = new Conversation(['contact_name' => 'Анна', 'contact_phone' => '+70000000000']);

        $clients = $this->clients();
        $clients->shouldNotReceive('recordName');
        $clients->shouldNotReceive('recordPhone');

        $this->capture($clients)->fromInbound($conversation, 'Павел');
    }

    private function clients(): Mockery\MockInterface
    {
        $clients = Mockery::mock(ClientService::class);
        $clients->shouldReceive('attachClient')->once()->byDefault();

        return $clients;
    }

    private function capture(
        Mockery\MockInterface $clients,
        ?MessageRepositoryInterface $messages = null,
        ?LlmClient $llm = null,
    ): ContactCapture {
        return new ContactCapture(
            $messages ?? Mockery::mock(MessageRepositoryInterface::class),
            new NameDetector($llm ?? Mockery::mock(LlmClient::class)),
            $clients,
        );
    }
}
