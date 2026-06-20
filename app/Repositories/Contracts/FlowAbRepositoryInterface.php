<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

/**
 * A/B-назначения вариантов сценариев. Скоупится текущим тенантом (RLS + scope).
 */
interface FlowAbRepositoryInterface
{
    /** Уже назначенный диалогу вариант в этом сценарии, либо null. */
    public function variantFor(string $flowId, string $conversationId): ?string;

    /** Назначает вариант диалогу (липко: если уже есть — не меняет). */
    public function assign(string $flowId, string $conversationId, string $variant): void;

    /**
     * Статистика по вариантам тенанта: всего диалогов и из них с записью (конверсия).
     *
     * @return list<array{flow_id: string, variant: string, total: int, booked: int}>
     */
    public function statsForCurrentTenant(): array;
}
