<script setup lang="ts">
import { computed, onUnmounted, ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ImageUploader from '@/Components/ImageUploader.vue';
import Pagination from '@/Components/Pagination.vue';
import Toggle from '@/Components/Toggle.vue';
import { useCan } from '@/composables/useCan';

// Право-действие: менять базу знаний (создание/правка/публикация/импорт). Без
// него раздел доступен только на просмотр — кнопки-мутации скрыты.
const canEdit = computed(() => useCan()('knowledge.edit'));

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

const tab = ref<'entries' | 'gaps'>('entries');
const showForm = ref(false);
const showTemplates = ref(false);
// Фильтр пикера шаблонов: поиск + тип бизнеса (их сотни — иначе не найти нужный).
const tplQuery = ref('');
const tplType = ref<string>('');

// Чипы-фильтры по типу бизнеса с количеством.
const tplChips = computed(() => {
    const chips = [{ key: '', label: 'Все', count: props.templates.length }];
    const general = props.templates.filter((t) => !t.businessType).length;
    if (general) chips.push({ key: 'general', label: 'Общие', count: general });
    for (const bt of props.businessTypes) {
        const count = props.templates.filter((t) => t.businessType === bt.value).length;
        if (count) chips.push({ key: bt.value, label: bt.label, count });
    }
    return chips;
});

// Шаблоны базы знаний: отфильтрованы (поиск + тип) и сгруппированы по типу бизнеса.
const templateGroups = computed<{ key: string; label: string; items: KbTemplate[] }[]>(() => {
    const q = tplQuery.value.trim().toLowerCase();
    const match = (t: KbTemplate): boolean => {
        const typeKey = t.businessType ?? 'general';
        if (tplType.value && typeKey !== tplType.value) return false;
        if (!q) return true;
        return t.title.toLowerCase().includes(q) || t.content.toLowerCase().includes(q);
    };
    const groups: { key: string; label: string; items: KbTemplate[] }[] = [];
    const general = props.templates.filter((t) => !t.businessType && match(t));
    if (general.length) groups.push({ key: 'general', label: 'Общие', items: general });
    for (const bt of props.businessTypes) {
        const items = props.templates.filter((t) => t.businessType === bt.value && match(t));
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

// Публикация/снятие прямо из списка (бот использует только опубликованные).
const togglePublish = (entry: Entry): void => {
    router.patch(`/cabinet/knowledge/${entry.id}/publish`, {}, { preserveScroll: true });
};

// --- Импорт базы знаний с сайта (фоновая задача + прогресс) ---
const showImport = ref(false);
const importForm = useForm({ url: '' });
const importing = ref(false);
const importPercent = ref(0);
const importCreated = ref(0);
const importDone = ref(false);
const importFailed = ref(false);
let importTimer: ReturnType<typeof setInterval> | null = null;

const stopImportPolling = (): void => {
    if (importTimer !== null) {
        clearInterval(importTimer);
        importTimer = null;
    }
};

const pollImport = async (): Promise<void> => {
    const res = await fetch('/cabinet/knowledge/import-site/status', {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });
    const data: { percent: number; state: string; created: number } = await res.json();
    importPercent.value = data.percent ?? 0;
    importCreated.value = data.created ?? 0;

    if (data.state === 'done') {
        importPercent.value = 100;
        stopImportPolling();
        importing.value = false;
        importDone.value = true;
        // Подтягиваем свежие черновики в список.
        router.reload({ only: ['entries', 'pagination'] });
    } else if (data.state === 'failed') {
        stopImportPolling();
        importing.value = false;
        importFailed.value = true;
    }
};

const startImport = (): void => {
    importDone.value = false;
    importFailed.value = false;
    importForm.post('/cabinet/knowledge/import-site', {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            importing.value = true;
            importPercent.value = 0;
            importCreated.value = 0;
            void pollImport();
            importTimer = setInterval(() => void pollImport(), 1500);
        },
    });
};

onUnmounted(stopImportPolling);
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

        <div v-if="canEdit" class="flex flex-wrap justify-end gap-2 mb-4">
            <button
                type="button"
                class="rounded-lg border border-[#2E74B5] px-4 py-2 text-sm font-medium text-[#2E74B5] hover:bg-[#EAF2FB] dark:hover:bg-white/10"
                @click="showImport = !showImport"
            >
                {{ showImport ? 'Скрыть импорт' : '✨ Заполнить с сайта' }}
            </button>
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

        <!-- Импорт базы знаний с сайта (AI собирает черновики) -->
        <div v-if="showImport" class="mb-6 rounded-xl border border-[#2E74B5]/30 bg-gradient-to-br from-[#EAF2FB] to-white p-5 dark:border-white/10 dark:from-white/10 dark:to-white/5">
            <div class="text-sm font-semibold text-[#1F4E79] dark:text-sky-200">Заполнить базу знаний с вашего сайта</div>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Дайте ссылку на сайт — AI пройдёт по ключевым страницам (услуги, цены, контакты) и соберёт записи.
                Всё сохранится <b>черновиками</b> — проверьте и опубликуйте нужное.
            </p>

            <form class="mt-3 flex flex-col gap-2 sm:flex-row" @submit.prevent="startImport">
                <input
                    v-model="importForm.url"
                    type="text"
                    inputmode="url"
                    placeholder="https://mysite.ru"
                    :disabled="importing"
                    class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5"
                />
                <button
                    type="submit"
                    :disabled="importing || !importForm.url.trim()"
                    class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50"
                >
                    {{ importing ? `Импорт… ${importPercent}%` : 'Собрать с сайта' }}
                </button>
            </form>
            <p v-if="importForm.errors.url" class="mt-1 text-sm text-red-600">{{ importForm.errors.url }}</p>

            <!-- Прогресс -->
            <div v-if="importing" class="mt-3 max-w-md">
                <div class="h-2 w-full overflow-hidden rounded-full bg-slate-200 dark:bg-white/10">
                    <div class="h-full rounded-full bg-[#2E74B5] transition-all duration-500" :style="{ width: importPercent + '%' }"></div>
                </div>
                <div class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    Читаем сайт и собираем записи… {{ importPercent }}%<span v-if="importCreated"> · черновиков: {{ importCreated }}</span>
                </div>
            </div>

            <div v-else-if="importDone && importCreated > 0" class="mt-3 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-700 dark:bg-green-500/10 dark:text-green-300">
                Готово! Собрано черновиков: <b>{{ importCreated }}</b>. Они ниже в списке — проверьте и опубликуйте нужные.
            </div>
            <div v-else-if="importDone" class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                На сайте не нашлось текста для разбора. Так бывает, если сайт собран на конструкторе с JS-рендерингом (контент подгружается скриптами). Попробуйте другой адрес, добавьте записи вручную или возьмите из шаблонов.
            </div>
            <div v-else-if="importFailed" class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-500/10 dark:text-red-300">
                Не удалось разобрать сайт. Проверьте адрес и попробуйте ещё раз.
            </div>
        </div>

        <!-- Готовые элементы базы знаний по типам бизнеса (сперва «Общие») -->
        <div v-if="showTemplates" class="bg-white rounded-xl border border-slate-200 p-5 mb-6 dark:border-white/10 dark:bg-white/5">
            <p class="text-xs text-slate-500 mb-3">
                Возьмите готовый элемент и замените «…» на данные вашего бизнеса. Он подставится в форму — проверьте и сохраните.
            </p>

            <!-- Поиск + фильтр по типу бизнеса -->
            <input
                v-model="tplQuery"
                type="search"
                placeholder="Поиск шаблона по заголовку или тексту…"
                class="mb-2 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5"
            />
            <div class="mb-4 flex flex-wrap gap-2">
                <button
                    v-for="chip in tplChips"
                    :key="chip.key"
                    type="button"
                    class="rounded-full px-3 py-1 text-xs font-medium transition"
                    :class="tplType === chip.key ? 'bg-[#2E74B5] text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-white/10 dark:text-slate-300'"
                    @click="tplType = chip.key"
                >
                    {{ chip.label }} <span class="opacity-70">{{ chip.count }}</span>
                </button>
            </div>

            <div v-if="templateGroups.length === 0" class="rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-400">
                Ничего не найдено — измените запрос или фильтр.
            </div>

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
                    <div v-if="canEdit" class="flex items-center gap-3 shrink-0">
                        <button
                            type="button"
                            class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                            :class="entry.is_published
                                ? 'border border-slate-300 text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300'
                                : 'bg-green-600 text-white hover:bg-green-700'"
                            @click="togglePublish(entry)"
                        >
                            {{ entry.is_published ? 'Снять с публикации' : 'Опубликовать' }}
                        </button>
                        <Link :href="`/cabinet/knowledge/${entry.id}/edit`" class="text-sm text-[#2E74B5] hover:underline">Изменить</Link>
                        <button type="button" class="text-sm text-red-600 hover:underline" @click="remove(entry.id)">Удалить</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Пагинация списка записей -->
        <Pagination :current="pagination.current" :last="pagination.last" :total="pagination.total" />
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
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <div class="min-w-0">
                            <div class="font-medium text-slate-700 break-words">«{{ gap.question }}»</div>
                            <div class="text-xs text-slate-400 mt-1 flex flex-wrap gap-x-3 gap-y-0.5">
                                <span>Спрашивали: <b class="text-slate-600">{{ gap.occurrences }}</b></span>
                                <span v-if="gap.channel">Канал: {{ gap.channel }}</span>
                                <span v-if="gap.last_seen_at">Последний раз: {{ gap.last_seen_at }}</span>
                                <Link v-if="gap.conversation_id" :href="`/cabinet/conversations/${gap.conversation_id}`" class="text-[#2E74B5] hover:underline">Диалог →</Link>
                            </div>
                        </div>
                        <div v-if="canEdit" class="flex items-center gap-3 shrink-0">
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
