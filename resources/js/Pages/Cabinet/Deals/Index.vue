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

const canEdit = useCan()('deals.edit');

interface Stage {
    id: string;
    name: string;
    kind: string;
}
interface ClientRef {
    id: string;
    name: string;
    phone: string | null;
}
interface Deal {
    id: string;
    title: string | null;
    value: number | null;
    stage_id: string;
    source: string;
    notes: string | null;
    client: ClientRef | null;
    client_id: string | null;
    assigned: { id: number; name: string } | null;
    assigned_user_id: number | null;
    custom: Record<string, unknown>;
    created_at: string | null;
}
interface PickerClient {
    id: string;
    name: string;
    phone: string | null;
}
interface TeamMember {
    id: number;
    name: string;
}

const props = defineProps<{
    stages: Stage[];
    deals: Deal[];
    clients: PickerClient[];
    team: TeamMember[];
    fields: FieldDef[];
    views: SavedView[];
}>();

const search = ref('');
const filteredDeals = computed<Deal[]>(() => {
    const q = search.value.trim().toLowerCase();
    if (!q) return props.deals;
    return props.deals.filter((d) =>
        [d.title, d.client?.name, d.client?.phone, d.notes, d.assigned?.name].some((x) => (x ?? '').toString().toLowerCase().includes(q)),
    );
});

const dealsByStage = computed<Record<string, Deal[]>>(() => {
    const map: Record<string, Deal[]> = {};
    for (const s of props.stages) map[s.id] = [];
    for (const d of filteredDeals.value) (map[d.stage_id] ??= []).push(d);
    return map;
});

const fmtMoney = (v: number | null): string => (v ? new Intl.NumberFormat('ru-RU').format(v) + ' ₽' : '');
const stageSum = (stageId: string): number => (dealsByStage.value[stageId] ?? []).reduce((s, d) => s + (d.value ?? 0), 0);
const pipelineSum = computed<number>(() =>
    props.stages.filter((s) => s.kind === 'active').reduce((sum, s) => sum + stageSum(s.id), 0),
);
const stageAccent = (kind: string): string =>
    kind === 'won' ? 'bg-emerald-500' : kind === 'lost' ? 'bg-rose-400' : 'bg-[#2E74B5]';

// --- Drag & drop ---
const dragId = ref<string | null>(null);
const dragOver = ref<string | null>(null);
const onDragStart = (id: string): void => {
    dragId.value = id;
};
const onDrop = (stageId: string): void => {
    const id = dragId.value;
    dragOver.value = null;
    dragId.value = null;
    if (!id) return;
    const deal = props.deals.find((d) => d.id === id);
    if (!deal || deal.stage_id === stageId) return;
    router.put(`/cabinet/deals/${id}`, { stage_id: stageId }, { preserveScroll: true, preserveState: false });
};

// --- Создание / редактирование ---
const showForm = ref(false);
const editingId = ref<string | null>(null);
const form = useForm<{
    stage_id: string;
    client_id: string;
    title: string;
    value: string;
    assigned_user_id: string;
    notes: string;
    custom: Record<string, unknown>;
}>({ stage_id: '', client_id: '', title: '', value: '', assigned_user_id: '', notes: '', custom: {} });

const showFields = ref(false);

const openCreate = (stageId?: string): void => {
    editingId.value = null;
    form.reset();
    form.custom = {};
    form.stage_id = stageId ?? props.stages[0]?.id ?? '';
    showForm.value = true;
};
const openEdit = (d: Deal): void => {
    editingId.value = d.id;
    form.stage_id = d.stage_id;
    form.client_id = d.client_id ?? '';
    form.title = d.title ?? '';
    form.value = d.value != null ? String(d.value) : '';
    form.assigned_user_id = d.assigned_user_id != null ? String(d.assigned_user_id) : '';
    form.notes = d.notes ?? '';
    form.custom = { ...(d.custom ?? {}) };
    showForm.value = true;
};
const submit = (): void => {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            showForm.value = false;
            form.reset();
        },
    };
    if (editingId.value) form.put(`/cabinet/deals/${editingId.value}`, opts);
    else form.post('/cabinet/deals', opts);
};
const remove = (d: Deal): void => {
    if (confirm('Удалить сделку?')) router.delete(`/cabinet/deals/${d.id}`, { preserveScroll: true });
};

const cardTitle = (d: Deal): string => d.title || d.client?.name || 'Без названия';

// --- Табличный вид (универсальный грид) ---
const viewMode = ref<'kanban' | 'table'>('kanban');
const stageNameById = computed<Record<string, string>>(() => Object.fromEntries(props.stages.map((s) => [s.id, s.name])));
const sourceLabel = (s: string): string => (s === 'bot' ? 'Из диалога' : 'Вручную');

const columns = computed<ColumnDef[]>(() => [
    { key: 'title', label: 'Название', type: 'text', always: true },
    { key: 'value', label: 'Сумма, ₽', type: 'number' },
    { key: 'stage_name', label: 'Стадия', type: 'badge', options: props.stages.map((s) => s.name) },
    { key: 'client.name', label: 'Клиент', type: 'text' },
    { key: 'assigned.name', label: 'Ответственный', type: 'text' },
    { key: 'source_label', label: 'Источник', type: 'badge', options: ['Из диалога', 'Вручную'] },
    { key: 'created_at', label: 'Создан', type: 'date' },
    ...props.fields.map((f): ColumnDef => ({ key: `custom.${f.key}`, label: f.label, type: f.type, options: f.options })),
]);

const gridRows = computed<Row[]>(() =>
    filteredDeals.value.map((d) => ({
        ...d,
        title: cardTitle(d),
        stage_name: stageNameById.value[d.stage_id] ?? '',
        source_label: sourceLabel(d.source),
    })),
);

const onRowClick = (row: Row): void => {
    const deal = props.deals.find((d) => d.id === row.id);
    if (deal && canEdit) openEdit(deal);
};
</script>

<template>
    <Head title="Сделки" />

    <AppLayout title="Сделки">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <p class="max-w-2xl text-sm text-slate-500">
                Воронка продаж. Тащите карточки между стадиями, ведите сумму и ответственного.
            </p>
            <div class="flex flex-wrap items-center gap-3">
                <input v-model="search" type="search" placeholder="Поиск по сделкам…" class="w-48 rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/15 dark:bg-white/5" />
                <div class="inline-flex rounded-lg border border-slate-200 p-0.5 dark:border-white/10">
                    <button type="button" class="rounded-md px-3 py-1 text-sm font-medium" :class="viewMode === 'kanban' ? 'bg-[#2E74B5] text-white' : 'text-slate-500'" @click="viewMode = 'kanban'">Канбан</button>
                    <button type="button" class="rounded-md px-3 py-1 text-sm font-medium" :class="viewMode === 'table' ? 'bg-[#2E74B5] text-white' : 'text-slate-500'" @click="viewMode = 'table'">Таблица</button>
                </div>
                <span class="rounded-lg bg-[#EAF2FB] px-3 py-1.5 text-sm font-medium text-[#1F4E79] dark:bg-white/10 dark:text-sky-200">
                    Сумма воронки: {{ fmtMoney(pipelineSum) || '0 ₽' }}
                </span>
                <button v-if="canEdit" type="button" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-600 hover:border-[#2E74B5]/40 hover:text-[#2E74B5] dark:border-white/15 dark:text-slate-300" @click="showFields = true">
                    ⚙ Поля
                </button>
                <button type="button" class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96]" @click="openCreate()">
                    + Новая сделка
                </button>
            </div>
        </div>

        <CustomFieldsManager v-if="showFields" entity="deal" :fields="fields" @close="showFields = false" />

        <CrmGrid
            v-if="viewMode === 'table'"
            entity="deal"
            :columns="columns"
            :rows="gridRows"
            :views="views"
            @row-click="onRowClick"
        >
            <template #[`cell:value`]="{ value }">
                <span class="font-medium text-emerald-600 dark:text-emerald-400">{{ value ? fmtMoney(Number(value)) : '—' }}</span>
            </template>
        </CrmGrid>

        <div v-else class="flex gap-4 overflow-x-auto pb-3">
            <div
                v-for="s in stages"
                :key="s.id"
                class="flex w-72 flex-none flex-col rounded-2xl bg-slate-100/70 p-3 transition dark:bg-white/5"
                :class="dragOver === s.id ? 'ring-2 ring-[#2E74B5]/50' : ''"
                @dragover.prevent="dragOver = s.id"
                @dragleave="dragOver = null"
                @drop="onDrop(s.id)"
            >
                <div class="mb-2 flex items-center justify-between px-1">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full" :class="stageAccent(s.kind)"></span>
                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ s.name }}</span>
                        <span class="rounded-full bg-white px-1.5 text-xs text-slate-400 dark:bg-white/10">{{ (dealsByStage[s.id] ?? []).length }}</span>
                    </div>
                    <button type="button" class="text-slate-400 hover:text-[#2E74B5]" title="Добавить сделку" @click="openCreate(s.id)">+</button>
                </div>
                <div v-if="stageSum(s.id)" class="mb-2 px-1 text-xs text-slate-400">{{ fmtMoney(stageSum(s.id)) }}</div>

                <div class="flex flex-col gap-2">
                    <div
                        v-for="d in dealsByStage[s.id]"
                        :key="d.id"
                        draggable="true"
                        class="group cursor-grab rounded-xl border border-slate-200 bg-white p-3 shadow-sm transition hover:border-[#2E74B5]/40 hover:shadow active:cursor-grabbing dark:border-white/10 dark:bg-slate-800/60"
                        @dragstart="onDragStart(d.id)"
                        @click="openEdit(d)"
                    >
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-sm font-medium text-slate-800 dark:text-slate-100">{{ cardTitle(d) }}</span>
                            <span v-if="d.value" class="flex-none text-xs font-semibold text-emerald-600 dark:text-emerald-400">{{ fmtMoney(d.value) }}</span>
                        </div>
                        <div v-if="d.client?.phone" class="mt-1 text-xs text-slate-400">{{ d.client.phone }}</div>
                        <div class="mt-2 flex items-center justify-between">
                            <span class="rounded-full px-2 py-0.5 text-[11px]" :class="d.source === 'bot' ? 'bg-[#EAF2FB] text-[#2E74B5] dark:bg-white/10 dark:text-sky-300' : 'bg-slate-100 text-slate-500 dark:bg-white/10 dark:text-slate-300'">
                                {{ d.source === 'bot' ? 'из диалога' : 'вручную' }}
                            </span>
                            <span v-if="d.assigned" class="text-[11px] text-slate-400">{{ d.assigned.name }}</span>
                        </div>
                    </div>
                    <p v-if="(dealsByStage[s.id] ?? []).length === 0" class="rounded-xl border border-dashed border-slate-300 px-2 py-4 text-center text-xs text-slate-300 dark:border-white/10">Перетащите сюда</p>
                </div>
            </div>
        </div>

        <!-- Модалка создания/редактирования -->
        <div v-if="showForm" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" @click.self="showForm = false">
            <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800">
                <div class="mb-4 text-lg font-bold text-[#1F4E79] dark:text-sky-200">{{ editingId ? 'Сделка' : 'Новая сделка' }}</div>
                <form class="space-y-3" @submit.prevent="submit">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Название</label>
                        <input v-model="form.title" type="text" placeholder="Напр.: Запрос на маникюр" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/15 dark:bg-white/5" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Стадия</label>
                            <select v-model="form.stage_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/15 dark:bg-white/5">
                                <option v-for="s in stages" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-500">Сумма, ₽</label>
                            <input v-model="form.value" type="number" min="0" placeholder="0" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/15 dark:bg-white/5" />
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Клиент</label>
                        <select v-model="form.client_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/15 dark:bg-white/5">
                            <option value="">— не выбран —</option>
                            <option v-for="c in clients" :key="c.id" :value="c.id">{{ c.name }}{{ c.phone ? ` (${c.phone})` : '' }}</option>
                        </select>
                    </div>
                    <div v-if="team.length">
                        <label class="mb-1 block text-xs font-medium text-slate-500">Ответственный</label>
                        <select v-model="form.assigned_user_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/15 dark:bg-white/5">
                            <option value="">— не назначен —</option>
                            <option v-for="m in team" :key="m.id" :value="String(m.id)">{{ m.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Заметки</label>
                        <textarea v-model="form.notes" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/15 dark:bg-white/5" />
                    </div>
                    <CustomFieldInputs :fields="fields" v-model="form.custom" />
                    <div class="flex items-center justify-between pt-1">
                        <button v-if="editingId" type="button" class="text-sm text-red-600 hover:underline" @click="remove(props.deals.find((x) => x.id === editingId)!)">Удалить</button>
                        <span v-else></span>
                        <div class="flex gap-2">
                            <button type="button" class="rounded-lg px-4 py-2 text-sm text-slate-500 hover:bg-slate-100 dark:hover:bg-white/10" @click="showForm = false">Отмена</button>
                            <button type="submit" :disabled="form.processing" class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50">Сохранить</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
