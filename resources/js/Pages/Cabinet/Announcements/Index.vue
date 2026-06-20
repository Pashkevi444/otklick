<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface Item {
    id: string;
    title: string;
    body: string;
    published_at: string | null;
    is_new: boolean;
}

defineProps<{ type: string; title: string; items: Item[] }>();

const fmt = (d: string | null): string => (d ? new Date(d.replace(' ', 'T')).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' }) : '');
</script>

<template>
    <Head :title="title" />

    <AppLayout :title="title">
        <div class="mx-auto max-w-2xl space-y-4">
            <p v-if="items.length === 0" class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-400 dark:border-white/10 dark:bg-white/5">
                Пока ничего нет. Здесь будут появляться {{ title.toLowerCase() }}.
            </p>

            <article
                v-for="item in items"
                :key="item.id"
                class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5"
            >
                <div class="flex items-start justify-between gap-3">
                    <h2 class="font-semibold text-[#1F4E79] dark:text-sky-200">{{ item.title }}</h2>
                    <span v-if="item.is_new" class="flex-none rounded-full bg-rose-500 px-2 py-0.5 text-[11px] font-bold text-white">новое</span>
                </div>
                <p v-if="item.published_at" class="mt-0.5 text-xs text-slate-400">{{ fmt(item.published_at) }}</p>
                <p class="mt-3 whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">{{ item.body }}</p>
            </article>
        </div>
    </AppLayout>
</template>
