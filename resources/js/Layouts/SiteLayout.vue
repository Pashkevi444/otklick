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
    <div ref="root" class="mkt text-slate-800 dark:text-slate-200" :class="{ 'reveal-armed': armed }">
        <!-- Анимированный градиентный фон + мягкое аврора-свечение -->
        <div class="bg-base"></div>
        <div class="aurora" aria-hidden="true"></div>

        <!-- Анимированные 3D-роботы: параллакс при скролле + наклон за курсором.
             Мы продаём ИИ — фон это подчёркивает. На мобиле слой скрыт (CSS). -->
        <div class="scene3d" aria-hidden="true">
            <div v-for="n in 4" :key="n" class="obj3d robot3d" :class="`obj-${n}`">
                <span class="r-antenna"></span>
                <span class="r-head"><span class="r-eye"></span><span class="r-eye"></span></span>
                <span class="r-body"></span>
            </div>
        </div>

        <!-- Шапка -->
        <header class="sticky top-0 z-30">
            <div class="mx-auto mt-3 max-w-6xl px-4">
                <div class="glass rounded-2xl px-4 sm:px-5">
                    <div class="flex h-14 items-center justify-between">
                        <Link href="/"><Logo class="text-lg text-[#1F4E79] dark:text-white" /></Link>
                        <nav class="hidden items-center gap-7 text-sm md:flex">
                            <Link
                                v-for="l in navLinks"
                                :key="l.href"
                                :href="l.href"
                                class="transition"
                                :class="isActive(l.href) ? 'font-semibold text-[#1F4E79] dark:text-white' : 'text-slate-600 hover:text-[#1F4E79] dark:text-slate-300 dark:hover:text-white'"
                            >
                                {{ l.label }}
                            </Link>
                        </nav>
                        <div class="flex items-center gap-2">
                            <ThemeToggle />
                            <a :href="loginUrl" class="hidden rounded-xl bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white shadow-lg shadow-[#2E74B5]/25 transition hover:-translate-y-0.5 hover:bg-[#255f96] sm:inline-block">Войти</a>
                            <button
                                type="button"
                                class="flex h-9 w-9 items-center justify-center rounded-xl border border-white/50 bg-white/40 text-[#1F4E79] md:hidden dark:border-white/10 dark:bg-white/10 dark:text-white"
                                :aria-label="mobileOpen ? 'Закрыть меню' : 'Открыть меню'"
                                @click="mobileOpen = !mobileOpen"
                            >
                                <Icon :name="mobileOpen ? 'close' : 'menu'" class="h-5 w-5" />
                            </button>
                        </div>
                    </div>
                    <nav v-if="mobileOpen" class="flex flex-col gap-1 border-t border-white/40 py-3 text-sm md:hidden dark:border-white/10">
                        <Link
                            v-for="l in navLinks"
                            :key="l.href"
                            :href="l.href"
                            class="rounded-lg px-3 py-2 text-slate-700 transition hover:bg-white/50 dark:text-slate-200 dark:hover:bg-white/10"
                            @click="mobileOpen = false"
                        >
                            {{ l.label }}
                        </Link>
                        <a :href="loginUrl" class="mt-1 rounded-lg bg-[#2E74B5] px-3 py-2 text-center font-medium text-white">Войти</a>
                    </nav>
                </div>
            </div>
        </header>

        <slot />

        <!-- Футер -->
        <footer class="mx-auto max-w-6xl px-6 pb-10 pt-4">
            <div class="glass rounded-3xl px-6 py-8">
                <div class="flex flex-col justify-between gap-4 sm:flex-row">
                    <div>
                        <div class="font-bold text-[#1F4E79] dark:text-white">Отклик</div>
                        <p class="mt-1 max-w-sm text-sm text-slate-400 dark:text-slate-500">AI-администратор для локального бизнеса: ответы клиентам и запись в CRM круглосуточно.</p>
                    </div>
                    <div class="flex flex-wrap items-start gap-x-6 gap-y-2 text-sm">
                        <Link v-for="l in navLinks" :key="l.href" :href="l.href" class="text-slate-500 transition hover:text-[#1F4E79] dark:text-slate-400 dark:hover:text-white">{{ l.label }}</Link>
                        <Link href="/tarify" class="text-slate-500 transition hover:text-[#1F4E79] dark:text-slate-400 dark:hover:text-white">Тарифы</Link>
                        <Link href="/privacy" class="text-slate-500 transition hover:text-[#1F4E79] dark:text-slate-400 dark:hover:text-white">Конфиденциальность</Link>
                        <Link href="/offer" class="text-slate-500 transition hover:text-[#1F4E79] dark:text-slate-400 dark:hover:text-white">Оферта</Link>
                        <Link href="/terms" class="text-slate-500 transition hover:text-[#1F4E79] dark:text-slate-400 dark:hover:text-white">Соглашение</Link>
                        <Link href="/consent" class="text-slate-500 transition hover:text-[#1F4E79] dark:text-slate-400 dark:hover:text-white">Согласие на ПДн</Link>
                        <a :href="loginUrl" class="text-slate-500 transition hover:text-[#1F4E79] dark:text-slate-400 dark:hover:text-white">Вход</a>
                    </div>
                </div>
                <div class="mt-8 border-t border-white/50 pt-6 text-xs leading-relaxed text-slate-400 dark:border-white/10 dark:text-slate-500">
                    <span v-if="site.legalName">{{ site.legalName }}</span>
                    <span v-if="site.inn"> · ИНН {{ site.inn }}</span>
                    <span v-if="site.ogrnip"> · ОГРНИП {{ site.ogrnip }}</span>
                    <div class="mt-1">© {{ year }} «Отклик». Все права защищены.</div>
                </div>
            </div>
        </footer>
    </div>
</template>
