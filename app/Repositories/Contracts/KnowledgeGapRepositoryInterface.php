<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\KnowledgeGapStatus;
use App\Models\KnowledgeGap;
use Illuminate\Support\Collection;

/**
 * Доступ к «пробелам бота» (вопросам без ответа). Скоупится текущим тенантом.
 */
interface KnowledgeGapRepositoryInterface
{
    /**
     * Фиксирует вопрос без ответа. Если такой (по нормализованному тексту) уже
     * открыт — увеличивает счётчик и обновляет «последний раз»; иначе создаёт.
     */
    public function record(string $question, ?string $conversationId, ?string $channelType): KnowledgeGap;

    /**
     * Открытые пробелы текущего тенанта (частые сверху).
     *
     * @return Collection<int, KnowledgeGap>
     */
    public function openForCurrentTenant(): Collection;

    public function countOpenForCurrentTenant(): int;

    public function find(string $id): ?KnowledgeGap;

    public function updateStatus(KnowledgeGap $gap, KnowledgeGapStatus $status): void;

    public function delete(KnowledgeGap $gap): void;
}
