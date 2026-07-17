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
            <div data-reveal class="inline-flex items-center gap-2 glass rounded-full px-4 py-1.5 text-sm font-semibold text-brand">
                <Icon name="chat" class="h-4 w-4" /> Мы на связи
            </div>
            <h1 data-reveal style="transition-delay: 80ms" class="font-display mt-6 text-4xl font-bold tracking-tight text-ink sm:text-5xl">Свяжитесь с нами</h1>
            <p data-reveal style="transition-delay: 140ms" class="mx-auto mt-5 max-w-2xl text-lg text-muted">{{ site.accessNote }}</p>
        </section>

        <!-- Карточки контактов -->
        <section class="mx-auto max-w-5xl px-6 py-8">
            <div class="grid gap-5 sm:grid-cols-3">
                <a
                    v-if="site.phone"
                    :href="`tel:${site.phone}`"
                    data-reveal
                    class="card-hover group flex flex-col items-center rounded-2xl border border-line bg-panel p-7 text-center"
                >
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-active text-brand transition group-hover:scale-110"><Icon name="phone" class="ico h-7 w-7" /></div>
                    <div class="mt-4 text-[11px] font-bold uppercase tracking-[0.09em] text-muted2">Телефон</div>
                    <div class="font-display mt-1 text-lg font-semibold tracking-tight text-ink">{{ site.phone }}</div>
                    <div class="mt-1 text-sm text-muted2">Позвонить</div>
                </a>

                <a
                    v-if="site.email"
                    :href="`mailto:${site.email}`"
                    :title="`Написать на ${site.email} (клик — скопировать)`"
                    data-reveal
                    style="transition-delay: 80ms"
                    class="card-hover group flex flex-col items-center rounded-2xl border border-line bg-panel p-7 text-center"
                    @click="copyEmail"
                >
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-active text-brand transition group-hover:scale-110"><Icon :name="emailCopied ? 'check' : 'mail'" class="ico h-7 w-7" /></div>
                    <div class="mt-4 text-[11px] font-bold uppercase tracking-[0.09em] text-muted2">Email</div>
                    <div class="font-display mt-1 break-all text-lg font-semibold tracking-tight text-ink">{{ site.email }}</div>
                    <div class="mt-1 text-sm text-muted2">{{ emailCopied ? 'Скопировано' : 'Написать письмо' }}</div>
                </a>

                <a
                    v-if="tgUrl"
                    :href="tgUrl"
                    target="_blank"
                    rel="noopener"
                    data-reveal
                    style="transition-delay: 160ms"
                    class="card-hover group flex flex-col items-center rounded-2xl border border-line bg-panel p-7 text-center"
                >
                    <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-active text-brand transition group-hover:scale-110"><Icon name="send" class="ico h-7 w-7" /></div>
                    <div class="mt-4 text-[11px] font-bold uppercase tracking-[0.09em] text-muted2">Telegram</div>
                    <div class="font-display mt-1 text-lg font-semibold tracking-tight text-ink">@{{ site.telegram }}</div>
                    <div class="mt-1 text-sm text-muted2">Написать в Telegram</div>
                </a>
            </div>
        </section>

        <!-- CTA -->
        <section class="mx-auto max-w-6xl px-6 py-14">
            <div data-reveal class="glow-frame" style="--gf-radius: 2rem">
                <div class="cta-glass relative overflow-hidden rounded-[calc(2rem-2px)] px-6 py-14 text-center text-white">
                    <h2 class="font-display text-3xl font-semibold tracking-tight sm:text-4xl">Готовы подключить «Отклик»?</h2>
                    <p class="mx-auto mt-3 max-w-2xl text-white/85">Покажем, как бот будет отвечать вашим клиентам и записывать их — на пробном периоде, без оплаты.</p>
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-3 text-sm">
                        <Link href="/tarify" class="rounded-full bg-white px-7 py-3.5 font-bold text-[#2B5CE0] transition hover:-translate-y-0.5">Тарифы и доступ</Link>
                        <a :href="loginUrl" class="rounded-full border border-white/30 bg-white/15 px-7 py-3.5 font-semibold text-white backdrop-blur transition hover:-translate-y-0.5 hover:bg-white/25">Уже есть доступ? Войти</a>
                    </div>
                </div>
            </div>
        </section>
    </SiteLayout>
</template>
