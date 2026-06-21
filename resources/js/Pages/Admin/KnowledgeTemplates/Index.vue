<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface Template {
    id: string;
    key: string;
    title: string;
    content: string;
    business_type: string | null;
    sort_order: number;
}
interface BizType {
    value: string;
    label: string;
}

const props = defineProps<{ templates: Template[]; businessTypes: BizType[] }>();

const editingId = ref<string | null>(null);
const formOpen = ref(false);
const query = ref('');
const activeType = ref<string>(''); // '' = все, 'general' = Общие (null), либо value ниши
const collapsed = ref<Set<string>>(new Set());

const form = useForm<{ key: string; title: string; content: string; business_type: string; sort_order: number }>({
    key: '',
    title: '',
    content: '',
    business_type: '',
    sort_order: 0,
});

const typeOf = (t: Template): string => t.business_type ?? 'general';
const labelOf = (key: string): string => (key === 'general' ? 'Общие' : (props.businessTypes.find((b) => b.value === key)?.label ?? key));

// Фильтр-чипы: Все / Общие / ниши — с количеством.
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
        return t.title.toLowerCase().includes(q) || t.key.toLowerCase().includes(q) || t.content.toLowerCase().includes(q);
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
    form.reset();
    form.clearErrors();
};

const edit = (t: Template): void => {
    editingId.value = t.id;
    formOpen.value = true;
    form.key = t.key;
    form.title = t.title;
    form.content = t.content;
    form.business_type = t.business_type ?? '';
    form.sort_order = t.sort_order;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const submit = (): void => {
    form.transform((data) => ({ ...data, business_type: data.business_type === '' ? null : data.business_type }));
    if (editingId.value) {
        form.put(`/admin/knowledge-templates/${editingId.value}`, { preserveScroll: true, onSuccess: reset });
    } else {
        form.post('/admin/knowledge-templates', { preserveScroll: true, onSuccess: reset });
    }
};

const remove = (t: Template): void => {
    if (!confirm(`Удалить шаблон «${t.title}»?`)) return;
    router.delete(`/admin/knowledge-templates/${t.id}`, { preserveScroll: true });
};
</script>

<template>
    <Head title="Шаблоны базы знаний" />

    <AppLayout title="Шаблоны базы знаний">
        <p class="mb-4 max-w-2xl text-sm text-slate-500">
            Готовые элементы базы знаний для бизнесов (глобальные). Бизнес добавляет их в один клик и дозаполняет
            конкретику вместо плейсхолдеров «…».
        </p>

        <!-- Тулбар: поиск + добавить -->
        <div class="mb-3 flex flex-wrap items-center gap-2">
            <input
                v-model="query"
                type="search"
                placeholder="Поиск по заголовку, тексту, ключу…"
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

        <!-- Форма создания/редактирования (за кнопкой) -->
        <form v-if="formOpen" class="mb-6 max-w-3xl space-y-4 rounded-xl border border-[#2E74B5]/40 bg-white p-5 dark:border-sky-400/30 dark:bg-white/5" @submit.prevent="submit">
            <div class="text-sm font-semibold text-[#1F4E79] dark:text-sky-200">
                {{ editingId ? 'Редактировать шаблон' : 'Новый шаблон' }}
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Ключ (латиница)</label>
                    <input v-model="form.key" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5" placeholder="nails_services" />
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
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Заголовок</label>
                <input v-model="form.title" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5" placeholder="Виды маникюра и цены" />
                <div v-if="form.errors.title" class="mt-1 text-xs text-red-600">{{ form.errors.title }}</div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Текст ответа</label>
                <textarea v-model="form.content" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5" placeholder="Маникюр — … ₽. Используйте «…» для конкретики бизнеса."></textarea>
                <div v-if="form.errors.content" class="mt-1 text-xs text-red-600">{{ form.errors.content }}</div>
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

        <!-- Список по типам бизнеса со сворачиванием -->
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
                        <div class="text-sm font-semibold text-slate-700 dark:text-slate-100">{{ t.title }} <span class="ml-1 text-xs font-normal text-slate-400">{{ t.key }}</span></div>
                        <div class="mt-1 line-clamp-2 text-xs text-slate-500 dark:text-slate-400">{{ t.content }}</div>
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
