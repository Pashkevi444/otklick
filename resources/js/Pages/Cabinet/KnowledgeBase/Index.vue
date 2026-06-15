<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ImageUploader from '@/Components/ImageUploader.vue';

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

defineProps<{ entries: Entry[] }>();

const showForm = ref(false);

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
        <p class="text-slate-500 text-sm mb-4 max-w-2xl">
            Добавляйте то, о чём чаще всего спрашивают клиенты: услуги, цены, условия, частые вопросы.
            К записи можно прикрепить ссылки (прайс, соцсети) и картинки — примеры работ.
        </p>

        <div class="flex justify-end mb-4">
            <button
                type="button"
                class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96]"
                @click="showForm = !showForm"
            >
                {{ showForm ? 'Отмена' : 'Новая запись' }}
            </button>
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
                <input v-model="form.is_published" type="checkbox" class="rounded border-slate-300" />
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
    </AppLayout>
</template>
