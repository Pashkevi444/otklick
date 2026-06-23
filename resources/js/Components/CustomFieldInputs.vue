<script setup lang="ts">
import { computed } from 'vue';

export interface FieldDef {
    id: string;
    key: string;
    label: string;
    type: 'text' | 'number' | 'select' | 'date' | 'bool';
    options: string[];
}

const props = defineProps<{ fields: FieldDef[]; modelValue: Record<string, unknown> }>();
const emit = defineEmits<{ 'update:modelValue': [Record<string, unknown>] }>();

const set = (key: string, value: unknown): void => {
    emit('update:modelValue', { ...props.modelValue, [key]: value });
};

const val = (key: string): unknown => props.modelValue?.[key];

const inputClass = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/15 dark:bg-white/5';
const hasFields = computed<boolean>(() => props.fields.length > 0);
</script>

<template>
    <div v-if="hasFields" class="space-y-3 border-t border-slate-200 pt-3 dark:border-white/10">
        <div v-for="f in fields" :key="f.id">
            <label class="mb-1 block text-xs font-medium text-slate-500">{{ f.label }}</label>

            <input
                v-if="f.type === 'text'"
                :value="(val(f.key) as string) ?? ''"
                type="text"
                :class="inputClass"
                @input="set(f.key, ($event.target as HTMLInputElement).value)"
            />
            <input
                v-else-if="f.type === 'number'"
                :value="(val(f.key) as number) ?? ''"
                type="number"
                :class="inputClass"
                @input="set(f.key, ($event.target as HTMLInputElement).value)"
            />
            <input
                v-else-if="f.type === 'date'"
                :value="(val(f.key) as string) ?? ''"
                type="date"
                :class="inputClass"
                @input="set(f.key, ($event.target as HTMLInputElement).value)"
            />
            <select
                v-else-if="f.type === 'select'"
                :value="(val(f.key) as string) ?? ''"
                :class="inputClass"
                @change="set(f.key, ($event.target as HTMLSelectElement).value)"
            >
                <option value="">— не выбрано —</option>
                <option v-for="o in f.options" :key="o" :value="o">{{ o }}</option>
            </select>
            <label v-else-if="f.type === 'bool'" class="inline-flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                <input type="checkbox" :checked="!!val(f.key)" class="h-4 w-4 rounded border-slate-300" @change="set(f.key, ($event.target as HTMLInputElement).checked)" />
                Да
            </label>
        </div>
    </div>
</template>
