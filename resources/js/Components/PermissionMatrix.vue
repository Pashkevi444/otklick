<script setup lang="ts">
import Toggle from '@/Components/Toggle.vue';

interface PermOption {
    key: string;
    label: string;
}
interface Group {
    access: PermOption | null;
    actions: PermOption[];
}

defineProps<{ groups: Group[] }>();
const model = defineModel<string[]>({ required: true });

const isOn = (key?: string): boolean => key !== undefined && model.value.includes(key);

const flip = (key: string | undefined, value: boolean): void => {
    if (key === undefined) {
        return;
    }
    if (value) {
        if (!model.value.includes(key)) {
            model.value = [...model.value, key];
        }
    } else {
        model.value = model.value.filter((k) => k !== key);
    }
};
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-2">
        <div v-for="(g, i) in groups" :key="g.access?.key ?? i" class="rounded-lg border border-slate-200 p-3 dark:border-white/10">
            <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                <Toggle :model-value="isOn(g.access?.key)" @update:model-value="flip(g.access?.key, $event)" />
                {{ g.access?.label }}
            </label>
            <div v-if="g.actions.length" class="mt-2 ml-6 space-y-1">
                <label v-for="a in g.actions" :key="a.key" class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                    <Toggle :model-value="isOn(a.key)" @update:model-value="flip(a.key, $event)" />
                    {{ a.label }}
                </label>
            </div>
        </div>
    </div>
</template>
