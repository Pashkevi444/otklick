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
const formOpen = ref(false);
const jsonError = ref<string | null>(null);
const query = ref('');
const activeType = ref<string>('');
const collapsed = ref<Set<string>>(new Set());

const DEFAULT_DEF = '{\n  "start": "n1",\n  "nodes": {\n    "n1": { "type": "message", "action": "escalate", "text": "…", "options": [], "position": { "x": 0, "y": 0 } }\n  }\n}';

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
    definitionText: DEFAULT_DEF,
});

const typeOf = (t: Template): string => t.business_type ?? 'general';
const labelOf = (key: string): string => (key === 'general' ? 'Общие' : (props.businessTypes.find((b) => b.value === key)?.label ?? key));

const filterChips = computed(() => {
    const chips = [{ key: '', label: 'Все', count: props.templates.length }];
    const general = props.templates.filter((t) => !t.business_type).length;
    if (general) chips.push({ key: 'general', label: 'Общие', count: general });
    for (const bt of props.businessTypes) {
        const count = props.templates.filter((t) => t.business_type === bt.value).length;
        if (count) chips.push({ key: bt.value, label: bt.label, count });
    }
    return chips;
});

const filtered = computed<Template[]>(() => {
    const q = query.value.trim().toLowerCase();
    return props.templates.filter((t) => {
        if (activeType.value && typeOf(t) !== activeType.value) return false;
        if (!q) return true;
        return (
            t.name.toLowerCase().includes(q) ||
            t.key.toLowerCase().includes(q) ||
            t.description.toLowerCase().includes(q) ||
            t.triggers.some((x) => x.toLowerCase().includes(q))
        );
    });
});

const groups = computed<{ key: string; label: string; items: Template[] }[]>(() => {
    const order = ['general', ...props.businessTypes.map((b) => b.value)];
    const map = new Map<string, Template[]>();
    for (const t of filtered.value) {
        const k = typeOf(t);
        (map.get(k) ?? map.set(k, []).get(k)!).push(t);
    }
    return order.filter((k) => map.has(k)).map((k) => ({ key: k, label: labelOf(k), items: map.get(k)! }));
});

const toggleGroup = (key: string): void => {
    const next = new Set(collapsed.value);
    next.has(key) ? next.delete(key) : next.add(key);
    collapsed.value = next;
};

const openCreate = (): void => {
    reset();
    formOpen.value = true;
    if (activeType.value && activeType.value !== 'general') form.business_type = activeType.value;
};

const reset = (): void => {
    editingId.value = null;
    formOpen.value = false;
    jsonError.value = null;
    form.reset();
    form.clearErrors();
};

const edit = (t: Template): void => {
    editingId.value = t.id;
    formOpen.value = true;
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
            Готовые сценарии-воронки для бизнесов (глобальные). Граф <b>Definition</b> — в том же формате, что и
            конструктор кабинета (узлы/переходы).
        </p>

        <!-- Тулбар: поиск + добавить -->
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <input
                v-model="query"
                type="search"
                placeholder="Поиск по названию, триггеру, ключу…"
                class="min-w-[240px] flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5"
            />
            <button type="button" class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96]" @click="formOpen ? reset() : openCreate()">
                {{ formOpen && !editingId ? 'Закрыть' : '+ Добавить шаблон' }}
            </button>
        </div>

        <!-- Фильтр по типу бизнеса -->
        <div class="mb-5 flex flex-wrap gap-2">
            <button
                v-for="chip in filterChips"
                :key="chip.key"
                type="button"
                class="rounded-full px-3 py-1 text-xs font-medium transition"
                :class="activeType === chip.key ? 'bg-[#2E74B5] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/10 dark:text-slate-300'"
                @click="activeType = chip.key"
            >
                {{ chip.label }} <span class="opacity-70">{{ chip.count }}</span>
            </button>
        </div>

        <!-- Форма (за кнопкой) -->
        <form v-if="formOpen" class="mb-6 max-w-3xl space-y-4 rounded-xl border border-[#2E74B5]/40 bg-white p-5 dark:border-sky-400/30 dark:bg-white/5" @submit.prevent="submit">
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
                <button type="button" class="rounded-lg border border-slate-300 px-4 py-2 text-sm text-slate-600 dark:border-white/10 dark:text-slate-300" @click="reset">
                    Отмена
                </button>
            </div>
        </form>

        <div v-if="filtered.length === 0" class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-400">
            Ничего не найдено.
        </div>

        <!-- Список со сворачиванием -->
        <div v-for="g in groups" :key="g.key" class="mb-4">
            <button type="button" class="mb-2 flex w-full items-center gap-2 text-left" @click="toggleGroup(g.key)">
                <span class="text-slate-400">{{ collapsed.has(g.key) ? '▸' : '▾' }}</span>
                <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ g.label }}</span>
                <span class="text-xs text-slate-400">({{ g.items.length }})</span>
                <span class="h-px flex-1 bg-slate-200 dark:bg-white/10"></span>
            </button>
            <div v-show="!collapsed.has(g.key)" class="space-y-2">
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
