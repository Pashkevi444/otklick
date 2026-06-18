<script setup lang="ts">
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
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-2">
        <div v-for="(g, i) in groups" :key="g.access?.key ?? i" class="rounded-lg border border-slate-200 p-3 dark:border-white/10">
            <label class="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                <input v-model="model" type="checkbox" :value="g.access?.key" class="rounded border-slate-300" />
                {{ g.access?.label }}
            </label>
            <div v-if="g.actions.length" class="mt-2 ml-6 space-y-1">
                <label v-for="a in g.actions" :key="a.key" class="flex items-center gap-2 text-xs text-slate-500 dark:text-slate-400">
                    <input v-model="model" type="checkbox" :value="a.key" class="rounded border-slate-300" />
                    {{ a.label }}
                </label>
            </div>
        </div>
    </div>
</template>
