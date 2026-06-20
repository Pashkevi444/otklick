<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface Item {
    id: string;
    title: string;
    body: string;
    is_published: boolean;
    published_at: string | null;
    created_at: string | null;
}

const props = defineProps<{ type: string; title: string; items: Item[] }>();

const editingId = ref<string | null>(null);
const form = useForm<{ type: string; title: string; body: string; is_published: boolean }>({
    type: props.type,
    title: '',
    body: '',
    is_published: true,
});

const reset = (): void => {
    editingId.value = null;
    form.reset();
    form.type = props.type;
    form.is_published = true;
};

const edit = (item: Item): void => {
    editingId.value = item.id;
    form.title = item.title;
    form.body = item.body;
    form.is_published = item.is_published;
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
</script>

<template>
    <Head :title="title" />

    <AppLayout :title="title">
        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Редактор -->
            <form class="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5" @submit.prevent="submit">
                <h2 class="font-semibold text-[#1F4E79] dark:text-sky-200">{{ editingId ? 'Редактировать' : 'Новый анонс' }}</h2>

                <input
                    v-model="form.title"
                    type="text"
                    placeholder="Заголовок"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/15 dark:bg-white/5"
                />
                <p v-if="form.errors.title" class="text-xs text-rose-600">{{ form.errors.title }}</p>

                <textarea
                    v-model="form.body"
                    rows="7"
                    placeholder="Текст анонса…"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/15 dark:bg-white/5"
                ></textarea>
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

            <!-- Список -->
            <div class="space-y-3">
                <p v-if="props.items.length === 0" class="rounded-2xl border border-slate-200 bg-white p-6 text-center text-slate-400 dark:border-white/10 dark:bg-white/5">
                    Анонсов пока нет.
                </p>
                <article
                    v-for="item in props.items"
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
                            <p class="mt-1 line-clamp-2 text-xs text-slate-500">{{ item.body }}</p>
                        </div>
                        <div class="flex flex-none gap-2 text-sm">
                            <button type="button" class="text-[#2E74B5] hover:underline" @click="edit(item)">Править</button>
                            <button type="button" class="text-rose-600 hover:underline" @click="remove(item)">Удалить</button>
                        </div>
                    </div>
                </article>
            </div>
        </div>
    </AppLayout>
</template>
