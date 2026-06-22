<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Прогресс фонового импорта базы знаний с сайта бизнеса (для индикатора в
 * кабинете). Храним в кэше (Redis — общий для app и horizon), чтобы воркер писал
 * прогресс, а кабинет его читал. Ключ — по тенанту. Помимо процента несём счётчик
 * созданных черновиков — его показываем пользователю по завершении.
 */
final readonly class SiteImportStatus
{
    private const int TTL = 900; // 15 минут

    public function __construct(private CacheRepository $cache) {}

    public function begin(string $tenantId): void
    {
        $this->put($tenantId, 0, 'running', 0);
    }

    public function report(string $tenantId, int $percent, int $created = 0): void
    {
        $this->put($tenantId, $percent, 'running', $created);
    }

    public function succeed(string $tenantId, int $created): void
    {
        $this->put($tenantId, 100, 'done', $created);
    }

    public function fail(string $tenantId): void
    {
        $this->put($tenantId, 0, 'failed', 0);
    }

    /**
     * @return array{percent: int, state: string, created: int}
     */
    public function get(string $tenantId): array
    {
        $value = $this->cache->get($this->key($tenantId));

        return is_array($value) && isset($value['percent'], $value['state'])
            ? [
                'percent' => (int) $value['percent'],
                'state' => (string) $value['state'],
                'created' => (int) ($value['created'] ?? 0),
            ]
            : ['percent' => 0, 'state' => 'idle', 'created' => 0];
    }

    private function put(string $tenantId, int $percent, string $state, int $created): void
    {
        $this->cache->put($this->key($tenantId), [
            'percent' => $percent,
            'state' => $state,
            'created' => $created,
        ], self::TTL);
    }

    private function key(string $tenantId): string
    {
        return "site_import_progress:{$tenantId}";
    }
}
