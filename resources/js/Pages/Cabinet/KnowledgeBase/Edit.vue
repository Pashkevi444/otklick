<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface Entry {
    id: string;
    title: string;
    content: string;
    is_published: boolean;
}

const props = defineProps<{ entry: Entry }>();

const form = useForm({
    title: props.entry.title,
    content: props.entry.content,
    is_published: props.entry.is_published,
});

const submit = (): void => {
    form.put(`/cabinet/knowledge/${props.entry.id}`);
};
</script>

<template>
    <Head title="Запись базы знаний" />

    <AppLayout>
        <Link href="/cabinet/knowledge" class="text-sm text-[#2E74B5] hover:underline">← К базе знаний</Link>

        <form class="bg-white rounded-xl border border-slate-200 p-6 mt-2 max-w-2xl space-y-4" @submit.prevent="submit">
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
