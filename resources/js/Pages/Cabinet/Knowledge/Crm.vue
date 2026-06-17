<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { onUnmounted, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

interface Entry {
    id: string;
    title: string;
    content: string;
}

const props = defineProps<{
    connected: boolean;
    lastSyncedAt: string | null;
    groups: Record<string, Entry[]>;
}>();

const syncing = ref(false);
const percent = ref(0);
let timer: ReturnType<typeof setInterval> | null = null;

const stopPolling = (): void => {
    if (timer !== null) {
        clearInterval(timer);
        timer = null;
    }
};

const poll = async (): Promise<void> => {
    const res = await fetch('/cabinet/knowledge-crm/status', {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });
    const data: { percent: number; state: string } = await res.json();
    percent.value = data.percent ?? 0;

    if (data.state === 'done') {
        percent.value = 100;
        stopPolling();
        router.reload({ only: ['groups', 'lastSyncedAt'], onFinish: () => (syncing.value = false) });
    } else if (data.state === 'failed') {
        stopPolling();
        syncing.value = false;
    }
};

const sync = (): void => {
    syncing.value = true;
    percent.value = 0;
    router.post('/cabinet/knowledge-crm/sync', {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            void poll();
            timer = setInterval(() => void poll(), 1500);
        },
        onError: () => (syncing.value = false),
    });
};

onUnmounted(stopPolling);

const hasEntries = (): boolean => Object.keys(props.groups).length > 0;

const formattedSync = (): string =>
    props.lastSyncedAt ? new Date(props.lastSyncedAt).toLocaleString('ru-RU') : '—';
</script>

<template>
    <Head title="База знаний из CRM" />

    <AppLayout title="База знаний из CRM">
        <p class="mb-3 max-w-2xl text-sm text-slate-500">
            Услуги, цены, мастера и филиал, выгруженные из вашей CRM. Эти записи бот использует как
            <b>приоритетные</b> (они всегда актуальнее) и редактировать их вручную нельзя — обновляются из CRM.
            Ваша обычная «База знаний» при этом не трогается.
        </p>

        <div class="mb-5 flex items-center gap-3">
            <button
                type="button"
                :disabled="!connected || syncing"
                class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white transition hover:-translate-y-0.5 disabled:opacity-50"
                @click="sync"
            >
                {{ syncing ? `Загрузка… ${percent}%` : '🔄 Загрузить данные из CRM' }}
            </button>
            <span v-if="!syncing" class="text-xs text-slate-400">Обновлено: {{ formattedSync() }}</span>
        </div>

        <!-- Прогресс выгрузки из CRM -->
        <div v-if="syncing" class="mb-5 max-w-md">
            <div class="h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-white/10">
                <div
                    class="h-full rounded-full bg-[#2E74B5] transition-all duration-500"
                    :style="{ width: percent + '%' }"
                ></div>
            </div>
            <div class="mt-1 text-xs text-slate-400">Загружаем данные из CRM… {{ percent }}%</div>
        </div>

        <div v-if="!connected" class="rounded-xl border border-amber-300/50 bg-amber-50 p-4 text-sm text-amber-900">
            Сначала подключите CRM в разделе «Интеграции».
        </div>

        <div v-else-if="!hasEntries()" class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500">
            Пока пусто. Нажмите «Загрузить данные из CRM» — записи появятся через минуту (загрузка идёт в фоне).
        </div>

        <div v-else class="space-y-6">
            <section v-for="(entries, category) in groups" :key="category">
                <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-slate-400">{{ category }}</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="entry in entries"
                        :key="entry.id"
                        class="rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5"
                    >
                        <div class="font-medium text-[#1F4E79] dark:text-sky-200">{{ entry.title }}</div>
                        <div class="mt-1 text-sm text-slate-500">{{ entry.content }}</div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
