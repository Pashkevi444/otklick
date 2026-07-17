<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import NotificationBell from '@/Components/NotificationBell.vue';
import Icon from '@/Components/Icon.vue';
import { realtime, type ReverbConfig } from '@/echo';

defineProps<{ title?: string }>();

const mobileOpen = ref(false);
const page = usePage();
const user = computed(() => page.props.auth.user);
const isSuperAdmin = computed(() => user.value?.role === 'super_admin');

// Новости от супер-админа приходят живьём: публичный канал «announcements» → бейдж
// непрочитанных перезапрашивается без перезагрузки (только нужный shared-prop).
onMounted(() => {
    const echo = realtime((page.props.reverb as ReverbConfig | null) ?? null);
    if (echo) {
        echo.channel('announcements').listen('.announcement.published', () => {
            router.reload({ only: ['announcementsUnread'] });
        });
    }
});
onBeforeUnmount(() => {
    const echo = realtime((page.props.reverb as ReverbConfig | null) ?? null);
    if (echo) echo.leave('announcements');
});

// Смена страницы — закрыть мобильный сайдбар.
watch(
    () => page.url,
    () => {
        mobileOpen.value = false;
    },
);

interface NavItem {
    key: string;
    label: string;
    href: string;
    icon: string;
    section?: string; // право раздела (member-гейтинг)
    feature?: 'analytics' | 'crm' | 'clientBase' | 'broadcasts' | 'flows'; // гейт тарифа
    ownerOnly?: boolean;
}
interface NavGroup {
    title: string;
    items: NavItem[];
}
interface DecoratedItem extends NavItem {
    maintenance: boolean;
    stateChip: string | null; // «Новое» / «Обновлено» от СУ
    notifyCount: number; // непрочитанные по разделу
}

// Непрочитанные новости тенанта (для бейджа пункта «Новости»).
const unread = computed<{ news: number }>(
    () => (page.props.announcementsUnread as { news: number } | null) ?? { news: 0 },
);
const features = computed(() => user.value?.tenant?.features);
const isOwner = computed(() => user.value?.isOwner ?? false);
const allowed = computed<string[]>(() => user.value?.allowedSections ?? []);
const cardStates = computed<Record<string, string>>(() => (page.props.cardStates as Record<string, string>) ?? {});
const notifySections = computed<Record<string, number>>(
    () => (page.props.notifications as { sections?: Record<string, number> } | null)?.sections ?? {},
);

// Полное меню кабинета — группы как в макете. Гейтинг зеркалит плашки дашборда:
// нет права раздела или фичи тарифа → пункта нет; тех. работы от СУ → пункт гаснет.
const cabinetGroups: NavGroup[] = [
    {
        title: 'Обзор',
        items: [
            { key: 'dashboard', label: 'Дашборд', href: '/cabinet', icon: 'grid' },
            { key: 'conversations', label: 'Лиды', href: '/cabinet/conversations', icon: 'chat', section: 'conversations' },
            { key: 'clients', label: 'База клиентов', href: '/cabinet/clients', icon: 'users', section: 'clients', feature: 'clientBase' },
            { key: 'analytics', label: 'Аналитика', href: '/cabinet/analytics', icon: 'chart', section: 'analytics', feature: 'analytics' },
            { key: 'broadcasts', label: 'Рассылки', href: '/cabinet/broadcasts', icon: 'send', section: 'broadcasts', feature: 'broadcasts' },
        ],
    },
    {
        title: 'Бот и автоматизация',
        items: [
            { key: 'scenarios', label: 'Сценарии', href: '/cabinet/scenarios', icon: 'wand', section: 'scenarios', feature: 'flows' },
            { key: 'knowledge', label: 'База знаний', href: '/cabinet/knowledge', icon: 'book', section: 'knowledge' },
            { key: 'menu', label: 'Меню бота', href: '/cabinet/menu', icon: 'menu', section: 'menu' },
            { key: 'testing', label: 'Тест бота', href: '/cabinet/testing', icon: 'flask', section: 'testing' },
        ],
    },
    {
        title: 'Подключения',
        items: [
            { key: 'channels', label: 'Каналы', href: '/cabinet/channels', icon: 'radio', section: 'channels' },
            { key: 'widget', label: 'Виджет на сайт', href: '/cabinet/widget', icon: 'globe', section: 'widget' },
            { key: 'integrations', label: 'YClients', href: '/cabinet/integrations', icon: 'calendar', section: 'integrations', feature: 'crm' },
            { key: 'notifications', label: 'Уведомления', href: '/cabinet/notifications', icon: 'bell', section: 'notifications' },
        ],
    },
    {
        title: 'Аккаунт',
        items: [
            { key: 'subscription', label: 'Подписка', href: '/cabinet/subscription', icon: 'card' },
            { key: 'billing', label: 'Оплата', href: '/cabinet/billing', icon: 'report' },
            { key: 'team', label: 'Команда', href: '/cabinet/team', icon: 'users', ownerOnly: true },
            { key: 'news', label: 'Новости', href: '/cabinet/news', icon: 'news' },
            { key: 'overview', label: 'Карточка бизнеса', href: '/cabinet/overview', icon: 'building' },
            { key: 'profile', label: 'Настройки бизнеса', href: '/cabinet/profile', icon: 'gear', section: 'profile' },
        ],
    },
];

// Меню супер-админки — разделы платформы.
const adminGroups: NavGroup[] = [
    {
        title: 'Обзор',
        items: [
            { key: 'admin-dash', label: 'Дашборд', href: '/admin', icon: 'grid' },
            { key: 'admin-tenants', label: 'Бизнесы', href: '/admin/tenants', icon: 'briefcase' },
        ],
    },
    {
        title: 'Шаблоны',
        items: [
            { key: 'admin-scenarios', label: 'Сценарии', href: '/admin/scenario-templates', icon: 'wand' },
            { key: 'admin-knowledge', label: 'База знаний', href: '/admin/knowledge-templates', icon: 'book' },
            { key: 'admin-prompts', label: 'Промпты', href: '/admin/prompt-templates', icon: 'pen' },
        ],
    },
    {
        title: 'Платформа',
        items: [
            { key: 'admin-news', label: 'Новости', href: '/admin/news', icon: 'news' },
            { key: 'admin-cards', label: 'Плашки дашборда', href: '/admin/dashboard-cards', icon: 'template' },
            { key: 'admin-site', label: 'Настройки сайта', href: '/admin/site', icon: 'globe' },
        ],
    },
];

const navGroups = computed<{ title: string; items: DecoratedItem[] }[]>(() => {
    const source = isSuperAdmin.value ? adminGroups : cabinetGroups;

    return source
        .map((g) => ({
            title: g.title,
            items: g.items
                .filter((i) => {
                    if (i.ownerOnly && !isOwner.value) return false;
                    if (i.section && !allowed.value.includes(i.section)) return false;
                    if (i.feature && !features.value?.[i.feature]) return false;
                    return true;
                })
                .map((i) => ({
                    ...i,
                    maintenance: cardStates.value[i.key] === 'maintenance',
                    stateChip:
                        cardStates.value[i.key] === 'new'
                            ? 'Новое'
                            : cardStates.value[i.key] === 'updated'
                              ? 'Обновлено'
                              : null,
                    notifyCount: i.key === 'news' ? unread.value.news : (notifySections.value[i.key] ?? 0),
                })),
        }))
        .filter((g) => g.items.length > 0);
});

// Быстрый поиск по разделам (клиентский): фильтр пунктов меню, Enter — переход.
const search = ref('');
const searchFocused = ref(false);
const searchMatches = computed<DecoratedItem[]>(() => {
    const q = search.value.trim().toLowerCase();
    if (q === '') return [];
    return navGroups.value
        .flatMap((g) => g.items)
        .filter((i) => !i.maintenance && i.label.toLowerCase().includes(q))
        .slice(0, 6);
});
const goFirstMatch = (): void => {
    const first = searchMatches.value[0];
    if (first) {
        search.value = '';
        router.visit(first.href);
    }
};

// Внешняя ссылка на трекер ошибок (GlitchTip/Sentry) — только супер-админу.
const errorTrackingUrl = computed<string | null>(() => (page.props.errorTrackingUrl as string | null) ?? null);

const homeHref = computed<string>(() => (isSuperAdmin.value ? '/admin' : '/cabinet'));

// «Дашборд» (/cabinet, /admin) — только точное совпадение, иначе он «активен» везде.
const isActive = (href: string): boolean =>
    href === '/cabinet' || href === '/admin'
        ? page.url === href
        : page.url === href || page.url.startsWith(href + '/') || page.url.startsWith(href + '?');

// Инициалы для аватар-чипа внизу сайдбара.
const initials = computed<string>(() => {
    const name = (user.value?.name ?? '').trim();
    if (name === '') return '·';
    const parts = name.split(/\s+/);
    return ((parts[0]?.[0] ?? '') + (parts[1]?.[0] ?? '')).toUpperCase() || name[0].toUpperCase();
});
const chipTitle = computed<string>(() => user.value?.tenant?.name ?? user.value?.name ?? '');
const chipSubtitle = computed<string>(() =>
    isSuperAdmin.value ? 'супер-админ' : user.value?.tenant ? `Тариф «${user.value.tenant.plan}»` : '',
);

const logout = (): void => {
    router.post('/logout');
};
</script>

<template>
    <div class="app-shell flex min-h-screen bg-page text-ink">
        <!-- Скрим мобильного меню -->
        <div
            v-if="mobileOpen"
            class="fixed inset-0 z-40 bg-[#0a101e]/45 lg:hidden"
            aria-hidden="true"
            @click="mobileOpen = false"
        ></div>

        <!-- ===== Сайдбар ===== -->
        <aside
            class="fixed inset-y-0 left-0 z-50 flex w-[266px] flex-none flex-col border-r border-line bg-side px-4 py-5 transition-transform duration-300 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
            :class="mobileOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full lg:shadow-none'"
        >
            <Link :href="homeHref" class="flex items-center gap-3 px-2 pb-5 transition hover:opacity-80">
                <span class="inline-flex h-8 w-8 flex-none items-center justify-center rounded-[10px] bg-brand shadow-[0_6px_16px_rgba(43,92,224,0.32)]">
                    <span class="h-3 w-3 rounded-[999px_999px_999px_2px] bg-white"></span>
                </span>
                <span class="min-w-0">
                    <span class="block truncate font-display text-lg font-semibold tracking-tight">Отклик</span>
                    <span v-if="isSuperAdmin" class="block font-display text-[9px] font-semibold tracking-[0.22em] text-brand">СУПЕРАДМИН</span>
                </span>
            </Link>

            <nav class="no-scrollbar flex flex-1 flex-col gap-1 overflow-y-auto pr-0.5">
                <template v-for="group in navGroups" :key="group.title">
                    <div class="px-3 pb-1.5 pt-4 text-[11px] font-bold uppercase tracking-[0.09em] text-muted2">{{ group.title }}</div>
                    <template v-for="item in group.items" :key="item.key">
                        <!-- Тех. работы: пункт гаснет и не кликается -->
                        <span
                            v-if="item.maintenance"
                            class="flex cursor-not-allowed items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-muted2 opacity-60"
                        >
                            <Icon :name="item.icon" class="h-[18px] w-[18px] flex-none" />
                            <span class="truncate">{{ item.label }}</span>
                            <span class="ml-auto rounded-full bg-chip px-2 py-0.5 text-[10px] font-bold text-muted2">Тех. работы</span>
                        </span>
                        <Link
                            v-else
                            :href="item.href"
                            class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition"
                            :class="isActive(item.href) ? 'bg-active text-brand' : 'text-muted hover:bg-hoverbg hover:text-ink'"
                        >
                            <Icon :name="item.icon" class="h-[18px] w-[18px] flex-none" />
                            <span class="truncate">{{ item.label }}</span>
                            <span
                                v-if="item.notifyCount"
                                class="ml-auto rounded-full bg-warm px-1.5 py-px text-[11px] font-bold text-white"
                            >{{ item.notifyCount }}</span>
                            <span
                                v-else-if="item.stateChip"
                                class="ml-auto rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-bold text-emerald-600 dark:text-emerald-400"
                            >{{ item.stateChip }}</span>
                        </Link>
                    </template>
                </template>
                <a
                    v-if="errorTrackingUrl"
                    :href="errorTrackingUrl"
                    target="_blank"
                    rel="noopener"
                    class="mt-1 flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-muted transition hover:bg-hoverbg hover:text-ink"
                    title="Трекер ошибок (GlitchTip/Sentry)"
                >
                    <Icon name="shield" class="h-[18px] w-[18px] flex-none" />
                    Ошибки ↗
                </a>
            </nav>

            <!-- Низ сайдбара: аккаунт -->
            <Link
                href="/account"
                class="mt-2 flex items-center gap-3 border-t border-line pt-4 transition hover:opacity-80"
                title="Настройки аккаунта"
            >
                <span
                    class="inline-flex h-9 w-9 flex-none items-center justify-center rounded-full bg-gradient-to-br from-brand to-violet-brand text-sm font-bold text-white"
                >{{ initials }}</span>
                <span class="min-w-0 leading-tight">
                    <span class="block truncate text-sm font-bold">{{ chipTitle }}</span>
                    <span class="block truncate text-xs text-muted">{{ chipSubtitle }}</span>
                </span>
            </Link>
        </aside>

        <!-- ===== Правая колонка ===== -->
        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Баннер режима «супер-админ вошёл в кабинет бизнеса» -->
            <div
                v-if="page.props.impersonating"
                class="relative z-40 flex flex-wrap items-center justify-center gap-3 bg-amber-500 px-4 py-2 text-center text-sm font-medium text-white"
            >
                Вы в кабинете бизнеса от имени супер-админа.
                <button type="button" class="rounded-md bg-white/20 px-3 py-1 hover:bg-white/30" @click="router.post('/impersonate/leave')">
                    Выйти обратно в админку
                </button>
            </div>

            <!-- Топбар -->
            <header class="topbar sticky top-0 z-30 border-b border-line">
                <div class="flex h-[68px] items-center gap-3 px-4 sm:gap-4 sm:px-8">
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 flex-none items-center justify-center rounded-xl border border-line bg-panel text-ink lg:hidden"
                        :aria-label="mobileOpen ? 'Закрыть меню' : 'Открыть меню'"
                        @click="mobileOpen = !mobileOpen"
                    >
                        <Icon :name="mobileOpen ? 'close' : 'menu'" class="h-[18px] w-[18px]" />
                    </button>

                    <h1 v-if="title" class="min-w-0 truncate font-display text-lg font-semibold tracking-tight sm:text-xl">{{ title }}</h1>

                    <div class="ml-auto flex flex-none items-center gap-2.5 sm:gap-3.5">
                        <!-- Быстрый переход по разделам -->
                        <div class="relative hidden md:block">
                            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted2">
                                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.5-3.5"/></svg>
                            </span>
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Поиск по разделам…"
                                class="w-56 rounded-full border border-line bg-panel py-2 pl-9 pr-3.5 text-sm text-ink outline-none transition placeholder:text-muted2 focus:border-brand"
                                @focus="searchFocused = true"
                                @blur="searchFocused = false"
                                @keydown.enter.prevent="goFirstMatch"
                            />
                            <div
                                v-if="searchFocused && searchMatches.length"
                                class="absolute right-0 top-11 z-50 w-64 overflow-hidden rounded-2xl border border-line bg-panel py-1.5 shadow-xl"
                            >
                                <Link
                                    v-for="m in searchMatches"
                                    :key="m.key"
                                    :href="m.href"
                                    class="flex items-center gap-2.5 px-3.5 py-2 text-sm font-medium text-ink transition hover:bg-hoverbg"
                                    @mousedown.prevent="search = ''; router.visit(m.href)"
                                >
                                    <Icon :name="m.icon" class="h-4 w-4 flex-none text-muted" />
                                    {{ m.label }}
                                </Link>
                            </div>
                        </div>

                        <NotificationBell v-if="user && !isSuperAdmin" />
                        <ThemeToggle />
                        <button
                            type="button"
                            class="rounded-xl border border-line bg-panel px-3.5 py-2 text-sm font-semibold text-ink transition hover:bg-hoverbg"
                            @click="logout"
                        >
                            Выйти
                        </button>
                    </div>
                </div>
            </header>

            <main class="ui-scope ui-fade-in mx-auto w-full max-w-6xl flex-1 px-4 py-8 sm:px-6 lg:px-8">
                <slot />
            </main>
        </div>
    </div>
</template>

<style scoped>
/* Топбар: полупрозрачный фон приложения с блюром (как в макете) */
.topbar {
    background: color-mix(in srgb, var(--otk-bg) 82%, transparent);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
}
</style>
