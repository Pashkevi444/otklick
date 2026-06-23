<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';

interface Item {
    id: string;
    title: string;
    excerpt: string;
    published_at: string | null;
    is_new: boolean;
}
interface Page {
    data: Item[];
    current_page: number;
    last_page: number;
    total: number;
}

const props = defineProps<{ type: string; title: string; page: Page }>();

const base = computed(() => '/cabinet/news');
const fmt = (d: string | null): string =>
    d ? new Date(d.replace(' ', 'T')).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' }) : '';
</script>

<template>
    <Head :title="title" />

    <AppLayout :title="title">
        <div class="mx-auto max-w-3xl space-y-4">
            <p v-if="page.data.length === 0" class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-slate-400 dark:border-white/10 dark:bg-white/5">
                Пока ничего нет. Здесь будут появляться {{ title.toLowerCase() }}.
            </p>

            <!-- Лента: одна новость в ряд, крупными карточками -->
            <Link
                v-for="item in page.data"
                :key="item.id"
                :href="`${base}/${item.id}`"
                class="group block rounded-2xl border bg-white p-6 transition hover:-translate-y-0.5 hover:border-[#2E74B5] hover:shadow-lg hover:shadow-slate-200/60 sm:p-7 dark:bg-white/5 dark:hover:shadow-black/30"
                :class="item.is_new ? 'border-rose-200 dark:border-rose-500/25' : 'border-slate-200 dark:border-white/10'"
            >
                <div class="flex items-center gap-3 text-xs">
                    <span v-if="item.is_new" class="rounded-full bg-rose-500 px-2.5 py-0.5 text-[11px] font-bold text-white">новое</span>
                    <span v-if="item.published_at" class="text-slate-400">{{ fmt(item.published_at) }}</span>
                </div>
                <h2 class="mt-2.5 text-lg font-bold leading-snug text-[#1F4E79] sm:text-xl dark:text-sky-200">{{ item.title }}</h2>
                <p class="mt-2.5 line-clamp-3 leading-relaxed text-slate-600 dark:text-slate-300">{{ item.excerpt }}</p>
                <span class="mt-4 inline-flex items-center gap-1 text-sm font-medium text-[#2E74B5] transition-all group-hover:gap-2 dark:text-sky-300">Читать →</span>
            </Link>

            <!-- Пагинация -->
            <Pagination :current="page.current_page" :last="page.last_page" :total="page.total" />
        </div>
    </AppLayout>
</template>
