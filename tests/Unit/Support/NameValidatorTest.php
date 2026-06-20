<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\NameValidator;
use PHPUnit\Framework\TestCase;

final class NameValidatorTest extends TestCase
{
    public function test_accepts_plausible_names(): void
    {
        $this->assertTrue(NameValidator::isPlausible('Алексей', 'Алексей'));
        $this->assertTrue(NameValidator::isPlausible('Иван Петров', 'Иван Петров, +7 999 123-45-67'));
    }

    public function test_rejects_questions_and_stopwords(): void
    {
        // Прод-баг: «а меня нет в базе?» → имя «Нет».
        $this->assertFalse(NameValidator::isPlausible('Нет', 'а меня нет в базе?'));
        $this->assertFalse(NameValidator::isPlausible('Нет', 'нет'));
        $this->assertFalse(NameValidator::isPlausible('Да', 'да'));
        $this->assertFalse(NameValidator::isPlausible('Привет', 'привет'));
        $this->assertFalse(NameValidator::isPlausible('Любой Мастер', 'любой мастер'));
        $this->assertFalse(NameValidator::isPlausible(null, 'x'));
        // Прод-баг: «/start» → имя «Start». Команды мессенджера — не имя.
        $this->assertFalse(NameValidator::isPlausible('Start', '/start'));
        $this->assertFalse(NameValidator::isPlausible('Help', '/help'));
        $this->assertFalse(NameValidator::isPlausible('Иван Иванович Петров Сергеевич', 'длинная строка не имя'));
    }
}
