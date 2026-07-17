<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const submit = (): void => {
    form.put('/account/password', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Смена пароля" />

    <AppLayout title="Смена пароля">
        <form class="otk-card max-w-md space-y-5 p-6" @submit.prevent="submit">
            <div>
                <label class="mb-1.5 block text-sm font-semibold text-ink">Текущий пароль</label>
                <input v-model="form.current_password" type="password" autocomplete="current-password" class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none transition placeholder:text-muted2 focus:border-brand" />
                <p v-if="form.errors.current_password" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.current_password }}</p>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-semibold text-ink">Новый пароль</label>
                <input v-model="form.password" type="password" autocomplete="new-password" class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none transition placeholder:text-muted2 focus:border-brand" />
                <p v-if="form.errors.password" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.password }}</p>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-semibold text-ink">Повторите новый пароль</label>
                <input v-model="form.password_confirmation" type="password" autocomplete="new-password" class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none transition placeholder:text-muted2 focus:border-brand" />
            </div>
            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="otk-btn-primary disabled:pointer-events-none disabled:opacity-50"
                >
                    Сменить пароль
                </button>
                <span v-if="form.recentlySuccessful" class="text-sm text-emerald-600 dark:text-emerald-400">Обновлено</span>
            </div>
        </form>
    </AppLayout>
</template>
