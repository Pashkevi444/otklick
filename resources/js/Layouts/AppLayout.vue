<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import Logo from '@/Components/Logo.vue';

defineProps<{ title?: string }>();

const mobileOpen = ref(false);
const page = usePage();
const user = computed(() => page.props.auth.user);

interface NavItem {
    label: string;
    href: string;
    badge?: number; // непрочитанные анонсы — подсветка пункта меню
}

// Непрочитанные новости тенанта (для бейджа в меню).
const unread = computed<{ news: number }>(
    () => (page.props.announcementsUnread as { news: number } | null) ?? { news: 0 },
);

const navItems = computed<NavItem[]>(() => {
    if (user.value?.role === 'super_admin') {
        // У СУ в меню только «Дашборд» — дальше навигация через плашки дашборда.
        return [{ label: 'Дашборд', href: '/admin' }];
    }

    // Меню короткое: всё по разделам — на дашборде. Здесь только ключевое.
    const items: NavItem[] = [{ label: 'Дашборд', href: '/cabinet' }];

    items.push(
        { label: 'Новости', href: '/cabinet/news', badge: unread.value.news },
        { label: 'Подписка', href: '/cabinet/subscription' },
        { label: 'Оплата', href: '/cabinet/billing' },
    );

    return items;
});

// Внешняя ссылка на трекер ошибок (GlitchTip/Sentry) — только супер-админу,
// если задан ERROR_TRACKING_URL. Открывается в новой вкладке.
const errorTrackingUrl = computed<string | null>(() => (page.props.errorTrackingUrl as string | null) ?? null);

// Куда ведёт логотип «Отклик»: супер-админа — в список бизнесов, владельца — на
// карточку бизнеса (домашняя страница кабинета).
const homeHref = computed<string>(() =>
    user.value?.role === 'super_admin' ? '/admin' : '/cabinet/overview',
);

const isActive = (href: string): boolean =>
    page.url === href || (href !== '/cabinet' && page.url.startsWith(href));

const logout = (): void => {
    router.post('/logout');
};
</script>

<template>
    <div class="relative min-h-screen overflow-x-clip text-slate-800 dark:text-slate-200">
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

        <div class="bg-base"></div>
        <div class="orbs" aria-hidden="true">
            <span class="orb orb-1"></span>
            <span class="orb orb-2"></span>
            <span class="orb orb-3"></span>
        </div>

        <!-- Шапка -->
        <header class="sticky top-0 z-30">
            <div class="mx-auto mt-3 max-w-6xl px-4">
                <div class="glass rounded-2xl px-4">
                    <div class="flex h-14 items-center justify-between gap-4">
                        <div class="flex min-w-0 items-center gap-5">
                            <Link :href="homeHref" class="transition hover:opacity-80">
                                <Logo class="text-[#1F4E79] dark:text-white" />
                            </Link>
                            <nav class="hidden items-center gap-1 md:flex">
                                <Link
                                    v-for="item in navItems"
                                    :key="item.href"
                                    :href="item.href"
                                    class="flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-sm font-medium transition"
                                    :class="isActive(item.href)
                                        ? 'bg-white/70 text-[#1F4E79] shadow-sm dark:bg-white/15 dark:text-white'
                                        : 'text-slate-600 hover:bg-white/50 dark:text-slate-300 dark:hover:bg-white/10'"
                                >
                                    {{ item.label }}
                                    <span v-if="item.badge" class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">{{ item.badge }}</span>
                                </Link>
                                <a
                                    v-if="errorTrackingUrl"
                                    :href="errorTrackingUrl"
                                    target="_blank"
                                    rel="noopener"
                                    class="flex items-center gap-1 rounded-xl px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-white/50 dark:text-slate-300 dark:hover:bg-white/10"
                                    title="Трекер ошибок (GlitchTip/Sentry)"
                                >
                                    Ошибки ↗
                                </a>
                            </nav>
                        </div>
                        <div class="flex flex-none items-center gap-2 text-sm sm:gap-3">
                            <ThemeToggle />
                            <Link
                                href="/account"
                                class="flex h-9 w-9 items-center justify-center rounded-xl border border-white/50 bg-white/40 text-lg text-[#1F4E79] transition hover:-translate-y-0.5 dark:border-white/10 dark:bg-white/10 dark:text-white"
                                aria-label="Настройки аккаунта"
                                title="Настройки аккаунта"
                            >
                                ⚙️
                            </Link>
                            <button
                                type="button"
                                class="hidden rounded-xl border border-white/50 bg-white/40 px-3 py-1.5 font-medium text-slate-600 transition hover:-translate-y-0.5 sm:block dark:border-white/10 dark:bg-white/10 dark:text-slate-300"
                                @click="logout"
                            >
                                Выйти
                            </button>
                            <button
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-xl border border-white/50 bg-white/40 text-lg text-[#1F4E79] md:hidden dark:border-white/10 dark:bg-white/10 dark:text-white"
                                :aria-label="mobileOpen ? 'Закрыть меню' : 'Открыть меню'"
                                @click="mobileOpen = !mobileOpen"
                            >
                                {{ mobileOpen ? '✕' : '☰' }}
                            </button>
                        </div>
                    </div>

                    <!-- Мобильное меню -->
                    <nav v-if="mobileOpen" class="flex flex-col gap-1 border-t border-white/40 py-3 text-sm md:hidden dark:border-white/10">
                        <Link
                            v-for="item in navItems"
                            :key="item.href"
                            :href="item.href"
                            class="flex items-center gap-1.5 rounded-lg px-3 py-2 font-medium transition"
                            :class="isActive(item.href)
                                ? 'bg-white/70 text-[#1F4E79] dark:bg-white/15 dark:text-white'
                                : 'text-slate-700 hover:bg-white/50 dark:text-slate-200 dark:hover:bg-white/10'"
                            @click="mobileOpen = false"
                        >
                            {{ item.label }}
                            <span v-if="item.badge" class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white">{{ item.badge }}</span>
                        </Link>
                        <Link href="/account" class="rounded-lg px-3 py-2 text-slate-700 hover:bg-white/50 dark:text-slate-200 dark:hover:bg-white/10" @click="mobileOpen = false">
                            ⚙️ Настройки аккаунта
                        </Link>
                        <button type="button" class="mt-1 rounded-lg bg-white/40 px-3 py-2 text-left font-medium text-slate-700 dark:bg-white/10 dark:text-slate-200" @click="logout">
                            Выйти
                        </button>
                    </nav>
                </div>
            </div>
        </header>

        <main class="ui-scope ui-fade-in mx-auto max-w-6xl px-6 py-8">
            <h1 v-if="title" class="mb-6 text-2xl font-bold text-[#1F4E79] dark:text-sky-200">{{ title }}</h1>
            <slot />
        </main>
    </div>
</template>

<style scoped>
.glass {
    background: rgba(255, 255, 255, 0.6);
    backdrop-filter: blur(18px) saturate(170%);
    -webkit-backdrop-filter: blur(18px) saturate(170%);
    border: 1px solid rgba(255, 255, 255, 0.6);
    box-shadow: 0 8px 32px rgba(31, 78, 121, 0.12);
}
html.dark .glass {
    background: rgba(20, 30, 48, 0.6);
    border-color: rgba(255, 255, 255, 0.1);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.45);
}

.bg-base {
    position: fixed;
    inset: 0;
    z-index: -2;
    pointer-events: none;
    background: linear-gradient(125deg, #eaf1fe 0%, #f6faff 45%, #e7f6ff 100%);
    background-size: 200% 200%;
    animation: bgpan 24s ease infinite;
}
html.dark .bg-base {
    background: linear-gradient(125deg, #0b1220 0%, #0e1828 45%, #0a1a26 100%);
    background-size: 200% 200%;
}

.orbs {
    position: fixed;
    inset: 0;
    z-index: -1;
    overflow: hidden;
    pointer-events: none;
}
.orb {
    position: absolute;
    border-radius: 9999px;
    filter: blur(80px);
    opacity: 0.4;
}
html.dark .orb {
    opacity: 0.25;
}
.orb-1 {
    width: 420px;
    height: 420px;
    background: #7cc0ff;
    top: -120px;
    left: -80px;
    animation: floaty 20s ease-in-out infinite;
}
.orb-2 {
    width: 340px;
    height: 340px;
    background: #b9a8ff;
    top: 20%;
    right: -90px;
    animation: floaty 26s ease-in-out infinite reverse;
}
.orb-3 {
    width: 300px;
    height: 300px;
    background: #7df3e1;
    bottom: 5%;
    left: 10%;
    animation: floaty 22s ease-in-out infinite;
}

@keyframes bgpan {
    0%,
    100% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
}
@keyframes floaty {
    0%,
    100% {
        transform: translate(0, 0) scale(1);
    }
    50% {
        transform: translate(24px, -30px) scale(1.06);
    }
}
@media (prefers-reduced-motion: reduce) {
    .bg-base,
    .orb {
        animation: none;
    }
}
</style>
