<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import Icon from '@/Components/Icon.vue';

interface Site {
    phone: string | null;
    email: string | null;
    telegram: string | null;
    legalName: string | null;
    inn: string | null;
    ogrnip: string | null;
    accessNote: string;
}
const props = defineProps<{ site: Site; loginUrl: string }>();

const tgUrl = props.site.telegram ? `https://t.me/${props.site.telegram}` : null;

const emailCopied = ref(false);
const copyEmail = (): void => {
    if (!props.site.email || !navigator.clipboard) return;
    navigator.clipboard.writeText(props.site.email).then(() => {
        emailCopied.value = true;
        window.setTimeout(() => (emailCopied.value = false), 2000);
    }).catch(() => {});
};
</script>

<template>
    <Head>
        <title>Контакты — «Отклик», AI-администратор для бизнеса</title>
        <meta name="description" content="Свяжитесь с командой «Отклик», чтобы получить доступ к AI-администратору для вашего бизнеса: телефон, почта, Telegram." />
        <meta property="og:title" content="Контакты — «Отклик»" />
    </Head>

    <SiteLayout :site="site" :login-url="loginUrl">
        <!-- Hero -->
        <section class="mx-auto max-w-4xl px-6 pt-16 pb-6 text-center sm:pt-20">
            <div data-reveal class="inline-flex items-center gap-2 glass rounded-full px-4 py-1.5 text-sm font-medium text-[#2E74B5] dark:text-sky-300">
                <Icon name="chat" class="h-4 w-4" /> Мы на связи
            </div>
            <h1 data-reveal style="transition-delay: 80ms" class="mt-6 text-4xl font-extrabold tracking-tight text-[#1F4E79] dark:text-sky-200 sm:text-5xl">Свяжитесь с нами</h1>
            <p data-reveal style="transition-delay: 140ms" class="mx-auto mt-5 max-w-2xl text-lg text-slate-600 dark:text-slate-300">{{ site.accessNote }}</p>
        </section>

        <!-- Карточки контактов -->
        <section class="mx-auto max-w-5xl px-6 py-8">
            <div class="grid gap-5 sm:grid-cols-3">
                <a
                    v-if="site.phone"
                    :href="`tel:${site.phone}`"
                    data-reveal
                    class="card-hover glass group flex flex-col items-center rounded-3xl p-7 text-center"
                >
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/70 text-[#2E74B5] shadow-sm transition group-hover:scale-110 dark:bg-white/10 dark:text-sky-300"><Icon name="phone" class="ico h-7 w-7" /></div>
                    <div class="mt-4 text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">Телефон</div>
                    <div class="mt-1 text-lg font-semibold text-[#1F4E79] dark:text-sky-200">{{ site.phone }}</div>
                    <div class="mt-1 text-sm text-slate-400">Позвонить</div>
                </a>

                <a
                    v-if="site.email"
                    :href="`mailto:${site.email}`"
                    :title="`Написать на ${site.email} (клик — скопировать)`"
                    data-reveal
                    style="transition-delay: 80ms"
                    class="card-hover glass group flex flex-col items-center rounded-3xl p-7 text-center"
                    @click="copyEmail"
                >
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/70 text-[#2E74B5] shadow-sm transition group-hover:scale-110 dark:bg-white/10 dark:text-sky-300"><Icon :name="emailCopied ? 'check' : 'mail'" class="ico h-7 w-7" /></div>
                    <div class="mt-4 text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">Email</div>
                    <div class="mt-1 break-all text-lg font-semibold text-[#1F4E79] dark:text-sky-200">{{ site.email }}</div>
                    <div class="mt-1 text-sm text-slate-400">{{ emailCopied ? 'Скопировано' : 'Написать письмо' }}</div>
                </a>

                <a
                    v-if="tgUrl"
                    :href="tgUrl"
                    target="_blank"
                    rel="noopener"
                    data-reveal
                    style="transition-delay: 160ms"
                    class="card-hover glass group flex flex-col items-center rounded-3xl p-7 text-center"
                >
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/70 text-[#2E74B5] shadow-sm transition group-hover:scale-110 dark:bg-white/10 dark:text-sky-300"><Icon name="send" class="ico h-7 w-7" /></div>
                    <div class="mt-4 text-xs uppercase tracking-wide text-slate-400 dark:text-slate-500">Telegram</div>
                    <div class="mt-1 text-lg font-semibold text-[#1F4E79] dark:text-sky-200">@{{ site.telegram }}</div>
                    <div class="mt-1 text-sm text-slate-400">Написать в Telegram</div>
                </a>
            </div>
        </section>

        <!-- CTA -->
        <section class="mx-auto max-w-6xl px-6 py-14">
            <div data-reveal class="cta-glass relative overflow-hidden rounded-[2rem] px-6 py-14 text-center text-white">
                <h2 class="text-3xl font-bold sm:text-4xl">Готовы подключить «Отклик»?</h2>
                <p class="mx-auto mt-3 max-w-2xl text-blue-50/90">Покажем, как бот будет отвечать вашим клиентам и записывать их — на пробном периоде, без оплаты.</p>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-3 text-sm">
                    <Link href="/tarify" class="rounded-2xl bg-white px-7 py-3.5 font-semibold text-[#1F4E79] transition hover:-translate-y-0.5 hover:bg-blue-50">Тарифы и доступ</Link>
                    <a :href="loginUrl" class="rounded-2xl bg-white/15 px-7 py-3.5 backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/25">Уже есть доступ? Войти</a>
                </div>
            </div>
        </section>
    </SiteLayout>
</template>
