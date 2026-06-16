<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\PhoneExtractor;
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
}
