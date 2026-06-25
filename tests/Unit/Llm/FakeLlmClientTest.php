<?php

declare(strict_types=1);

namespace Tests\Unit\Llm;

use App\Modules\Bot\Services\PromptBuilder;
use App\Shared\Llm\FakeLlmClient;
use PHPUnit\Framework\TestCase;

final class FakeLlmClientTest extends TestCase
{
    private const string SYSTEM = "Ты — администратор.\n\nБаза знаний:\n• Доставка: Бесплатно от 1000 рублей\n• Оплата: Картой и наличными";

    public function test_returns_matching_knowledge_line(): void
    {
        $answer = (new FakeLlmClient)->generate(self::SYSTEM, [
            ['role' => 'user', 'content' => 'есть ли доставка?'],
        ]);

        $this->assertSame('Доставка: Бесплатно от 1000 рублей', $answer);
    }

    public function test_clarifies_when_no_match(): void
    {
        $answer = (new FakeLlmClient)->generate(self::SYSTEM, [
            ['role' => 'user', 'content' => 'расскажи про вселенную'],
        ]);

        // Нет ответа в базе → не эскалация, а уточняющий вопрос.
        $this->assertStringStartsWith(PromptBuilder::CLARIFY, $answer);
    }

    public function test_escalates_without_user_message(): void
    {
        $this->assertSame(PromptBuilder::ESCALATE, (new FakeLlmClient)->generate(self::SYSTEM, []));
    }
}
