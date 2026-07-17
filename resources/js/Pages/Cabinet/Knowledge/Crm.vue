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
    <Head title="База знаний из YClients" />

    <AppLayout title="База знаний из YClients">
        <p class="mb-3 max-w-2xl text-sm text-muted">
            Услуги, цены, мастера и филиал, выгруженные из YClients. Эти записи бот использует как
            <b>приоритетные</b> (они всегда актуальнее) и редактировать их вручную нельзя — обновляются из YClients.
            Ваша обычная «База знаний» при этом не трогается.
        </p>

        <div class="mb-5 flex items-center gap-3">
            <button
                type="button"
                :disabled="!connected || syncing"
                class="otk-btn-primary disabled:opacity-50"
                @click="sync"
            >
                {{ syncing ? `Загрузка… ${percent}%` : '🔄 Загрузить данные из YClients' }}
            </button>
            <span v-if="!syncing" class="text-xs text-muted2">Обновлено: {{ formattedSync() }}</span>
        </div>

        <!-- Прогресс выгрузки из CRM -->
        <div v-if="syncing" class="mb-5 max-w-md">
            <div class="h-2 w-full overflow-hidden rounded-full bg-chip">
                <div
                    class="h-full rounded-full bg-brand transition-all duration-500"
                    :style="{ width: percent + '%' }"
                ></div>
            </div>
            <div class="mt-1 text-xs text-muted2">Загружаем данные из YClients… {{ percent }}%</div>
        </div>

        <div v-if="!connected" class="rounded-2xl border border-[#EE8A5C]/30 bg-[#EE8A5C]/10 p-4 text-sm font-semibold text-warm">
            Сначала подключите YClients в разделе «YClients».
        </div>

        <div v-else-if="!hasEntries()" class="otk-card p-6 text-sm text-muted">
            Пока пусто. Нажмите «Загрузить данные из YClients» — записи появятся через минуту (загрузка идёт в фоне).
        </div>

        <div v-else class="space-y-6">
            <section v-for="(entries, category) in groups" :key="category">
                <h2 class="mb-2 text-[11px] font-bold uppercase tracking-[0.09em] text-muted2">{{ category }}</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div
                        v-for="entry in entries"
                        :key="entry.id"
                        class="rounded-2xl border border-line bg-panel p-4"
                    >
                        <div class="font-semibold text-ink">{{ entry.title }}</div>
                        <div class="mt-1 text-sm text-muted">{{ entry.content }}</div>
                    </div>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
