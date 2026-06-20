<?php

declare(strict_types=1);

namespace App\Services;

use App\Llm\Contracts\Embedder;
use App\Models\Flow;
use App\Repositories\Contracts\FlowRepositoryInterface;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Бизнес-логика сценариев-воронок: создание/правка/переключение + подсчёт
 * эмбеддингов фраз-триггеров (для семантического матчинга в {@see FlowEngine}).
 * Эмбеддинги считаем при сохранении (триггеров единицы), чтобы не гонять
 * эмбеддер на каждое входящее сообщение. Сбой эмбеддера не ломает сохранение —
 * сценарий просто матчится по основе слова (стеммер).
 */
final readonly class FlowService
{
    public function __construct(
        private FlowRepositoryInterface $flows,
        private Embedder $embedder,
    ) {}

    /**
     * @return Collection<int, Flow>
     */
    public function forCurrentTenant(): Collection
    {
        return $this->flows->forCurrentTenant();
    }

    public function find(string $id): ?Flow
    {
        return $this->flows->find($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(string $tenantId, array $data): Flow
    {
        return $this->flows->create([
            ...$data,
            'tenant_id' => $tenantId,
            'trigger_embeddings' => $this->embedTriggers($data['triggers'] ?? []),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Flow $flow, array $data): void
    {
        if (array_key_exists('triggers', $data)) {
            $data['trigger_embeddings'] = $this->embedTriggers($data['triggers']);
        }

        $this->flows->update($flow, $data);
    }

    public function toggle(Flow $flow): void
    {
        $this->flows->update($flow, ['is_active' => ! $flow->is_active]);
    }

    public function delete(Flow $flow): void
    {
        $this->flows->delete($flow);
    }

    /**
     * Вектор на каждую непустую фразу-триггер. null — нет триггеров или эмбеддер
     * недоступен (тогда матчинг остаётся только по основе слова).
     *
     * @param  array<int, mixed>  $triggers
     * @return list<list<float>>|null
     */
    private function embedTriggers(array $triggers): ?array
    {
        $phrases = array_values(array_filter(
            array_map(static fn ($t): string => trim((string) $t), $triggers),
            static fn (string $t): bool => $t !== '',
        ));

        if ($phrases === []) {
            return null;
        }

        try {
            return array_map(fn (string $phrase): array => $this->embedder->embed($phrase), $phrases);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
