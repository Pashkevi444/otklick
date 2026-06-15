<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface Entry {
    id: string;
    title: string;
    content: string;
    is_published: boolean;
    updated_at: string | null;
}

defineProps<{ entries: Entry[] }>();

const showForm = ref(false);

const form = useForm({
    title: '',
    content: '',
    is_published: true,
});

const submit = (): void => {
    form.post('/cabinet/knowledge', {
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
        <div class="flex justify-end mb-4">
            <button
                type="button"
                class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96]"
                @click="showForm = !showForm"
            >
                {{ showForm ? 'Отмена' : 'Новая запись' }}
            </button>
        </div>

        <form v-if="showForm" class="bg-white rounded-xl border border-slate-200 p-6 mb-6 space-y-4" @submit.prevent="submit">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Заголовок</label>
                <input v-model="form.title" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Текст</label>
                <textarea v-model="form.content" rows="4" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                <p v-if="form.errors.content" class="mt-1 text-sm text-red-600">{{ form.errors.content }}</p>
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
            Записей пока нет. Добавьте текст, по которому бот будет отвечать.
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="entry in entries"
                :key="entry.id"
                class="bg-white rounded-xl border border-slate-200 p-5 flex items-start justify-between gap-4"
            >
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
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <Link :href="`/cabinet/knowledge/${entry.id}/edit`" class="text-sm text-[#2E74B5] hover:underline">
                        Изменить
                    </Link>
                    <button type="button" class="text-sm text-red-600 hover:underline" @click="remove(entry.id)">
                        Удалить
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
