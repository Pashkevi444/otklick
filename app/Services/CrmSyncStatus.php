<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Прогресс фоновой выгрузки из CRM (для индикатора в кабинете). Храним в кэше
 * (Redis — общий для app и horizon), чтобы воркер писал прогресс, а кабинет его
 * читал. Ключ — по тенанту.
 */
final readonly class CrmSyncStatus
{
    private const int TTL = 600; // 10 минут

    public function __construct(private CacheRepository $cache) {}

    public function begin(string $tenantId): void
    {
        $this->put($tenantId, 0, 'running');
    }

    public function report(string $tenantId, int $percent): void
    {
        $this->put($tenantId, $percent, 'running');
    }

    public function succeed(string $tenantId): void
    {
        $this->put($tenantId, 100, 'done');
    }

    public function fail(string $tenantId): void
    {
        $this->put($tenantId, 0, 'failed');
    }

    /**
     * @return array{percent: int, state: string}
     */
    public function get(string $tenantId): array
    {
        $value = $this->cache->get($this->key($tenantId));

        return is_array($value) && isset($value['percent'], $value['state'])
            ? ['percent' => (int) $value['percent'], 'state' => (string) $value['state']]
            : ['percent' => 0, 'state' => 'idle'];
    }

    private function put(string $tenantId, int $percent, string $state): void
    {
        $this->cache->put($this->key($tenantId), ['percent' => $percent, 'state' => $state], self::TTL);
    }

    private function key(string $tenantId): string
    {
        return "crm_sync_progress:{$tenantId}";
    }
}
