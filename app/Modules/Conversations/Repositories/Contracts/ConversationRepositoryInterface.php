<?php

declare(strict_types=1);

namespace App\Modules\Conversations\Repositories\Contracts;

use App\Modules\Conversations\Models\Conversation;
use App\Shared\Enums\ChannelType;
use App\Shared\Enums\ConversationOutcome;
use App\Shared\Enums\ConversationStatus;
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

    /** Перехват диалога оператором: ставит флаг и фиксирует, кто перехватил. */
    public function setOperator(Conversation $conversation, ?int $operatorUserId): void;

    /** Снимает перехват — диалогом снова управляет бот. */
    public function clearOperator(Conversation $conversation): void;

    /** Продлевает перехват (активность оператора) — сдвигает таймер авто-возврата. */
    public function touchOperator(Conversation $conversation): void;

    /**
     * Перехваченные диалоги текущего тенанта без активности оператора дольше
     * порога (для авто-возврата боту).
     *
     * @return Collection<int, Conversation>
     */
    public function idleOperatorHandled(Carbon $before): Collection;

    /**
     * Закрывает открытые диалоги текущего тенанта без активности с момента
     * $before и без записи (потерянные лиды). Возвращает число закрытых.
     */
    public function closeStaleOpen(Carbon $before): int;

    /**
     * Закрывает диалоги текущего тенанта, зависшие в статусе «нужен человек» без
     * активности с момента $before (оператор не разобрал) и без записи — лид
     * считаем потерянным (status → Closed ⇒ ConversationOutcome::Lost). Возвращает
     * число закрытых.
     */
    public function closeStaleNeedsHuman(Carbon $before): int;

    /**
     * Закрывает диалоги с CRM-записью, время визита которой уже прошло (услуга
     * оказана) — лид становится «Успешным» (см. Conversation::outcome). Возвращает
     * число закрытых. Вызывается планировщиком только у тенантов с CRM.
     */
    public function closeCompletedBookingsForCurrentTenant(Carbon $now): int;

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

    /** Отмечает, что контактная форма отработала (имя+телефон собраны/узнаны). */
    public function markContactsGateDone(Conversation $conversation): void;

    /** Фиксирует согласие клиента на обработку ПД (152-ФЗ) + момент получения. */
    public function markConsentGiven(Conversation $conversation): void;

    /** Привязывает диалог к карточке клиента (база клиентов). */
    public function setClientId(Conversation $conversation, string $clientId): void;

    /** Отвязывает все лиды от клиента (client_id → null) при удалении клиента. */
    public function clearClientLinks(string $clientId): void;

    /** Переносит лиды с карточки $fromClientId на $toClientId (склейка клиентов). */
    public function reassignClient(string $fromClientId, string $toClientId): void;

    /** Удаляет диалог (с каскадом сообщений). */
    public function delete(Conversation $conversation): void;

    /**
     * Типы каналов, по которым у тенанта реально есть диалоги — для фильтра грида
     * (чтобы не показывать пустые каналы).
     *
     * @return list<string>
     */
    public function channelTypesForCurrentTenant(): array;

    /**
     * Сохраняет состояние пошаговой записи (BookingFlow). null очищает —
     * активной записи больше нет.
     *
     * @param  array<string, mixed>|null  $state
     */
    public function setBookingState(Conversation $conversation, ?array $state): void;

    /**
     * Сохраняет состояние активной воронки (FlowEngine). null очищает —
     * диалог выходит из воронки.
     *
     * @param  array<string, mixed>|null  $state
     */
    public function setFlowState(Conversation $conversation, ?array $state): void;

    /**
     * Сохраняет идентификатор записи в CRM (для последующей отмены). null —
     * запись отменена/отсутствует.
     */
    public function setCrmRecordId(Conversation $conversation, ?string $recordId): void;

    /**
     * Снимок ценности оформленной записи для «Отчёта ценности»: в какую CRM ушла
     * запись и какую услугу/цену зафиксировали в момент записи. Цена — рубли,
     * null если CRM её не отдала.
     */
    public function recordBookingValue(
        Conversation $conversation,
        string $crmConnectionId,
        ?string $serviceId,
        ?string $serviceTitle,
        ?int $servicePrice,
    ): void;

    /**
     * Последний диалог чата (по каналу и external_chat_id) с непустым
     * crm_record_id — для отмены ранее оформленной записи. null, если такой нет.
     */
    public function lastWithCrmRecordForChat(string $channelId, string $externalChatId): ?Conversation;

    /**
     * Предстоящие записи чата (есть `crm_record_id`, `booked_for` в будущем) —
     * для меню «перенести/отменить/новая запись» у вернувшегося клиента.
     *
     * @return Collection<int, Conversation>
     */
    public function activeBookingsForChat(string $channelId, string $externalChatId): Collection;

    /**
     * Время визита (из слота CRM) для напоминаний; сбрасывает отметки об
     * отправленных напоминаниях.
     */
    public function setBookedFor(Conversation $conversation, Carbon $bookedFor): void;

    /**
     * Отмечает, что напоминание за $offsetMinutes до визита отправлено.
     */
    public function markReminderSent(Conversation $conversation, int $offsetMinutes): void;

    /**
     * Диалоги текущего тенанта с активной записью (есть crm_record_id), чьё
     * время визита между $from и $to — кандидаты на напоминание. С каналом.
     *
     * @return Collection<int, Conversation>
     */
    public function upcomingBookedForCurrentTenant(Carbon $from, Carbon $to): Collection;

    /**
     * Краткие счётчики для дашборда: лиды сегодня/за 7 дней и записи за 7 дней.
     *
     * @return array{leadsToday: int, leadsWeek: int, bookedWeek: int}
     */
    public function dashboardStats(): array;

    /**
     * Диалоги текущего тенанта (scoped/RLS) для журнала: с каналом, последним
     * сообщением и числом сообщений, новые сверху.
     *
     * @return Collection<int, Conversation>
     */
    public function forCurrentTenant(): Collection;

    public function findForCurrentTenant(string $id): ?Conversation;

    /**
     * Диалоги текущего тенанта с поиском, фильтрами по статусу и каналу,
     * сортировкой и пагинацией (для грид-журнала).
     *
     * @param  'last'|'contact'|'messages'|'created'  $sort
     * @param  'asc'|'desc'  $direction
     * @return LengthAwarePaginator<int, Conversation>
     */
    public function paginateForCurrentTenant(
        ?string $search,
        ?ConversationStatus $status,
        ?ChannelType $channel,
        string $sort,
        string $direction,
        int $perPage,
    ): LengthAwarePaginator;
}
