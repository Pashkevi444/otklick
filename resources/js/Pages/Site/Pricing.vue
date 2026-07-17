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
            <h1 data-reveal class="font-display text-4xl font-bold tracking-tight text-ink sm:text-5xl">Тарифы</h1>
            <p data-reveal style="transition-delay: 100ms" class="mx-auto mt-5 max-w-2xl text-lg text-muted">Пробный период включён в любой тариф — оцените результат до оплаты.</p>
        </section>

        <!-- Тарифы -->
        <section class="mx-auto max-w-6xl px-6 py-10">
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
            <div data-reveal class="glow-frame" style="--gf-radius: 2rem">
                <div class="cta-glass relative overflow-hidden rounded-[calc(2rem-2px)] px-6 py-16 text-center text-white">
                    <h2 class="font-display text-3xl font-semibold tracking-tight sm:text-4xl">Подключите «Отклик» к своему бизнесу</h2>
                    <p class="mx-auto mt-3 max-w-2xl text-white/85">{{ site.accessNote }}</p>
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-3 text-sm">
                        <Link href="/contacts" class="rounded-full bg-white px-7 py-3.5 font-bold text-[#2B5CE0] transition hover:-translate-y-0.5">Связаться с нами</Link>
                        <a :href="loginUrl" class="rounded-full border border-white/30 bg-white/15 px-7 py-3.5 font-semibold text-white backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/25">Уже есть доступ? Войти</a>
                    </div>
                </div>
            </div>
        </section>
    </SiteLayout>
</template>
