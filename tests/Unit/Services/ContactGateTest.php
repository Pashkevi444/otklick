<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Client;
use App\Models\Conversation;
use App\Models\CrmConnection;
use App\Models\Tenant;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\CrmConnectionRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\ClientService;
use App\Services\ContactGate;
use App\Services\LeadService;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

final class ContactGateTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /** Карточка клиента, которую record*-методы мутируют (источник правды гейта). */
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new Client(['name' => null, 'phone' => null, 'email' => null]);
    }

    private function gate(?string $lastOutbound = null, bool $crmConnected = false): ContactGate
    {
        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('markContactsGateDone')->andReturnUsing(fn (Conversation $c) => $c->contacts_gate_done = true)->byDefault();

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('latestOutboundText')->andReturn($lastOutbound)->byDefault();

        $clients = Mockery::mock(ClientService::class);
        $clients->shouldReceive('recordName')->andReturnUsing(fn (Conversation $c, string $v) => $this->client->name = $v)->byDefault();
        $clients->shouldReceive('recordPhone')->andReturnUsing(fn (Conversation $c, string $v) => $this->client->phone = $v)->byDefault();
        $clients->shouldReceive('recordEmail')->andReturnUsing(fn (Conversation $c, string $v) => $this->client->email = $v)->byDefault();

        $crm = Mockery::mock(CrmConnectionRepositoryInterface::class);
        $crm->shouldReceive('activeForCurrentTenant')->andReturn($crmConnected ? new CrmConnection : null)->byDefault();

        $leads = Mockery::mock(LeadService::class);
        $leads->shouldReceive('createFromConversation')->byDefault();

        return new ContactGate($conversations, $messages, $clients, $crm, $leads);
    }

    private function conversation(array $attrs = []): Conversation
    {
        $c = new Conversation($attrs);
        $c->setRelation('client', $this->client); // лид всегда привязан к карточке

        return $c;
    }

    private function tenant(): Tenant
    {
        return new Tenant(['name' => 'Барбершоп']);
    }

    public function test_first_message_shows_greeting_form_without_mining_name(): void
    {
        $c = $this->conversation();

        $r = $this->gate()->handle($this->tenant(), $c, 'есть ли у вас фейд?');

        $this->assertNotNull($r);
        $this->assertStringContainsString('имя и телефон', $r->text);
        $this->assertNull($this->client->name); // вопрос НЕ принят за имя
        $this->assertFalse((bool) $c->contacts_gate_done);
    }

    public function test_question_message_is_not_taken_as_name(): void
    {
        $c = $this->conversation();
        $r = $this->gate(lastOutbound: 'форма')->handle($this->tenant(), $c, 'а меня нет в базе?');

        $this->assertNull($this->client->name);
        $this->assertFalse((bool) $c->contacts_gate_done);
        $this->assertStringNotContainsString('Спасибо', $r->text);
    }

    public function test_slash_command_is_not_taken_as_name(): void
    {
        $c = $this->conversation();
        $r = $this->gate()->handle($this->tenant(), $c, '/start');

        $this->assertNull($this->client->name);
        $this->assertFalse((bool) $c->contacts_gate_done);
        $this->assertStringNotContainsString('Спасибо', $r->text);
        $this->assertStringContainsString('имя и телефон', $r->text);
    }

    public function test_stopword_is_not_taken_as_name(): void
    {
        $this->client->phone = '+79991234567'; // телефон есть, не хватает имени
        $c = $this->conversation();
        $r = $this->gate(lastOutbound: 'форма')->handle($this->tenant(), $c, 'да');

        $this->assertNull($this->client->name);
        $this->assertStringContainsString('зовут', $r->text);
    }

    public function test_invalid_phone_is_rejected_with_fix_request(): void
    {
        $c = $this->conversation();

        $r = $this->gate(lastOutbound: 'форма')->handle($this->tenant(), $c, '+72223322123123');

        $this->assertNotNull($r);
        $this->assertStringContainsString('некорректно', $r->text);
        $this->assertNull($this->client->phone); // мусор не сохранён
        $this->assertFalse((bool) $c->contacts_gate_done);
    }

    public function test_valid_name_and_phone_completes_with_action_buttons(): void
    {
        $c = $this->conversation();

        $r = $this->gate(lastOutbound: 'форма', crmConnected: true)->handle($this->tenant(), $c, 'Алексей, +7 999 123-45-67, a@b.ru');

        $this->assertNotNull($r);
        $this->assertSame('Алексей', $this->client->name);
        $this->assertSame('+79991234567', $this->client->phone);
        $this->assertSame('a@b.ru', $this->client->email);
        $this->assertTrue((bool) $c->contacts_gate_done);
        $this->assertStringContainsString('Спасибо', $r->text);
        $this->assertNotNull($r->keyboard);
        $this->assertContains('Записаться', $r->keyboard->labels());
    }

    public function test_new_client_finishing_form_is_not_greeted_as_returning(): void
    {
        // Имя/телефон собраны на прошлом шаге, форма уже показывалась — НОВИЧОК.
        $this->client->name = 'Павел';
        $this->client->phone = '+79992223323';
        $c = $this->conversation();

        $r = $this->gate(lastOutbound: 'форма')->handle($this->tenant(), $c, '+79992223323');

        $this->assertNotNull($r);
        $this->assertStringNotContainsString('возвращением', $r->text);
        $this->assertStringContainsString('Спасибо', $r->text);
        $this->assertTrue((bool) $c->contacts_gate_done);
    }

    public function test_returning_known_client_is_welcomed_by_name_without_form(): void
    {
        $this->client->name = 'Пётр';
        $this->client->phone = '+79991112233';
        $c = $this->conversation();

        $r = $this->gate(crmConnected: true)->handle($this->tenant(), $c, 'привет');

        $this->assertNotNull($r);
        $this->assertStringContainsString('С возвращением, Пётр', $r->text);
        $this->assertNotNull($r->keyboard);
        $this->assertTrue((bool) $c->contacts_gate_done);
    }

    public function test_passes_through_once_gate_is_done(): void
    {
        $c = $this->conversation(['contacts_gate_done' => true]);

        $this->assertNull($this->gate()->handle($this->tenant(), $c, 'сколько стоит стрижка?'));
    }
}
