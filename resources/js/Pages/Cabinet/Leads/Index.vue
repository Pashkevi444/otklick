<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import CustomFieldInputs, { type FieldDef } from '@/Components/CustomFieldInputs.vue';
import CustomFieldsManager from '@/Components/CustomFieldsManager.vue';
import CrmGrid from '@/Components/CrmGrid.vue';
import type { SavedView } from '@/Components/FilterBar.vue';
import { type ColumnDef, type Row } from '@/lib/grid';
import { useCan } from '@/composables/useCan';

interface ClientRef {
    id: string;
    name: string;
    phone: string | null;
}
interface Lead {
    id: string;
    title: string | null;
    status: string;
    statusLabel: string;
    source: string;
    notes: string | null;
    client: ClientRef | null;
    deal_id: string | null;
    created_at: string | null;
}
interface PickerClient {
    id: string;
    name: string;
    phone: string | null;
}

const props = defineProps<{ leads: Lead[]; clients: PickerClient[]; fields: FieldDef[]; views: SavedView[] }>();

const canEdit = useCan()('leads.edit');
const showFields = ref(false);

// --- Табличный вид (универсальный грид) ---
const viewMode = ref<'cards' | 'table'>('cards');
const columns = computed<ColumnDef[]>(() => [
    { key: 'title', label: 'Название', type: 'text', always: true },
    { key: 'client.name', label: 'Клиент', type: 'text' },
    { key: 'client.phone', label: 'Телефон', type: 'text' },
    { key: 'statusLabel', label: 'Статус', type: 'badge', options: ['Новый', 'В работе', 'В сделке', 'Отклонён'] },
    { key: 'source_label', label: 'Источник', type: 'badge', options: ['Из диалога', 'Вручную'] },
    { key: 'created_at', label: 'Создан', type: 'date' },
    ...props.fields.map((f): ColumnDef => ({ key: `custom.${f.key}`, label: f.label, type: f.type, options: f.options })),
]);
const gridRows = computed<Row[]>(() =>
    props.leads.map((l) => ({
        ...l,
        title: l.title || l.client?.name || 'Без названия',
        source_label: l.source === 'bot' ? 'Из диалога' : 'Вручную',
    })),
);

const statusClass = (s: string): string =>
    ({
        new: 'bg-sky-100 text-sky-700 dark:bg-sky-400/15 dark:text-sky-300',
        working: 'bg-amber-100 text-amber-700 dark:bg-amber-400/15 dark:text-amber-300',
        converted: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300',
        dismissed: 'bg-slate-200 text-slate-500 dark:bg-white/10 dark:text-slate-400',
    })[s] ?? 'bg-slate-100 text-slate-500';

const convert = (l: Lead): void => router.post(`/cabinet/leads/${l.id}/convert`, {}, { preserveScroll: true });
const dismiss = (l: Lead): void => router.put(`/cabinet/leads/${l.id}`, { status: 'dismissed' }, { preserveScroll: true });
const remove = (l: Lead): void => {
    if (confirm('Удалить лид?')) router.delete(`/cabinet/leads/${l.id}`, { preserveScroll: true });
};

const showForm = ref(false);
const form = useForm<{ title: string; client_id: string; notes: string; custom: Record<string, unknown> }>({ title: '', client_id: '', notes: '', custom: {} });
const openCreate = (): void => {
    form.reset();
    form.custom = {};
    showForm.value = true;
};
const submit = (): void => {
    form.post('/cabinet/leads', {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            form.reset();
        },
    });
};
</script>

<template>
    <Head title="Лиды" />

    <AppLayout title="Лиды">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <p class="max-w-2xl text-sm text-slate-500">
                Входящие обращения — из диалогов бота (по контактной форме) и заведённые вручную. Разбирайте и
                конвертируйте в сделки.
            </p>
            <div class="flex flex-wrap items-center gap-3">
                <div class="inline-flex rounded-lg border border-slate-200 p-0.5 dark:border-white/10">
                    <button type="button" class="rounded-md px-3 py-1 text-sm font-medium" :class="viewMode === 'cards' ? 'bg-[#2E74B5] text-white' : 'text-slate-500'" @click="viewMode = 'cards'">Карточки</button>
                    <button type="button" class="rounded-md px-3 py-1 text-sm font-medium" :class="viewMode === 'table' ? 'bg-[#2E74B5] text-white' : 'text-slate-500'" @click="viewMode = 'table'">Таблица</button>
                </div>
                <button v-if="canEdit" type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:border-[#2E74B5]/40 hover:text-[#2E74B5] dark:border-white/15 dark:text-slate-300" @click="showFields = true">
                    ⚙ Поля
                </button>
                <button v-if="canEdit" type="button" class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96]" @click="openCreate">
                    + Новый лид
                </button>
            </div>
        </div>

        <CustomFieldsManager v-if="showFields" entity="lead" :fields="fields" @close="showFields = false" />

        <CrmGrid v-if="viewMode === 'table'" entity="lead" :columns="columns" :rows="gridRows" :views="views" />

        <p v-else-if="leads.length === 0" class="rounded-2xl border border-slate-200 bg-white p-10 text-center text-slate-400 dark:border-white/10 dark:bg-white/5">
            Пока нет лидов. Как только клиент пройдёт контактную форму у бота — лид появится здесь.
        </p>

        <div v-else class="space-y-3">
            <div
                v-for="l in leads"
                :key="l.id"
                class="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-5 sm:flex-row sm:items-center sm:justify-between dark:border-white/10 dark:bg-white/5"
            >
                <div class="min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="font-medium text-slate-800 dark:text-slate-100">{{ l.title || l.client?.name || 'Без названия' }}</span>
                        <span class="rounded-full px-2 py-0.5 text-[11px] font-medium" :class="statusClass(l.status)">{{ l.statusLabel }}</span>
                        <span class="rounded-full px-2 py-0.5 text-[11px]" :class="l.source === 'bot' ? 'bg-[#EAF2FB] text-[#2E74B5] dark:bg-white/10 dark:text-sky-300' : 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-300'">{{ l.source === 'bot' ? 'из диалога' : 'вручную' }}</span>
                    </div>
                    <div class="mt-1 flex flex-wrap gap-x-3 text-xs text-slate-400">
                        <span v-if="l.client?.name">{{ l.client.name }}</span>
                        <span v-if="l.client?.phone">{{ l.client.phone }}</span>
                        <span v-if="l.created_at">{{ l.created_at }}</span>
                    </div>
                    <p v-if="l.notes" class="mt-1 line-clamp-2 text-sm text-slate-500 dark:text-slate-400">{{ l.notes }}</p>
                </div>
                <div v-if="canEdit" class="flex flex-none items-center gap-3">
                    <button
                        v-if="!l.deal_id"
                        type="button"
                        class="rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700"
                        @click="convert(l)"
                    >
                        В сделку →
                    </button>
                    <a v-else href="/cabinet/deals" class="text-sm font-medium text-emerald-600 hover:underline dark:text-emerald-400">В сделке ✓</a>
                    <button v-if="l.status !== 'dismissed' && !l.deal_id" type="button" class="text-sm text-slate-500 hover:underline" @click="dismiss(l)">Отклонить</button>
                    <button type="button" class="text-sm text-red-600 hover:underline" @click="remove(l)">Удалить</button>
                </div>
            </div>
        </div>

        <!-- Модалка ручного лида -->
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showForm = false">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800">
                <div class="mb-4 text-lg font-bold text-[#1F4E79] dark:text-sky-200">Новый лид</div>
                <form class="space-y-3" @submit.prevent="submit">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Название</label>
                        <input v-model="form.title" type="text" placeholder="Напр.: Заявка с сайта" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/15 dark:bg-white/5" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Клиент</label>
                        <select v-model="form.client_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/15 dark:bg-white/5">
                            <option value="">— не выбран —</option>
                            <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }}{{ c.phone ? ` (${c.phone})` : '' }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Заметки</label>
                        <textarea v-model="form.notes" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/15 dark:bg-white/5" />
                    </div>
                    <CustomFieldInputs :fields="fields" v-model="form.custom" />
                    <div class="flex justify-end gap-2 pt-1">
                        <button type="button" class="rounded-lg px-4 py-2 text-sm text-slate-500 hover:bg-slate-100 dark:hover:bg-white/10" @click="showForm = false">Отмена</button>
                        <button type="submit" :disabled="form.processing" class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50">Сохранить</button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
