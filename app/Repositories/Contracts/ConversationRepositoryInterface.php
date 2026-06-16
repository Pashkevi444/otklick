<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\ConversationStatus;
use App\Models\Conversation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Контракт доступа к данным диалогов.
 */
interface ConversationRepositoryInterface
{
    public function updateStatus(Conversation $conversation, ConversationStatus $status): void;

    /**
     * Находит диалог по чату канала или создаёт новый. tenant_id проставляется
     * автоматически из текущего тенант-контекста. $contactRef — ссылка на аккаунт
     * клиента (мессенджеры) или IP посетителя (веб-виджет).
     */
    public function firstOrCreateForChat(string $channelId, string $externalChatId, ?string $contactName, ?string $contactRef = null): Conversation;

    public function touchLastMessage(Conversation $conversation): void;

    /**
     * Увеличивает счётчик подряд идущих уточняющих вопросов бота и возвращает
     * новое значение.
     */
    public function bumpClarificationAttempts(Conversation $conversation): int;

    /**
     * Обнуляет счётчик уточняющих вопросов (бот ответил по делу или диалог ушёл
     * на человека).
     */
    public function resetClarificationAttempts(Conversation $conversation): void;

    /**
     * Сохраняет телефон клиента (для обратной связи), если он ещё не задан.
     */
    public function setContactPhone(Conversation $conversation, string $phone): void;

    /**
     * Сохраняет имя клиента (как он представился боту).
     */
    public function setContactName(Conversation $conversation, string $name): void;

    /**
     * Диалоги текущего тенанта (scoped/RLS) для журнала: с каналом, последним
     * сообщением и числом сообщений, новые сверху.
     *
     * @return Collection<int, Conversation>
     */
    public function forCurrentTenant(): Collection;

    public function findForCurrentTenant(string $id): ?Conversation;

    /**
     * Диалоги текущего тенанта с поиском, фильтром по статусу, сортировкой и
     * пагинацией (для грид-журнала).
     *
     * @param  'last'|'contact'|'messages'  $sort
     * @param  'asc'|'desc'  $direction
     * @return LengthAwarePaginator<int, Conversation>
     */
    public function paginateForCurrentTenant(
        ?string $search,
        ?ConversationStatus $status,
        string $sort,
        string $direction,
        int $perPage,
    ): LengthAwarePaginator;
}
