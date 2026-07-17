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
            <p v-if="page.data.length === 0" class="otk-card p-10 text-center text-muted2">
                Пока ничего нет. Здесь будут появляться {{ title.toLowerCase() }}.
            </p>

            <!-- Лента: одна новость в ряд, крупными карточками -->
            <Link
                v-for="item in page.data"
                :key="item.id"
                :href="`${base}/${item.id}`"
                class="group block rounded-2xl border bg-panel p-6 transition hover:-translate-y-0.5 hover:border-brand hover:shadow-lg hover:shadow-[rgba(16,28,51,0.06)] sm:p-7"
                :class="item.is_new ? 'border-brand' : 'border-line'"
            >
                <div class="flex items-center gap-3 text-xs">
                    <span v-if="item.is_new" class="rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-[11px] font-bold text-emerald-600 dark:text-emerald-400">новое</span>
                    <span v-if="item.published_at" class="text-muted2">{{ fmt(item.published_at) }}</span>
                </div>
                <h2 class="mt-2.5 font-display text-lg font-semibold leading-snug text-ink sm:text-xl">{{ item.title }}</h2>
                <p class="mt-2.5 line-clamp-3 leading-relaxed text-muted">{{ item.excerpt }}</p>
                <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-brand transition-all group-hover:gap-2">Читать →</span>
            </Link>

            <!-- Пагинация -->
            <Pagination :current="page.current_page" :last="page.last_page" :total="page.total" />
        </div>
    </AppLayout>
</template>
