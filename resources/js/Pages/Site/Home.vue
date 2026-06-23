<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import Icon from '@/Components/Icon.vue';
import { features, metricsData, reliability } from '@/marketing';

const teaser = features.slice(0, 6);

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
defineProps<{ site: Site; loginUrl: string }>();

const metrics = ref(metricsData.map((m) => ({ ...m, current: 0 })));
const metricsEl = ref<HTMLElement | null>(null);
let metricsObserver: IntersectionObserver | null = null;

const prefersReduced = (): boolean =>
    typeof window !== 'undefined' && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function runCountUp(): void {
    if (prefersReduced()) {
        metrics.value.forEach((m) => (m.current = m.target));
        return;
    }
    const start = performance.now();
    const tick = (now: number): void => {
        const p = Math.min(1, (now - start) / 1400);
        const eased = 1 - Math.pow(1 - p, 3);
        metrics.value.forEach((m) => (m.current = Math.round(m.target * eased)));
        if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
}

onMounted(() => {
    if (metricsEl.value) {
        metricsObserver = new IntersectionObserver(
            (entries) => entries.forEach((en) => {
                if (en.isIntersecting) {
                    runCountUp();
                    metricsObserver?.disconnect();
                }
            }),
            { threshold: 0.4 },
        );
        metricsObserver.observe(metricsEl.value);
    }
});
onBeforeUnmount(() => metricsObserver?.disconnect());
</script>

<template>
    <Head>
        <title>Отклик — AI-администратор для бизнеса: ответы в Telegram, ВКонтакте, MAX, WhatsApp и на сайте, запись клиентов</title>
        <meta name="description" content="Отклик — AI-администратор для салонов, барбершопов, клиник и сервиса. Мгновенно отвечает клиентам в Telegram, ВКонтакте, MAX, WhatsApp и на сайте по вашей базе знаний и записывает в CRM. Не теряйте заявки 24/7." />
        <meta name="keywords" content="AI-администратор, чат-бот для бизнеса, бот WhatsApp, автоответы Telegram, бот ВКонтакте, бот MAX, виджет на сайт, запись клиентов, бот для записи, распознавание голосовых, YClients, 152-ФЗ" />
        <meta property="og:type" content="website" />
        <meta property="og:title" content="Отклик — цифровой администратор для локального бизнеса" />
        <meta property="og:description" content="Мгновенные ответы клиентам в Telegram, ВКонтакте, MAX и на сайте по вашей базе знаний и запись в CRM. Не теряйте ни одного обращения." />
    </Head>

    <SiteLayout :site="site" :login-url="loginUrl">
        <!-- Hero -->
        <section class="relative mx-auto max-w-6xl px-6 pt-20 pb-14 text-center sm:pt-28">
            <h1 data-reveal style="transition-delay: 80ms" class="mx-auto mt-2 max-w-4xl text-4xl font-extrabold leading-[1.08] tracking-tight text-[#1F4E79] dark:text-sky-200 sm:text-5xl lg:text-6xl">
                {{ site.heroTitle }}
            </h1>
            <p data-reveal style="transition-delay: 160ms" class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-slate-600 dark:text-slate-300">
                {{ site.heroSubtitle }}
            </p>
            <div data-reveal style="transition-delay: 240ms" class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <Link href="/tarify" class="btn-shine w-full rounded-2xl bg-[#2E74B5] px-8 py-3.5 font-semibold text-white shadow-xl shadow-[#2E74B5]/30 transition hover:-translate-y-0.5 hover:bg-[#255f96] sm:w-auto">Получить доступ</Link>
                <Link href="/vozmozhnosti" class="glass w-full rounded-2xl px-8 py-3.5 font-semibold text-[#1F4E79] transition hover:-translate-y-0.5 sm:w-auto dark:text-sky-200">Как это работает</Link>
            </div>
            <p data-reveal style="transition-delay: 300ms" class="mt-4 text-sm text-slate-400 dark:text-slate-500">{{ site.accessNote }}</p>

            <div data-reveal style="transition-delay: 360ms" class="mt-10 flex flex-wrap items-center justify-center gap-3">
                <span class="glass inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm text-slate-600 dark:text-slate-300"><Icon name="bolt" class="h-4 w-4 text-[#2E74B5] dark:text-sky-300" /> Ответ за секунды</span>
                <span class="glass inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm text-slate-600 dark:text-slate-300"><Icon name="clock" class="h-4 w-4 text-[#2E74B5] dark:text-sky-300" /> Круглосуточно</span>
                <span class="glass inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm text-slate-600 dark:text-slate-300"><Icon name="shield" class="h-4 w-4 text-[#2E74B5] dark:text-sky-300" /> Серверы в РФ</span>
                <span class="glass inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm text-slate-600 dark:text-slate-300"><Icon name="lock" class="h-4 w-4 text-[#2E74B5] dark:text-sky-300" /> 152-ФЗ</span>
            </div>

            <!-- Мокап чата -->
            <div data-reveal style="transition-delay: 440ms" class="mx-auto mt-14 max-w-md">
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
            <div ref="metricsEl" data-reveal class="glow-frame" style="--gf-radius: 1.5rem">
              <div class="glow-inner grid grid-cols-2 gap-6 px-6 py-10 lg:grid-cols-4">
                <div v-for="m in metrics" :key="m.label" class="text-center">
                    <div class="bg-gradient-to-r from-[#1F4E79] to-[#2E74B5] bg-clip-text text-4xl font-extrabold text-transparent sm:text-5xl dark:from-sky-300 dark:to-blue-300">
                        {{ m.prefix }}{{ m.current }}{{ m.suffix }}
                    </div>
                    <div class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ m.label }}</div>
                </div>
              </div>
            </div>
        </section>

        <!-- Что умеет — кратко, с переходом на «Возможности» -->
        <section class="mx-auto max-w-6xl px-6 py-16">
            <div data-reveal class="mb-9 text-center">
                <h2 class="text-3xl font-bold text-[#1F4E79] dark:text-sky-200">Что умеет «Отклик»</h2>
                <p class="mx-auto mt-3 max-w-2xl text-slate-500 dark:text-slate-400">Виртуальный администратор, который встречает каждого клиента и доводит его до записи.</p>
            </div>
            <div data-reveal class="mx-auto flex max-w-4xl flex-wrap justify-center gap-2.5">
                <span v-for="f in teaser" :key="f.title" class="glass inline-flex items-center gap-2 rounded-full px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                    <Icon :name="f.icon" class="h-4 w-4 text-[#2E74B5] dark:text-sky-300" />{{ f.title }}
                </span>
            </div>
            <div data-reveal class="mt-9 text-center">
                <Link href="/vozmozhnosti" class="btn-shine inline-block rounded-2xl bg-[#2E74B5] px-8 py-3.5 font-semibold text-white shadow-lg shadow-[#2E74B5]/25 transition hover:-translate-y-0.5 hover:bg-[#255f96]">Все возможности →</Link>
            </div>
        </section>

        <!-- Ваши данные в безопасности -->
        <section class="mx-auto max-w-6xl px-6 py-16">
            <div data-reveal class="mb-12 text-center">
                <h2 class="text-3xl font-bold text-[#1F4E79] dark:text-sky-200">Ваши данные в безопасности</h2>
                <p class="mx-auto mt-3 max-w-2xl text-slate-500 dark:text-slate-400">Всё размещено в России и построено вокруг защиты вашего бизнеса.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="(r, i) in reliability" :key="r.title" data-reveal :style="{ transitionDelay: i * 70 + 'ms' }" class="card-hover glass group rounded-3xl p-6">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-white/70 text-[#2E74B5] shadow-sm dark:bg-white/10 dark:text-sky-300"><Icon :name="r.icon" class="ico h-6 w-6" /></div>
                    <div class="mt-3 font-semibold text-[#1F4E79] dark:text-sky-200">{{ r.title }}</div>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ r.text }}</p>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="mx-auto max-w-6xl px-6 py-16">
            <div data-reveal class="glow-frame" style="--gf-radius: 2rem">
                <div class="cta-glass relative overflow-hidden rounded-[calc(2rem-2px)] px-6 py-16 text-center text-white">
                    <h2 class="text-3xl font-bold sm:text-4xl">Запустите «Отклик» за вечер</h2>
                    <p class="mx-auto mt-3 max-w-2xl text-blue-50/90">{{ site.accessNote }}</p>
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-3 text-sm">
                        <Link href="/tarify" class="rounded-2xl bg-white px-7 py-3.5 font-semibold text-[#1F4E79] transition hover:-translate-y-0.5 hover:bg-blue-50">Тарифы и доступ</Link>
                        <Link href="/contacts" class="rounded-2xl bg-white/15 px-7 py-3.5 backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/25">Связаться с нами</Link>
                    </div>
                </div>
            </div>
        </section>
    </SiteLayout>
</template>
