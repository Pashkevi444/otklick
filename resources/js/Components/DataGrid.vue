<script setup lang="ts">
import { computed } from 'vue';
import { type ColumnDef, type GridConfig, type Row, applyFilters, applySort, formatCell, resolvePath } from '@/lib/grid';

const props = defineProps<{
    columns: ColumnDef[]; // все доступные колонки
    rows: Row[];
    config: GridConfig; // видимые колонки / фильтры / сортировка
    rowKey?: string; // ключ-идентификатор строки (по умолчанию 'id')
}>();
const emit = defineEmits<{ 'update:sort': [GridConfig['sort']]; rowClick: [Row] }>();

const idKey = computed<string>(() => props.rowKey ?? 'id');

const visibleColumns = computed<ColumnDef[]>(() => {
    const order = props.config.columns;
    const byKey = new Map(props.columns.map((c) => [c.key, c]));
    // Порядок — как в config.columns; если пусто, показываем все.
    const keys = order.length ? order : props.columns.map((c) => c.key);
    return keys.map((k) => byKey.get(k)).filter((c): c is ColumnDef => !!c);
});

const processed = computed<Row[]>(() => applySort(applyFilters(props.rows, props.config.filters), props.config.sort));

const toggleSort = (col: ColumnDef): void => {
    if (col.sortable === false) return;
    const cur = props.config.sort;
    if (!cur || cur.field !== col.key) {
        emit('update:sort', { field: col.key, dir: 'asc' });
    } else if (cur.dir === 'asc') {
        emit('update:sort', { field: col.key, dir: 'desc' });
    } else {
        emit('update:sort', null);
    }
};

const sortMark = (col: ColumnDef): string => {
    const cur = props.config.sort;
    if (!cur || cur.field !== col.key) return '';
    return cur.dir === 'asc' ? '▲' : '▼';
};
</script>

<template>
    <div class="overflow-x-auto rounded-2xl border border-slate-200 bg-white dark:border-white/10 dark:bg-white/5">
        <table class="w-full text-sm">
            <thead>
                <tr class="border-b border-slate-200 text-left text-xs uppercase tracking-wide text-slate-400 dark:border-white/10">
                    <th
                        v-for="col in visibleColumns"
                        :key="col.key"
                        class="px-4 py-3 font-semibold"
                        :class="col.sortable === false ? '' : 'cursor-pointer select-none hover:text-[#2E74B5]'"
                        @click="toggleSort(col)"
                    >
                        {{ col.label }} <span class="text-[#2E74B5]">{{ sortMark(col) }}</span>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr
                    v-for="row in processed"
                    :key="String(row[idKey])"
                    class="border-b border-slate-100 transition last:border-0 hover:bg-slate-50 dark:border-white/5 dark:hover:bg-white/5"
                    :class="$attrs.onRowClick ? 'cursor-pointer' : ''"
                    @click="emit('rowClick', row)"
                >
                    <td v-for="col in visibleColumns" :key="col.key" class="px-4 py-3 text-slate-700 dark:text-slate-200">
                        <slot :name="`cell:${col.key}`" :row="row" :value="resolvePath(row, col.key)">
                            <span :class="col.type === 'badge' ? 'rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-white/10 dark:text-slate-300' : ''">
                                {{ formatCell(resolvePath(row, col.key), col.type) }}
                            </span>
                        </slot>
                    </td>
                </tr>
                <tr v-if="processed.length === 0">
                    <td :colspan="visibleColumns.length" class="px-4 py-10 text-center text-slate-400">Ничего не найдено.</td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
