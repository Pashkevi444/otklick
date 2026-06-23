<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';

interface Stats {
    leadsToday: number;
    leadsWeek: number;
    bookedWeek: number;
    clients: number | null;
}
defineProps<{ stats: Stats | null }>();

const page = usePage();
const tenantName = computed(() => page.props.auth.user?.tenant?.name ?? 'ваш бизнес');
const features = computed(() => page.props.auth.user?.tenant?.features);
const isOwner = computed(() => page.props.auth.user?.isOwner ?? false);
const allowed = computed<string[]>(() => page.props.auth.user?.allowedSections ?? []);
const cardStates = computed<Record<string, string>>(() => (page.props.cardStates as Record<string, string>) ?? {});

type GroupKey = 'crm' | 'sales' | 'bot' | 'connect' | 'business';

interface Card {
    key: string;
    icon: string;
    label: string;
    text: string;
    href: string;
    group: GroupKey;
    section?: string;
    feature?: 'analytics' | 'crm' | 'clientBase' | 'broadcasts' | 'flows';
}
interface DecoratedCard extends Card {
    disabled: boolean;
    maintenance: boolean;
    planLocked: boolean;
    statusLabel: string | null;
    badge: string | null;
    to: string | null;
}

// Группы со своим акцентным цветом — глаз сразу различает зоны.
const groups: { key: GroupKey; title: string }[] = [
    { key: 'crm', title: 'CRM' },
    { key: 'sales', title: 'Клиенты и продажи' },
    { key: 'bot', title: 'Бот и автоматизация' },
    { key: 'connect', title: 'Подключения' },
    { key: 'business', title: 'Бизнес' },
];

const allCards: Card[] = [
    { key: 'leads', group: 'crm', icon: 'bolt', label: 'Лиды', text: 'Входящие обращения и конвертация в сделки', href: '/cabinet/leads', section: 'leads', feature: 'crm' },
    { key: 'deals', group: 'crm', icon: 'target', label: 'Сделки', text: 'Воронка продаж: канбан, стадии, ответственные', href: '/cabinet/deals', section: 'deals', feature: 'crm' },
    { key: 'conversations', group: 'sales', icon: 'chat', label: 'Диалоги', text: 'Переписка бота с клиентами по всем каналам', href: '/cabinet/conversations', section: 'conversations' },
    { key: 'clients', group: 'sales', icon: 'users', label: 'База клиентов', text: 'Карточки клиентов, история и краткое резюме', href: '/cabinet/clients', section: 'clients', feature: 'clientBase' },
    { key: 'analytics', group: 'sales', icon: 'chart', label: 'Аналитика', text: 'Лиды, конверсия и что улучшить', href: '/cabinet/analytics', section: 'analytics', feature: 'analytics' },
    { key: 'broadcasts', group: 'sales', icon: 'megaphone', label: 'Рассылки', text: 'Сообщения по базе клиентов: мессенджеры и почта', href: '/cabinet/broadcasts', section: 'broadcasts', feature: 'broadcasts' },

    { key: 'scenarios', group: 'bot', icon: 'wand', label: 'Сценарии', text: 'No-code воронки: «если клиент написал X → ответь Y»', href: '/cabinet/scenarios', section: 'scenarios', feature: 'flows' },
    { key: 'knowledge', group: 'bot', icon: 'book', label: 'База знаний', text: 'Тексты, по которым отвечает бот', href: '/cabinet/knowledge', section: 'knowledge' },
    { key: 'menu', group: 'bot', icon: 'menu', label: 'Главное меню бота', text: 'Кнопки-подсказки после приветствия', href: '/cabinet/menu', section: 'menu' },
    { key: 'testing', group: 'bot', icon: 'flask', label: 'Тестирование бота', text: 'Поговорите с ботом как клиент — без создания лидов', href: '/cabinet/testing', section: 'testing' },

    { key: 'channels', group: 'connect', icon: 'radio', label: 'Каналы', text: 'Telegram и другие каналы общения с клиентами', href: '/cabinet/channels', section: 'channels' },
    { key: 'widget', group: 'connect', icon: 'globe', label: 'Виджет на сайт', text: 'Чат с ботом для вашего сайта', href: '/cabinet/widget', section: 'widget' },
    { key: 'integrations', group: 'crm', icon: 'calendar', label: 'YClients', text: 'Запись клиентов в YClients', href: '/cabinet/integrations', section: 'integrations', feature: 'crm' },
    { key: 'notifications', group: 'connect', icon: 'bell', label: 'Уведомления', text: 'Лиды и записи на почту/в Telegram + ответ клиенту', href: '/cabinet/notifications', section: 'notifications' },

    { key: 'profile', group: 'business', icon: 'building', label: 'Профиль бизнеса', text: 'Часы работы, контакты, эскалация', href: '/cabinet/profile', section: 'profile' },
];

const decorate = (c: Card): DecoratedCard => {
    const state = cardStates.value[c.key];
    const maintenance = state === 'maintenance';
    const planLocked = !!(c.feature && !features.value?.[c.feature]);
    const disabled = maintenance || planLocked;

    return {
        ...c,
        disabled,
        maintenance,
        planLocked,
        statusLabel: maintenance ? 'Тех. работы' : planLocked ? 'Не в тарифе' : null,
        badge: !disabled && state === 'new' ? 'Новое' : !disabled && state === 'updated' ? 'Обновлено' : null,
        to: maintenance ? null : planLocked ? '/cabinet/subscription' : c.href,
    };
};

const decoratedAll = computed<DecoratedCard[]>(() => {
    const list: Card[] = allCards.filter((c) => !c.section || allowed.value.includes(c.section));
    if (isOwner.value) {
        list.push({ key: 'team', group: 'business', icon: 'users', label: 'Команда', text: 'Сотрудники и их доступы', href: '/cabinet/team' });
    }
    return list.map(decorate);
});

// Группы с карточками; внутри группы доступные — сверху, недоступные — вниз.
const grouped = computed(() =>
    groups
        .map((g) => ({
            ...g,
            items: [
                ...decoratedAll.value.filter((c) => c.group === g.key && !c.disabled),
                ...decoratedAll.value.filter((c) => c.group === g.key && c.disabled),
            ],
        }))
        .filter((g) => g.items.length > 0),
);

const accentTile: Record<GroupKey, string> = {
    crm: 'bg-indigo-500/12 text-indigo-600 dark:bg-indigo-400/15 dark:text-indigo-300',
    sales: 'bg-[#2E74B5]/12 text-[#2E74B5] dark:bg-sky-400/15 dark:text-sky-300',
    bot: 'bg-violet-500/12 text-violet-600 dark:bg-violet-400/15 dark:text-violet-300',
    connect: 'bg-emerald-500/12 text-emerald-600 dark:bg-emerald-400/15 dark:text-emerald-300',
    business: 'bg-amber-500/12 text-amber-600 dark:bg-amber-400/15 dark:text-amber-300',
};
const accentDot: Record<GroupKey, string> = {
    crm: 'bg-indigo-500',
    sales: 'bg-[#2E74B5]',
    bot: 'bg-violet-500',
    connect: 'bg-emerald-500',
    business: 'bg-amber-500',
};
const accentHover: Record<GroupKey, string> = {
    crm: 'hover:border-indigo-400/50',
    sales: 'hover:border-[#2E74B5]/50',
    bot: 'hover:border-violet-400/50',
    connect: 'hover:border-emerald-400/50',
    business: 'hover:border-amber-400/50',
};

const cardClass = (c: DecoratedCard): string =>
    c.disabled
        ? 'cursor-not-allowed opacity-60 grayscale'
        : `transition hover:-translate-y-1 hover:shadow-lg hover:shadow-slate-200/60 dark:hover:shadow-black/30 ${accentHover[c.group]}`;

const badgeClass = (label: string): string => {
    if (label === 'Новое') return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300';
    if (label === 'Обновлено') return 'bg-sky-100 text-sky-700 dark:bg-sky-400/15 dark:text-sky-300';
    if (label === 'Тех. работы') return 'bg-amber-100 text-amber-700 dark:bg-amber-400/15 dark:text-amber-300';
    return 'bg-slate-200 text-slate-600 dark:bg-white/10 dark:text-slate-300';
};
</script>

<template>
    <Head title="Дашборд" />

    <AppLayout title="Дашборд">
        <!-- Живой hero -->
        <div class="dash-hero relative mb-6 overflow-hidden rounded-3xl px-6 py-7 sm:px-8 sm:py-8">
            <span class="dash-orb dash-orb-1"></span>
            <span class="dash-orb dash-orb-2"></span>
            <span class="dash-orb dash-orb-3"></span>
            <span class="dash-grid"></span>
            <div class="relative">
                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-white/60">Панель управления</div>
                <h1 class="mt-1 text-2xl font-extrabold text-white sm:text-3xl">С возвращением 👋</h1>
                <p class="mt-1 text-sm text-white/80">Обзор по «{{ tenantName }}»</p>
            </div>
        </div>

        <!-- Мини-статы -->
        <div v-if="stats" class="mb-8 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="stat-card stat-sky">
                <div class="text-2xl font-extrabold">{{ stats.leadsToday }}</div>
                <div class="mt-0.5 text-xs opacity-70">Лидов сегодня</div>
            </div>
            <div class="stat-card stat-indigo">
                <div class="text-2xl font-extrabold">{{ stats.leadsWeek }}</div>
                <div class="mt-0.5 text-xs opacity-70">Лидов за 7 дней</div>
            </div>
            <div class="stat-card stat-emerald">
                <div class="text-2xl font-extrabold">{{ stats.bookedWeek }}</div>
                <div class="mt-0.5 text-xs opacity-70">Записей за неделю</div>
            </div>
            <div v-if="stats.clients !== null" class="stat-card stat-amber">
                <div class="text-2xl font-extrabold">{{ stats.clients }}</div>
                <div class="mt-0.5 text-xs opacity-70">Клиентов в базе</div>
            </div>
        </div>

        <!-- Группы разделов -->
        <div class="space-y-8">
            <section v-for="g in grouped" :key="g.key">
                <div class="mb-3 flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full" :class="accentDot[g.key]"></span>
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">{{ g.title }}</h2>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <component
                        :is="c.to ? Link : 'div'"
                        v-for="c in g.items"
                        :key="c.key"
                        :href="c.to ?? undefined"
                        class="group relative block rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5"
                        :class="cardClass(c)"
                    >
                        <span
                            v-if="c.statusLabel || c.badge"
                            class="absolute right-3 top-3 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                            :class="badgeClass(c.statusLabel ?? c.badge ?? '')"
                        >
                            {{ c.statusLabel ?? c.badge }}
                        </span>

                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl transition" :class="[accentTile[c.group], !c.disabled && 'group-hover:scale-110']">
                            <Icon :name="c.icon" class="h-6 w-6" />
                        </div>
                        <div class="mt-3.5 font-semibold text-[#1F4E79] dark:text-sky-200">{{ c.label }}</div>
                        <div class="mt-1 text-sm leading-snug text-slate-500 dark:text-slate-400">{{ c.text }}</div>
                        <div v-if="c.planLocked" class="mt-2 text-xs font-medium text-[#2E74B5]">Открыть в подписке →</div>
                    </component>
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Живой hero дашборда: переливающийся градиент + плавающие свечения + сетка. */
.dash-hero {
    background: linear-gradient(120deg, #2e74b5, #1f4e79 40%, #4f46e5 78%, #2e74b5);
    background-size: 240% 240%;
    animation: dashPan 16s ease infinite;
}
.dash-orb {
    position: absolute;
    border-radius: 9999px;
    filter: blur(28px);
    opacity: 0.5;
    pointer-events: none;
    will-change: transform;
}
.dash-orb-1 { width: 180px; height: 180px; background: #7cc0ff; top: -80px; left: 4%; animation: dashFloat 9s ease-in-out infinite; }
.dash-orb-2 { width: 150px; height: 150px; background: #a78bfa; top: -50px; right: 12%; animation: dashFloat 12s ease-in-out infinite reverse; }
.dash-orb-3 { width: 130px; height: 130px; background: #5eead4; bottom: -70px; left: 38%; animation: dashFloat 10s ease-in-out infinite; }
.dash-grid {
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255, 255, 255, 0.16) 1px, transparent 1.4px);
    background-size: 18px 18px;
    mask-image: linear-gradient(180deg, #000, transparent);
    -webkit-mask-image: linear-gradient(180deg, #000, transparent);
    pointer-events: none;
}
@keyframes dashPan {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}
@keyframes dashFloat {
    0%, 100% { transform: translate(0, 0); }
    50% { transform: translate(22px, 16px); }
}

/* Цветные мини-статы. */
.stat-card {
    position: relative;
    overflow: hidden;
    border-radius: 1rem;
    padding: 1rem 1.15rem;
    border: 1px solid rgba(148, 163, 184, 0.25);
    transition: transform 0.2s ease;
}
.stat-card:hover { transform: translateY(-2px); }
.stat-sky { background: linear-gradient(135deg, rgba(46, 116, 181, 0.14), rgba(46, 116, 181, 0.04)); color: #1f4e79; border-color: rgba(46, 116, 181, 0.25); }
.stat-indigo { background: linear-gradient(135deg, rgba(79, 70, 229, 0.14), rgba(79, 70, 229, 0.04)); color: #4338ca; border-color: rgba(79, 70, 229, 0.25); }
.stat-emerald { background: linear-gradient(135deg, rgba(16, 185, 129, 0.16), rgba(16, 185, 129, 0.04)); color: #047857; border-color: rgba(16, 185, 129, 0.25); }
.stat-amber { background: linear-gradient(135deg, rgba(245, 158, 11, 0.16), rgba(245, 158, 11, 0.05)); color: #b45309; border-color: rgba(245, 158, 11, 0.25); }
.dark .stat-sky { color: #7cc0ff; }
.dark .stat-indigo { color: #c7d2fe; }
.dark .stat-emerald { color: #6ee7b7; }
.dark .stat-amber { color: #fcd34d; }

@media (prefers-reduced-motion: reduce) {
    .dash-hero, .dash-orb { animation: none; }
}
</style>
