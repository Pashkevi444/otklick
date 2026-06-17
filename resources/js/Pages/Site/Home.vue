<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import Logo from '@/Components/Logo.vue';

interface Site {
    heroTitle: string;
    heroSubtitle: string;
    phone: string | null;
    email: string | null;
    telegram: string | null;
    legalName: string | null;
    inn: string | null;
    ogrnip: string | null;
    accessNote: string;
}

const props = defineProps<{ site: Site; loginUrl: string }>();

const tgUrl = computed(() => (props.site.telegram ? `https://t.me/${props.site.telegram}` : null));

const mobileOpen = ref(false);

const navLinks = [
    { href: '#features', label: 'Возможности' },
    { href: '#how', label: 'Как работает' },
    { href: '#integrations', label: 'Интеграции' },
    { href: '#roadmap', label: 'Планы' },
    { href: '#pricing', label: 'Тарифы' },
    { href: '#reliability', label: 'Надёжность' },
    { href: '#contacts', label: 'Контакты' },
];

const metrics = ref([
    { target: 24, current: 0, prefix: '', suffix: '/7', label: 'на связи без выходных и перерывов' },
    { target: 2, current: 0, prefix: '<', suffix: ' сек', label: 'до первого ответа клиенту' },
    { target: 70, current: 0, prefix: '', suffix: '%', label: 'типовых вопросов берёт на себя' },
    { target: 100, current: 0, prefix: '', suffix: '%', label: 'диалогов сохраняется в кабинете' },
]);

const features = [
    { icon: '💬', title: 'Все каналы в одном окне', text: 'Telegram, ВКонтакте и виджет на сайте. Один помощник отвечает везде одинаково ровно.' },
    { icon: '🧠', title: 'Отвечает по вашему бизнесу', text: 'Цены, услуги, часы работы, условия — строго из вашей базы знаний, без фантазий и «я уточню».' },
    { icon: '📅', title: 'Записывает клиентов сам', text: 'Подбирает услугу и удобное время и оформляет запись прямо в вашей CRM (YClients).' },
    { icon: '🙋', title: 'Горячий клиент — менеджеру', text: 'Сложные и важные диалоги передаёт администратору с полной историей переписки.' },
    { icon: '⚙️', title: 'Запуск без программистов', text: 'Загрузите услуги и частые вопросы — помощник готов к работе за вечер.' },
    { icon: '🇷🇺', title: 'Данные остаются в России', text: 'Размещение на серверах РФ, соответствие 152-ФЗ и полная изоляция каждого бизнеса.' },
];

const steps = [
    { n: '01', title: 'Подключите канал', text: 'Telegram или ВКонтакте за пару минут — по токену. Виджет на сайт — одной строкой.' },
    { n: '02', title: 'Заполните базу знаний', text: 'Услуги, цены, частые вопросы, примеры работ — в удобном кабинете.' },
    { n: '03', title: 'Помощник работает за вас', text: 'Отвечает клиентам, записывает на услуги, а сложное передаёт администратору.' },
];

const integrationsNow = ['Telegram', 'ВКонтакте', 'Виджет на сайте', 'YClients'];

// Планы по внедрению инструментов бизнеса (ещё не в продакшене — честно отдельным блоком).
const roadmap = [
    { icon: '💚', title: 'WhatsApp', text: 'Ответы и запись в самом массовом мессенджере.' },
    { icon: '🟢', title: 'Avito', text: 'Обработка обращений с объявлений Авито.' },
    { icon: '📞', title: 'Телефония', text: 'Голосовой ассистент и пропущенные звонки.' },
    { icon: '📨', title: 'Рассылки и база клиентов', text: 'Возврат клиентов и акции — с согласием (152-ФЗ).' },
    { icon: '🔗', title: 'Другие CRM', text: 'Altegio, amoCRM, Bitrix24 — подключаем под бизнес.' },
];

const reliability = [
    { icon: '🔒', title: 'Безопасность', text: 'Шифрование канала (TLS), доступы под ролями, никаких данных за пределами РФ.' },
    { icon: '🧩', title: 'Изоляция бизнесов', text: 'Данные каждого клиента строго отделены на уровне приложения и базы данных.' },
    { icon: '📋', title: 'Соответствие 152-ФЗ', text: 'Хранение и обработка персональных данных в российской инфраструктуре.' },
    { icon: '🤝', title: 'Человек всегда рядом', text: 'Если вопрос вне компетенции — диалог мгновенно уходит живому администратору.' },
];

const pricing = [
    {
        name: 'Пробный',
        price: '0 ₽',
        period: '14 дней бесплатно',
        highlight: false,
        note: 'Возможности уровня «Стандарт», чтобы попробовать.',
        features: ['Все возможности «Стандарта»', 'Telegram, ВКонтакте и виджет на сайт', 'База знаний и AI-ответы 24/7', 'Без банковской карты'],
        cta: 'Попробовать',
    },
    {
        name: 'Стандарт',
        price: '9 900 ₽',
        period: 'в месяц',
        highlight: false,
        note: 'Для большинства локальных бизнесов.',
        features: ['Telegram, ВКонтакте и виджет на сайт', 'База знаний (фото, ссылки, цены)', 'AI-ответы 24/7 + передача администратору', 'До 2 операторов', 'Базовая статистика'],
        cta: 'Получить доступ',
    },
    {
        name: 'Макс',
        price: '14 900 ₽',
        period: 'в месяц',
        highlight: true,
        note: 'Всё включено и удвоенные лимиты.',
        features: ['Всё из «Стандарта»', 'CRM YClients: автозапись, отмена, напоминания клиентам', 'База знаний из CRM (услуги, цены, мастера)', 'Умный поиск по знаниям (RAG)', 'Расширенная аналитика и ИИ-рекомендации', 'До 10 операторов', 'Больше получателей уведомлений', 'Приоритетная поддержка'],
        cta: 'Получить доступ',
    },
    {
        name: 'Индивидуальный',
        price: 'по договорённости',
        period: 'индивидуально',
        highlight: false,
        note: 'Корпоративный: всё из «Макс» и кратно бо́льшие лимиты, по договору.',
        features: ['Всё из «Макс»', 'Кратно бо́льшие лимиты операторов и уведомлений', 'Приоритетное внедрение новых каналов', 'Индивидуальные доработки и SLA'],
        cta: 'Обсудить',
    },
];

const year = new Date().getFullYear();

const metricsEl = ref<HTMLElement | null>(null);
let revealObserver: IntersectionObserver | null = null;
let metricsObserver: IntersectionObserver | null = null;

const prefersReduced = (): boolean =>
    typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function runCountUp(): void {
    if (prefersReduced()) {
        metrics.value.forEach((m) => (m.current = m.target));
        return;
    }
    const duration = 1400;
    const start = performance.now();
    const tick = (now: number): void => {
        const p = Math.min(1, (now - start) / duration);
        const eased = 1 - Math.pow(1 - p, 3);
        metrics.value.forEach((m) => (m.current = Math.round(m.target * eased)));
        if (p < 1) {
            requestAnimationFrame(tick);
        }
    };
    requestAnimationFrame(tick);
}

onMounted(() => {
    revealObserver = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('reveal-in');
                    revealObserver?.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.12 },
    );
    document.querySelectorAll('[data-reveal]').forEach((el) => revealObserver?.observe(el));

    if (metricsEl.value) {
        metricsObserver = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        runCountUp();
                        metricsObserver?.disconnect();
                    }
                });
            },
            { threshold: 0.4 },
        );
        metricsObserver.observe(metricsEl.value);
    }
});

onBeforeUnmount(() => {
    revealObserver?.disconnect();
    metricsObserver?.disconnect();
});
</script>

<template>
    <Head>
        <title>Отклик — цифровой администратор для бизнеса: ответы в Telegram, ВКонтакте и на сайте, запись клиентов</title>
        <meta
            name="description"
            content="Отклик — AI-администратор для салонов, барбершопов, клиник и сервиса. Мгновенно отвечает клиентам в Telegram, ВКонтакте и на сайте по вашей базе знаний и записывает в CRM. Не теряйте заявки 24/7."
        />
        <meta name="keywords" content="AI-администратор, чат-бот для бизнеса, автоответы Telegram, бот ВКонтакте, виджет на сайт, запись клиентов, виртуальный помощник, бот для записи, YClients, 152-ФЗ" />
        <meta property="og:type" content="website" />
        <meta property="og:title" content="Отклик — цифровой администратор для локального бизнеса" />
        <meta
            property="og:description"
            content="Мгновенные ответы клиентам в Telegram, ВКонтакте и на сайте по вашей базе знаний и запись в CRM. Не теряйте ни одного обращения."
        />
    </Head>

    <div class="page relative min-h-screen overflow-x-clip text-slate-800 dark:text-slate-200">
        <!-- Анимированный фон + плавающие орбы -->
        <div class="bg-base"></div>
        <div class="orbs" aria-hidden="true">
            <span class="orb orb-1"></span>
            <span class="orb orb-2"></span>
            <span class="orb orb-3"></span>
            <span class="orb orb-4"></span>
        </div>

        <!-- Шапка -->
        <header class="sticky top-0 z-30">
            <div class="mx-auto mt-3 max-w-6xl px-4">
                <div class="glass rounded-2xl px-4 sm:px-5">
                    <div class="flex h-14 items-center justify-between">
                        <Logo class="text-lg text-[#1F4E79] dark:text-white" />
                        <nav class="hidden md:flex items-center gap-7 text-sm text-slate-600 dark:text-slate-300">
                            <a v-for="l in navLinks" :key="l.href" :href="l.href" class="transition hover:text-[#1F4E79] dark:hover:text-white">{{ l.label }}</a>
                        </nav>
                        <div class="flex items-center gap-2">
                            <ThemeToggle />
                            <a :href="loginUrl" class="hidden sm:inline-block rounded-xl bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white shadow-lg shadow-[#2E74B5]/25 transition hover:bg-[#255f96] hover:-translate-y-0.5">
                                Войти
                            </a>
                            <button
                                type="button"
                                class="md:hidden flex h-9 w-9 items-center justify-center rounded-xl border border-white/50 bg-white/40 text-lg text-[#1F4E79] dark:border-white/10 dark:bg-white/10 dark:text-white"
                                :aria-label="mobileOpen ? 'Закрыть меню' : 'Открыть меню'"
                                @click="mobileOpen = !mobileOpen"
                            >
                                {{ mobileOpen ? '✕' : '☰' }}
                            </button>
                        </div>
                    </div>

                    <!-- Мобильное меню -->
                    <nav v-if="mobileOpen" class="md:hidden flex flex-col gap-1 border-t border-white/40 py-3 text-sm dark:border-white/10">
                        <a
                            v-for="l in navLinks"
                            :key="l.href"
                            :href="l.href"
                            class="rounded-lg px-3 py-2 text-slate-700 transition hover:bg-white/50 dark:text-slate-200 dark:hover:bg-white/10"
                            @click="mobileOpen = false"
                        >
                            {{ l.label }}
                        </a>
                        <a :href="loginUrl" class="mt-1 rounded-lg bg-[#2E74B5] px-3 py-2 text-center font-medium text-white">Войти</a>
                    </nav>
                </div>
            </div>
        </header>

        <!-- Hero -->
        <section class="relative mx-auto max-w-6xl px-6 pt-16 pb-14 text-center sm:pt-24">
            <div data-reveal class="inline-flex items-center gap-2 glass rounded-full px-4 py-1.5 text-sm font-medium text-[#2E74B5] dark:text-sky-300">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#2E74B5] opacity-60"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-[#2E74B5]"></span>
                </span>
                AI-администратор для локального бизнеса
            </div>
            <h1 data-reveal style="transition-delay: 80ms" class="mx-auto mt-6 max-w-4xl text-4xl font-extrabold leading-[1.08] tracking-tight text-[#1F4E79] dark:text-sky-200 sm:text-5xl lg:text-6xl">
                {{ site.heroTitle }}
            </h1>
            <p data-reveal style="transition-delay: 160ms" class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-slate-600 dark:text-slate-300">
                {{ site.heroSubtitle }}
            </p>
            <div data-reveal style="transition-delay: 240ms" class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <a href="#contacts" class="btn-shine w-full rounded-2xl bg-[#2E74B5] px-8 py-3.5 font-semibold text-white shadow-xl shadow-[#2E74B5]/30 transition hover:-translate-y-0.5 hover:bg-[#255f96] sm:w-auto">
                    Получить доступ
                </a>
                <a href="#how" class="glass w-full rounded-2xl px-8 py-3.5 font-semibold text-[#1F4E79] dark:text-sky-200 transition hover:-translate-y-0.5 sm:w-auto">
                    Как это работает
                </a>
            </div>
            <p data-reveal style="transition-delay: 300ms" class="mt-4 text-sm text-slate-400 dark:text-slate-500">{{ site.accessNote }}</p>

            <div data-reveal style="transition-delay: 360ms" class="mt-10 flex flex-wrap items-center justify-center gap-3">
                <span class="glass rounded-full px-4 py-2 text-sm text-slate-600 dark:text-slate-300">⚡ Ответ за секунды</span>
                <span class="glass rounded-full px-4 py-2 text-sm text-slate-600 dark:text-slate-300">🕐 Круглосуточно</span>
                <span class="glass rounded-full px-4 py-2 text-sm text-slate-600 dark:text-slate-300">🇷🇺 Серверы в РФ</span>
                <span class="glass rounded-full px-4 py-2 text-sm text-slate-600 dark:text-slate-300">🔒 152-ФЗ</span>
            </div>
        </section>

        <!-- Метрики -->
        <section class="mx-auto max-w-6xl px-6 py-8">
            <div ref="metricsEl" data-reveal class="glass grid grid-cols-2 gap-6 rounded-3xl px-6 py-10 lg:grid-cols-4">
                <div v-for="m in metrics" :key="m.label" class="text-center">
                    <div class="bg-gradient-to-r from-[#1F4E79] to-[#2E74B5] dark:from-sky-300 dark:to-blue-300 bg-clip-text text-4xl font-extrabold text-transparent sm:text-5xl">
                        {{ m.prefix }}{{ m.current }}{{ m.suffix }}
                    </div>
                    <div class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ m.label }}</div>
                </div>
            </div>
        </section>

        <!-- Возможности -->
        <section id="features" class="mx-auto max-w-6xl px-6 py-16">
            <div data-reveal class="mb-12 text-center">
                <h2 class="text-3xl font-bold text-[#1F4E79] dark:text-sky-200">Что умеет «Отклик»</h2>
                <p class="mx-auto mt-3 max-w-2xl text-slate-500 dark:text-slate-400">Виртуальный администратор, который встречает каждого клиента и доводит его до записи.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="(f, i) in features"
                    :key="f.title"
                    data-reveal
                    :style="{ transitionDelay: i * 70 + 'ms' }"
                    class="card-hover glass group rounded-3xl p-6"
                >
                    <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-white/70 dark:bg-white/10 text-2xl shadow-sm transition group-hover:scale-110">
                        {{ f.icon }}
                    </div>
                    <div class="mt-4 font-semibold text-[#1F4E79] dark:text-sky-200">{{ f.title }}</div>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ f.text }}</p>
                </div>
            </div>
        </section>

        <!-- Как работает -->
        <section id="how" class="mx-auto max-w-6xl px-6 py-16">
            <h2 data-reveal class="mb-12 text-center text-3xl font-bold text-[#1F4E79] dark:text-sky-200">Запуск за один вечер</h2>
            <div class="grid gap-6 sm:grid-cols-3">
                <div
                    v-for="(s, i) in steps"
                    :key="s.n"
                    data-reveal
                    :style="{ transitionDelay: i * 90 + 'ms' }"
                    class="card-hover glass rounded-3xl p-7"
                >
                    <div class="text-4xl font-extrabold text-[#2E74B5]/30 dark:text-sky-300/30">{{ s.n }}</div>
                    <div class="mt-3 font-semibold text-slate-800 dark:text-slate-100">{{ s.title }}</div>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ s.text }}</p>
                </div>
            </div>
        </section>

        <!-- Интеграции -->
        <section id="integrations" class="mx-auto max-w-6xl px-6 py-16">
            <div data-reveal class="mb-10 text-center">
                <h2 class="text-3xl font-bold text-[#1F4E79] dark:text-sky-200">Работает там, где ваши клиенты</h2>
                <p class="mt-3 text-slate-500 dark:text-slate-400">Каналы общения и CRM подключаются в пару кликов.</p>
            </div>
            <div data-reveal class="flex flex-wrap justify-center gap-3">
                <span v-for="i in integrationsNow" :key="i" class="glass rounded-full px-5 py-2.5 text-sm font-medium text-[#1F4E79] dark:text-sky-200">
                    {{ i }}
                </span>
            </div>
            <div data-reveal style="transition-delay: 200ms" class="mx-auto mt-8 max-w-2xl">
                <div class="glass rounded-2xl p-6 text-center">
                    <div class="font-semibold text-[#1F4E79] dark:text-sky-200">Своя CRM? Подключим под вас</div>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                        Интеграцию с вашей CRM настроим по договорённости. На данный момент поддерживается
                        <span class="font-medium text-[#1F4E79] dark:text-sky-200">YClients</span> — остальные подключаем индивидуально.
                    </p>
                </div>
            </div>
        </section>

        <!-- Планы по внедрению инструментов бизнеса (roadmap) -->
        <section id="roadmap" class="mx-auto max-w-6xl px-6 py-16">
            <div data-reveal class="mb-10 text-center">
                <h2 class="text-3xl font-bold text-[#1F4E79] dark:text-sky-200">Планы по внедрению инструментов бизнеса</h2>
                <p class="mx-auto mt-3 max-w-2xl text-slate-500 dark:text-slate-400">
                    Над чем работаем дальше. Этого пока нет в продукте — добавляем по мере готовности.
                </p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="(r, i) in roadmap"
                    :key="r.title"
                    data-reveal
                    :style="{ transitionDelay: i * 70 + 'ms' }"
                    class="glass rounded-3xl p-6"
                >
                    <div class="flex items-center gap-2">
                        <span class="text-2xl">{{ r.icon }}</span>
                        <span class="font-semibold text-[#1F4E79] dark:text-sky-200">{{ r.title }}</span>
                        <span class="ml-auto rounded-full border border-white/60 bg-white/40 px-2.5 py-0.5 text-[11px] text-slate-400 backdrop-blur dark:border-white/10 dark:bg-white/5 dark:text-slate-500">в планах</span>
                    </div>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ r.text }}</p>
                </div>
            </div>
        </section>

        <!-- Надёжность -->
        <section id="reliability" class="mx-auto max-w-6xl px-6 py-16">
            <div data-reveal class="mb-12 text-center">
                <h2 class="text-3xl font-bold text-[#1F4E79] dark:text-sky-200">Спокойно за данные и клиентов</h2>
                <p class="mx-auto mt-3 max-w-2xl text-slate-500 dark:text-slate-400">Всё размещено в России и построено вокруг защиты вашего бизнеса.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="(r, i) in reliability"
                    :key="r.title"
                    data-reveal
                    :style="{ transitionDelay: i * 70 + 'ms' }"
                    class="card-hover glass rounded-3xl p-6"
                >
                    <div class="text-2xl">{{ r.icon }}</div>
                    <div class="mt-3 font-semibold text-[#1F4E79] dark:text-sky-200">{{ r.title }}</div>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ r.text }}</p>
                </div>
            </div>
        </section>

        <!-- Тарифы -->
        <section id="pricing" class="mx-auto max-w-6xl px-6 py-16">
            <div data-reveal class="mb-12 text-center">
                <h2 class="text-3xl font-bold text-[#1F4E79] dark:text-sky-200">Тарифы</h2>
                <p class="mx-auto mt-3 max-w-2xl text-slate-500 dark:text-slate-400">Пробный период включён в любой тариф — оцените результат до оплаты.</p>
            </div>
            <div class="grid items-start gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="(p, i) in pricing"
                    :key="p.name"
                    data-reveal
                    :style="{ transitionDelay: i * 90 + 'ms' }"
                    class="card-hover glass relative rounded-3xl p-7"
                    :class="p.highlight ? 'ring-2 ring-[#2E74B5]' : ''"
                >
                    <div v-if="p.highlight" class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-[#2E74B5] px-3 py-1 text-xs font-medium text-white shadow">
                        Популярный
                    </div>
                    <div class="text-lg font-bold text-[#1F4E79] dark:text-sky-200">{{ p.name }}</div>
                    <div class="mt-3 flex flex-wrap items-end gap-x-1.5">
                        <span
                            class="font-extrabold leading-tight text-[#1F4E79] dark:text-sky-200"
                            :class="/[0-9]/.test(p.price) ? 'text-3xl' : 'text-xl'"
                        >{{ p.price }}</span>
                        <span class="pb-1 text-sm text-slate-400 dark:text-slate-500">{{ p.period }}</span>
                    </div>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ p.note }}</p>
                    <ul class="mt-5 space-y-2.5">
                        <li v-for="feat in p.features" :key="feat" class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <span class="mt-0.5 flex h-4 w-4 flex-none items-center justify-center rounded-full bg-[#2E74B5]/10 text-[10px] text-[#2E74B5] dark:text-sky-300">✓</span>
                            {{ feat }}
                        </li>
                    </ul>
                    <a
                        href="#contacts"
                        class="mt-7 block rounded-xl px-5 py-3 text-center font-semibold transition hover:-translate-y-0.5"
                        :class="p.highlight ? 'bg-[#2E74B5] text-white shadow-lg shadow-[#2E74B5]/25 hover:bg-[#255f96]' : 'border border-[#2E74B5]/30 bg-white/60 text-[#1F4E79] dark:bg-white/10 dark:text-sky-200'"
                    >
                        {{ p.cta }}
                    </a>
                </div>
            </div>
        </section>

        <!-- Контакты / CTA -->
        <section id="contacts" class="mx-auto max-w-6xl px-6 py-16">
            <div data-reveal class="cta-glass relative overflow-hidden rounded-[2rem] px-6 py-16 text-center text-white">
                <h2 class="text-3xl font-bold sm:text-4xl">Подключите «Отклик» к своему бизнесу</h2>
                <p class="mx-auto mt-3 max-w-2xl text-blue-50/90">{{ site.accessNote }}</p>
                <div class="mt-9 flex flex-wrap items-center justify-center gap-3 text-sm">
                    <a v-if="site.phone" :href="`tel:${site.phone}`" class="rounded-2xl bg-white/15 px-6 py-3.5 backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/25">
                        📞 {{ site.phone }}
                    </a>
                    <a v-if="site.email" :href="`mailto:${site.email}`" class="rounded-2xl bg-white/15 px-6 py-3.5 backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/25">
                        ✉️ {{ site.email }}
                    </a>
                    <a v-if="tgUrl" :href="tgUrl" target="_blank" class="rounded-2xl bg-white px-6 py-3.5 font-semibold text-[#1F4E79] transition hover:-translate-y-0.5 hover:bg-blue-50">
                        ✈️ Написать в Telegram
                    </a>
                </div>
                <div class="mt-8">
                    <a :href="loginUrl" class="text-blue-50/90 underline underline-offset-4 transition hover:text-white">Уже есть доступ? Войти →</a>
                </div>
            </div>
        </section>

        <!-- Футер с реквизитами -->
        <footer class="mx-auto max-w-6xl px-6 pb-10">
            <div class="glass rounded-3xl px-6 py-8">
                <div class="flex flex-col justify-between gap-4 sm:flex-row">
                    <div>
                        <div class="font-bold text-[#1F4E79] dark:text-white">Отклик</div>
                        <p class="mt-1 max-w-sm text-sm text-slate-400 dark:text-slate-500">AI-администратор для локального бизнеса: ответы клиентам и запись в CRM круглосуточно.</p>
                    </div>
                    <div class="flex items-start gap-6 text-sm">
                        <Link href="/contacts" class="text-slate-500 transition hover:text-[#1F4E79] dark:text-slate-400 dark:hover:text-white">Контакты</Link>
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

<style scoped>
/* iOS-стекло */
.glass {
    background: rgba(255, 255, 255, 0.55);
    backdrop-filter: blur(18px) saturate(170%);
    -webkit-backdrop-filter: blur(18px) saturate(170%);
    border: 1px solid rgba(255, 255, 255, 0.6);
    box-shadow: 0 8px 32px rgba(31, 78, 121, 0.12);
}

/* Тёмная тема: стекло на тёмном */
html.dark .glass {
    background: rgba(20, 30, 48, 0.55);
    border-color: rgba(255, 255, 255, 0.1);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.45);
}

/* Анимированный градиентный фон */
.bg-base {
    position: fixed;
    inset: 0;
    z-index: -2;
    pointer-events: none;
    background: linear-gradient(125deg, #eaf1fe 0%, #f6faff 45%, #e7f6ff 100%);
    background-size: 200% 200%;
    animation: bgpan 22s ease infinite;
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
    filter: blur(70px);
    opacity: 0.5;
    will-change: transform;
}
html.dark .orb {
    opacity: 0.28;
}
.orb-1 {
    width: 420px;
    height: 420px;
    background: #7cc0ff;
    top: -90px;
    left: -70px;
    animation: floaty 18s ease-in-out infinite;
}
.orb-2 {
    width: 360px;
    height: 360px;
    background: #b9a8ff;
    top: 30%;
    right: -80px;
    animation: floaty 22s ease-in-out infinite reverse;
}
.orb-3 {
    width: 300px;
    height: 300px;
    background: #7df3e1;
    bottom: 10%;
    left: 5%;
    animation: floaty 20s ease-in-out infinite;
}
.orb-4 {
    width: 340px;
    height: 340px;
    background: #9fd0ff;
    bottom: -120px;
    right: 20%;
    animation: floaty 26s ease-in-out infinite reverse;
}

/* Появление при скролле */
[data-reveal] {
    opacity: 0;
    transform: translateY(28px);
    transition:
        opacity 0.7s cubic-bezier(0.2, 0.7, 0.2, 1),
        transform 0.7s cubic-bezier(0.2, 0.7, 0.2, 1);
}
[data-reveal].reveal-in {
    opacity: 1;
    transform: none;
}

.card-hover {
    transition:
        transform 0.35s cubic-bezier(0.2, 0.7, 0.2, 1),
        box-shadow 0.35s ease,
        opacity 0.7s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.card-hover:hover {
    transform: translateY(-6px);
    box-shadow: 0 20px 48px rgba(31, 78, 121, 0.18);
}
html.dark .card-hover:hover {
    box-shadow: 0 20px 48px rgba(0, 0, 0, 0.5);
}

/* Блик на главной кнопке */
.btn-shine {
    position: relative;
    overflow: hidden;
}
.btn-shine::after {
    content: '';
    position: absolute;
    top: 0;
    left: -120%;
    width: 60%;
    height: 100%;
    background: linear-gradient(120deg, transparent, rgba(255, 255, 255, 0.45), transparent);
    transform: skewX(-20deg);
    animation: shine 3.4s ease-in-out infinite;
}

/* Стеклянная CTA-панель на градиенте */
.cta-glass {
    background: linear-gradient(135deg, rgba(31, 78, 121, 0.92), rgba(46, 116, 181, 0.92));
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    border: 1px solid rgba(255, 255, 255, 0.18);
    box-shadow: 0 24px 60px rgba(31, 78, 121, 0.35);
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
        transform: translate(26px, -34px) scale(1.06);
    }
}
@keyframes shine {
    0% {
        left: -120%;
    }
    55%,
    100% {
        left: 140%;
    }
}

@media (prefers-reduced-motion: reduce) {
    .bg-base,
    .orb,
    .btn-shine::after {
        animation: none;
    }
    [data-reveal] {
        opacity: 1;
        transform: none;
        transition: none;
    }
}
</style>
