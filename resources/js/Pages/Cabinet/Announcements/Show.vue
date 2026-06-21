<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface Item {
    id: string;
    title: string;
    body: string;
    published_at: string | null;
}

const props = defineProps<{ type: string; title: string; item: Item }>();

const backHref = computed(() => (props.type === 'news' ? '/cabinet/news' : '/cabinet/updates'));
const fmt = (d: string | null): string =>
    d ? new Date(d.replace(' ', 'T')).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' }) : '';
</script>

<template>
    <Head :title="item.title" />

    <AppLayout :title="title">
        <div class="mx-auto max-w-2xl">
            <Link :href="backHref" class="mb-4 inline-block text-sm text-[#2E74B5] hover:underline">← Все {{ title.toLowerCase() }}</Link>

            <article class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-white/10 dark:bg-white/5">
                <h1 class="text-xl font-bold text-[#1F4E79] dark:text-sky-200">{{ item.title }}</h1>
                <p v-if="item.published_at" class="mt-1 text-xs text-slate-400">{{ fmt(item.published_at) }}</p>
                <!-- Текст анонса — форматированный HTML от супер-админа (доверенный автор). -->
                <div class="rte mt-4 text-sm text-slate-700 dark:text-slate-200" v-html="item.body"></div>
            </article>
        </div>
    </AppLayout>
</template>
