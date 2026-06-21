<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface Template {
    id: string;
    key: string;
    name: string;
    description: string;
    business_type: string | null;
    triggers: string[];
    definition: Record<string, unknown>;
    sort_order: number;
}
interface BizType {
    value: string;
    label: string;
}

const props = defineProps<{ templates: Template[]; businessTypes: BizType[] }>();

const editingId = ref<string | null>(null);
const jsonError = ref<string | null>(null);
const form = useForm<{
    key: string;
    name: string;
    description: string;
    business_type: string;
    sort_order: number;
    triggersText: string;
    definitionText: string;
}>({
    key: '',
    name: '',
    description: '',
    business_type: '',
    sort_order: 0,
    triggersText: '',
    definitionText: '{\n  "start": "n1",\n  "nodes": {\n    "n1": { "type": "message", "action": "escalate", "text": "…", "options": [], "position": { "x": 0, "y": 0 } }\n  }\n}',
});

const groups = computed<{ key: string; label: string; items: Template[] }[]>(() => {
    const result: { key: string; label: string; items: Template[] }[] = [];
    const general = props.templates.filter((t) => !t.business_type);
    if (general.length) result.push({ key: 'general', label: 'Общие', items: general });
    for (const bt of props.businessTypes) {
        const items = props.templates.filter((t) => t.business_type === bt.value);
        if (items.length) result.push({ key: bt.value, label: bt.label, items });
    }
    return result;
});

const reset = (): void => {
    editingId.value = null;
    jsonError.value = null;
    form.reset();
    form.clearErrors();
};

const edit = (t: Template): void => {
    editingId.value = t.id;
    jsonError.value = null;
    form.key = t.key;
    form.name = t.name;
    form.description = t.description;
    form.business_type = t.business_type ?? '';
    form.sort_order = t.sort_order;
    form.triggersText = t.triggers.join('\n');
    form.definitionText = JSON.stringify(t.definition, null, 2);
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const submit = (): void => {
    jsonError.value = null;
    let definition: unknown;
    try {
        definition = JSON.parse(form.definitionText);
    } catch (e) {
        jsonError.value = 'Definition — невалидный JSON: ' + (e as Error).message;
        return;
    }

    form.transform((data) => ({
        key: data.key,
        name: data.name,
        description: data.description,
        business_type: data.business_type === '' ? null : data.business_type,
        sort_order: data.sort_order,
        triggers: data.triggersText
            .split('\n')
            .map((s) => s.trim())
            .filter(Boolean),
        definition,
    }));

    if (editingId.value) {
        form.put(`/admin/scenario-templates/${editingId.value}`, { preserveScroll: true, onSuccess: reset });
    } else {
        form.post('/admin/scenario-templates', { preserveScroll: true, onSuccess: reset });
    }
};

const remove = (t: Template): void => {
    if (!confirm(`Удалить шаблон «${t.name}»?`)) return;
    router.delete(`/admin/scenario-templates/${t.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Шаблоны сценариев" />

    <AppLayout title="Шаблоны сценариев">
        <p class="mb-4 max-w-2xl text-sm text-slate-500">
            Готовые сценарии-воронки для бизнесов (глобальные). Бизнес берёт готовый и правит под себя. Граф
            <b>Definition</b> — в том же формате, что и конструктор кабинета (узлы/переходы). Без выката кода.
        </p>

        <!-- Форма создания/редактирования -->
        <form class="mb-6 max-w-3xl space-y-4 rounded-xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5" @submit.prevent="submit">
            <div class="text-sm font-semibold text-[#1F4E79] dark:text-sky-200">
                {{ editingId ? 'Редактировать шаблон' : 'Новый шаблон' }}
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Ключ (латиница)</label>
                    <input v-model="form.key" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5" placeholder="nails_book" />
                    <div v-if="form.errors.key" class="mt-1 text-xs text-red-600">{{ form.errors.key }}</div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Тип бизнеса</label>
                    <select v-model="form.business_type" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5">
                        <option value="">Общие (для всех)</option>
                        <option v-for="bt in businessTypes" :key="bt.value" :value="bt.value">{{ bt.label }}</option>
                    </select>
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Название</label>
                <input v-model="form.name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5" placeholder="Запись на маникюр" />
                <div v-if="form.errors.name" class="mt-1 text-xs text-red-600">{{ form.errors.name }}</div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Описание</label>
                <input v-model="form.description" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5" placeholder="Клиент выбирает услугу и идёт на запись." />
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Триггеры (по одному в строке)</label>
                <textarea v-model="form.triggersText" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5" placeholder="маникюр&#10;педикюр&#10;записаться на маникюр"></textarea>
                <div v-if="form.errors.triggers" class="mt-1 text-xs text-red-600">{{ form.errors.triggers }}</div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Definition (JSON графа)</label>
                <textarea v-model="form.definitionText" rows="10" spellcheck="false" class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-xs dark:border-white/10 dark:bg-white/5"></textarea>
                <div v-if="jsonError" class="mt-1 text-xs text-red-600">{{ jsonError }}</div>
                <div v-if="form.errors.definition" class="mt-1 text-xs text-red-600">{{ form.errors.definition }}</div>
                <div v-if="form.errors['definition.start']" class="mt-1 text-xs text-red-600">{{ form.errors['definition.start'] }}</div>
                <div v-if="form.errors['definition.nodes']" class="mt-1 text-xs text-red-600">{{ form.errors['definition.nodes'] }}</div>
            </div>
            <div class="flex items-end gap-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Порядок</label>
                    <input v-model.number="form.sort_order" type="number" class="w-28 rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5" />
                </div>
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50">
                    {{ editingId ? 'Сохранить' : 'Добавить' }}
                </button>
                <button v-if="editingId" type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 dark:border-white/10 dark:text-slate-300" @click="reset">
                    Отмена
                </button>
            </div>
        </form>

        <!-- Список шаблонов по типам бизнеса -->
        <div v-for="g in groups" :key="g.key" class="mb-5">
            <div class="mb-2 flex items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ g.label }}</span>
                <span class="text-xs text-slate-400">({{ g.items.length }})</span>
                <span class="h-px flex-1 bg-slate-200 dark:bg-white/10"></span>
            </div>
            <div class="space-y-2">
                <div v-for="t in g.items" :key="t.id" class="flex items-start justify-between gap-4 rounded-xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
                    <div class="min-w-0">
                        <div class="text-sm font-semibold text-slate-700 dark:text-slate-100">{{ t.name }} <span class="ml-1 text-xs font-normal text-slate-400">{{ t.key }}</span></div>
                        <div class="mt-1 line-clamp-1 text-xs text-slate-500 dark:text-slate-400">{{ t.description }}</div>
                        <div class="mt-1 text-[11px] text-slate-400">Запуск по: {{ t.triggers.slice(0, 4).join(', ') }}</div>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <button type="button" class="text-sm text-[#2E74B5] hover:underline dark:text-sky-300" @click="edit(t)">Изменить</button>
                        <button type="button" class="text-sm text-red-600 hover:underline" @click="remove(t)">Удалить</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
