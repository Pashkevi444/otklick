<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Conversation;
use App\Models\Tenant;
use App\Repositories\Contracts\ConversationRepositoryInterface;
use App\Repositories\Contracts\MessageRepositoryInterface;
use App\Services\ContactGate;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

final class ContactGateTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private function gate(?string $lastOutbound = null): ContactGate
    {
        $conversations = Mockery::mock(ConversationRepositoryInterface::class);
        $conversations->shouldReceive('setContactPhone')->andReturnUsing(fn (Conversation $c, string $v) => $c->contact_phone = $v)->byDefault();
        $conversations->shouldReceive('setContactName')->andReturnUsing(fn (Conversation $c, string $v) => $c->contact_name = $v)->byDefault();
        $conversations->shouldReceive('setContactEmail')->andReturnUsing(fn (Conversation $c, string $v) => $c->contact_email = $v)->byDefault();
        $conversations->shouldReceive('markContactsGateDone')->andReturnUsing(fn (Conversation $c) => $c->contacts_gate_done = true)->byDefault();

        $messages = Mockery::mock(MessageRepositoryInterface::class);
        $messages->shouldReceive('latestOutboundText')->andReturn($lastOutbound)->byDefault();

        return new ContactGate($conversations, $messages);
    }

    private function tenant(): Tenant
    {
        return new Tenant(['name' => 'Барбершоп']);
    }

    public function test_first_message_shows_greeting_form_without_mining_name(): void
    {
        $c = new Conversation; // нет контактов, форма ещё не показана

        $r = $this->gate()->handle($this->tenant(), $c, 'есть ли у вас фейд?');

        $this->assertNotNull($r);
        $this->assertStringContainsString('имя и телефон', $r->text);
        $this->assertNull($c->contact_name); // вопрос НЕ принят за имя
        $this->assertFalse((bool) $c->contacts_gate_done);
    }

    public function test_invalid_phone_is_rejected_with_fix_request(): void
    {
        $c = new Conversation;

        // Форма уже показана → это ответ на неё; номер из 14 цифр — невалиден.
        $r = $this->gate(lastOutbound: 'форма')->handle($this->tenant(), $c, '+72223322123123');

        $this->assertNotNull($r);
        $this->assertStringContainsString('некорректно', $r->text);
        $this->assertNull($c->contact_phone); // мусор не сохранён
        $this->assertFalse((bool) $c->contacts_gate_done);
    }

    public function test_valid_name_and_phone_completes_with_action_buttons(): void
    {
        $c = new Conversation;

        $r = $this->gate(lastOutbound: 'форма')->handle($this->tenant(), $c, 'Алексей, +7 999 123-45-67, a@b.ru');

        $this->assertNotNull($r);
        $this->assertSame('Алексей', $c->contact_name);
        $this->assertSame('+79991234567', $c->contact_phone);
        $this->assertSame('a@b.ru', $c->contact_email);
        $this->assertTrue((bool) $c->contacts_gate_done);
        $this->assertStringContainsString('Спасибо', $r->text);
        $this->assertNotNull($r->keyboard);
        $this->assertContains('Записаться', $r->keyboard->labels());
    }

    public function test_new_client_finishing_form_is_not_greeted_as_returning(): void
    {
        // Прод-баг: ContactCapture проставил телефон ДО гейта, имя собрано на
        // прошлом шаге, форма уже показывалась — это НОВИЧОК, а не «вернувшийся».
        $c = new Conversation;
        $c->contact_name = 'Павел';
        $c->contact_phone = '+79992223323';

        $r = $this->gate(lastOutbound: 'форма')->handle($this->tenant(), $c, '+79992223323');

        $this->assertNotNull($r);
        $this->assertStringNotContainsString('возвращением', $r->text);
        $this->assertStringContainsString('Спасибо', $r->text);
        $this->assertTrue((bool) $c->contacts_gate_done);
    }

    public function test_returning_known_client_is_welcomed_by_name_without_form(): void
    {
        $c = new Conversation; // контакты перенеслись из прошлого диалога
        $c->contact_name = 'Пётр';
        $c->contact_phone = '+79991112233';

        $r = $this->gate()->handle($this->tenant(), $c, 'привет');

        $this->assertNotNull($r);
        $this->assertStringContainsString('С возвращением, Пётр', $r->text);
        $this->assertNotNull($r->keyboard);
        $this->assertTrue((bool) $c->contacts_gate_done);
    }

    public function test_passes_through_once_gate_is_done(): void
    {
        $c = new Conversation;
        $c->contacts_gate_done = true;

        $this->assertNull($this->gate()->handle($this->tenant(), $c, 'сколько стоит стрижка?'));
    }
}
