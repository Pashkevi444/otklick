<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import Logo from '@/Components/Logo.vue';
import Icon from '@/Components/Icon.vue';

interface Site {
    phone?: string | null;
    email?: string | null;
    telegram?: string | null;
    legalName?: string | null;
    inn?: string | null;
    ogrnip?: string | null;
    accessNote?: string | null;
}
defineProps<{ site: Site; loginUrl: string }>();

const page = usePage();
const isActive = (href: string): boolean => (href === '/' ? page.url === '/' : page.url.startsWith(href));

const mobileOpen = ref(false);
const year = new Date().getFullYear();

const navLinks = [
    { href: '/', label: 'Главная' },
    { href: '/vozmozhnosti', label: 'Возможности' },
    { href: '/tarify', label: 'Тарифы' },
    { href: '/contacts', label: 'Контакты' },
];

// ===== Анимации: появление, параллакс, 3D-тилт за курсором =====
const root = ref<HTMLElement | null>(null);
const armed = ref(false);
let revealObserver: IntersectionObserver | null = null;
let scrollRaf = 0;
let pointerRaf = 0;

const prefersReduced = (): boolean =>
    typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const onScroll = (): void => {
    if (scrollRaf) return;
    scrollRaf = requestAnimationFrame(() => {
        const max = document.documentElement.scrollHeight - window.innerHeight;
        const p = max > 0 ? Math.min(1, window.scrollY / max) : 0;
        root.value?.style.setProperty('--sp', p.toFixed(4));
        scrollRaf = 0;
    });
};

let lastX = 0;
let lastY = 0;
const onPointer = (e: PointerEvent): void => {
    lastX = (e.clientX / window.innerWidth - 0.5) * 2;
    lastY = (e.clientY / window.innerHeight - 0.5) * 2;
    if (pointerRaf) return;
    pointerRaf = requestAnimationFrame(() => {
        root.value?.style.setProperty('--mx', lastX.toFixed(3));
        root.value?.style.setProperty('--my', lastY.toFixed(3));
        pointerRaf = 0;
    });
};

onMounted(() => {
    armed.value = true;
    revealObserver = new IntersectionObserver(
        (entries) => entries.forEach((en) => {
            if (en.isIntersecting) {
                en.target.classList.add('reveal-in');
                revealObserver?.unobserve(en.target);
            }
        }),
        { threshold: 0.12 },
    );
    document.querySelectorAll('[data-reveal]').forEach((el) => revealObserver?.observe(el));
    window.setTimeout(() => document.querySelectorAll('[data-reveal]:not(.reveal-in)').forEach((el) => el.classList.add('reveal-in')), 2500);

    if (!prefersReduced()) {
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('pointermove', onPointer, { passive: true });
        onScroll();
    }
});

onBeforeUnmount(() => {
    revealObserver?.disconnect();
    window.removeEventListener('scroll', onScroll);
    window.removeEventListener('pointermove', onPointer);
});
</script>

<template>
    <div ref="root" class="mkt text-ink" :class="{ 'reveal-armed': armed }">
        <!-- Фон страницы + вращающееся аврора-кольцо (эстетика нового макета) -->
        <div class="bg-base"></div>
        <div class="aurora" aria-hidden="true"></div>

        <!-- Шапка: плавающая «пилюля» с блюром -->
        <header class="sticky top-0 z-40 px-3 sm:px-4">
            <div class="mx-auto mt-3 max-w-6xl">
                <div class="mkt-nav px-4 sm:px-6" :class="mobileOpen ? 'rounded-3xl' : 'rounded-full'">
                    <div class="flex h-14 items-center justify-between gap-4">
                        <Link href="/" class="text-ink"><Logo :size="30" class="text-[17px]" /></Link>
                        <nav class="hidden items-center gap-7 text-[15px] md:flex">
                            <Link
                                v-for="l in navLinks"
                                :key="l.href"
                                :href="l.href"
                                class="transition"
                                :class="isActive(l.href) ? 'font-bold text-ink' : 'font-medium text-muted hover:text-ink'"
                            >
                                {{ l.label }}
                            </Link>
                        </nav>
                        <div class="flex items-center gap-2 sm:gap-3">
                            <ThemeToggle />
                            <a :href="loginUrl" class="hidden px-1 text-[15px] font-semibold text-ink transition hover:text-brand md:inline-block">Войти</a>
                            <Link href="/tarify" class="hidden rounded-full bg-gradient-to-r from-[#2B5CE0] to-[#5B62F0] px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-[#2B5CE0]/30 transition hover:-translate-y-0.5 sm:inline-block">Попробовать</Link>
                            <button
                                type="button"
                                class="flex h-10 w-10 items-center justify-center rounded-full border border-line bg-panel text-ink md:hidden"
                                :aria-label="mobileOpen ? 'Закрыть меню' : 'Открыть меню'"
                                @click="mobileOpen = !mobileOpen"
                            >
                                <Icon :name="mobileOpen ? 'close' : 'menu'" class="h-5 w-5" />
                            </button>
                        </div>
                    </div>
                    <nav v-if="mobileOpen" class="flex flex-col gap-1 border-t border-line py-3 text-sm md:hidden">
                        <Link
                            v-for="l in navLinks"
                            :key="l.href"
                            :href="l.href"
                            class="rounded-xl px-3 py-2.5 font-semibold text-ink transition hover:bg-hoverbg"
                            @click="mobileOpen = false"
                        >
                            {{ l.label }}
                        </Link>
                        <a :href="loginUrl" class="mt-1 rounded-full bg-[#2B5CE0] px-3 py-2.5 text-center font-bold text-white">Войти</a>
                    </nav>
                </div>
            </div>
        </header>

        <slot />

        <!-- Футер: светлая тема — белая панель, тёмная — deep-нави -->
        <footer class="mkt-footer mt-16">
            <div class="mx-auto max-w-6xl px-6 pb-10 pt-14">
                <div class="flex flex-col justify-between gap-10 border-b border-line pb-10 sm:flex-row dark:border-white/10">
                    <div class="max-w-xs">
                        <Logo :size="30" class="text-[17px] text-ink dark:text-white" />
                        <p class="mt-3.5 text-sm leading-relaxed text-muted dark:text-white/60">AI-администратор для локального бизнеса: ответы клиентам и запись в CRM круглосуточно.</p>
                    </div>
                    <div class="flex max-w-xl flex-wrap content-start items-start gap-x-6 gap-y-2.5 text-sm">
                        <Link v-for="l in navLinks" :key="l.href" :href="l.href" class="text-muted transition hover:text-ink dark:text-white/70 dark:hover:text-white">{{ l.label }}</Link>
                        <Link href="/privacy" class="text-muted transition hover:text-ink dark:text-white/70 dark:hover:text-white">Конфиденциальность</Link>
                        <Link href="/offer" class="text-muted transition hover:text-ink dark:text-white/70 dark:hover:text-white">Оферта</Link>
                        <Link href="/terms" class="text-muted transition hover:text-ink dark:text-white/70 dark:hover:text-white">Соглашение</Link>
                        <Link href="/consent" class="text-muted transition hover:text-ink dark:text-white/70 dark:hover:text-white">Согласие на ПДн</Link>
                        <a :href="loginUrl" class="text-muted transition hover:text-ink dark:text-white/70 dark:hover:text-white">Вход</a>
                    </div>
                </div>
                <div class="pt-6 text-xs leading-relaxed text-muted2 dark:text-white/45">
                    <span v-if="site.legalName">{{ site.legalName }}</span>
                    <span v-if="site.inn"> · ИНН {{ site.inn }}</span>
                    <span v-if="site.ogrnip"> · ОГРНИП {{ site.ogrnip }}</span>
                    <div class="mt-1">© {{ year }} «Отклик». Все права защищены.</div>
                </div>
            </div>
        </footer>
    </div>
</template>
