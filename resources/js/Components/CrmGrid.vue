<script setup lang="ts">
import { ref } from 'vue';
import DataGrid from '@/Components/DataGrid.vue';
import FilterBar, { type SavedView } from '@/Components/FilterBar.vue';
import { type ColumnDef, type GridConfig, type Row } from '@/lib/grid';

const props = defineProps<{
    entity: string;
    columns: ColumnDef[];
    rows: Row[];
    views: SavedView[];
    rowKey?: string;
}>();
defineEmits<{ rowClick: [Row] }>();

const config = ref<GridConfig>({
    columns: props.columns.map((c) => c.key),
    filters: [],
    sort: null,
});
</script>

<template>
    <div>
        <FilterBar :entity="entity" :columns="columns" :config="config" :views="views" @update:config="config = $event" />
        <DataGrid :columns="columns" :rows="rows" :config="config" :row-key="rowKey" @update:sort="config = { ...config, sort: $event }" @row-click="$emit('rowClick', $event)">
            <template v-for="(_, name) in $slots" #[name]="slotProps">
                <slot :name="name" v-bind="slotProps ?? {}" />
            </template>
        </DataGrid>
    </div>
</template>
