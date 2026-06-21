<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

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

const goPage = (p: number): void => {
    router.get(base.value, { page: p }, { preserveScroll: true, preserveState: true });
};
</script>

<template>
    <Head :title="title" />

    <AppLayout :title="title">
        <div class="space-y-5">
            <p v-if="page.data.length === 0" class="rounded-2xl border border-slate-200 bg-white p-8 text-center text-slate-400 dark:border-white/10 dark:bg-white/5">
                Пока ничего нет. Здесь будут появляться {{ title.toLowerCase() }}.
            </p>

            <!-- Лента на всю ширину сеткой -->
            <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                <Link
                    v-for="item in page.data"
                    :key="item.id"
                    :href="`${base}/${item.id}`"
                    class="flex flex-col rounded-2xl border border-slate-200 bg-white p-5 transition hover:-translate-y-0.5 hover:border-[#2E74B5] hover:shadow-md dark:border-white/10 dark:bg-white/5"
                >
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="font-semibold text-[#1F4E79] dark:text-sky-200">{{ item.title }}</h2>
                        <span v-if="item.is_new" class="flex-none rounded-full bg-rose-500 px-2 py-0.5 text-[11px] font-bold text-white">новое</span>
                    </div>
                    <p v-if="item.published_at" class="mt-0.5 text-xs text-slate-400">{{ fmt(item.published_at) }}</p>
                    <p class="mt-2 line-clamp-4 text-sm text-slate-600 dark:text-slate-300">{{ item.excerpt }}</p>
                    <span class="mt-3 inline-block text-xs font-medium text-[#2E74B5]">Читать →</span>
                </Link>
            </div>

            <!-- Пагинация -->
            <div v-if="page.last_page > 1" class="flex items-center justify-center gap-3 pt-2">
                <button
                    type="button"
                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm disabled:opacity-40 dark:border-white/15"
                    :disabled="page.current_page <= 1"
                    @click="goPage(page.current_page - 1)"
                >
                    ← Назад
                </button>
                <span class="text-sm text-slate-500">Стр. {{ page.current_page }} из {{ page.last_page }}</span>
                <button
                    type="button"
                    class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm disabled:opacity-40 dark:border-white/15"
                    :disabled="page.current_page >= page.last_page"
                    @click="goPage(page.current_page + 1)"
                >
                    Вперёд →
                </button>
            </div>
        </div>
    </AppLayout>
</template>
