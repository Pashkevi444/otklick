<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

interface Site {
    phone: string | null;
    email: string | null;
    telegram: string | null;
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

    <div class="min-h-screen bg-slate-50 text-slate-800">
        <header class="bg-white border-b border-slate-100">
            <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
                <Link href="/" class="font-bold text-lg text-[#1F4E79]">Отклик</Link>
                <a :href="loginUrl" class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96]">Войти</a>
            </div>
        </header>

        <main class="max-w-2xl mx-auto px-6 py-16">
            <h1 class="text-3xl font-bold text-[#1F4E79]">Контакты</h1>
            <p class="mt-3 text-slate-600">{{ site.accessNote }}</p>

            <div class="mt-8 space-y-3">
                <a v-if="site.phone" :href="`tel:${site.phone}`" class="block bg-white rounded-xl border border-slate-200 p-4 hover:border-[#2E74B5]">
                    <div class="text-xs text-slate-400">Телефон</div>
                    <div class="font-medium text-slate-800">{{ site.phone }}</div>
                </a>
                <a v-if="site.email" :href="`mailto:${site.email}`" class="block bg-white rounded-xl border border-slate-200 p-4 hover:border-[#2E74B5]">
                    <div class="text-xs text-slate-400">Email</div>
                    <div class="font-medium text-slate-800">{{ site.email }}</div>
                </a>
                <a v-if="tgUrl" :href="tgUrl" target="_blank" class="block bg-white rounded-xl border border-slate-200 p-4 hover:border-[#2E74B5]">
                    <div class="text-xs text-slate-400">Telegram</div>
                    <div class="font-medium text-slate-800">@{{ site.telegram }}</div>
                </a>
            </div>

            <Link href="/" class="inline-block mt-8 text-sm text-[#2E74B5] hover:underline">← На главную</Link>
        </main>
    </div>
</template>
