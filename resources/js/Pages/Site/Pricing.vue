<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import Icon from '@/Components/Icon.vue';
import { pricing } from '@/marketing';

interface Site {
    phone: string | null;
    email: string | null;
    telegram: string | null;
    legalName: string | null;
    inn: string | null;
    ogrnip: string | null;
    accessNote: string;
}
defineProps<{ site: Site; loginUrl: string }>();
</script>

<template>
    <Head>
        <title>Тарифы «Отклик» — AI-администратор для бизнеса, пробный период включён</title>
        <meta name="description" content="Тарифы «Отклик»: пробный период бесплатно, «Стандарт» и «Макс» с CRM, сценариями и аналитикой, индивидуальный для корпоративных клиентов. Оцените результат до оплаты." />
        <meta property="og:title" content="Тарифы «Отклик»" />
    </Head>

    <SiteLayout :site="site" :login-url="loginUrl">
        <section class="mx-auto max-w-6xl px-6 pt-16 pb-6 text-center sm:pt-20">
            <h1 data-reveal class="text-4xl font-extrabold tracking-tight text-[#1F4E79] dark:text-sky-200 sm:text-5xl">Тарифы</h1>
            <p data-reveal style="transition-delay: 100ms" class="mx-auto mt-5 max-w-2xl text-lg text-slate-600 dark:text-slate-300">Пробный период включён в любой тариф — оцените результат до оплаты.</p>
        </section>

        <!-- Тарифы -->
        <section class="mx-auto max-w-6xl px-6 py-10">
            <div class="grid items-start gap-5 sm:grid-cols-2 lg:grid-cols-4">
                <div
                    v-for="(p, i) in pricing"
                    :key="p.name"
                    data-reveal
                    :style="{ transitionDelay: i * 90 + 'ms' }"
                    class="card-hover glass relative rounded-3xl p-7"
                    :class="p.highlight ? 'ring-2 ring-[#2E74B5]' : ''"
                >
                    <div v-if="p.highlight" class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-[#2E74B5] px-3 py-1 text-xs font-medium text-white shadow">Популярный</div>
                    <div class="text-lg font-bold text-[#1F4E79] dark:text-sky-200">{{ p.name }}</div>
                    <div class="mt-3 flex flex-wrap items-end gap-x-1.5">
                        <span class="font-extrabold leading-tight text-[#1F4E79] dark:text-sky-200" :class="/[0-9]/.test(p.price) ? 'text-3xl' : 'text-xl'">{{ p.price }}</span>
                        <span class="pb-1 text-sm text-slate-400 dark:text-slate-500">{{ p.period }}</span>
                    </div>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ p.note }}</p>
                    <ul class="mt-5 space-y-2.5">
                        <li v-for="feat in p.features" :key="feat" class="flex items-start gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <span class="mt-0.5 flex h-4 w-4 flex-none items-center justify-center rounded-full bg-[#2E74B5]/10 text-[#2E74B5] dark:text-sky-300"><Icon name="check" class="h-3 w-3" /></span>{{ feat }}
                        </li>
                    </ul>
                    <Link href="/contacts" class="mt-7 block rounded-xl px-5 py-3 text-center font-semibold transition hover:-translate-y-0.5" :class="p.highlight ? 'bg-[#2E74B5] text-white shadow-lg shadow-[#2E74B5]/25 hover:bg-[#255f96]' : 'border border-[#2E74B5]/30 bg-white/60 text-[#1F4E79] dark:bg-white/10 dark:text-sky-200'">{{ p.cta }}</Link>
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="mx-auto max-w-6xl px-6 py-16">
            <div data-reveal class="cta-glass relative overflow-hidden rounded-[2rem] px-6 py-16 text-center text-white">
                <h2 class="text-3xl font-bold sm:text-4xl">Подключите «Отклик» к своему бизнесу</h2>
                <p class="mx-auto mt-3 max-w-2xl text-blue-50/90">{{ site.accessNote }}</p>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-3 text-sm">
                    <Link href="/contacts" class="rounded-2xl bg-white px-7 py-3.5 font-semibold text-[#1F4E79] transition hover:-translate-y-0.5 hover:bg-blue-50">Связаться с нами</Link>
                    <a :href="loginUrl" class="rounded-2xl bg-white/15 px-7 py-3.5 backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/25">Уже есть доступ? Войти</a>
                </div>
            </div>
        </section>
    </SiteLayout>
</template>
