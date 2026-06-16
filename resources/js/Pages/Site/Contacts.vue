<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import Logo from '@/Components/Logo.vue';

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

const tgUrl = computed(() => (props.site.telegram ? `https://t.me/${props.site.telegram}` : null));
</script>

<template>
    <Head>
        <title>Контакты — Отклик</title>
        <meta name="description" content="Свяжитесь с командой «Отклик», чтобы получить доступ к AI-администратору для вашего бизнеса." />
    </Head>

    <div class="relative min-h-screen overflow-x-hidden text-slate-800 dark:text-slate-200">
        <div class="bg-base"></div>
        <div class="orbs" aria-hidden="true">
            <span class="orb orb-1"></span>
            <span class="orb orb-2"></span>
            <span class="orb orb-3"></span>
        </div>

        <header class="sticky top-0 z-30">
            <div class="mx-auto mt-3 max-w-6xl px-4">
                <div class="glass flex h-14 items-center justify-between rounded-2xl px-5">
                    <Link href="/"><Logo class="text-lg text-[#1F4E79] dark:text-white" /></Link>
                    <div class="flex items-center gap-2">
                        <ThemeToggle />
                        <a :href="loginUrl" class="rounded-xl bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white shadow-lg shadow-[#2E74B5]/25 transition hover:-translate-y-0.5 hover:bg-[#255f96]">Войти</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="content-enter mx-auto max-w-2xl px-6 py-16">
            <h1 class="text-3xl font-bold text-[#1F4E79] dark:text-sky-200">Контакты</h1>
            <p class="mt-3 text-slate-600 dark:text-slate-300">{{ site.accessNote }}</p>

            <div class="mt-8 space-y-3">
                <a v-if="site.phone" :href="`tel:${site.phone}`" class="glass card-hover block rounded-2xl p-4">
                    <div class="text-xs text-slate-400 dark:text-slate-500">Телефон</div>
                    <div class="font-medium text-slate-800 dark:text-slate-100">{{ site.phone }}</div>
                </a>
                <a v-if="site.email" :href="`mailto:${site.email}`" class="glass card-hover block rounded-2xl p-4">
                    <div class="text-xs text-slate-400 dark:text-slate-500">Email</div>
                    <div class="font-medium text-slate-800 dark:text-slate-100">{{ site.email }}</div>
                </a>
                <a v-if="tgUrl" :href="tgUrl" target="_blank" class="glass card-hover block rounded-2xl p-4">
                    <div class="text-xs text-slate-400 dark:text-slate-500">Telegram</div>
                    <div class="font-medium text-slate-800 dark:text-slate-100">@{{ site.telegram }}</div>
                </a>
            </div>

            <Link href="/" class="mt-8 inline-block text-sm text-[#2E74B5] hover:underline dark:text-sky-300">← На главную</Link>

            <div v-if="site.legalName || site.inn || site.ogrnip" class="mt-10 border-t border-white/60 pt-6 text-xs leading-relaxed text-slate-400 dark:border-white/10 dark:text-slate-500">
                <span v-if="site.legalName">{{ site.legalName }}</span>
                <span v-if="site.inn"> · ИНН {{ site.inn }}</span>
                <span v-if="site.ogrnip"> · ОГРНИП {{ site.ogrnip }}</span>
            </div>
        </main>
    </div>
</template>

<style scoped>
.glass {
    background: rgba(255, 255, 255, 0.6);
    backdrop-filter: blur(18px) saturate(170%);
    -webkit-backdrop-filter: blur(18px) saturate(170%);
    border: 1px solid rgba(255, 255, 255, 0.6);
    box-shadow: 0 8px 32px rgba(31, 78, 121, 0.12);
}
html.dark .glass {
    background: rgba(20, 30, 48, 0.55);
    border-color: rgba(255, 255, 255, 0.1);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.45);
}

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
}
html.dark .orb {
    opacity: 0.28;
}
.orb-1 {
    width: 380px;
    height: 380px;
    background: #7cc0ff;
    top: -80px;
    left: -60px;
    animation: floaty 18s ease-in-out infinite;
}
.orb-2 {
    width: 320px;
    height: 320px;
    background: #b9a8ff;
    top: 30%;
    right: -80px;
    animation: floaty 24s ease-in-out infinite reverse;
}
.orb-3 {
    width: 260px;
    height: 260px;
    background: #7df3e1;
    bottom: 8%;
    left: 6%;
    animation: floaty 20s ease-in-out infinite;
}

.card-hover {
    transition:
        transform 0.35s cubic-bezier(0.2, 0.7, 0.2, 1),
        box-shadow 0.35s ease;
}
.card-hover:hover {
    transform: translateY(-4px);
    box-shadow: 0 18px 40px rgba(31, 78, 121, 0.16);
}
html.dark .card-hover:hover {
    box-shadow: 0 18px 40px rgba(0, 0, 0, 0.5);
}

.content-enter {
    animation: enter 0.7s cubic-bezier(0.2, 0.7, 0.2, 1) both;
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
        transform: translate(24px, -30px) scale(1.06);
    }
}
@keyframes enter {
    from {
        opacity: 0;
        transform: translateY(22px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .bg-base,
    .orb {
        animation: none;
    }
    .content-enter {
        animation: none;
    }
}
</style>
