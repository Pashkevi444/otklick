<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Conversation;
use App\Models\Deal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Доступ к данным для аналитики по лидам текущего тенанта. Тонкий слой: только
 * выборка (агрегации считает сервис). Всё скоупится тенантом (RLS + scope).
 */
interface LeadAnalyticsRepositoryInterface
{
    /**
     * Лиды (диалоги), созданные в окне [from, to], с типом канала и числом
     * входящих сообщений (inbound_count) — основа для всех метрик периода.
     *
     * @return Collection<int, Conversation>
     */
    public function leadsForAnalytics(Carbon $from, Carbon $to): Collection;

    /**
     * Сделки, созданные в окне [from, to], со стадией — для разбивки воронки по
     * стадиям (CRM-аналитика).
     *
     * @return Collection<int, Deal>
     */
    public function dealsForAnalytics(Carbon $from, Carbon $to): Collection;

    /**
     * Типы подключённых (активных) каналов тенанта — для анализа пробелов.
     *
     * @return list<string>
     */
    public function connectedChannelTypes(): array;

    /**
     * Свежие лиды для таблицы (с каналом, последним сообщением и числом сообщений).
     *
     * @return Collection<int, Conversation>
     */
    public function recentLeads(int $limit): Collection;

    /**
     * Записи, оформленные ботом в конкретную CRM за окно [from, to] (для «Отчёта
     * ценности»): диалоги этого подключения с непустым booked_at. Несут снимок
     * услуги/цены (booked_service_*) и reminders_sent.
     *
     * @return Collection<int, Conversation>
     */
    public function bookingsForCrm(string $crmConnectionId, Carbon $from, Carbon $to): Collection;

    /**
     * Число отмен записей этой CRM за окно [from, to] (упущенная выручка).
     */
    public function cancelledCountForCrm(string $crmConnectionId, Carbon $from, Carbon $to): int;

    /**
     * Всего лидов (диалогов), созданных в окне [from, to] — знаменатель конверсии
     * «лид → запись».
     */
    public function leadsCount(Carbon $from, Carbon $to): int;
}
