<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import Logo from '@/Components/Logo.vue';
import Icon from '@/Components/Icon.vue';

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

// mailto: открывается только при настроенном почтовом клиенте (на десктопе часто
// нет). Поэтому по клику ещё и копируем адрес в буфер с подтверждением — тогда
// кнопка «работает» у всех (mailto в href остаётся для тех, у кого клиент есть).
const emailCopied = ref(false);
const copyEmail = (): void => {
    if (!props.site.email || !navigator.clipboard) return;
    navigator.clipboard
        .writeText(props.site.email)
        .then(() => {
            emailCopied.value = true;
            window.setTimeout(() => (emailCopied.value = false), 2000);
        })
        .catch(() => {});
};

const navLinks = [
    { href: '#features', label: 'Возможности' },
    { href: '#templates', label: 'Шаблоны' },
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
    { target: 100, current: 0, prefix: '', suffix: '%', label: 'обращений получают ответ — ни одного потерянного' },
]);

const features = [
    { icon: 'chat', title: 'Все каналы в одном окне', text: 'Telegram, WhatsApp, ВКонтакте, MAX и виджет на сайте. Один помощник отвечает везде одинаково ровно.' },
    { icon: 'brain', title: 'Отвечает по вашему бизнесу', text: 'Цены, услуги, часы работы, условия — строго из вашей базы знаний, без фантазий и «я уточню».' },
    { icon: 'calendar', title: 'Записывает клиентов сам', text: 'Подбирает услугу и время, оформляет запись в вашей CRM (YClients), а также переносит, отменяет и напоминает клиенту о визите.' },
    { icon: 'mic', title: 'Понимает голосовые', text: 'Клиент отправил войс вместо текста — бот распознаёт речь и отвечает как обычно. Такое умеют единицы.' },
    { icon: 'megaphone', title: 'Возвращает клиентов рассылками', text: 'Акции и напоминания по базе клиентов — в мессенджеры и на почту, вручную или по расписанию, с отчётом о доставке.' },
    { icon: 'wand', title: 'Сценарии без программистов', text: 'No-code конструктор воронок: задайте «если клиент написал X → ответь Y, предложи кнопки Z, запиши или позови администратора». Меняете логику бота сами, без разработчиков.' },
    { icon: 'template', title: 'Старт не с нуля — готовые шаблоны', text: 'Десятки готовых сценариев и элементов базы знаний под вашу нишу. Добавил в один клик, заменил «…» на свои цены и контакты — и бот готов.' },
    { icon: 'target', title: 'Заточено под вашу нишу', text: 'Выбираете тип бизнеса — маникюр, барбершоп, косметология, тату, продажи или B2B — и видите шаблоны именно под него, без лишнего.' },
    { icon: 'users', title: 'Помнит каждого клиента', text: 'Ведёт базу клиентов: имя, телефон, история обращений. Вернувшегося встречает по имени.' },
    { icon: 'chart', title: 'Аналитика и ИИ-подсказки', text: 'Показывает лиды, конверсию и что мешает записям — с рекомендациями, где вы теряете клиентов.' },
    { icon: 'report', title: 'Недельный отчёт «директор»', text: 'Каждый понедельник присылаем владельцу в Telegram/на почту сводку за неделю: сколько лидов, конверсия в запись, что мешает и что улучшить — с ИИ-рекомендациями. Личный аналитик, без захода в кабинет.' },
    { icon: 'hand', title: 'Горячий клиент — менеджеру', text: 'Сложные и важные диалоги передаёт администратору с полной историей переписки.' },
    { icon: 'gear', title: 'Запуск без программистов', text: 'Загрузите услуги и частые вопросы — помощник готов к работе за вечер.' },
    { icon: 'shield', title: 'Данные остаются в России', text: 'Размещение на серверах РФ, соответствие 152-ФЗ и полная изоляция каждого бизнеса.' },
];

// Готовые шаблоны под нишу — примеры для лендинга (маркетинговые, не из БД).
// Бизнес выбирает свой тип — и видит готовые сценарии и элементы базы знаний
// именно под него + общие. Остаётся заменить «…» на свои данные.
interface Niche {
    key: string;
    label: string;
    icon: string;
    scenarios: string[];
    knowledge: string[];
}
const niches: Niche[] = [
    {
        key: 'nails',
        label: 'Маникюр',
        icon: 'polish',
        scenarios: ['Запись на маникюр', 'Подбор дизайна', 'Наращивание — вопросы', 'Перенос или отмена'],
        knowledge: ['Виды маникюра и цены', 'Гель-лак и покрытие', 'Уход после процедуры', 'Стерильность и гигиена'],
    },
    {
        key: 'barbershop',
        label: 'Барбершоп',
        icon: 'scissors',
        scenarios: ['Запись в барбершоп', 'Выбрать мастера', 'Первый визит — скидка', 'Детская стрижка'],
        knowledge: ['Услуги и цены', 'Моделирование бороды', 'Камуфляж седины', 'Наши мастера'],
    },
    {
        key: 'beauty',
        label: 'Косметология',
        icon: 'sparkle',
        scenarios: ['Запись на процедуру', 'Подбор процедуры', 'Противопоказания', 'Курс процедур'],
        knowledge: ['Популярные процедуры', 'Чистка лица', 'Подготовка к процедуре', 'Инъекционная косметология'],
    },
    {
        key: 'tattoo',
        label: 'Тату-студия',
        icon: 'pen',
        scenarios: ['Эскиз / консультация', 'Выбрать мастера', 'Перекрытие старой тату', 'Пирсинг'],
        knowledge: ['Как проходит сеанс', 'Стоимость и расчёт', 'Уход после сеанса', 'Противопоказания'],
    },
    {
        key: 'sales',
        label: 'Продажи',
        icon: 'bag',
        scenarios: ['Заявка / узнать цену', 'Статус заказа', 'Помощь с выбором', 'Возврат и обмен'],
        knowledge: ['Каталог и наличие', 'Доставка', 'Оплата', 'Гарантия на товар'],
    },
    {
        key: 'b2b',
        label: 'B2B',
        icon: 'briefcase',
        scenarios: ['Запрос КП', 'Демо или презентация', 'Заказать звонок', 'Партнёрство'],
        knowledge: ['Условия сотрудничества', 'Как начать работу', 'Документы', 'Логистика и доставка'],
    },
];
const activeNiche = ref('barbershop');
const activeNicheData = computed<Niche>(() => niches.find((n) => n.key === activeNiche.value) ?? niches[0]);

const steps = [
    { n: '01', title: 'Подключите канал', text: 'Telegram, ВКонтакте, MAX или WhatsApp за пару минут. Виджет на сайт — одной строкой.' },
    { n: '02', title: 'Выберите нишу и шаблоны', text: 'Укажите тип бизнеса — подтянутся готовые сценарии и база знаний под вас. Замените «…» на свои цены и услуги.' },
    { n: '03', title: 'Помощник работает за вас', text: 'Отвечает клиентам, записывает на услуги, а сложное передаёт администратору.' },
];

const integrationsNow = ['Telegram', 'ВКонтакте', 'MAX', 'WhatsApp', 'Виджет на сайте', 'YClients'];

// Планы по внедрению инструментов бизнеса (ещё не в продакшене — честно отдельным блоком).
const roadmap = [
    { icon: 'shop', title: 'Avito', text: 'Обработка обращений с объявлений Авито.' },
    { icon: 'phone', title: 'Телефония', text: 'Голосовой ассистент и пропущенные звонки.' },
    { icon: 'link', title: 'Другие CRM', text: 'Altegio, amoCRM, Bitrix24 — подключаем под бизнес.' },
];

const reliability = [
    { icon: 'lock', title: 'Безопасность', text: 'Шифрование канала (TLS), доступы под ролями, никаких данных за пределами РФ.' },
    { icon: 'puzzle', title: 'Изоляция бизнесов', text: 'Данные каждого клиента строго отделены на уровне приложения и базы данных.' },
    { icon: 'shield', title: 'Соответствие 152-ФЗ', text: 'Хранение и обработка персональных данных в российской инфраструктуре.' },
    { icon: 'hand', title: 'Человек всегда рядом', text: 'Если вопрос вне компетенции — диалог мгновенно уходит живому администратору.' },
];

const pricing = [
    {
        name: 'Пробный',
        price: '0 ₽',
        period: '14 дней бесплатно',
        highlight: false,
        note: 'Возможности уровня «Стандарт», чтобы попробовать.',
        features: ['Все возможности «Стандарта»', 'Telegram, ВКонтакте, MAX, WhatsApp и виджет на сайт', 'База знаний и AI-ответы 24/7', 'Без банковской карты'],
        cta: 'Попробовать',
    },
    {
        name: 'Стандарт',
        price: '3 599 ₽',
        period: 'в месяц',
        highlight: false,
        note: 'Для большинства локальных бизнесов.',
        features: ['Telegram, ВКонтакте, MAX, WhatsApp и виджет на сайт', 'База знаний (фото, ссылки, цены)', 'AI-ответы 24/7 + передача администратору', 'До 2 операторов', 'Базовая статистика'],
        cta: 'Получить доступ',
    },
    {
        name: 'Макс',
        price: '5 599 ₽',
        period: 'в месяц',
        highlight: true,
        note: 'Всё включено и удвоенные лимиты.',
        features: ['Всё из «Стандарта»', 'CRM YClients: автозапись, отмена, напоминания клиентам', 'База знаний из CRM (услуги, цены, мастера)', 'Рассылки по базе клиентов (мессенджеры + почта, по расписанию)', 'Конструктор сценариев (no-code воронки)', 'Умный поиск по знаниям (RAG)', 'Расширенная аналитика и ИИ-рекомендации', 'До 10 операторов', 'Больше получателей уведомлений', 'Приоритетная поддержка'],
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
// Анимации появления включаем только ПОСЛЕ монтирования JS: без JS/наблюдателя
// контент остаётся видимым (страховка от «пустой страницы» для крауллеров/превью).
const armed = ref(false);
let revealObserver: IntersectionObserver | null = null;
let metricsObserver: IntersectionObserver | null = null;
let scrollRaf = 0;

const prefersReduced = (): boolean =>
    typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

// Параллакс фона при прокрутке: прогресс 0..1 в CSS-переменную --sp.
const onScroll = (): void => {
    if (scrollRaf) return;
    scrollRaf = requestAnimationFrame(() => {
        const max = document.documentElement.scrollHeight - window.innerHeight;
        const p = max > 0 ? Math.min(1, window.scrollY / max) : 0;
        document.documentElement.style.setProperty('--sp', p.toFixed(4));
        scrollRaf = 0;
    });
};

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
    // JS поднялся → можно прятать секции до появления (до этого они видимы).
    armed.value = true;

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

    // Страховка: через 2.5 с раскрываем всё, что наблюдатель не успел (медленный JS,
    // экзотические браузеры) — контент гарантированно не «залипнет» невидимым.
    window.setTimeout(() => {
        document.querySelectorAll('[data-reveal]:not(.reveal-in)').forEach((el) => el.classList.add('reveal-in'));
    }, 2500);

    if (!prefersReduced()) {
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

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
    window.removeEventListener('scroll', onScroll);
});
</script>

<template>
    <Head>
        <title>Отклик — цифровой администратор для бизнеса: ответы в Telegram, ВКонтакте, MAX, WhatsApp и на сайте, запись клиентов</title>
        <meta
            name="description"
            content="Отклик — AI-администратор для салонов, барбершопов, клиник и сервиса. Мгновенно отвечает клиентам в Telegram, ВКонтакте, MAX, WhatsApp и на сайте по вашей базе знаний и записывает в CRM. Не теряйте заявки 24/7."
        />
        <meta name="keywords" content="AI-администратор, чат-бот для бизнеса, бот WhatsApp, чат-бот WhatsApp, автоответы Telegram, бот ВКонтакте, бот MAX, мессенджер MAX, виджет на сайт, запись клиентов, виртуальный помощник, бот для записи, распознавание голосовых сообщений, бот понимает голос, YClients, 152-ФЗ" />
        <meta property="og:type" content="website" />
        <meta property="og:title" content="Отклик — цифровой администратор для локального бизнеса" />
        <meta
            property="og:description"
            content="Мгновенные ответы клиентам в Telegram, ВКонтакте, MAX и на сайте по вашей базе знаний и запись в CRM. Не теряйте ни одного обращения."
        />
    </Head>

    <div class="page relative min-h-screen overflow-x-clip text-slate-800 dark:text-slate-200" :class="{ 'reveal-armed': armed }">
        <!-- Анимированный фон + плавающие орбы (с параллаксом при прокрутке) -->
        <div class="bg-base"></div>
        <div class="orbs" aria-hidden="true">
            <span class="orb orb-1"></span>
            <span class="orb orb-2"></span>
            <span class="orb orb-3"></span>
            <span class="orb orb-4"></span>
            <span class="orb orb-5"></span>
            <span class="orb orb-6"></span>
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
                                <Icon :name="mobileOpen ? 'close' : 'menu'" class="h-5 w-5" />
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
                <span class="glass inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm text-slate-600 dark:text-slate-300"><Icon name="bolt" class="h-4 w-4 text-[#2E74B5] dark:text-sky-300" /> Ответ за секунды</span>
                <span class="glass inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm text-slate-600 dark:text-slate-300"><Icon name="clock" class="h-4 w-4 text-[#2E74B5] dark:text-sky-300" /> Круглосуточно</span>
                <span class="glass inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm text-slate-600 dark:text-slate-300"><Icon name="shield" class="h-4 w-4 text-[#2E74B5] dark:text-sky-300" /> Серверы в РФ</span>
                <span class="glass inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm text-slate-600 dark:text-slate-300"><Icon name="lock" class="h-4 w-4 text-[#2E74B5] dark:text-sky-300" /> 152-ФЗ</span>
            </div>

            <!-- Мокап чата: показываем продукт «в деле» -->
            <div data-reveal style="transition-delay: 440ms" class="chat-wrap mx-auto mt-14 max-w-md">
                <div class="chat-mock glass rounded-3xl p-4 text-left">
                    <div class="flex items-center gap-2.5 border-b border-white/40 pb-3 dark:border-white/10">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#2E74B5] text-white shadow-sm"><Icon name="robot" class="h-5 w-5" /></span>
                        <div class="leading-tight">
                            <div class="text-sm font-semibold text-[#1F4E79] dark:text-sky-200">Отклик</div>
                            <div class="flex items-center gap-1.5 text-[11px] text-emerald-500"><span class="live-dot"></span>на связи 24/7</div>
                        </div>
                    </div>
                    <div class="space-y-2.5 pt-3.5">
                        <div class="msg msg-client" style="--d: 0.3s">Здравствуйте! Сколько стоит мужская стрижка и есть окно сегодня?</div>
                        <div class="msg msg-bot" style="--d: 1s">Мужская стрижка — 1500 ₽. Сегодня свободно в 15:00 и 18:30. На какое время записать?</div>
                        <div class="chat-chips" style="--d: 1.7s">
                            <span class="chip chip-primary">Записаться на 15:00</span>
                            <span class="chip">18:30</span>
                            <span class="chip">Другой день</span>
                        </div>
                    </div>
                </div>
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
                    <div class="ico-box flex h-12 w-12 items-center justify-center rounded-2xl bg-white/70 text-[#2E74B5] shadow-sm transition group-hover:scale-110 dark:bg-white/10 dark:text-sky-300">
                        <Icon :name="f.icon" class="ico h-6 w-6" />
                    </div>
                    <div class="mt-4 font-semibold text-[#1F4E79] dark:text-sky-200">{{ f.title }}</div>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ f.text }}</p>
                </div>
            </div>
        </section>

        <!-- Готовые шаблоны под нишу -->
        <section id="templates" class="mx-auto max-w-6xl px-6 py-16">
            <div data-reveal class="mb-10 text-center">
                <div class="inline-flex items-center gap-2 glass rounded-full px-4 py-1.5 text-sm font-medium text-[#2E74B5] dark:text-sky-300">
                    <Icon name="template" class="h-4 w-4" /> Запуск не с нуля
                </div>
                <h2 class="mt-4 text-3xl font-bold text-[#1F4E79] dark:text-sky-200">Готовые шаблоны под вашу нишу</h2>
                <p class="mx-auto mt-3 max-w-2xl text-slate-500 dark:text-slate-400">
                    Выберите тип бизнеса — и получите готовые сценарии и базу знаний именно под него. Остаётся заменить «…» на свои цены и контакты.
                </p>
            </div>

            <!-- Чипы ниш -->
            <div data-reveal class="mb-8 flex flex-wrap justify-center gap-2.5">
                <button
                    v-for="n in niches"
                    :key="n.key"
                    type="button"
                    class="niche-chip glass inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-medium transition"
                    :class="activeNiche === n.key ? 'niche-chip-active' : 'text-slate-600 dark:text-slate-300'"
                    @click="activeNiche = n.key"
                >
                    <Icon :name="n.icon" class="h-4 w-4" /><span>{{ n.label }}</span>
                </button>
            </div>

            <!-- Превью шаблонов выбранной ниши (с анимацией смены) -->
            <div data-reveal class="relative">
                <Transition name="swap" mode="out-in">
                    <div :key="activeNiche" class="grid gap-5 md:grid-cols-2">
                        <!-- Сценарии -->
                        <div class="glass rounded-3xl p-6">
                            <div class="mb-4 flex items-center gap-2">
                                <Icon name="wand" class="h-5 w-5 text-[#2E74B5] dark:text-sky-300" />
                                <span class="font-semibold text-[#1F4E79] dark:text-sky-200">Сценарии</span>
                                <span class="ml-auto rounded-full bg-[#EAF2FB] px-2.5 py-0.5 text-xs font-medium text-[#1F4E79] dark:bg-white/10 dark:text-sky-200">в один клик</span>
                            </div>
                            <ul class="space-y-2.5">
                                <li
                                    v-for="(s, i) in activeNicheData.scenarios"
                                    :key="s"
                                    class="tpl-row flex items-center gap-3 rounded-xl border border-slate-200/70 bg-white/60 px-4 py-3 text-sm text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200"
                                    :style="{ animationDelay: i * 60 + 'ms' }"
                                >
                                    <span class="flex h-7 w-7 flex-none items-center justify-center rounded-lg bg-[#2E74B5]/10 text-[#2E74B5] dark:text-sky-300">▸</span>
                                    {{ s }}
                                </li>
                            </ul>
                        </div>
                        <!-- База знаний -->
                        <div class="glass rounded-3xl p-6">
                            <div class="mb-4 flex items-center gap-2">
                                <Icon name="book" class="h-5 w-5 text-[#2E74B5] dark:text-sky-300" />
                                <span class="font-semibold text-[#1F4E79] dark:text-sky-200">База знаний</span>
                                <span class="ml-auto rounded-full bg-[#EAF2FB] px-2.5 py-0.5 text-xs font-medium text-[#1F4E79] dark:bg-white/10 dark:text-sky-200">заготовки</span>
                            </div>
                            <ul class="space-y-2.5">
                                <li
                                    v-for="(k, i) in activeNicheData.knowledge"
                                    :key="k"
                                    class="tpl-row flex items-center gap-3 rounded-xl border border-slate-200/70 bg-white/60 px-4 py-3 text-sm text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200"
                                    :style="{ animationDelay: i * 60 + 'ms' }"
                                >
                                    <span class="flex h-7 w-7 flex-none items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"><Icon name="check" class="h-4 w-4" /></span>
                                    {{ k }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </Transition>
            </div>

            <p data-reveal class="mx-auto mt-6 max-w-2xl text-center text-sm text-slate-400 dark:text-slate-500">
                И ещё десятки общих шаблонов, которые подойдут любому бизнесу. Всё редактируется под вас.
            </p>
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
                    <div class="flex items-center gap-2.5">
                        <span class="ico-box flex h-9 w-9 items-center justify-center rounded-xl bg-white/70 text-[#2E74B5] dark:bg-white/10 dark:text-sky-300">
                            <Icon :name="r.icon" class="ico h-5 w-5" />
                        </span>
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
                    <div class="ico-box flex h-11 w-11 items-center justify-center rounded-2xl bg-white/70 text-[#2E74B5] shadow-sm dark:bg-white/10 dark:text-sky-300">
                        <Icon :name="r.icon" class="ico h-6 w-6" />
                    </div>
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
                            <span class="mt-0.5 flex h-4 w-4 flex-none items-center justify-center rounded-full bg-[#2E74B5]/10 text-[#2E74B5] dark:text-sky-300"><Icon name="check" class="h-3 w-3" /></span>
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
                    <a v-if="site.phone" :href="`tel:${site.phone}`" class="inline-flex items-center gap-2 rounded-2xl bg-white/15 px-6 py-3.5 backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/25">
                        <Icon name="phone" class="h-4 w-4" /> {{ site.phone }}
                    </a>
                    <a v-if="site.email" :href="`mailto:${site.email}`" :title="`Написать на ${site.email} (клик — скопировать адрес)`" class="inline-flex items-center gap-2 rounded-2xl bg-white/15 px-6 py-3.5 backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/25" @click="copyEmail">
                        <Icon :name="emailCopied ? 'check' : 'mail'" class="h-4 w-4" /> {{ emailCopied ? 'Скопировано' : site.email }}
                    </a>
                    <a v-if="tgUrl" :href="tgUrl" target="_blank" class="inline-flex items-center gap-2 rounded-2xl bg-white px-6 py-3.5 font-semibold text-[#1F4E79] transition hover:-translate-y-0.5 hover:bg-blue-50">
                        <Icon name="send" class="h-4 w-4" /> Написать в Telegram
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
                    <div class="flex flex-wrap items-start gap-x-6 gap-y-2 text-sm">
                        <Link href="/contacts" class="text-slate-500 transition hover:text-[#1F4E79] dark:text-slate-400 dark:hover:text-white">Контакты</Link>
                        <Link href="/privacy" class="text-slate-500 transition hover:text-[#1F4E79] dark:text-slate-400 dark:hover:text-white">Конфиденциальность</Link>
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
    /* Контейнер шире вьюпорта сверху и снизу: при параллакс-сдвиге низ страницы
       не оголяется («плешь»), а overflow обрезает орбы далеко за экраном — без
       видимого жёсткого края. */
    inset: -220px 0;
    z-index: -1;
    overflow: hidden;
    pointer-events: none;
    /* Параллакс: при прокрутке слой орбов плавно уплывает вверх (--sp задаёт JS). */
    transform: translate3d(0, calc(var(--sp, 0) * -70px), 0);
    transition: transform 0.2s linear;
    will-change: transform;
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
.orb-5 {
    width: 300px;
    height: 300px;
    background: #c7b3ff;
    top: 52%;
    left: 32%;
    animation: floaty 24s ease-in-out infinite;
}
.orb-6 {
    width: 380px;
    height: 380px;
    background: #7df3e1;
    top: 78%;
    right: 6%;
    animation: floaty 19s ease-in-out infinite reverse;
}

/* Появление при скролле. Скрываем секции ТОЛЬКО когда JS поднялся (.reveal-armed):
   без JS/наблюдателя контент остаётся видимым (страховка от пустой страницы). */
[data-reveal] {
    transition:
        opacity 0.7s cubic-bezier(0.2, 0.7, 0.2, 1),
        transform 0.7s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.reveal-armed [data-reveal] {
    opacity: 0;
    transform: translateY(28px);
}
.reveal-armed [data-reveal].reveal-in {
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

/* Чипы ниш в секции шаблонов */
.niche-chip {
    cursor: pointer;
}
.niche-chip:hover {
    transform: translateY(-2px);
}
.niche-chip-active {
    background: linear-gradient(135deg, #2e74b5, #1f4e79);
    color: #fff;
    box-shadow: 0 8px 22px rgba(46, 116, 181, 0.35);
}

/* Плавная смена набора шаблонов при выборе ниши */
.swap-enter-active,
.swap-leave-active {
    transition:
        opacity 0.3s ease,
        transform 0.3s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.swap-enter-from {
    opacity: 0;
    transform: translateY(12px);
}
.swap-leave-to {
    opacity: 0;
    transform: translateY(-12px);
}

/* Поочерёдное «проявление» строк шаблонов */
.tpl-row {
    animation: tplRow 0.45s both;
}
@keyframes tplRow {
    from {
        opacity: 0;
        transform: translateX(-8px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Иконки: лёгкий «пружинистый» отклик при наведении на карточку */
.ico {
    transition: transform 0.35s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.group:hover .ico,
.card-hover:hover .ico {
    animation: icoPop 0.55s;
}
@keyframes icoPop {
    0% {
        transform: scale(1) rotate(0);
    }
    35% {
        transform: scale(1.18) rotate(-7deg);
    }
    70% {
        transform: scale(0.96) rotate(4deg);
    }
    100% {
        transform: scale(1) rotate(0);
    }
}
@media (prefers-reduced-motion: reduce) {
    .tpl-row,
    .swap-enter-active,
    .swap-leave-active,
    .group:hover .ico,
    .card-hover:hover .ico {
        animation: none;
        transition: none;
    }
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

/* Мокап чата в hero */
.chat-mock {
    box-shadow: 0 24px 60px rgba(31, 78, 121, 0.18);
}
html.dark .chat-mock {
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.5);
}
.live-dot {
    width: 7px;
    height: 7px;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.5);
    animation: livePulse 2s infinite;
}
.msg {
    max-width: 88%;
    width: fit-content;
    border-radius: 16px;
    padding: 9px 13px;
    font-size: 13px;
    line-height: 1.45;
    opacity: 0;
    animation: bubbleIn 0.55s cubic-bezier(0.2, 0.7, 0.2, 1) forwards;
    animation-delay: var(--d, 0s);
}
.msg-client {
    margin-left: auto;
    background: #2e74b5;
    color: #fff;
    border-bottom-right-radius: 5px;
}
.msg-bot {
    background: rgba(255, 255, 255, 0.82);
    color: #1f3550;
    border-bottom-left-radius: 5px;
}
html.dark .msg-bot {
    background: rgba(255, 255, 255, 0.1);
    color: #dbe7f5;
}
.chat-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    padding-top: 2px;
    opacity: 0;
    animation: bubbleIn 0.55s cubic-bezier(0.2, 0.7, 0.2, 1) forwards;
    animation-delay: var(--d, 0s);
}
.chip {
    border: 1px solid rgba(46, 116, 181, 0.35);
    color: #2e74b5;
    border-radius: 999px;
    padding: 6px 12px;
    font-size: 12px;
    font-weight: 500;
    background: rgba(255, 255, 255, 0.55);
}
html.dark .chip {
    color: #7cc0ff;
    background: rgba(255, 255, 255, 0.06);
    border-color: rgba(124, 192, 255, 0.28);
}
.chip-primary {
    background: #2e74b5;
    color: #fff;
    border-color: transparent;
    box-shadow: 0 6px 16px rgba(46, 116, 181, 0.3);
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
@keyframes bubbleIn {
    from {
        opacity: 0;
        transform: translateY(10px) scale(0.97);
    }
    to {
        opacity: 1;
        transform: none;
    }
}
@keyframes livePulse {
    0% {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.5);
    }
    70% {
        box-shadow: 0 0 0 7px rgba(16, 185, 129, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
    }
}

@media (prefers-reduced-motion: reduce) {
    .bg-base,
    .orb,
    .btn-shine::after,
    .msg,
    .chat-chips,
    .live-dot {
        animation: none;
    }
    .orbs {
        transform: none;
    }
    .msg,
    .chat-chips {
        opacity: 1;
    }
    .reveal-armed [data-reveal] {
        opacity: 1;
        transform: none;
        transition: none;
    }
}
</style>
