<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Enums\ConversationOutcome;
use App\Enums\ConversationStatus;
use App\Models\Conversation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Контракт доступа к данным диалогов.
 */
interface ConversationRepositoryInterface
{
    public function updateStatus(Conversation $conversation, ConversationStatus $status): void;

    /**
     * Фиксирует оформленную запись: закрывает диалог и проставляет booked_at
     * (для аналитики конверсии).
     */
    public function markBooked(Conversation $conversation): void;

    /**
     * Фиксирует отмену записи клиентом: закрывает диалог и проставляет cancelled_at
     * (итог «Отменён клиентом»).
     */
    public function markCancelled(Conversation $conversation): void;

    /**
     * Вручную (админом) выставляет итог лида и синхронизирует статус диалога
     * (закрытые итоги → closed, «нужен человек»/«в работе» → соответствующий
     * статус). Для успеха/отмены проставляет недостающую отметку времени.
     */
    public function setOutcome(Conversation $conversation, ConversationOutcome $outcome): void;

    /**
     * Находит диалог по чату канала или создаёт новый. tenant_id проставляется
     * автоматически из текущего тенант-контекста. $contactRef — ссылка на аккаунт
     * клиента (мессенджеры) или IP посетителя (веб-виджет).
     */
    public function firstOrCreateForChat(string $channelId, string $externalChatId, ?string $contactName, ?string $contactRef = null): Conversation;

    /**
     * Активный (незакрытый) диалог чата канала или null — без создания.
     */
    public function findActiveForChat(string $channelId, string $externalChatId): ?Conversation;

    public function touchLastMessage(Conversation $conversation): void;

    /**
     * Закрывает открытые диалоги текущего тенанта без активности с момента
     * $before и без записи (потерянные лиды). Возвращает число закрытых.
     */
    public function closeStaleOpen(Carbon $before): int;

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
