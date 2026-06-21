<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Tenant;

/**
 * Главное меню бота — кнопки-подсказки, которые бизнес задаёт в кабинете
 * (`tenants.settings['bot_menu']`). Бот показывает их после приветствия и даёт
 * кнопку возврата в меню из других контекстов.
 *
 * Пустое меню → бот не показывает ни кнопок, ни возврата. При подключённой записи
 * (YClients) кнопка «Записаться» добавляется автоматически — даже если такая же
 * есть у бизнеса (дубль остаётся на усмотрение бизнеса).
 */
final class BotMenu
{
    /** Авто-кнопка записи (добавляется при активной CRM). */
    public const string BOOKING_BUTTON = 'Записаться';

    /** Кнопка возврата в главное меню (показывается, когда меню непустое). */
    public const string RETURN_BUTTON = '🏠 Главное меню';

    /**
     * Кнопки, заданные бизнесом (без авто-записи).
     *
     * @return list<string>
     */
    public static function items(Tenant $tenant): array
    {
        $raw = $tenant->settings['bot_menu'] ?? [];

        return array_values(array_filter(
            array_map('strval', is_array($raw) ? $raw : []),
            static fn (string $v): bool => trim($v) !== '',
        ));
    }

    /**
     * Эффективное меню: авто-«Записаться» (если запись доступна) + кнопки бизнеса.
     *
     * @return list<string>
     */
    public static function effective(Tenant $tenant, bool $bookingAvailable): array
    {
        $items = self::items($tenant);

        return $bookingAvailable ? array_merge([self::BOOKING_BUTTON], $items) : $items;
    }

    /** Просьба вернуться в главное меню (текст кнопки или синонимы). */
    public static function isReturn(string $text): bool
    {
        return in_array(mb_strtolower(trim($text)), [
            'главное меню',
            'вернуться в главное меню',
            mb_strtolower(self::RETURN_BUTTON),
        ], true);
    }
}
