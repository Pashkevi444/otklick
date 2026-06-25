<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';

interface NItem {
    id: string;
    type: string;
    icon: string;
    title: string;
    body: string | null;
    url: string | null;
    read: boolean;
    at: string;
}
interface PageMeta {
    current: number;
    last: number;
    total: number;
    from: number | null;
    to: number | null;
}
interface Option {
    value: string;
    label: string;
}

const props = defineProps<{
    notifications: NItem[];
    pagination: PageMeta;
    filters: { section: string };
    sections: Option[];
}>();

// CSRF из cookie (как у колокольчика) — эндпоинт «прочитать всё» отдаёт JSON, не Inertia.
const xsrf = (): string => {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
};

const markAll = async (): Promise<void> => {
    try {
        await fetch('/cabinet/notifications/read', {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf(), 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
    } catch {
        // игнор — перезагрузка ниже подтянет актуальное состояние
    }
    router.reload();
};

const filterBy = (section: string): void => {
    router.get('/cabinet/notifications/history', section ? { section } : {}, {
        preserveScroll: true,
        preserveState: true,
    });
};

const go = (item: NItem): void => {
    if (item.url) router.visit(item.url);
};

// Журнал — точные дата и время (не относительное «14 мин»): «18.06.2026, 14:32».
const when = (iso: string): string =>
    new Date(iso).toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
</script>

<template>
    <Head title="Уведомления" />

    <AppLayout>
        <div class="mx-auto max-w-3xl">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-[#2E74B5] dark:text-sky-300">Журнал</p>
                    <h1 class="text-2xl font-bold text-[#1F4E79] dark:text-sky-100">Уведомления</h1>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Все события сервиса, доступные вам по вашим правам.
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-[#2E74B5] transition hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-sky-300"
                    @click="markAll"
                >
                    Прочитать всё
                </button>
            </div>

            <!-- Фильтр по разделу -->
            <div class="mb-5 flex flex-wrap gap-2">
                <button
                    v-for="s in props.sections"
                    :key="s.value"
                    type="button"
                    class="rounded-full px-3.5 py-1.5 text-sm font-medium transition"
                    :class="props.filters.section === s.value
                        ? 'bg-[#2E74B5] text-white'
                        : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-300'"
                    @click="filterBy(s.value)"
                >
                    {{ s.label }}
                </button>
            </div>

            <!-- Список -->
            <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-white/10 dark:bg-white/5">
                <p v-if="props.notifications.length === 0" class="px-5 py-16 text-center text-sm text-slate-400">
                    Уведомлений пока нет
                </p>
                <component
                    :is="item.url ? 'button' : 'div'"
                    v-for="item in props.notifications"
                    :key="item.id"
                    type="button"
                    class="flex w-full items-start gap-3 border-b border-slate-100 px-5 py-3.5 text-left transition last:border-0 hover:bg-slate-50 dark:border-white/10 dark:hover:bg-white/5"
                    :class="!item.read ? 'bg-[#EAF2FB]/50 dark:bg-white/5' : ''"
                    @click="go(item)"
                >
                    <span class="mt-0.5 text-lg leading-5">{{ item.icon }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center justify-between gap-2">
                            <span class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ item.title }}</span>
                            <span class="shrink-0 text-xs text-slate-400">{{ when(item.at) }}</span>
                        </span>
                        <span v-if="item.body" class="mt-0.5 block truncate text-sm text-slate-500 dark:text-slate-400">{{ item.body }}</span>
                    </span>
                    <span v-if="!item.read" class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-rose-500"></span>
                </component>
            </div>

            <Pagination
                :current="props.pagination.current"
                :last="props.pagination.last"
                :total="props.pagination.total"
                :from="props.pagination.from ?? undefined"
                :to="props.pagination.to ?? undefined"
            />
        </div>
    </AppLayout>
</template>
