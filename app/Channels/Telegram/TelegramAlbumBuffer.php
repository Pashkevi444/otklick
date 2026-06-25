<?php

declare(strict_types=1);

namespace App\Channels\Telegram;

use App\Jobs\ProcessTelegramAlbum;
use Illuminate\Support\Facades\Cache;

/**
 * Буфер фото Telegram-альбома. Альбом приходит ОТДЕЛЬНЫМИ апдейтами с общим
 * `media_group_id` — копим их в кэше под локом (без гонок между воркерами Horizon)
 * и через короткую задержку отдаём одним сообщением (см. {@see ProcessTelegramAlbum}),
 * чтобы бот ответил на альбом ОДИН раз, а не по реплике на каждое фото.
 */
final class TelegramAlbumBuffer
{
    private const int TTL_MINUTES = 5;

    private const int LOCK_SECONDS = 5;

    private const int LOCK_WAIT_SECONDS = 3;

    /**
     * Добавляет фото группы в буфер (атомарно, под локом).
     *
     * @param  array<string, mixed>  $entry
     */
    public function add(string $channelId, string $groupId, array $entry): void
    {
        $key = $this->key($channelId, $groupId);

        Cache::lock($key.':lock', self::LOCK_SECONDS)->block(self::LOCK_WAIT_SECONDS, function () use ($key, $entry): void {
            $buffer = Cache::get($key, []);
            $buffer[] = $entry;
            Cache::put($key, $buffer, now()->addMinutes(self::TTL_MINUTES));
        });
    }

    /**
     * Забирает и очищает буфер группы (атомарно).
     *
     * @return list<array<string, mixed>>
     */
    public function pull(string $channelId, string $groupId): array
    {
        $key = $this->key($channelId, $groupId);

        return Cache::lock($key.':lock', self::LOCK_SECONDS)->block(self::LOCK_WAIT_SECONDS, function () use ($key): array {
            $buffer = Cache::get($key, []);
            Cache::forget($key);
            Cache::forget($key.':scheduled');

            return array_values($buffer);
        });
    }

    /**
     * true только для ПЕРВОГО фото группы — он и планирует отложенный флаш
     * (остальные просто докладываются в буфер). Защёлка живёт TTL группы.
     */
    public function shouldSchedule(string $channelId, string $groupId): bool
    {
        return Cache::add($this->key($channelId, $groupId).':scheduled', true, now()->addMinutes(self::TTL_MINUTES));
    }

    private function key(string $channelId, string $groupId): string
    {
        return "tg-album:{$channelId}:{$groupId}";
    }
}
