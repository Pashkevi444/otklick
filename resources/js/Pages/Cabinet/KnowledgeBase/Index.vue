<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ImageUploader from '@/Components/ImageUploader.vue';
import Toggle from '@/Components/Toggle.vue';

interface LinkItem {
    label: string;
    url: string;
}
interface ImageItem {
    path: string;
    url: string;
}
interface Entry {
    id: string;
    title: string;
    content: string;
    is_published: boolean;
    links: LinkItem[];
    images: ImageItem[];
    updated_at: string | null;
}
interface Gap {
    id: string;
    question: string;
    occurrences: number;
    channel: string | null;
    conversation_id: string | null;
    last_seen_at: string | null;
}

interface KbTemplate {
    key: string;
    title: string;
    content: string;
    businessType: string | null;
}
interface BizType {
    value: string;
    label: string;
}
const props = defineProps<{
    entries: Entry[];
    pagination: { current: number; last: number; total: number };
    gaps: Gap[];
    templates: KbTemplate[];
    businessTypes: BizType[];
}>();

// Пагинация списка записей (серверная, по ?page).
const goToPage = (page: number): void => {
    router.get(route('cabinet.knowledge.index'), { page }, { preserveScroll: true, preserveState: false });
};

const tab = ref<'entries' | 'gaps'>('entries');
const showForm = ref(false);
const showTemplates = ref(false);

// Шаблоны базы знаний, сгруппированные по типу бизнеса (сперва «Общие», дефолт).
const templateGroups = computed<{ key: string; label: string; items: KbTemplate[] }[]>(() => {
    const groups: { key: string; label: string; items: KbTemplate[] }[] = [];
    const general = props.templates.filter((t) => !t.businessType);
    if (general.length) groups.push({ key: 'general', label: 'Общие', items: general });
    for (const bt of props.businessTypes) {
        const items = props.templates.filter((t) => t.businessType === bt.value);
        if (items.length) groups.push({ key: bt.value, label: bt.label, items });
    }
    return groups;
});

// «Развитие бота»: вопрос → черновик записи; скрыть/удалить как нерелевантный.
const promoteGap = (id: string): void => {
    router.post(`/cabinet/knowledge-gaps/${id}/to-knowledge`);
};
const dismissGap = (id: string): void => {
    router.post(`/cabinet/knowledge-gaps/${id}/dismiss`, {}, { preserveScroll: true });
};
const removeGap = (id: string): void => {
    if (confirm('Удалить этот вопрос?')) {
        router.delete(`/cabinet/knowledge-gaps/${id}`, { preserveScroll: true });
    }
};

const form = useForm<{
    title: string;
    content: string;
    is_published: boolean;
    links: LinkItem[];
    images: File[];
}>({
    title: '',
    content: '',
    is_published: true,
    links: [],
    images: [],
});

const addLink = (): void => {
    form.links.push({ label: '', url: '' });
};
const removeLink = (i: number): void => {
    form.links.splice(i, 1);
};

// Применить шаблон: предзаполнить форму создания (бизнес дозаполняет «…» и сохраняет).
const useTemplate = (t: KbTemplate): void => {
    form.title = t.title;
    form.content = t.content;
    form.is_published = true;
    showTemplates.value = false;
    showForm.value = true;
};

const submit = (): void => {
    form.post('/cabinet/knowledge', {
        forceFormData: true,
        onSuccess: () => {
            form.reset();
            showForm.value = false;
        },
    });
};

const remove = (id: string): void => {
    if (confirm('Удалить запись?')) {
        router.delete(`/cabinet/knowledge/${id}`);
    }
};
</script>

<template>
    <Head title="База знаний" />

    <AppLayout title="База знаний">
        <!-- Табы -->
        <div class="flex gap-2 mb-5 border-b border-slate-200">
            <button
                type="button"
                class="px-4 py-2 text-sm font-medium border-b-2 -mb-px"
                :class="tab === 'entries' ? 'border-[#2E74B5] text-[#1F4E79]' : 'border-transparent text-slate-500 hover:text-[#1F4E79]'"
                @click="tab = 'entries'"
            >
                Записи
            </button>
            <button
                type="button"
                class="px-4 py-2 text-sm font-medium border-b-2 -mb-px flex items-center gap-2"
                :class="tab === 'gaps' ? 'border-[#2E74B5] text-[#1F4E79]' : 'border-transparent text-slate-500 hover:text-[#1F4E79]'"
                @click="tab = 'gaps'"
            >
                Развитие бота
                <span v-if="gaps.length" class="rounded-full bg-amber-100 text-amber-700 text-xs px-2 py-0.5">{{ gaps.length }}</span>
            </button>
        </div>

        <!-- Вкладка: записи -->
        <div v-if="tab === 'entries'">
            <p class="text-slate-500 text-sm mb-4 max-w-2xl">
                Добавляйте то, о чём чаще всего спрашивают клиенты: услуги, цены, условия, частые вопросы.
                К записи можно прикрепить ссылки (прайс, соцсети) и картинки — примеры работ.
            </p>

        <div class="flex flex-wrap justify-end gap-2 mb-4">
            <button
                v-if="templates.length"
                type="button"
                class="rounded-lg border border-[#2E74B5] px-4 py-2 text-sm font-medium text-[#2E74B5] hover:bg-[#EAF2FB] dark:hover:bg-white/10"
                @click="showTemplates = !showTemplates"
            >
                {{ showTemplates ? 'Скрыть шаблоны' : '📋 Шаблоны базы знаний' }}
            </button>
            <button
                type="button"
                class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96]"
                @click="showForm = !showForm"
            >
                {{ showForm ? 'Отмена' : 'Новая запись' }}
            </button>
        </div>

        <!-- Готовые элементы базы знаний по типам бизнеса (сперва «Общие») -->
        <div v-if="showTemplates" class="bg-white rounded-xl border border-slate-200 p-5 mb-6 dark:border-white/10 dark:bg-white/5">
            <p class="text-xs text-slate-500 mb-3">
                Возьмите готовый элемент и замените «…» на данные вашего бизнеса. Он подставится в форму — проверьте и сохраните.
            </p>
            <div v-for="g in templateGroups" :key="g.key" class="mb-4">
                <div class="mb-2 flex items-center gap-2">
                    <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ g.label }}</span>
                    <span class="h-px flex-1 bg-slate-200 dark:bg-white/10"></span>
                </div>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <button
                        v-for="t in g.items"
                        :key="t.key"
                        type="button"
                        class="rounded-xl border border-slate-200 bg-white p-4 text-left transition hover:border-[#2E74B5] hover:shadow-sm dark:border-white/10 dark:bg-white/5"
                        @click="useTemplate(t)"
                    >
                        <div class="text-sm font-semibold text-[#1F4E79] dark:text-sky-200">{{ t.title }}</div>
                        <div class="mt-1 line-clamp-2 text-xs text-slate-500 dark:text-slate-400">{{ t.content }}</div>
                    </button>
                </div>
            </div>
        </div>

        <form v-if="showForm" class="bg-white rounded-xl border border-slate-200 p-6 mb-6 space-y-5" @submit.prevent="submit">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Заголовок</label>
                <input v-model="form.title" type="text" placeholder="Например: Стрижка и укладка" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                <p class="mt-1 text-xs text-slate-400">Коротко, по сути вопроса — как тему в FAQ.</p>
                <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Текст</label>
                <textarea v-model="form.content" rows="4" placeholder="Что входит, цена, длительность, нюансы. Пишите так, как ответили бы клиенту." class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                <p v-if="form.errors.content" class="mt-1 text-sm text-red-600">{{ form.errors.content }}</p>
            </div>

            <!-- Ссылки -->
            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="text-sm font-medium text-slate-700">Ссылки</label>
                    <button type="button" class="text-sm text-[#2E74B5] hover:underline" @click="addLink">+ ссылка</button>
                </div>
                <p class="text-xs text-slate-400 mb-2">Прайс, запись, соцсети, отзывы — что полезно прислать клиенту.</p>
                <div v-for="(link, i) in form.links" :key="i" class="flex gap-2 mb-2">
                    <input v-model="link.label" type="text" placeholder="Подпись (Прайс)" class="w-1/3 rounded-lg border border-slate-300 px-3 py-2" />
                    <input v-model="link.url" type="url" placeholder="https://..." class="flex-1 rounded-lg border border-slate-300 px-3 py-2" />
                    <button type="button" class="text-red-600 px-2" @click="removeLink(i)">×</button>
                </div>
                <p v-if="form.errors.links" class="mt-1 text-sm text-red-600">{{ form.errors.links }}</p>
            </div>

            <!-- Картинки -->
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Картинки (примеры работ)</label>
                <ImageUploader v-model="form.images" />
                <p class="text-xs text-slate-400 mt-1">Бот сможет показывать их клиенту.</p>
                <p v-if="form.errors.images" class="mt-1 text-sm text-red-600">{{ form.errors.images }}</p>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <Toggle v-model="form.is_published" />
                Опубликовать (бот будет использовать)
            </label>

            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50"
            >
                Сохранить
            </button>
        </form>

        <div v-if="entries.length === 0" class="text-slate-400 text-center py-8">
            Записей пока нет. Добавьте первую — например, «Часы работы» или «Цены».
        </div>

        <div v-else class="space-y-3">
            <div v-for="entry in entries" :key="entry.id" class="bg-white rounded-xl border border-slate-200 p-5">
                <div class="flex items-start justify-between gap-4">
                    <div class="min-w-0">
                        <div class="font-medium text-slate-700">
                            {{ entry.title }}
                            <span
                                class="ml-2 text-xs rounded-full px-2 py-0.5"
                                :class="entry.is_published ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'"
                            >
                                {{ entry.is_published ? 'опубликовано' : 'черновик' }}
                            </span>
                        </div>
                        <div class="text-sm text-slate-500 mt-1 line-clamp-2">{{ entry.content }}</div>
                        <div v-if="entry.links.length" class="flex flex-wrap gap-2 mt-2">
                            <a v-for="(l, i) in entry.links" :key="i" :href="l.url" target="_blank" class="text-xs text-[#2E74B5] hover:underline">
                                🔗 {{ l.label }}
                            </a>
                        </div>
                        <div v-if="entry.images.length" class="flex flex-wrap gap-2 mt-2">
                            <img v-for="(img, i) in entry.images" :key="i" :src="img.url" class="h-14 w-14 object-cover rounded border border-slate-200" />
                        </div>
                    </div>
                    <div class="flex items-center gap-3 shrink-0">
                        <Link :href="`/cabinet/knowledge/${entry.id}/edit`" class="text-sm text-[#2E74B5] hover:underline">Изменить</Link>
                        <button type="button" class="text-sm text-red-600 hover:underline" @click="remove(entry.id)">Удалить</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Пагинация списка записей -->
        <div v-if="pagination.last > 1" class="mt-5 flex items-center justify-center gap-3 text-sm">
            <button
                type="button"
                class="rounded-lg border border-slate-200 px-3 py-1.5 text-slate-600 disabled:opacity-40"
                :disabled="pagination.current <= 1"
                @click="goToPage(pagination.current - 1)"
            >
                ← Назад
            </button>
            <span class="text-slate-500">Стр. {{ pagination.current }} из {{ pagination.last }}</span>
            <button
                type="button"
                class="rounded-lg border border-slate-200 px-3 py-1.5 text-slate-600 disabled:opacity-40"
                :disabled="pagination.current >= pagination.last"
                @click="goToPage(pagination.current + 1)"
            >
                Вперёд →
            </button>
        </div>
        </div>

        <!-- Вкладка: развитие бота -->
        <div v-else>
            <p class="text-slate-500 text-sm mb-4 max-w-2xl">
                Вопросы клиентов, на которые бот не смог дать ответ по базе знаний. Добавьте ответ в базу — и бот будет
                отвечать на них сам. Число справа — сколько раз вопрос задавали.
            </p>

            <div v-if="gaps.length === 0" class="text-slate-400 text-center py-8">
                Пока пусто — бот отвечает на всё. Сюда попадут вопросы, на которые он не нашёл ответа.
            </div>

            <div v-else class="space-y-3">
                <div v-for="gap in gaps" :key="gap.id" class="bg-white rounded-xl border border-slate-200 p-5">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="font-medium text-slate-700">«{{ gap.question }}»</div>
                            <div class="text-xs text-slate-400 mt-1 flex flex-wrap gap-x-3 gap-y-0.5">
                                <span>Спрашивали: <b class="text-slate-600">{{ gap.occurrences }}</b></span>
                                <span v-if="gap.channel">Канал: {{ gap.channel }}</span>
                                <span v-if="gap.last_seen_at">Последний раз: {{ gap.last_seen_at }}</span>
                                <Link v-if="gap.conversation_id" :href="`/cabinet/conversations/${gap.conversation_id}`" class="text-[#2E74B5] hover:underline">Диалог →</Link>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 shrink-0">
                            <button type="button" class="rounded-lg bg-[#2E74B5] px-3 py-1.5 text-sm font-medium text-white hover:bg-[#255f96]" @click="promoteGap(gap.id)">В базу знаний</button>
                            <button type="button" class="text-sm text-slate-500 hover:underline" @click="dismissGap(gap.id)">Скрыть</button>
                            <button type="button" class="text-sm text-red-600 hover:underline" @click="removeGap(gap.id)">Удалить</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
