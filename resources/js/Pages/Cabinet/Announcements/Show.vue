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

const backHref = computed(() => '/cabinet/news');
const fmt = (d: string | null): string =>
    d ? new Date(d.replace(' ', 'T')).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' }) : '';
</script>

<template>
    <Head :title="item.title" />

    <AppLayout :title="title">
        <div class="mx-auto max-w-2xl">
            <Link :href="backHref" class="mb-4 inline-block text-sm font-semibold text-brand hover:underline">← Все {{ title.toLowerCase() }}</Link>

            <article class="otk-card p-6">
                <h1 class="font-display text-xl font-semibold text-ink">{{ item.title }}</h1>
                <p v-if="item.published_at" class="mt-1 text-xs text-muted2">{{ fmt(item.published_at) }}</p>
                <!-- Текст анонса — форматированный HTML от супер-админа (доверенный автор). -->
                <div class="rte mt-4 text-sm text-ink" v-html="item.body"></div>
            </article>
        </div>
    </AppLayout>
</template>
