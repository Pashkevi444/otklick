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
// Непрочитанные уведомления по разделам-плашкам (диалоги/база знаний/клиенты).
const notifySections = computed<Record<string, number>>(
    () => (page.props.notifications as { sections?: Record<string, number> } | null)?.sections ?? {},
);

type GroupKey = 'sales' | 'bot' | 'connect' | 'business';

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
    notifyCount: number;
    to: string | null;
}

// Группы со своим акцентным цветом — глаз сразу различает зоны.
const groups: { key: GroupKey; title: string }[] = [
    { key: 'sales', title: 'Клиенты и продажи' },
    { key: 'bot', title: 'Бот и автоматизация' },
    { key: 'connect', title: 'Подключения' },
    { key: 'business', title: 'Бизнес' },
];

const allCards: Card[] = [
    { key: 'conversations', group: 'sales', icon: 'chat', label: 'Лиды', text: 'Обращения клиентов и переписка бота', href: '/cabinet/conversations', section: 'conversations' },
    { key: 'clients', group: 'sales', icon: 'users', label: 'База клиентов', text: 'Карточки клиентов, история и краткое резюме', href: '/cabinet/clients', section: 'clients', feature: 'clientBase' },
    { key: 'analytics', group: 'sales', icon: 'chart', label: 'Аналитика', text: 'Лиды, конверсия и что улучшить', href: '/cabinet/analytics', section: 'analytics', feature: 'analytics' },
    { key: 'broadcasts', group: 'sales', icon: 'megaphone', label: 'Рассылки', text: 'Сообщения по базе клиентов: мессенджеры и почта', href: '/cabinet/broadcasts', section: 'broadcasts', feature: 'broadcasts' },

    { key: 'scenarios', group: 'bot', icon: 'wand', label: 'Сценарии', text: 'No-code воронки: «если клиент написал X → ответь Y»', href: '/cabinet/scenarios', section: 'scenarios', feature: 'flows' },
    { key: 'knowledge', group: 'bot', icon: 'book', label: 'База знаний', text: 'Тексты, по которым отвечает бот', href: '/cabinet/knowledge', section: 'knowledge' },
    { key: 'menu', group: 'bot', icon: 'menu', label: 'Главное меню бота', text: 'Кнопки-подсказки после приветствия', href: '/cabinet/menu', section: 'menu' },
    { key: 'testing', group: 'bot', icon: 'flask', label: 'Тестирование бота', text: 'Поговорите с ботом как клиент — без создания лидов', href: '/cabinet/testing', section: 'testing' },

    { key: 'channels', group: 'connect', icon: 'radio', label: 'Каналы', text: 'Telegram и другие каналы общения с клиентами', href: '/cabinet/channels', section: 'channels' },
    { key: 'widget', group: 'connect', icon: 'globe', label: 'Виджет на сайт', text: 'Чат с ботом для вашего сайта', href: '/cabinet/widget', section: 'widget' },
    { key: 'integrations', group: 'connect', icon: 'calendar', label: 'YClients', text: 'Запись клиентов в YClients', href: '/cabinet/integrations', section: 'integrations', feature: 'crm' },
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
        notifyCount: disabled ? 0 : (notifySections.value[c.key] ?? 0),
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
    sales: 'bg-active text-brand',
    bot: 'bg-violet-500/12 text-violet-600 dark:text-violet-300',
    connect: 'bg-emerald-500/12 text-emerald-600 dark:text-emerald-400',
    business: 'bg-[#EE8A5C]/15 text-warm',
};
const accentDot: Record<GroupKey, string> = {
    sales: 'bg-brand',
    bot: 'bg-violet-brand',
    connect: 'bg-emerald-500',
    business: 'bg-warm',
};
const accentHover: Record<GroupKey, string> = {
    sales: 'hover:border-brand/40',
    bot: 'hover:border-violet-brand/40',
    connect: 'hover:border-emerald-500/40',
    business: 'hover:border-warm/40',
};

const cardClass = (c: DecoratedCard): string =>
    c.disabled
        ? 'cursor-not-allowed opacity-60 grayscale'
        : `transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-[rgba(16,28,51,0.06)] ${accentHover[c.group]}`;

const badgeClass = (label: string): string => {
    if (label === 'Новое') return 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400';
    if (label === 'Обновлено') return 'bg-active text-brand';
    if (label === 'Тех. работы') return 'bg-[#EE8A5C]/15 text-warm';
    return 'bg-chip text-muted';
};
</script>

<template>
    <Head title="Дашборд" />

    <AppLayout title="Дашборд">
        <!-- Герой-баннер: градиент бренд→фиолет, мягкие орбы + точечная сетка -->
        <div
            class="relative mb-7 overflow-hidden rounded-3xl p-7 text-white sm:p-9"
            style="background: linear-gradient(135deg, #2b5ce0 0%, #4d6ef0 45%, #7c5cfc 100%)"
        >
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -top-40 -right-10 h-[420px] w-[420px] rounded-full"
                style="background: radial-gradient(circle, rgba(255, 255, 255, 0.22), transparent 66%)"
            ></div>
            <div
                aria-hidden="true"
                class="pointer-events-none absolute -bottom-56 right-1/4 h-[360px] w-[360px] rounded-full"
                style="background: radial-gradient(circle, rgba(238, 138, 92, 0.4), transparent 64%)"
            ></div>
            <div
                aria-hidden="true"
                class="pointer-events-none absolute inset-0"
                style="
                    background-image: radial-gradient(rgba(255, 255, 255, 0.16) 1px, transparent 1.4px);
                    background-size: 18px 18px;
                    -webkit-mask-image: linear-gradient(180deg, #000, transparent);
                    mask-image: linear-gradient(180deg, #000, transparent);
                "
            ></div>
            <div class="relative">
                <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-white/70">Панель управления</div>
                <h1 class="mt-2 font-display text-3xl font-semibold">С возвращением 👋</h1>
                <p class="mt-2 text-sm text-white/85 sm:text-base">Обзор по «{{ tenantName }}» за сегодня.</p>
            </div>
        </div>

        <!-- Мини-статы -->
        <div v-if="stats" class="mb-8 grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
            <div class="otk-card p-5">
                <div class="text-xs font-semibold text-muted">Лидов сегодня</div>
                <div class="mt-2 font-display text-3xl font-semibold text-ink">{{ stats.leadsToday }}</div>
            </div>
            <div class="otk-card p-5">
                <div class="text-xs font-semibold text-muted">Лидов за 7 дней</div>
                <div class="mt-2 font-display text-3xl font-semibold text-ink">{{ stats.leadsWeek }}</div>
            </div>
            <div class="otk-card p-5">
                <div class="text-xs font-semibold text-muted">Записей за неделю</div>
                <div class="mt-2 font-display text-3xl font-semibold text-ink">{{ stats.bookedWeek }}</div>
            </div>
            <div v-if="stats.clients !== null" class="otk-card p-5">
                <div class="text-xs font-semibold text-muted">Клиентов в базе</div>
                <div class="mt-2 font-display text-3xl font-semibold text-ink">{{ stats.clients }}</div>
            </div>
        </div>

        <!-- Группы разделов -->
        <div class="space-y-8">
            <section v-for="g in grouped" :key="g.key">
                <div class="mb-3 flex items-center gap-2.5">
                    <span class="h-2 w-2 rounded-full" :class="accentDot[g.key]"></span>
                    <h2 class="text-[11px] font-bold uppercase tracking-[0.09em] text-muted2">{{ g.title }}</h2>
                </div>
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <component
                        :is="c.to ? Link : 'div'"
                        v-for="c in g.items"
                        :key="c.key"
                        :href="c.to ?? undefined"
                        class="group relative block rounded-2xl border border-line bg-panel p-5"
                        :class="cardClass(c)"
                    >
                        <span
                            v-if="c.statusLabel || c.badge"
                            class="absolute right-3 top-3 rounded-full px-2.5 py-0.5 text-[11px] font-bold"
                            :class="badgeClass(c.statusLabel ?? c.badge ?? '')"
                        >
                            {{ c.statusLabel ?? c.badge }}
                        </span>

                        <div class="relative flex h-[42px] w-[42px] items-center justify-center rounded-xl transition" :class="[accentTile[c.group], !c.disabled && 'group-hover:scale-110']">
                            <Icon :name="c.icon" class="h-6 w-6" />
                            <span
                                v-if="c.notifyCount > 0"
                                class="absolute -right-1.5 -top-1.5 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-warm px-1 text-[11px] font-bold text-white shadow"
                            >{{ c.notifyCount > 99 ? '99+' : c.notifyCount }}</span>
                        </div>
                        <div class="mt-3.5 font-semibold text-ink">{{ c.label }}</div>
                        <div class="mt-1 text-sm leading-snug text-muted">{{ c.text }}</div>
                        <div v-if="c.planLocked" class="mt-2 text-xs font-semibold text-brand">Открыть в подписке →</div>
                    </component>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
