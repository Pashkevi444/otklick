<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Имя ПУБЛИЧНОГО реалтайм-канала веб-виджета (для индикатора «оператор печатает»).
 * Выводится детерминированно из канала и id сессии посетителя через keyed-hash на
 * APP_KEY — так имя не угадать и нельзя перебрать чужие сессии, а сервер и виджет
 * приходят к одному имени независимо (виджет — из своей сессии, кабинет — из диалога).
 *
 * По каналу гоняем только эфемерный сигнал «печатает», без данных.
 */
final class WidgetRealtimeChannel
{
    public static function name(string $channelId, string $sessionId): string
    {
        $digest = hash_hmac('sha256', $channelId.'|'.$sessionId, (string) config('app.key'));

        return 'widget.'.substr($digest, 0, 32);
    }
}
