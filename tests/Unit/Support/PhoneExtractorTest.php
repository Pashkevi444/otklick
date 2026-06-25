<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Shared\Support\PhoneExtractor;
use PHPUnit\Framework\TestCase;

final class PhoneExtractorTest extends TestCase
{
    public function test_extracts_and_normalizes_russian_numbers(): void
    {
        $this->assertSame('+79991234567', PhoneExtractor::fromText('звоните +7 999 123-45-67'));
        $this->assertSame('+79991234567', PhoneExtractor::fromText('мой номер 8 (999) 123 45 67, спасибо'));
        $this->assertSame('+79991234567', PhoneExtractor::fromText('9991234567'));
    }

    public function test_returns_null_when_no_phone(): void
    {
        $this->assertNull(PhoneExtractor::fromText('Здравствуйте, сколько стоит фейд?'));
        $this->assertNull(PhoneExtractor::fromText('номер дома 12'));
    }

    public function test_rejects_invalid_length_numbers(): void
    {
        // Прод-баг: 14-значный «номер» больше нельзя «схавать».
        $this->assertNull(PhoneExtractor::fromText('+72223322123123'));
        // Слишком короткий.
        $this->assertNull(PhoneExtractor::fromText('+7 999 12-34'));
        // Слишком длинный.
        $this->assertNull(PhoneExtractor::fromText('+7 999 123 45 67 89 00'));
    }

    public function test_analyze_distinguishes_invalid_from_absent(): void
    {
        $this->assertSame('valid', PhoneExtractor::analyze('+7 999 123-45-67')['status']);

        $invalid = PhoneExtractor::analyze('+72223322123123');
        $this->assertSame('invalid', $invalid['status']); // есть похожее на номер, но некорректное
        $this->assertNull($invalid['phone']);

        $this->assertSame('none', PhoneExtractor::analyze('хочу записаться завтра')['status']);
    }
}
