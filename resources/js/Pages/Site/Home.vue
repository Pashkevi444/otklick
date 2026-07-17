<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import Icon from '@/Components/Icon.vue';
import { features, metricsData, pricing, reliability } from '@/marketing';

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
const props = defineProps<{ site: Site; loginUrl: string }>();

// Чисто визуальное разбиение заголовка: последние слова — градиентный акцент.
const heroWords = computed(() => props.site.heroTitle.trim().split(/\s+/));
const heroAccentCount = computed(() => (heroWords.value.length > 3 ? 2 : 1));
const heroHead = computed(() => heroWords.value.slice(0, -heroAccentCount.value).join(' '));
const heroAccent = computed(() => heroWords.value.slice(-heroAccentCount.value).join(' '));

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
        <!-- Hero: тёмный #0B1120 на всю ширину, уходит под пилюлю-шапку -->
        <section class="mkt-hero relative -mt-[70px] overflow-hidden pb-20 pt-32 sm:pb-24 sm:pt-40">
            <!-- Декор: точечные сетки, вращающийся пунктирный круг, тёплый квадрат -->
            <div aria-hidden="true" class="pointer-events-none absolute inset-0">
                <div class="absolute left-[8%] top-32 hidden h-32 w-44 dark:lg:block" style="background-image: radial-gradient(rgba(255, 255, 255, 0.22) 1.6px, transparent 1.6px); background-size: 18px 18px; opacity: 0.35"></div>
                <div class="absolute bottom-24 right-[9%] hidden h-28 w-40 dark:lg:block" style="background-image: radial-gradient(rgba(255, 255, 255, 0.22) 1.6px, transparent 1.6px); background-size: 18px 18px; opacity: 0.3"></div>
                <div class="spin-slow absolute right-[15%] top-32 hidden h-28 w-28 rounded-full border-[1.5px] border-dashed border-[#2B5CE0]/50 lg:block"></div>
                <div class="floaty2 absolute bottom-28 left-[12%] hidden h-16 w-16 rounded-[20px] border-[6px] border-[#EE8A5C]/50 lg:block" style="rotate: 18deg"></div>
            </div>

            <!-- Плавающие чипы каналов -->
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 hidden xl:block">
                <div class="floaty absolute left-[5%] top-36"><span class="hero-chip"><span class="h-2 w-2 rounded-full bg-[#2AABEE]"></span>Telegram</span></div>
                <div class="floaty2 absolute left-[6%] top-72" style="--dur: 9s"><span class="hero-chip"><span class="h-2 w-2 rounded-full bg-[#0077FF]"></span>ВКонтакте</span></div>
                <div class="floaty absolute right-[6%] top-52" style="--dur: 8s"><span class="hero-chip"><span class="h-2 w-2 rounded-full bg-[#25D366]"></span>WhatsApp</span></div>
                <div class="floaty2 absolute right-[10%] top-[21rem]" style="--dur: 7.5s"><span class="hero-chip"><span class="h-2 w-2 rounded-full bg-[#7C5CFC]"></span>MAX</span></div>
                <div class="floaty absolute bottom-44 right-[18%]" style="--dur: 9s"><span class="hero-chip hero-chip-cta"><span class="pulse-dot h-2 w-2 rounded-full bg-[#7DFFA8]"></span>Онлайн 24/7</span></div>
            </div>

            <div class="relative mx-auto max-w-4xl px-6 text-center">
                <div data-reveal class="inline-flex items-center gap-2 rounded-full border border-line bg-panel px-4 py-2 text-[13px] font-semibold tracking-wide text-muted dark:border-white/10 dark:bg-white/5 dark:text-slate-200">
                    <span class="h-1.5 w-1.5 rounded-full bg-[#EE8A5C]"></span>
                    AI-администратор для локального бизнеса
                </div>
                <h1 data-reveal style="transition-delay: 80ms" class="font-display mx-auto mt-7 max-w-4xl text-4xl font-bold leading-[1.05] tracking-tight text-ink sm:text-5xl dark:text-white lg:text-6xl xl:text-[64px]">
                    {{ heroHead }}
                    <span class="bg-gradient-to-r from-[#4d7bff] via-[#7C5CFC] to-[#EE8A5C] bg-clip-text text-transparent">{{ heroAccent }}</span>
                </h1>
                <p data-reveal style="transition-delay: 160ms" class="mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-muted dark:text-[#9AA6BB]">
                    {{ site.heroSubtitle }}
                </p>
                <div data-reveal style="transition-delay: 240ms" class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                    <Link href="/tarify" class="btn-shine w-full rounded-full bg-gradient-to-r from-[#2B5CE0] to-[#5B62F0] px-8 py-4 font-bold text-white shadow-xl shadow-[#2B5CE0]/40 transition hover:-translate-y-0.5 sm:w-auto">Получить доступ</Link>
                    <Link href="/vozmozhnosti" class="w-full rounded-full border border-line bg-panel px-7 py-4 font-semibold text-ink transition hover:-translate-y-0.5 hover:bg-hoverbg sm:w-auto dark:border-white/15 dark:bg-white/5 dark:text-white dark:hover:bg-white/10">Как это работает</Link>
                </div>
                <p data-reveal style="transition-delay: 300ms" class="mt-5 text-sm text-muted2 dark:text-slate-500">{{ site.accessNote }}</p>

                <div data-reveal style="transition-delay: 360ms" class="mt-9 flex flex-wrap items-center justify-center gap-2.5">
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-line bg-panel px-4 py-2 text-sm text-muted dark:border-white/10 dark:bg-white/5 dark:text-slate-300"><Icon name="bolt" class="h-4 w-4 text-brand dark:text-[#8FB0FF]" /> Ответ за секунды</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-line bg-panel px-4 py-2 text-sm text-muted dark:border-white/10 dark:bg-white/5 dark:text-slate-300"><Icon name="clock" class="h-4 w-4 text-brand dark:text-[#8FB0FF]" /> Круглосуточно</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-line bg-panel px-4 py-2 text-sm text-muted dark:border-white/10 dark:bg-white/5 dark:text-slate-300"><Icon name="shield" class="h-4 w-4 text-brand dark:text-[#8FB0FF]" /> Серверы в РФ</span>
                    <span class="inline-flex items-center gap-1.5 rounded-full border border-line bg-panel px-4 py-2 text-sm text-muted dark:border-white/10 dark:bg-white/5 dark:text-slate-300"><Icon name="lock" class="h-4 w-4 text-brand dark:text-[#8FB0FF]" /> 152-ФЗ</span>
                </div>

                <!-- Мокап чата -->
                <div data-reveal style="transition-delay: 440ms" class="relative mx-auto mt-14 max-w-md">
                    <div aria-hidden="true" class="pointer-events-none absolute -inset-5" style="background: radial-gradient(circle at 70% 20%, rgba(238, 138, 92, 0.16), transparent 60%), radial-gradient(circle at 20% 80%, rgba(43, 92, 224, 0.22), transparent 60%); filter: blur(18px)"></div>
                    <div class="chat-mock relative rounded-3xl p-4 text-left">
                        <div class="flex items-center gap-2.5 border-b border-line pb-3 dark:border-white/10">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-[#2B5CE0] text-white shadow-sm"><Icon name="robot" class="h-5 w-5" /></span>
                            <div class="leading-tight">
                                <div class="text-sm font-semibold text-ink dark:text-white">Отклик</div>
                                <div class="flex items-center gap-1.5 text-[11px] text-emerald-400"><span class="live-dot"></span>на связи 24/7</div>
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
            </div>
        </section>

        <!-- Метрики: продолжение тёмной зоны героя, мягко закругляется в крем -->
        <section class="mkt-hero-tail relative -mt-px overflow-hidden px-6 pb-14 pt-2 dark:pb-20">
            <div ref="metricsEl" data-reveal class="relative mx-auto max-w-6xl overflow-hidden rounded-[28px] bg-[#0F1E3D] px-8 py-12 text-white">
                <div aria-hidden="true" class="pointer-events-none absolute -right-10 -top-16 h-72 w-72 rounded-full" style="background: radial-gradient(circle, rgba(43, 92, 224, 0.5), transparent 68%); filter: blur(10px)"></div>
                <div class="relative grid grid-cols-2 gap-8 text-center lg:grid-cols-4">
                    <div v-for="m in metrics" :key="m.label">
                        <div class="font-display text-4xl font-semibold tracking-tight sm:text-5xl" :class="m.prefix === '<' ? 'text-[#EE8A5C]' : 'text-white'">
                            {{ m.prefix }}{{ m.current }}{{ m.suffix }}
                        </div>
                        <div class="mt-2.5 text-sm text-white/65">{{ m.label }}</div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Что умеет — кратко, с переходом на «Возможности» -->
        <section class="mx-auto max-w-6xl px-6 py-16">
            <div data-reveal class="mb-10 max-w-2xl">
                <p class="text-sm font-semibold uppercase tracking-[0.09em] text-brand">Возможности</p>
                <h2 class="font-display mt-3 text-3xl font-semibold tracking-tight text-ink sm:text-4xl">Что умеет «Отклик»</h2>
                <p class="mt-3 text-muted">Виртуальный администратор, который встречает каждого клиента и доводит его до записи.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="(f, i) in teaser"
                    :key="f.title"
                    data-reveal
                    :style="{ transitionDelay: i * 70 + 'ms' }"
                    class="card-hover rounded-2xl p-7"
                    :class="i === teaser.length - 1 ? 'bg-[#2B5CE0] text-white shadow-xl shadow-[#2B5CE0]/30' : 'border border-line bg-panel'"
                >
                    <div class="font-display text-sm font-semibold" :class="i === teaser.length - 1 ? 'text-white/70' : 'text-warm'">{{ String(i + 1).padStart(2, '0') }}</div>
                    <h3 class="font-display mt-4 text-lg font-medium tracking-tight" :class="i === teaser.length - 1 ? 'text-white' : 'text-ink'">{{ f.title }}</h3>
                    <p class="mt-2.5 text-sm leading-relaxed" :class="i === teaser.length - 1 ? 'text-white/85' : 'text-muted'">{{ f.text }}</p>
                </div>
            </div>
            <div data-reveal class="mt-10 text-center">
                <Link href="/vozmozhnosti" class="btn-shine inline-block rounded-full bg-gradient-to-r from-[#2B5CE0] to-[#5B62F0] px-8 py-3.5 font-bold text-white shadow-lg shadow-[#2B5CE0]/30 transition hover:-translate-y-0.5">Все возможности →</Link>
            </div>
        </section>

        <!-- Ваши данные в безопасности -->
        <section class="mx-auto max-w-6xl px-6 py-16">
            <div data-reveal class="mb-12 text-center">
                <p class="text-sm font-semibold uppercase tracking-[0.09em] text-brand">Надёжность</p>
                <h2 class="font-display mt-3 text-3xl font-semibold tracking-tight text-ink sm:text-4xl">Ваши данные в безопасности</h2>
                <p class="mx-auto mt-3 max-w-2xl text-muted">Всё размещено в России и построено вокруг защиты вашего бизнеса.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="(r, i) in reliability" :key="r.title" data-reveal :style="{ transitionDelay: i * 70 + 'ms' }" class="card-hover group rounded-2xl border border-line bg-panel p-6">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-active text-brand"><Icon :name="r.icon" class="ico h-6 w-6" /></div>
                    <div class="font-display mt-4 font-medium tracking-tight text-ink">{{ r.title }}</div>
                    <p class="mt-1.5 text-sm leading-relaxed text-muted">{{ r.text }}</p>
                </div>
            </div>
        </section>

        <!-- Тарифы (на видном месте, перед финальным CTA) -->
        <section class="mx-auto max-w-6xl px-6 py-16">
            <div data-reveal class="mb-10 text-center">
                <p class="text-sm font-semibold uppercase tracking-[0.09em] text-brand">Цены</p>
                <h2 class="font-display mt-3 text-3xl font-semibold tracking-tight text-ink sm:text-4xl">Тарифы</h2>
                <p class="mx-auto mt-3 max-w-2xl text-muted">Пробный период включён в любой тариф — оцените результат до оплаты.</p>
            </div>
            <div class="grid items-stretch gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="(p, i) in pricing"
                    :key="p.name"
                    data-reveal
                    :style="{ transitionDelay: i * 90 + 'ms' }"
                    class="card-hover relative flex flex-col rounded-2xl p-7"
                    :class="p.highlight ? 'bg-gradient-to-br from-[#2B5CE0] to-[#4B4BD6] text-white shadow-2xl shadow-[#2B5CE0]/30' : 'border border-line bg-panel'"
                >
                    <div v-if="p.highlight" class="absolute right-5 top-5 rounded-full bg-warm px-3 py-1 text-xs font-bold text-white">Популярный</div>
                    <div class="text-lg font-bold" :class="p.highlight ? 'text-white' : 'text-ink'">{{ p.name }}</div>
                    <div class="mt-3 flex flex-wrap items-end gap-x-1.5">
                        <span class="font-display font-semibold leading-tight tracking-tight" :class="[/[0-9]/.test(p.price) ? 'text-3xl' : 'text-xl', p.highlight ? 'text-white' : 'text-ink']">{{ p.price }}</span>
                        <span class="pb-1 text-sm" :class="p.highlight ? 'text-white/70' : 'text-muted2'">{{ p.period }}</span>
                    </div>
                    <p class="mt-2 text-sm" :class="p.highlight ? 'text-white/80' : 'text-muted'">{{ p.note }}</p>
                    <ul class="mt-5 flex-1 space-y-2.5">
                        <li v-for="feat in p.features" :key="feat" class="flex items-start gap-2 text-sm" :class="p.highlight ? 'text-white/90' : 'text-muted'">
                            <span class="mt-0.5 flex h-4 w-4 flex-none items-center justify-center rounded-full" :class="p.highlight ? 'bg-white/20 text-white' : 'bg-active text-brand'"><Icon name="check" class="h-3 w-3" /></span>{{ feat }}
                        </li>
                    </ul>
                    <Link href="/contacts" class="mt-7 block rounded-full px-5 py-3 text-center font-bold transition hover:-translate-y-0.5" :class="p.highlight ? 'bg-white text-[#2B5CE0] shadow-lg' : 'border border-line bg-chip text-ink hover:bg-hoverbg'">{{ p.cta }}</Link>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="mx-auto max-w-6xl px-6 py-16">
            <div data-reveal class="cta-glass relative overflow-hidden rounded-[32px] px-6 py-16 text-center text-white sm:py-20">
                <div aria-hidden="true" class="pointer-events-none absolute -left-10 -top-20 h-80 w-80 rounded-full" style="background: radial-gradient(circle, rgba(255, 255, 255, 0.18), transparent 68%)"></div>
                <div aria-hidden="true" class="pointer-events-none absolute -bottom-24 -right-8 h-80 w-80 rounded-full" style="background: radial-gradient(circle, rgba(238, 138, 92, 0.4), transparent 66%)"></div>
                <div class="relative mx-auto max-w-2xl">
                    <h2 class="font-display text-3xl font-semibold tracking-tight sm:text-5xl">Запустите «Отклик» за вечер</h2>
                    <p class="mx-auto mt-5 max-w-xl text-lg text-white/85">{{ site.accessNote }}</p>
                    <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                        <Link href="/tarify" class="rounded-full bg-white px-8 py-4 font-bold text-[#2B5CE0] shadow-xl shadow-[#0F1E3D]/30 transition hover:-translate-y-0.5">Тарифы и доступ</Link>
                        <Link href="/contacts" class="rounded-full border border-white/30 bg-white/15 px-7 py-4 font-semibold text-white backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/25">Связаться с нами</Link>
                    </div>
                </div>
            </div>
        </section>
    </SiteLayout>
</template>
