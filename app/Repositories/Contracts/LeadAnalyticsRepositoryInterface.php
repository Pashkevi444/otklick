<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Conversation;
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
}
