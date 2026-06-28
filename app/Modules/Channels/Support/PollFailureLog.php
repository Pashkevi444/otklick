<?php

declare(strict_types=1);

namespace App\Modules\Channels\Support;

use App\Shared\Support\SecretScrubber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Логирование сбоя опроса канала в КОНКУРЕНТНОМ круге (Http::pool). Упавший запрос
 * приходит из пула значением — либо ConnectionException (транзиентная сеть), либо
 * не-2xx Response, либо иной Throwable. Сбойный канал просто отсутствует в результате
 * пула (ретрай следующим кругом), но факт сбоя фиксируем структурно — чтобы по логам
 * можно было восстановить причину у конкретного канала. Секрет (токен в URL/заголовке/
 * сообщении исключения) вырезаем SecretScrubber'ом.
 */
final class PollFailureLog
{
    public static function record(string $provider, string $channelId, mixed $outcome): void
    {
        if ($outcome instanceof ConnectionException) {
            // Транзиентный сетевой сбой до провайдера — поллер ретраит следующим кругом.
            Log::warning("{$provider}.poll_connection", [
                'channel_id' => $channelId,
                'error' => SecretScrubber::scrub($outcome->getMessage()),
            ]);

            return;
        }

        $error = match (true) {
            $outcome instanceof Throwable => $outcome->getMessage(),
            $outcome instanceof Response => 'HTTP '.$outcome->status(),
            default => 'unknown',
        };

        Log::warning("{$provider}.poll_failed", [
            'channel_id' => $channelId,
            'error' => SecretScrubber::scrub($error),
        ]);
    }
}
