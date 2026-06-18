<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Находит и СТРОГО валидирует телефон РФ в тексте. Корректный номер — это +7/8 и
 * 10 цифр абонента (11 всего) либо 10 цифр без кода. Всё остальное (короткий,
 * длинный, мусор) — невалидно: бот должен попросить исправить, а не «схавать».
 */
final class PhoneExtractor
{
    /**
     * Нормализованный номер (+7XXXXXXXXXX) или null, если в тексте нет
     * корректного РФ-номера.
     */
    public static function fromText(string $text): ?string
    {
        return self::analyze($text)['phone'];
    }

    /**
     * Разбирает телефон в сообщении:
     *  - valid   — найден корректный РФ-номер (нормализованный в `phone`);
     *  - invalid — есть похожая на номер последовательность, но некорректная
     *              (короткая/длинная/не РФ) — просим исправить;
     *  - none    — номера в сообщении нет вовсе.
     *
     * @return array{status: 'valid'|'invalid'|'none', phone: string|null}
     */
    public static function analyze(string $text): array
    {
        // Кандидат: длинный прогон цифр и телефонных разделителей.
        if (preg_match('/\+?\d[\d\s\-()]{5,}\d/u', $text, $m) !== 1) {
            return ['status' => 'none', 'phone' => null];
        }

        $digits = preg_replace('/\D+/', '', $m[0]) ?? '';

        // Меньше 7 цифр — это не попытка телефона («дом 12», «в 14»), а не «короткий номер».
        if (strlen($digits) < 7) {
            return ['status' => 'none', 'phone' => null];
        }

        $phone = self::normalizeRu($digits);

        return $phone !== null
            ? ['status' => 'valid', 'phone' => $phone]
            : ['status' => 'invalid', 'phone' => null];
    }

    /** Нормализует РФ-номер (+7 и 10 цифр абонента) или null при некорректной длине. */
    private static function normalizeRu(string $digits): ?string
    {
        $len = strlen($digits);

        if ($len === 11 && ($digits[0] === '8' || $digits[0] === '7')) {
            return '+7'.substr($digits, 1);
        }

        if ($len === 10) {
            return '+7'.$digits;
        }

        return null;
    }
}
