<script setup lang="ts">
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { type ColumnDef, type Filter, type GridConfig, OPS_BY_TYPE } from '@/lib/grid';

export interface SavedView {
    id: string;
    name: string;
    config: GridConfig;
}

const props = defineProps<{
    entity: string;
    columns: ColumnDef[];
    config: GridConfig;
    views: SavedView[];
}>();
const emit = defineEmits<{ 'update:config': [GridConfig] }>();

const colByKey = computed(() => new Map(props.columns.map((c) => [c.key, c])));
const patch = (p: Partial<GridConfig>): void => emit('update:config', { ...props.config, ...p });

// --- Добавление фильтра ---
const adding = ref(false);
const fField = ref('');
const fOp = ref('');
const fValue = ref<string | boolean>('');
const fCol = computed<ColumnDef | undefined>(() => colByKey.value.get(fField.value));
const fOps = computed(() => (fCol.value ? OPS_BY_TYPE[fCol.value.type] : []));

const pickField = (key: string): void => {
    fField.value = key;
    fOp.value = OPS_BY_TYPE[colByKey.value.get(key)!.type][0]?.op ?? '';
    fValue.value = colByKey.value.get(key)!.type === 'bool' ? true : '';
};
const addFilter = (): void => {
    if (!fField.value || !fOp.value) return;
    patch({ filters: [...props.config.filters, { field: fField.value, op: fOp.value, value: fValue.value }] });
    adding.value = false;
    fField.value = '';
    fOp.value = '';
    fValue.value = '';
};
const removeFilter = (i: number): void => patch({ filters: props.config.filters.filter((_, idx) => idx !== i) });

const filterLabel = (f: Filter): string => {
    const col = colByKey.value.get(f.field);
    const op = (col ? OPS_BY_TYPE[col.type] : []).find((o) => o.op === f.op)?.label ?? f.op;
    const val = typeof f.value === 'boolean' ? (f.value ? 'Да' : 'Нет') : String(f.value);
    return `${col?.label ?? f.field} ${op} ${val}`;
};

// --- Видимость колонок ---
const showColumns = ref(false);
const visible = computed<string[]>(() => (props.config.columns.length ? props.config.columns : props.columns.map((c) => c.key)));
const isVisible = (key: string): boolean => visible.value.includes(key);
const toggleColumn = (col: ColumnDef): void => {
    if (col.always) return;
    const set = new Set(visible.value);
    set.has(col.key) ? set.delete(col.key) : set.add(col.key);
    // сохраняем исходный порядок колонок
    patch({ columns: props.columns.filter((c) => set.has(c.key)).map((c) => c.key) });
};

// --- Сохранённые виды ---
const showViews = ref(false);
const newViewName = ref('');
const loadView = (v: SavedView): void => {
    emit('update:config', { columns: v.config.columns ?? [], filters: v.config.filters ?? [], sort: v.config.sort ?? null });
    showViews.value = false;
};
const saveView = (): void => {
    const name = newViewName.value.trim() || 'Мой вид';
    router.post('/cabinet/grid-views', { entity: props.entity, name, config: props.config }, { preserveScroll: true, onSuccess: () => ((newViewName.value = ''), (showViews.value = false)) });
};
const deleteView = (v: SavedView): void => {
    if (confirm(`Удалить вид «${v.name}»?`)) router.delete(`/cabinet/grid-views/${v.id}`, { preserveScroll: true });
};

const hasFilters = computed<boolean>(() => props.config.filters.length > 0);
const inputClass = 'rounded-lg border border-slate-300 px-2.5 py-1.5 text-sm dark:border-white/15 dark:bg-white/5';
</script>

<template>
    <div class="mb-4 rounded-2xl border border-slate-200 bg-white p-3 dark:border-white/10 dark:bg-white/5">
        <div class="flex flex-wrap items-center gap-2">
            <!-- Активные фильтры -->
            <span
                v-for="(f, i) in config.filters"
                :key="i"
                class="inline-flex items-center gap-1 rounded-full bg-[#EAF2FB] px-3 py-1 text-xs font-medium text-[#1F4E79] dark:bg-white/10 dark:text-sky-200"
            >
                {{ filterLabel(f) }}
                <button type="button" class="text-[#2E74B5] hover:text-red-500" @click="removeFilter(i)">✕</button>
            </span>

            <!-- Добавить фильтр -->
            <div class="relative">
                <button type="button" class="rounded-lg border border-dashed border-slate-300 px-3 py-1.5 text-sm text-slate-500 hover:border-[#2E74B5]/40 hover:text-[#2E74B5] dark:border-white/15" @click="adding = !adding">
                    + Фильтр
                </button>
                <div v-if="adding" class="absolute left-0 top-10 z-30 w-72 rounded-xl border border-slate-200 bg-white p-3 shadow-lg dark:border-white/10 dark:bg-slate-800">
                    <select :value="fField" :class="[inputClass, 'mb-2 w-full']" @change="pickField(($event.target as HTMLSelectElement).value)">
                        <option value="">Поле…</option>
                        <option v-for="c in columns" :key="c.key" :value="c.key">{{ c.label }}</option>
                    </select>
                    <div v-if="fField" class="flex gap-2">
                        <select v-model="fOp" :class="[inputClass, 'flex-none']">
                            <option v-for="o in fOps" :key="o.op" :value="o.op">{{ o.label }}</option>
                        </select>
                        <select v-if="fCol?.type === 'bool'" v-model="fValue" :class="[inputClass, 'flex-1']">
                            <option :value="true">Да</option>
                            <option :value="false">Нет</option>
                        </select>
                        <select v-else-if="fCol?.type === 'select' || fCol?.type === 'badge'" v-model="fValue" :class="[inputClass, 'flex-1']">
                            <option value="">—</option>
                            <option v-for="o in fCol?.options ?? []" :key="o" :value="o">{{ o }}</option>
                        </select>
                        <input v-else v-model="fValue" :type="fCol?.type === 'number' ? 'number' : fCol?.type === 'date' ? 'date' : 'text'" :class="[inputClass, 'flex-1']" placeholder="Значение" @keyup.enter="addFilter" />
                    </div>
                    <div class="mt-3 flex justify-end gap-2">
                        <button type="button" class="text-sm text-slate-400 hover:underline" @click="adding = false">Отмена</button>
                        <button type="button" :disabled="!fField" class="rounded-lg bg-[#2E74B5] px-3 py-1 text-sm font-medium text-white disabled:opacity-40" @click="addFilter">Применить</button>
                    </div>
                </div>
            </div>

            <div class="ml-auto flex items-center gap-2">
                <!-- Колонки -->
                <div class="relative">
                    <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-500 hover:text-[#2E74B5] dark:border-white/15 dark:text-slate-300" @click="showColumns = !showColumns">
                        ⛃ Колонки
                    </button>
                    <div v-if="showColumns" class="absolute right-0 top-10 z-30 max-h-72 w-56 overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-lg dark:border-white/10 dark:bg-slate-800">
                        <label v-for="c in columns" :key="c.key" class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-slate-50 dark:hover:bg-white/5" :class="c.always ? 'opacity-50' : ''">
                            <input type="checkbox" :checked="isVisible(c.key)" :disabled="c.always" class="h-4 w-4 rounded border-slate-300" @change="toggleColumn(c)" />
                            {{ c.label }}
                        </label>
                    </div>
                </div>

                <!-- Сохранённые виды -->
                <div class="relative">
                    <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm text-slate-500 hover:text-[#2E74B5] dark:border-white/15 dark:text-slate-300" @click="showViews = !showViews">
                        ★ Виды
                    </button>
                    <div v-if="showViews" class="absolute right-0 top-10 z-30 w-64 rounded-xl border border-slate-200 bg-white p-3 shadow-lg dark:border-white/10 dark:bg-slate-800">
                        <div v-if="views.length" class="mb-2 space-y-1">
                            <div v-for="v in views" :key="v.id" class="flex items-center justify-between rounded-lg px-2 py-1 text-sm hover:bg-slate-50 dark:hover:bg-white/5">
                                <button type="button" class="truncate text-left text-slate-700 hover:text-[#2E74B5] dark:text-slate-200" @click="loadView(v)">{{ v.name }}</button>
                                <button type="button" class="flex-none text-xs text-red-500 hover:underline" @click="deleteView(v)">✕</button>
                            </div>
                        </div>
                        <p v-else class="mb-2 text-xs text-slate-400">Сохранённых видов пока нет.</p>
                        <div class="flex gap-2 border-t border-slate-200 pt-2 dark:border-white/10">
                            <input v-model="newViewName" type="text" placeholder="Название вида" :class="[inputClass, 'min-w-0 flex-1']" @keyup.enter="saveView" />
                            <button type="button" class="rounded-lg bg-emerald-600 px-2.5 py-1 text-sm font-medium text-white hover:bg-emerald-700" @click="saveView">Сохранить</button>
                        </div>
                    </div>
                </div>

                <button v-if="hasFilters" type="button" class="text-sm text-slate-400 hover:text-red-500" @click="patch({ filters: [] })">Сбросить</button>
            </div>
        </div>
    </div>
</template>
