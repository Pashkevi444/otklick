<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';

interface Item {
    id: string;
    title: string;
    body: string;
    excerpt: string;
    is_published: boolean;
    published_at: string | null;
    created_at: string | null;
}
interface Page {
    data: Item[];
    current_page: number;
    last_page: number;
    total: number;
}

const props = defineProps<{ type: string; title: string; page: Page; search: string | null }>();

const editingId = ref<string | null>(null);
const showPreview = ref(false);

// Серверный поиск по новостям (по заголовку/тексту), с дебаунсом.
const search = ref(props.search ?? '');
let searchTimer: ReturnType<typeof setTimeout> | undefined;
const runSearch = (): void => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(base.value, { search: search.value || undefined }, { preserveScroll: true, preserveState: true, replace: true });
    }, 350);
};
const form = useForm<{ type: string; title: string; body: string; is_published: boolean }>({
    type: props.type,
    title: '',
    body: '',
    is_published: true,
});

const base = computed(() => '/admin/news');
const fmt = (d: string | null): string =>
    d ? new Date(d.replace(' ', 'T')).toLocaleString('ru-RU', { day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—';

const reset = (): void => {
    editingId.value = null;
    showPreview.value = false;
    form.reset();
    form.type = props.type;
    form.is_published = true;
};

const edit = (item: Item): void => {
    editingId.value = item.id;
    form.title = item.title;
    form.body = item.body;
    form.is_published = item.is_published;
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const submit = (): void => {
    if (editingId.value) {
        form.put(`/admin/announcements/${editingId.value}`, { preserveScroll: true, onSuccess: reset });
    } else {
        form.post('/admin/announcements', { preserveScroll: true, onSuccess: reset });
    }
};

const remove = (item: Item): void => {
    if (confirm('Удалить анонс?')) router.delete(`/admin/announcements/${item.id}`, { preserveScroll: true });
};

const goPage = (p: number): void => router.get(base.value, { page: p, search: search.value || undefined }, { preserveScroll: true, preserveState: true });
</script>

<template>
    <Head :title="title" />

    <AppLayout :title="title">
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <!-- Редактор -->
            <form class="min-w-0 space-y-3 rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5" @submit.prevent="submit">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold text-[#1F4E79] dark:text-sky-200">{{ editingId ? 'Редактировать' : 'Новый анонс' }}</h2>
                    <button type="button" class="text-sm text-slate-500 hover:underline" @click="showPreview = !showPreview">
                        {{ showPreview ? 'Скрыть предпросмотр' : 'Предпросмотр' }}
                    </button>
                </div>

                <input
                    v-model="form.title"
                    type="text"
                    placeholder="Заголовок"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/15 dark:bg-white/5"
                />
                <p v-if="form.errors.title" class="text-xs text-rose-600">{{ form.errors.title }}</p>

                <template v-if="!showPreview">
                    <RichTextEditor v-model="form.body" upload-url="/admin/announcements/images" />
                    <p class="text-xs text-slate-400">Выделяйте жирным/курсивом, добавляйте заголовки, списки, ссылки и картинки прямо в текст.</p>
                </template>

                <!-- Предпросмотр (как увидит бизнес) -->
                <div v-else class="rounded-lg border border-slate-200 bg-slate-50 p-4 dark:border-white/10 dark:bg-white/5">
                    <h3 class="text-lg font-bold text-[#1F4E79] dark:text-sky-200">{{ form.title || 'Заголовок' }}</h3>
                    <div class="rte mt-2 text-sm text-slate-700 dark:text-slate-200" v-html="form.body || '<p class=\'text-slate-400\'>Текст анонса…</p>'"></div>
                </div>
                <p v-if="form.errors.body" class="text-xs text-rose-600">{{ form.errors.body }}</p>

                <label class="flex items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                    <input v-model="form.is_published" type="checkbox" class="rounded" />
                    Опубликовать (видно бизнесам)
                </label>

                <div class="flex gap-2">
                    <button type="submit" class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-semibold text-white hover:bg-[#255f97] disabled:opacity-50" :disabled="form.processing">
                        {{ editingId ? 'Сохранить' : 'Добавить' }}
                    </button>
                    <button v-if="editingId" type="button" class="rounded-lg px-4 py-2 text-sm text-slate-500 hover:underline" @click="reset">Отмена</button>
                </div>
            </form>

            <!-- Правая колонка: поиск + список -->
            <div class="min-w-0">
                <!-- Поиск -->
                <input
                    v-model="search"
                    type="search"
                    placeholder="Поиск по заголовку и тексту…"
                    class="mb-3 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/15 dark:bg-white/5"
                    @input="runSearch"
                />

                <!-- Список -->
                <div class="space-y-3">
                <p v-if="props.page.data.length === 0" class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-slate-400 dark:border-white/10 dark:bg-white/5">
                    {{ search ? 'Ничего не найдено.' : 'Анонсов пока нет.' }}
                </p>
                <article
                    v-for="item in props.page.data"
                    :key="item.id"
                    class="rounded-2xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="truncate font-semibold text-slate-800 dark:text-slate-100">{{ item.title }}</h3>
                                <span
                                    class="flex-none rounded-full px-2 py-0.5 text-[10px] font-semibold"
                                    :class="item.is_published ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300' : 'bg-slate-200 text-slate-500 dark:bg-white/10'"
                                >
                                    {{ item.is_published ? 'Опубликовано' : 'Черновик' }}
                                </span>
                            </div>
                            <p class="mt-1 line-clamp-2 text-xs text-slate-500">{{ item.excerpt }}</p>
                            <p class="mt-1 text-[11px] text-slate-400">
                                Создано: {{ fmt(item.created_at) }}<template v-if="item.published_at"> · Опубликовано: {{ fmt(item.published_at) }}</template>
                            </p>
                        </div>
                        <div class="flex flex-none gap-2 text-sm">
                            <button type="button" class="text-[#2E74B5] hover:underline" @click="edit(item)">Править</button>
                            <button type="button" class="text-rose-600 hover:underline" @click="remove(item)">Удалить</button>
                        </div>
                    </div>
                </article>

                <div v-if="props.page.last_page > 1" class="flex items-center justify-center gap-3 pt-1">
                    <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm disabled:opacity-40 dark:border-white/15" :disabled="props.page.current_page <= 1" @click="goPage(props.page.current_page - 1)">←</button>
                    <span class="text-sm text-slate-500">{{ props.page.current_page }} / {{ props.page.last_page }}</span>
                    <button type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm disabled:opacity-40 dark:border-white/15" :disabled="props.page.current_page >= props.page.last_page" @click="goPage(props.page.current_page + 1)">→</button>
                </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
