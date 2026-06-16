<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Находит телефон в свободном тексте сообщения клиента и нормализует его.
 * Ориентирован на РФ-номера (+7 / 8 / 10 цифр), но принимает и зарубежные.
 */
final class PhoneExtractor
{
    public static function fromText(string $text): ?string
    {
        if (preg_match('/(?:\+7|8|7)?[\s\-()]*\d[\d\s\-()]{8,}\d/u', $text, $m) !== 1) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $m[0]) ?? '';
        $len = strlen($digits);

        if ($len === 11 && ($digits[0] === '8' || $digits[0] === '7')) {
            return '+7'.substr($digits, 1);
        }
        if ($len === 10) {
            return '+7'.$digits;
        }
        if ($len >= 11 && $len <= 15) {
            return '+'.$digits;
        }

        return null;
    }
}
