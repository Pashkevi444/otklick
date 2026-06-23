<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CountUp from '@/Components/Charts/CountUp.vue';
import AreaChart from '@/Components/Charts/AreaChart.vue';
import DonutChart from '@/Components/Charts/DonutChart.vue';
import BarChart from '@/Components/Charts/BarChart.vue';

interface Kpi {
    key: string;
    label: string;
    value: number;
    unit: string;
    deltaPct: number | null;
    goodWhenUp: boolean;
    hint: string;
}
interface Slice {
    key: string;
    label: string;
    value: number;
    pct: number;
    color: string;
}
interface Stage {
    key: string;
    label: string;
    value: number;
    pct: number;
}
interface GapItem {
    severity: string;
    title: string;
    detail: string;
    action: string;
}
interface RecentLead {
    id: string;
    contact: string;
    phone: string | null;
    channel: string;
    booked: boolean;
    escalated: boolean;
    messages: number;
    createdAt: string | null;
}
interface Analytics {
    period: { key: string; label: string; from: string; to: string };
    periods: { key: string; label: string }[];
    kpis: Kpi[];
    daily: { date: string; label: string; value: number }[];
    byChannel: Slice[];
    byStage: Slice[];
    byDaypart: Slice[];
    funnel: Stage[];
    hourly: { hour: number; value: number }[];
    weekday: { key: string; label: string; value: number }[];
    engagement: { label: string; value: number }[];
    gaps: GapItem[];
    recent: RecentLead[];
    totals: Record<string, number>;
}
interface Insights {
    items: GapItem[];
    source: string;
    generatedAt: string;
    period: string;
}
interface ServiceRevenueRow {
    title: string;
    bookings: number;
    revenue: number;
}
interface ValueReport {
    crmConnectionId: string;
    crmLabel: string;
    kpis: Kpi[];
    topServices: ServiceRevenueRow[];
    note: string | null;
}

const props = defineProps<{ analytics: Analytics; insights: Insights | null; valueReports: ValueReport[]; aiInsights: boolean }>();

const activeCrm = ref(0);
const activeReport = computed<ValueReport | null>(() => props.valueReports[activeCrm.value] ?? props.valueReports[0] ?? null);
const valueExportUrl = (crmId: string): string =>
    `/cabinet/analytics/export/value?crm=${encodeURIComponent(crmId)}&${new URLSearchParams(rangeParams()).toString()}`;
const fmtMoney = (n: number): string => n.toLocaleString('ru-RU');

const refreshing = ref(false);

const isCustom = computed<boolean>(() => props.analytics.period.key === 'custom');
const customFrom = ref(props.analytics.period.from);
const customTo = ref(props.analytics.period.to);

// Поля дат следуют за активным окном (в т.ч. после смены пресета).
watch(
    () => props.analytics.period,
    (p) => {
        customFrom.value = p.from;
        customTo.value = p.to;
    },
);

// Параметры текущего окна для экспорта/обновления: пресет или произвольные даты.
const rangeParams = (): Record<string, string> =>
    isCustom.value ? { from: props.analytics.period.from, to: props.analytics.period.to } : { period: props.analytics.period.key };

const setPeriod = (key: string): void => {
    router.get('/cabinet/analytics', { period: key }, { preserveScroll: true, preserveState: true, only: ['analytics', 'insights', 'valueReports'] });
};

const applyCustom = (): void => {
    if (!customFrom.value || !customTo.value) return;
    router.get(
        '/cabinet/analytics',
        { from: customFrom.value, to: customTo.value },
        { preserveScroll: true, preserveState: true, only: ['analytics', 'insights', 'valueReports'] },
    );
};

const refreshInsights = (): void => {
    router.post('/cabinet/analytics/insights/refresh', rangeParams(), {
        preserveScroll: true,
        onStart: () => (refreshing.value = true),
        onFinish: () => (refreshing.value = false),
    });
};

const exportUrl = (type: string): string =>
    `/cabinet/analytics/export/${type}?${new URLSearchParams(rangeParams()).toString()}`;

const hourBars = computed(() => props.analytics.hourly.map((h) => ({ label: String(h.hour), value: h.value })));
const weekBars = computed(() => props.analytics.weekday.map((w) => ({ label: w.label, value: w.value })));

const afterHoursCaption = computed<string>(() => {
    const n = props.analytics.totals.afterHours ?? 0;
    return n > 0
        ? `${n} обращений пришли вне рабочих часов (до 8:00 и после 20:00) — их не пропустил бот`
        : 'Все обращения за период пришли в рабочее время';
});

// ИИ-разбор, если посчитан; иначе — базовый разбор по правилам (мгновенно).
const gapItems = computed<GapItem[]>(() => props.insights?.items ?? props.analytics.gaps);
const insightSource = computed<string>(() => (props.insights ? (props.insights.source === 'ai' ? 'Разбор ИИ' : 'Базовый разбор') : 'Базовый разбор'));

const kpiDecimals = (k: Kpi): number => (k.key === 'clarifications' ? 2 : 0);
const deltaClass = (k: Kpi): string => {
    if (k.deltaPct === null || k.deltaPct === 0) return 'text-slate-400';
    const good = k.deltaPct > 0 === k.goodWhenUp;
    return good ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-500 dark:text-rose-400';
};
const deltaText = (k: Kpi): string => {
    if (k.deltaPct === null) return 'нет данных';
    if (k.deltaPct === 0) return '→ 0%';
    return `${k.deltaPct > 0 ? '▲' : '▼'} ${Math.abs(k.deltaPct)}%`;
};

const gapClass = (s: string): string =>
    ({
        high: 'border-rose-300/70 bg-rose-50 dark:border-rose-400/30 dark:bg-rose-500/10',
        medium: 'border-amber-300/70 bg-amber-50 dark:border-amber-400/30 dark:bg-amber-500/10',
        low: 'border-slate-200 bg-slate-50 dark:border-white/10 dark:bg-white/5',
        ok: 'border-emerald-300/70 bg-emerald-50 dark:border-emerald-400/30 dark:bg-emerald-500/10',
    })[s] ?? 'border-slate-200 bg-slate-50';
const gapIcon = (s: string): string => ({ high: '⚠️', medium: '⚡', low: 'ℹ️', ok: '✅' })[s] ?? 'ℹ️';

</script>

<template>
    <Head title="Аналитика" />

    <AppLayout title="Аналитика по лидам">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <p class="text-sm text-slate-500">Период: {{ analytics.period.from }} — {{ analytics.period.to }}</p>
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex rounded-xl border border-slate-200 bg-white/60 p-1 dark:border-white/10 dark:bg-white/5">
                    <button
                        v-for="p in analytics.periods"
                        :key="p.key"
                        type="button"
                        class="rounded-lg px-3 py-1 text-sm font-medium transition"
                        :class="p.key === analytics.period.key ? 'bg-[#2E74B5] text-white shadow' : 'text-slate-500 hover:text-[#1F4E79] dark:text-slate-300'"
                        @click="setPeriod(p.key)"
                    >
                        {{ p.label }}
                    </button>
                </div>
                <div
                    class="flex items-center gap-1.5 rounded-xl border bg-white/60 px-2 py-1 dark:bg-white/5"
                    :class="isCustom ? 'border-[#2E74B5] dark:border-sky-400' : 'border-slate-200 dark:border-white/10'"
                >
                    <input
                        v-model="customFrom"
                        type="date"
                        class="bg-transparent text-sm text-slate-600 outline-none dark:text-slate-200 dark:[color-scheme:dark]"
                        aria-label="Дата начала"
                    />
                    <span class="text-slate-400">—</span>
                    <input
                        v-model="customTo"
                        type="date"
                        class="bg-transparent text-sm text-slate-600 outline-none dark:text-slate-200 dark:[color-scheme:dark]"
                        aria-label="Дата конца"
                    />
                    <button
                        type="button"
                        class="rounded-lg bg-[#2E74B5] px-2.5 py-1 text-xs font-medium text-white transition hover:-translate-y-0.5 disabled:opacity-50"
                        :disabled="!customFrom || !customTo"
                        @click="applyCustom"
                    >
                        Применить
                    </button>
                </div>
                <a :href="exportUrl('leads')" class="rounded-xl border border-slate-200 bg-white/60 px-3 py-1.5 text-sm font-medium text-[#1F4E79] transition hover:-translate-y-0.5 dark:border-white/10 dark:bg-white/5 dark:text-sky-300">⬇ Лиды CSV</a>
                <a :href="exportUrl('daily')" class="rounded-xl border border-slate-200 bg-white/60 px-3 py-1.5 text-sm font-medium text-[#1F4E79] transition hover:-translate-y-0.5 dark:border-white/10 dark:bg-white/5 dark:text-sky-300">⬇ По дням CSV</a>
            </div>
        </div>

        <div :key="analytics.period.key" class="ui-fade-in space-y-5">
            <!-- Отчёт ценности (по каждой CRM) -->
            <div
                v-if="valueReports.length > 0"
                class="rounded-2xl border border-[#2E74B5]/30 bg-gradient-to-br from-[#EAF2FB] to-white p-5 dark:border-sky-400/20 dark:bg-none dark:bg-white/5"
            >
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div class="font-semibold text-[#1F4E79] dark:text-sky-200">💰 Отчёт ценности — что бот принёс в деньгах</div>
                    <a
                        v-if="activeReport"
                        :href="valueExportUrl(activeReport.crmConnectionId)"
                        class="rounded-xl border border-slate-200 bg-white/60 px-3 py-1.5 text-sm font-medium text-[#1F4E79] transition hover:-translate-y-0.5 dark:border-white/10 dark:bg-white/5 dark:text-sky-300"
                    >⬇ Записи CSV</a>
                </div>

                <!-- Несколько CRM — вкладки; одна — просто подпись. -->
                <div v-if="valueReports.length > 1" class="mb-3 flex flex-wrap gap-2">
                    <button
                        v-for="(r, i) in valueReports"
                        :key="r.crmConnectionId"
                        type="button"
                        class="rounded-lg px-3 py-1 text-sm font-medium transition"
                        :class="i === activeCrm ? 'bg-[#2E74B5] text-white shadow' : 'bg-white/60 text-slate-500 hover:text-[#1F4E79] dark:bg-white/5 dark:text-slate-300'"
                        @click="activeCrm = i"
                    >
                        {{ r.crmLabel }}
                    </button>
                </div>
                <div v-else class="mb-3 text-xs text-slate-500 dark:text-slate-400">{{ activeReport?.crmLabel }}</div>

                <template v-if="activeReport">
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                        <div
                            v-for="k in activeReport.kpis"
                            :key="k.key"
                            class="rounded-2xl border border-slate-200 bg-white p-4 transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-slate-100 dark:border-white/10 dark:bg-white/5 dark:hover:shadow-none"
                            :title="k.hint"
                        >
                            <div class="text-xs font-medium text-slate-500">{{ k.label }}</div>
                            <div class="mt-1 text-2xl font-bold text-[#1F4E79] dark:text-sky-200">
                                <CountUp :value="k.value" :suffix="k.unit" />
                            </div>
                            <div class="mt-1 text-xs" :class="deltaClass(k)">{{ deltaText(k) }}</div>
                        </div>
                    </div>

                    <p v-if="activeReport.note" class="mt-2 text-xs text-amber-600 dark:text-amber-400">⚠️ {{ activeReport.note }}</p>

                    <div v-if="activeReport.topServices.length > 0" class="mt-4">
                        <div class="mb-2 text-sm font-semibold text-[#1F4E79] dark:text-sky-200">Топ услуг по выручке</div>
                        <div class="space-y-1.5">
                            <div
                                v-for="s in activeReport.topServices"
                                :key="s.title"
                                class="flex items-center justify-between rounded-lg bg-white/70 px-3 py-1.5 text-sm dark:bg-white/5"
                            >
                                <span class="text-slate-700 dark:text-slate-200">{{ s.title }}</span>
                                <span class="text-slate-500 dark:text-slate-400">{{ s.bookings }} зап. · <b class="text-[#1F4E79] dark:text-sky-200">{{ fmtMoney(s.revenue) }} ₽</b></span>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- KPI -->
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                <div
                    v-for="k in analytics.kpis"
                    :key="k.key"
                    class="rounded-2xl border border-slate-200 bg-white p-4 transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-slate-100 dark:border-white/10 dark:bg-white/5 dark:hover:shadow-none"
                    :title="k.hint"
                >
                    <div class="text-xs font-medium text-slate-500">{{ k.label }}</div>
                    <div class="mt-1 text-2xl font-bold text-[#1F4E79] dark:text-sky-200">
                        <CountUp :value="k.value" :decimals="kpiDecimals(k)" :suffix="k.unit" />
                    </div>
                    <div class="mt-1 text-xs" :class="deltaClass(k)">{{ deltaText(k) }}</div>
                </div>
            </div>

            <!-- Динамика по дням -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <div class="mb-3 font-semibold text-[#1F4E79] dark:text-sky-200">Новые лиды по дням</div>
                <div class="text-[#2E74B5] dark:text-sky-400">
                    <AreaChart :points="analytics.daily" />
                </div>
            </div>

            <!-- Разбивки -->
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <div class="mb-3 font-semibold text-[#1F4E79] dark:text-sky-200">Источники</div>
                    <DonutChart :slices="analytics.byChannel" center-label="лидов" :center-value="analytics.totals.leads" />
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <div class="mb-3 font-semibold text-[#1F4E79] dark:text-sky-200">Сделки по стадиям</div>
                    <DonutChart v-if="analytics.byStage.length" :slices="analytics.byStage" center-label="сделок" :center-value="analytics.totals.deals" />
                    <p v-else class="py-10 text-center text-sm text-slate-400">Сделок за период нет — воронка появится, когда бот доведёт лиды до сделок.</p>
                </div>
            </div>

            <!-- Покрытие 24/7 + глубина диалога -->
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <div class="font-semibold text-[#1F4E79] dark:text-sky-200">🌙 Покрытие 24/7</div>
                    <p class="mb-3 mt-0.5 text-xs text-slate-400">{{ afterHoursCaption }}</p>
                    <DonutChart :slices="analytics.byDaypart" center-label="обращений" :center-value="analytics.totals.leads" />
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <div class="font-semibold text-[#1F4E79] dark:text-sky-200">💬 Глубина диалога</div>
                    <p class="mb-3 mt-0.5 text-xs text-slate-400">Сколько сообщений клиенты пишут боту — выше столбики справа значат более вовлечённые диалоги.</p>
                    <BarChart :bars="analytics.engagement" :height="150" />
                </div>
            </div>

            <!-- Воронка + дни недели -->
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <div class="mb-4 font-semibold text-[#1F4E79] dark:text-sky-200">Воронка лида</div>
                    <div class="space-y-3">
                        <div v-for="(s, i) in analytics.funnel" :key="s.key">
                            <div class="mb-1 flex justify-between text-sm">
                                <span class="text-slate-600 dark:text-slate-300">{{ s.label }}</span>
                                <span class="font-semibold text-[#1F4E79] dark:text-sky-200">{{ s.value }} · {{ s.pct }}%</span>
                            </div>
                            <div class="h-3 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10">
                                <div class="funnel-bar h-full rounded-full bg-gradient-to-r from-[#2E74B5] to-[#1F4E79]" :style="{ width: `${Math.max(s.pct, 2)}%`, animationDelay: `${i * 120}ms` }" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <div class="mb-3 font-semibold text-[#1F4E79] dark:text-sky-200">По дням недели</div>
                    <BarChart :bars="weekBars" />
                </div>
            </div>

            <!-- По времени суток -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <div class="mb-3 font-semibold text-[#1F4E79] dark:text-sky-200">По времени суток</div>
                <BarChart :bars="hourBars" :label-step="3" />
            </div>

            <!-- ИИ-разбор «чего не хватает» — премиум (Макс+) -->
            <div v-if="aiInsights" class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                    <div class="font-semibold text-[#1F4E79] dark:text-sky-200">Чего и где не хватает</div>
                    <div class="flex items-center gap-2 text-xs text-slate-400">
                        <span class="rounded-full bg-[#EAF2FB] px-2 py-0.5 text-[#2E74B5] dark:bg-white/10 dark:text-sky-300">
                            {{ insightSource }}<template v-if="insights"> · {{ insights.generatedAt }}</template>
                        </span>
                        <button
                            type="button"
                            class="rounded-lg border border-slate-200 bg-white/60 px-3 py-1.5 font-medium text-[#1F4E79] transition hover:-translate-y-0.5 disabled:opacity-60 dark:border-white/10 dark:bg-white/5 dark:text-sky-300"
                            :disabled="refreshing"
                            @click="refreshInsights"
                        >
                            {{ refreshing ? 'Обновляю…' : '✨ Обновить разбор' }}
                        </button>
                    </div>
                </div>
                <p v-if="!insights" class="mb-3 text-xs text-slate-400">ИИ-разбор готовится в фоне — пока показан базовый. Можно обновить кнопкой.</p>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div v-for="(g, i) in gapItems" :key="i" class="rounded-xl border p-4" :class="gapClass(g.severity)">
                        <div class="flex items-start gap-2">
                            <span class="text-lg leading-none">{{ gapIcon(g.severity) }}</span>
                            <div>
                                <div class="font-semibold text-slate-800 dark:text-slate-100">{{ g.title }}</div>
                                <div class="mt-0.5 text-sm text-slate-600 dark:text-slate-300">{{ g.detail }}</div>
                                <div v-if="g.action" class="mt-1 text-sm font-medium text-[#1F4E79] dark:text-sky-300">→ {{ g.action }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Свежие лиды -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <div class="mb-3 flex items-center justify-between">
                    <div class="font-semibold text-[#1F4E79] dark:text-sky-200">Свежие лиды</div>
                    <Link href="/cabinet/conversations" class="text-sm text-[#2E74B5] hover:underline dark:text-sky-300">Все диалоги →</Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs text-slate-400">
                            <tr>
                                <th class="py-2 pr-3 font-medium">Клиент</th>
                                <th class="py-2 pr-3 font-medium">Источник</th>
                                <th class="py-2 pr-3 font-medium">Итог</th>
                                <th class="py-2 pr-3 text-center font-medium">Сообщений</th>
                                <th class="py-2 font-medium">Когда</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="r in analytics.recent"
                                :key="r.id"
                                class="cursor-pointer border-t border-slate-100 transition hover:bg-slate-50 dark:border-white/5 dark:hover:bg-white/5"
                                @click="router.get(`/cabinet/conversations/${r.id}`)"
                            >
                                <td class="py-2.5 pr-3">
                                    <div class="font-medium text-slate-700 dark:text-slate-200">{{ r.contact }}</div>
                                    <div v-if="r.phone" class="text-xs text-slate-400">{{ r.phone }}</div>
                                </td>
                                <td class="py-2.5 pr-3 text-slate-500">{{ r.channel }}</td>
                                <td class="py-2.5 pr-3">
                                    <span v-if="r.booked" class="rounded-full bg-green-100 px-2 py-0.5 text-xs text-green-700">✅ Запись</span>
                                    <span v-else-if="r.escalated" class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700">🙋 На человека</span>
                                    <span v-else class="text-xs text-slate-400">—</span>
                                </td>
                                <td class="py-2.5 pr-3 text-center text-slate-500">{{ r.messages }}</td>
                                <td class="py-2.5 text-slate-400">{{ r.createdAt }}</td>
                            </tr>
                            <tr v-if="analytics.recent.length === 0">
                                <td colspan="6" class="py-6 text-center text-slate-400">Пока нет лидов</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.funnel-bar {
    transform-origin: left;
    animation: funnel-grow 0.8s cubic-bezier(0.2, 0.7, 0.2, 1) both;
}
@keyframes funnel-grow {
    from {
        transform: scaleX(0);
    }
    to {
        transform: scaleX(1);
    }
}
@media (prefers-reduced-motion: reduce) {
    .funnel-bar {
        animation: none;
    }
}
</style>
