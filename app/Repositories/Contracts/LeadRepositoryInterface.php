<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTO\LeadData;
use App\Models\Lead;
use Illuminate\Support\Collection;

interface LeadRepositoryInterface
{
    /**
     * Лиды текущего тенанта (с клиентом) для списка «Входящие».
     *
     * @return Collection<int, Lead>
     */
    public function forCurrentTenant(): Collection;

    public function find(string $id): ?Lead;

    public function create(LeadData $data): Lead;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Lead $lead, array $attributes): void;

    public function delete(Lead $lead): void;

    /** Уже есть лид по этому диалогу (идемпотентность авто-создания). */
    public function existsForConversation(string $conversationId): bool;

    /** Лид, привязанный к диалогу (для авто-движка воронки). */
    public function findByConversation(string $conversationId): ?Lead;
}
