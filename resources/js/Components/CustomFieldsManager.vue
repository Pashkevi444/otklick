<script setup lang="ts">
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import type { FieldDef } from '@/Components/CustomFieldInputs.vue';

const props = defineProps<{ entity: 'lead' | 'deal'; fields: FieldDef[] }>();
const emit = defineEmits<{ close: [] }>();

const typeLabels: Record<string, string> = { text: 'Текст', number: 'Число', select: 'Список', date: 'Дата', bool: 'Да/Нет' };

// --- Добавление ---
const label = ref('');
const type = ref<FieldDef['type']>('text');
const optionsText = ref('');
const saving = ref(false);

const parseOptions = (raw: string): string[] =>
    raw.split('\n').map((s) => s.trim()).filter((s) => s.length > 0);

const add = (): void => {
    if (!label.value.trim()) return;
    saving.value = true;
    router.post(
        '/cabinet/custom-fields',
        { entity: props.entity, label: label.value, type: type.value, options: type.value === 'select' ? parseOptions(optionsText.value) : [] },
        {
            preserveScroll: true,
            onSuccess: () => {
                label.value = '';
                type.value = 'text';
                optionsText.value = '';
            },
            onFinish: () => (saving.value = false),
        },
    );
};

// --- Правка подписи существующего ---
const editingId = ref<string | null>(null);
const editLabel = ref('');
const editOptions = ref('');
const startEdit = (f: FieldDef): void => {
    editingId.value = f.id;
    editLabel.value = f.label;
    editOptions.value = (f.options ?? []).join('\n');
};
const saveEdit = (f: FieldDef): void => {
    router.put(
        `/cabinet/custom-fields/${f.id}`,
        { label: editLabel.value, options: f.type === 'select' ? parseOptions(editOptions.value) : [] },
        { preserveScroll: true, onSuccess: () => (editingId.value = null) },
    );
};

const remove = (f: FieldDef): void => {
    if (confirm(`Удалить поле «${f.label}»? Значения в карточках перестанут отображаться.`)) {
        router.delete(`/cabinet/custom-fields/${f.id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <div class="fixed inset-0 z-[60] flex items-center justify-center bg-black/40 p-4" @click.self="emit('close')">
        <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl dark:bg-slate-800">
            <div class="mb-4 flex items-center justify-between">
                <div class="text-lg font-bold text-[#1F4E79] dark:text-sky-200">Кастомные поля · {{ entity === 'deal' ? 'Сделки' : 'Лиды' }}</div>
                <button type="button" class="text-slate-400 hover:text-slate-600" @click="emit('close')">✕</button>
            </div>

            <!-- Существующие -->
            <div v-if="fields.length" class="mb-4 space-y-2">
                <div v-for="f in fields" :key="f.id" class="rounded-xl border border-slate-200 p-3 dark:border-white/10">
                    <template v-if="editingId === f.id">
                        <input v-model="editLabel" type="text" class="mb-2 w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm dark:border-white/15 dark:bg-white/5" />
                        <textarea v-if="f.type === 'select'" v-model="editOptions" rows="3" placeholder="По варианту на строку" class="mb-2 w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm dark:border-white/15 dark:bg-white/5" />
                        <div class="flex justify-end gap-2">
                            <button type="button" class="text-sm text-slate-500 hover:underline" @click="editingId = null">Отмена</button>
                            <button type="button" class="rounded-lg bg-[#2E74B5] px-3 py-1 text-sm font-medium text-white hover:bg-[#255f96]" @click="saveEdit(f)">Сохранить</button>
                        </div>
                    </template>
                    <div v-else class="flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <span class="font-medium text-slate-800 dark:text-slate-100">{{ f.label }}</span>
                            <span class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-500 dark:bg-white/10 dark:text-slate-300">{{ typeLabels[f.type] }}</span>
                        </div>
                        <div class="flex flex-none gap-3">
                            <button type="button" class="text-sm text-[#2E74B5] hover:underline" @click="startEdit(f)">Изм.</button>
                            <button type="button" class="text-sm text-red-600 hover:underline" @click="remove(f)">Удалить</button>
                        </div>
                    </div>
                </div>
            </div>
            <p v-else class="mb-4 rounded-xl border border-dashed border-slate-300 p-4 text-center text-sm text-slate-400 dark:border-white/10">Полей пока нет. Добавьте первое ниже.</p>

            <!-- Добавить -->
            <div class="border-t border-slate-200 pt-4 dark:border-white/10">
                <div class="mb-2 text-sm font-semibold text-slate-600 dark:text-slate-300">Новое поле</div>
                <div class="grid grid-cols-[1fr_auto] gap-2">
                    <input v-model="label" type="text" placeholder="Название поля" class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/15 dark:bg-white/5" @keyup.enter="add" />
                    <select v-model="type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/15 dark:bg-white/5">
                        <option v-for="(lbl, t) in typeLabels" :key="t" :value="t">{{ lbl }}</option>
                    </select>
                </div>
                <textarea v-if="type === 'select'" v-model="optionsText" rows="3" placeholder="Варианты списка — по одному на строку" class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/15 dark:bg-white/5" />
                <div class="mt-3 flex justify-end">
                    <button type="button" :disabled="saving || !label.trim()" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-50" @click="add">+ Добавить поле</button>
                </div>
            </div>
        </div>
    </div>
</template>
