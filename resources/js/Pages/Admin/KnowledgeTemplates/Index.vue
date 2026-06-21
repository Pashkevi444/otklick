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
const form = useForm<{ key: string; title: string; content: string; business_type: string; sort_order: number }>({
    key: '',
    title: '',
    content: '',
    business_type: '',
    sort_order: 0,
});

// Группируем по типу бизнеса: сперва «Общие», затем ниши.
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
    form.reset();
    form.clearErrors();
};

const edit = (t: Template): void => {
    editingId.value = t.id;
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
            конкретику вместо плейсхолдеров «…». Здесь можно править, добавлять и удалять — без выката кода.
        </p>

        <!-- Форма создания/редактирования -->
        <form class="mb-6 max-w-3xl space-y-4 rounded-xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5" @submit.prevent="submit">
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
