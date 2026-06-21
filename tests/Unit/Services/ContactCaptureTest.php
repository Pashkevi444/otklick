<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Llm\Contracts\LlmClient;
use App\Models\Client;
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
        $conversation = $this->conv('Иван', null);

        $clients = $this->clients();
        $clients->shouldReceive('recordPhone')->once()->with($conversation, '+79991234567');
        $clients->shouldNotReceive('recordName');

        $this->capture($clients)->fromInbound($conversation, 'Мой номер 8 999 123-45-67');
    }

    public function test_captures_name_via_model_when_bot_asked(): void
    {
        $conversation = $this->conv(null, '+70000000000');

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('latestOutboundText')->once()->with($conversation)->andReturn('Как вас зовут?');

        $llm = Mockery::mock(LlmClient::class);
        $llm->shouldReceive('generate')->once()->andReturn('Павел');

        $clients = $this->clients();
        $clients->shouldReceive('recordName')->once()->with($conversation, 'Павел');
        $clients->shouldNotReceive('recordPhone');

        $this->capture($clients, $messages, $llm)->fromInbound($conversation, 'Павел');
    }

    public function test_does_not_capture_name_from_first_message(): void
    {
        // Первое сообщение клиента (бот ещё НЕ ответил → latestOutboundText null):
        // даже явное «меня зовут Паша» именем НЕ считаем — почти всегда это вопрос.
        // Телефон однозначен — его всё равно забираем.
        $conversation = $this->conv(null, null);

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('latestOutboundText')->once()->with($conversation)->andReturnNull();

        $clients = $this->clients();
        $clients->shouldReceive('recordPhone')->once()->with($conversation, '+79991234567');
        $clients->shouldNotReceive('recordName');

        $this->capture($clients, $messages)->fromInbound($conversation, 'запиши меня к Савелию, меня зовут Паша, телефон 8 999 123-45-67');
    }

    public function test_does_not_touch_contacts_when_already_known(): void
    {
        $conversation = $this->conv('Анна', '+70000000000');

        $clients = $this->clients();
        $clients->shouldNotReceive('recordName');
        $clients->shouldNotReceive('recordPhone');

        $this->capture($clients)->fromInbound($conversation, 'Павел');
    }

    /** Лид всегда привязан к карточке; имя/телефон — её атрибуты. */
    private function conv(?string $name, ?string $phone): Conversation
    {
        $c = new Conversation;
        $c->setRelation('client', new Client(['name' => $name, 'phone' => $phone]));

        return $c;
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
