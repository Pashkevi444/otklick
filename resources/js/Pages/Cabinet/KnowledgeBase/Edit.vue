<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
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
}

const props = defineProps<{ entry: Entry }>();

const existing = ref<ImageItem[]>([...props.entry.images]);

const form = useForm<{
    title: string;
    content: string;
    is_published: boolean;
    links: LinkItem[];
    existing_images: string[];
    images: File[];
}>({
    title: props.entry.title,
    content: props.entry.content,
    is_published: props.entry.is_published,
    links: props.entry.links.map((l) => ({ ...l })),
    existing_images: props.entry.images.map((i) => i.path),
    images: [],
});

const addLink = (): void => {
    form.links.push({ label: '', url: '' });
};
const removeLink = (i: number): void => {
    form.links.splice(i, 1);
};

const removeExisting = (i: number): void => {
    existing.value.splice(i, 1);
    form.existing_images = existing.value.map((e) => e.path);
};

const submit = (): void => {
    form.put(`/cabinet/knowledge/${props.entry.id}`, { forceFormData: true });
};
</script>

<template>
    <Head title="Запись базы знаний" />

    <AppLayout>
        <Link href="/cabinet/knowledge" class="text-sm text-[#2E74B5] hover:underline">← К базе знаний</Link>

        <form class="bg-white rounded-xl border border-slate-200 p-6 mt-2 max-w-2xl space-y-5" @submit.prevent="submit">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Заголовок</label>
                <input v-model="form.title" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Текст</label>
                <textarea v-model="form.content" rows="6" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                <p v-if="form.errors.content" class="mt-1 text-sm text-red-600">{{ form.errors.content }}</p>
            </div>

            <div>
                <div class="flex items-center justify-between mb-1">
                    <label class="text-sm font-medium text-slate-700">Ссылки</label>
                    <button type="button" class="text-sm text-[#2E74B5] hover:underline" @click="addLink">+ ссылка</button>
                </div>
                <div v-for="(link, i) in form.links" :key="i" class="flex gap-2 mb-2">
                    <input v-model="link.label" type="text" placeholder="Подпись" class="w-1/3 rounded-lg border border-slate-300 px-3 py-2" />
                    <input v-model="link.url" type="url" placeholder="https://..." class="flex-1 rounded-lg border border-slate-300 px-3 py-2" />
                    <button type="button" class="text-red-600 px-2" @click="removeLink(i)">×</button>
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-2">Картинки</label>
                <div v-if="existing.length" class="flex flex-wrap gap-2 mb-3">
                    <div v-for="(img, i) in existing" :key="img.path" class="relative">
                        <img :src="img.url" class="h-20 w-20 object-cover rounded-lg border border-slate-200" />
                        <button
                            type="button"
                            class="absolute -top-2 -right-2 bg-red-600 text-white rounded-full w-5 h-5 text-xs"
                            @click="removeExisting(i)"
                        >×</button>
                    </div>
                </div>
                <ImageUploader v-model="form.images" />
                <p v-if="form.errors.images" class="mt-1 text-sm text-red-600">{{ form.errors.images }}</p>
            </div>

            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input v-model="form.is_published" type="checkbox" class="rounded border-slate-300" />
                Опубликовано
            </label>

            <button
                type="submit"
                :disabled="form.processing"
                class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50"
            >
                Сохранить
            </button>
        </form>
    </AppLayout>
</template>
