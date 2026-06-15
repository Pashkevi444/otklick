<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface Settings {
    hero_title: string;
    hero_subtitle: string;
    phone: string | null;
    email: string | null;
    telegram: string | null;
    access_note: string;
}

const props = defineProps<{ settings: Settings }>();

const form = useForm({
    hero_title: props.settings.hero_title,
    hero_subtitle: props.settings.hero_subtitle,
    phone: props.settings.phone ?? '',
    email: props.settings.email ?? '',
    telegram: props.settings.telegram ?? '',
    access_note: props.settings.access_note,
});

const submit = (): void => {
    form.put('/admin/site', { preserveScroll: true });
};
</script>

<template>
    <Head title="Сайт" />

    <AppLayout title="Сайт">
        <p class="text-slate-500 text-sm mb-4 max-w-2xl">Контент публичного лендинга и контакты.</p>

        <form class="bg-white rounded-xl border border-slate-200 p-6 max-w-2xl space-y-5" @submit.prevent="submit">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Заголовок (hero)</label>
                <input v-model="form.hero_title" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                <p v-if="form.errors.hero_title" class="mt-1 text-sm text-red-600">{{ form.errors.hero_title }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Подзаголовок (hero)</label>
                <textarea v-model="form.hero_subtitle" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                <p v-if="form.errors.hero_subtitle" class="mt-1 text-sm text-red-600">{{ form.errors.hero_subtitle }}</p>
            </div>

            <div class="grid sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Телефон</label>
                    <input v-model="form.phone" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Email</label>
                    <input v-model="form.email" type="email" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Telegram (без @)</label>
                    <input v-model="form.telegram" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Пометка о доступе (оплата)</label>
                <textarea v-model="form.access_note" rows="2" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                <p v-if="form.errors.access_note" class="mt-1 text-sm text-red-600">{{ form.errors.access_note }}</p>
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50"
                >
                    Сохранить
                </button>
                <span v-if="form.recentlySuccessful" class="text-sm text-green-600">Сохранено</span>
            </div>
        </form>
    </AppLayout>
</template>
