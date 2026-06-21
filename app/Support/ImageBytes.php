<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Возвращает байты картинки по её публичному URL. Сначала читает файл с локального
 * публичного диска (быстро, без сети) — это важно для отправки фото в мессенджеры
 * ЗАГРУЗКОЙ, а не ссылкой: их серверы (за рубежом) не всегда могут скачать URL с
 * РФ-хостинга. Если по URL не наш диск — скачиваем по сети. null — не получилось.
 */
final class ImageBytes
{
    public static function fetch(string $url): ?string
    {
        $marker = '/storage/';
        $pos = strpos($url, $marker);
        if ($pos !== false) {
            $path = substr($url, $pos + strlen($marker));
            if (Storage::disk('public')->exists($path)) {
                return Storage::disk('public')->get($path);
            }
        }

        $res = Http::connectTimeout(5)->timeout(15)->get($url);

        return $res->successful() ? $res->body() : null;
    }
}
