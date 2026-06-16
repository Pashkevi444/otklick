<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
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
    status: string;
    statusLabel: string;
    booked: boolean;
    messages: number;
    createdAt: string | null;
}
interface Analytics {
    period: { key: string; label: string; from: string; to: string };
    periods: { key: string; label: string }[];
    kpis: Kpi[];
    daily: { date: string; label: string; value: number }[];
    byChannel: Slice[];
    byStatus: Slice[];
    funnel: Stage[];
    hourly: { hour: number; value: number }[];
    weekday: { key: string; label: string; value: number }[];
    gaps: GapItem[];
    recent: RecentLead[];
    totals: Record<string, number>;
}

const props = defineProps<{ analytics: Analytics }>();

const page = usePage();
const tenantName = computed(() => page.props.auth.user?.tenant?.name ?? 'ваш бизнес');
const hasCrm = computed(() => page.props.auth.user?.tenant?.features?.crm ?? false);

const setPeriod = (key: string): void => {
    router.get('/cabinet', { period: key }, { preserveScroll: true, preserveState: true, only: ['analytics'] });
};

const exportUrl = (type: string): string =>
    `/cabinet/analytics/export/${type}?period=${props.analytics.period.key}`;

const hourBars = computed(() => props.analytics.hourly.map((h) => ({ label: String(h.hour), value: h.value })));
const weekBars = computed(() => props.analytics.weekday.map((w) => ({ label: w.label, value: w.value })));

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

const statusClass = (s: string): string =>
    s === 'needs_human'
        ? 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'
        : s === 'closed'
          ? 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-300'
          : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300';

interface Card {
    icon: string;
    label: string;
    text: string;
    href: string;
}
const cards = computed<Card[]>(() => {
    const list: Card[] = [
        { icon: '💬', label: 'Диалоги', text: 'Журнал переписок бота с клиентами', href: '/cabinet/conversations' },
        { icon: '📡', label: 'Каналы', text: 'Подключите Telegram-бота', href: '/cabinet/channels' },
        { icon: '🌐', label: 'Виджет на сайт', text: 'Чат с ботом для вашего сайта', href: '/cabinet/widget' },
        { icon: '🏢', label: 'Профиль бизнеса', text: 'Часы работы, контакты, эскалация', href: '/cabinet/profile' },
        { icon: '📚', label: 'База знаний', text: 'Тексты, по которым отвечает бот', href: '/cabinet/knowledge' },
    ];
    if (hasCrm.value) {
        list.push({ icon: '🔗', label: 'Интеграции', text: 'Подключение CRM и автозапись', href: '/cabinet/integrations' });
    }
    list.push({ icon: '⭐', label: 'Подписка', text: 'Ваш тариф и возможности', href: '/cabinet/subscription' });
    return list;
});
</script>

<template>
    <Head title="Дашборд" />

    <AppLayout title="Дашборд">
        <div class="mb-5 flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-[#1F4E79] dark:text-sky-200">Аналитика по лидам</h2>
                <p class="text-sm text-slate-500">
                    «{{ tenantName }}» · {{ analytics.period.from }} — {{ analytics.period.to }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex rounded-xl border border-slate-200 bg-white/60 p-1 dark:border-white/10 dark:bg-white/5">
                    <button
                        v-for="p in analytics.periods"
                        :key="p.key"
                        type="button"
                        class="rounded-lg px-3 py-1 text-sm font-medium transition"
                        :class="p.key === analytics.period.key
                            ? 'bg-[#2E74B5] text-white shadow'
                            : 'text-slate-500 hover:text-[#1F4E79] dark:text-slate-300'"
                        @click="setPeriod(p.key)"
                    >
                        {{ p.label }}
                    </button>
                </div>
                <a
                    :href="exportUrl('leads')"
                    class="rounded-xl border border-slate-200 bg-white/60 px-3 py-1.5 text-sm font-medium text-[#1F4E79] transition hover:-translate-y-0.5 dark:border-white/10 dark:bg-white/5 dark:text-sky-300"
                >
                    ⬇ Лиды CSV
                </a>
                <a
                    :href="exportUrl('daily')"
                    class="rounded-xl border border-slate-200 bg-white/60 px-3 py-1.5 text-sm font-medium text-[#1F4E79] transition hover:-translate-y-0.5 dark:border-white/10 dark:bg-white/5 dark:text-sky-300"
                >
                    ⬇ По дням CSV
                </a>
            </div>
        </div>

        <div :key="analytics.period.key" class="ui-fade-in space-y-5">
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

            <!-- Динамика + каналы -->
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 lg:col-span-2 dark:border-white/10 dark:bg-white/5">
                    <div class="mb-3 font-semibold text-[#1F4E79] dark:text-sky-200">Новые лиды по дням</div>
                    <div class="text-[#2E74B5] dark:text-sky-400">
                        <AreaChart :points="analytics.daily" />
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <div class="mb-3 font-semibold text-[#1F4E79] dark:text-sky-200">Источники</div>
                    <DonutChart :slices="analytics.byChannel" center-label="лидов" :center-value="analytics.totals.leads" />
                </div>
            </div>

            <!-- Воронка + статусы -->
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 lg:col-span-2 dark:border-white/10 dark:bg-white/5">
                    <div class="mb-4 font-semibold text-[#1F4E79] dark:text-sky-200">Воронка лида</div>
                    <div class="space-y-3">
                        <div v-for="(s, i) in analytics.funnel" :key="s.key">
                            <div class="mb-1 flex justify-between text-sm">
                                <span class="text-slate-600 dark:text-slate-300">{{ s.label }}</span>
                                <span class="font-semibold text-[#1F4E79] dark:text-sky-200">{{ s.value }} · {{ s.pct }}%</span>
                            </div>
                            <div class="h-3 overflow-hidden rounded-full bg-slate-100 dark:bg-white/10">
                                <div
                                    class="funnel-bar h-full rounded-full bg-gradient-to-r from-[#2E74B5] to-[#1F4E79]"
                                    :style="{ width: `${Math.max(s.pct, 2)}%`, animationDelay: `${i * 120}ms` }"
                                />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <div class="mb-3 font-semibold text-[#1F4E79] dark:text-sky-200">Статусы</div>
                    <DonutChart :slices="analytics.byStatus" center-label="диалогов" :center-value="analytics.totals.leads" />
                </div>
            </div>

            <!-- Когда пишут -->
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 lg:col-span-2 dark:border-white/10 dark:bg-white/5">
                    <div class="mb-3 font-semibold text-[#1F4E79] dark:text-sky-200">По времени суток</div>
                    <BarChart :bars="hourBars" :label-step="3" />
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                    <div class="mb-3 font-semibold text-[#1F4E79] dark:text-sky-200">По дням недели</div>
                    <BarChart :bars="weekBars" />
                </div>
            </div>

            <!-- Пробелы -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <div class="mb-3 font-semibold text-[#1F4E79] dark:text-sky-200">Чего и где не хватает</div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="(g, i) in analytics.gaps"
                        :key="i"
                        class="rounded-xl border p-4"
                        :class="gapClass(g.severity)"
                    >
                        <div class="flex items-start gap-2">
                            <span class="text-lg leading-none">{{ gapIcon(g.severity) }}</span>
                            <div>
                                <div class="font-semibold text-slate-800 dark:text-slate-100">{{ g.title }}</div>
                                <div class="mt-0.5 text-sm text-slate-600 dark:text-slate-300">{{ g.detail }}</div>
                                <div class="mt-1 text-sm font-medium text-[#1F4E79] dark:text-sky-300">→ {{ g.action }}</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Свежие лиды -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <div class="mb-3 flex items-center justify-between">
                    <div class="font-semibold text-[#1F4E79] dark:text-sky-200">Свежие лиды</div>
                    <Link href="/cabinet/conversations" class="text-sm text-[#2E74B5] hover:underline dark:text-sky-300">
                        Все диалоги →
                    </Link>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="text-left text-xs text-slate-400">
                            <tr>
                                <th class="py-2 pr-3 font-medium">Клиент</th>
                                <th class="py-2 pr-3 font-medium">Источник</th>
                                <th class="py-2 pr-3 font-medium">Статус</th>
                                <th class="py-2 pr-3 text-center font-medium">Сообщений</th>
                                <th class="py-2 pr-3 text-center font-medium">Запись</th>
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
                                    <span class="rounded-full px-2 py-0.5 text-xs" :class="statusClass(r.status)">{{ r.statusLabel }}</span>
                                </td>
                                <td class="py-2.5 pr-3 text-center text-slate-500">{{ r.messages }}</td>
                                <td class="py-2.5 pr-3 text-center">{{ r.booked ? '✅' : '—' }}</td>
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

        <!-- Разделы кабинета -->
        <h2 class="mt-8 mb-4 text-lg font-semibold text-[#1F4E79] dark:text-sky-200">Разделы</h2>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="card in cards"
                :key="card.href"
                :href="card.href"
                class="group block rounded-2xl border border-slate-200 bg-white p-5 transition hover:-translate-y-1 hover:border-[#2E74B5] hover:shadow-lg hover:shadow-slate-100 dark:border-white/10 dark:bg-white/5"
            >
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#EAF2FB] text-2xl transition group-hover:scale-110 dark:bg-white/10">
                    {{ card.icon }}
                </div>
                <div class="mt-4 font-semibold text-[#1F4E79] dark:text-sky-200">{{ card.label }}</div>
                <div class="mt-1 text-sm text-slate-500">{{ card.text }}</div>
            </Link>
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
