<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Статус фоновой генерации AI-черновика ответа на «пробел бота» (для индикатора
 * «приложение думает» в кабинете). Храним в кэше (Redis — общий для app и
 * horizon): джоба пишет статус, кабинет читает. Ключ — по записи БЗ.
 */
final readonly class GapDraftStatus
{
    private const int TTL = 600; // 10 минут

    public function __construct(private CacheRepository $cache) {}

    public function begin(string $entryId): void
    {
        $this->cache->put($this->key($entryId), 'drafting', self::TTL);
    }

    public function finish(string $entryId): void
    {
        $this->cache->put($this->key($entryId), 'ready', self::TTL);
    }

    public function isDrafting(string $entryId): bool
    {
        return $this->state($entryId) === 'drafting';
    }

    /** drafting | ready | idle */
    public function state(string $entryId): string
    {
        $value = $this->cache->get($this->key($entryId));

        return is_string($value) ? $value : 'idle';
    }

    private function key(string $entryId): string
    {
        return "gap_draft:{$entryId}";
    }
}
