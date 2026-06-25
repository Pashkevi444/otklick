<?php

declare(strict_types=1);

namespace App\Shared\Support;

use Illuminate\Http\Request;

/**
 * Публичный конфиг Reverb-клиента (ключ + хост/порт/схема). Хост берём из запроса
 * (тот же домен, с которого пришёл клиент — Caddy проксирует /app/* на reverb).
 * null, если реалтайм выключен (BROADCAST != reverb) — фронт/виджет работают без
 * сокетов. Используется и кабинетом (Inertia shared prop), и веб-виджетом.
 */
final class RealtimeConfig
{
    /**
     * @return array{key: string, host: string, port: int, scheme: string}|null
     */
    public static function fromRequest(Request $request): ?array
    {
        if (config('broadcasting.default') !== 'reverb') {
            return null;
        }

        $key = config('broadcasting.connections.reverb.key');

        if (! is_string($key) || $key === '') {
            return null;
        }

        $secure = $request->isSecure();

        return [
            'key' => $key,
            'host' => $request->getHost(),
            'port' => $secure ? 443 : ((int) $request->getPort() ?: 80),
            'scheme' => $secure ? 'https' : 'http',
        ];
    }
}
