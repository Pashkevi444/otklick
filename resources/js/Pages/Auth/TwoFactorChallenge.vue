<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const form = useForm({ code: '' });

const submit = (): void => {
    form.post('/two-factor-challenge', { onFinish: () => form.reset('code') });
};
</script>

<template>
    <Head title="Подтверждение входа" />

    <AuthLayout title="Подтверждение входа" subtitle="Введите код из приложения-аутентификатора">
        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label class="mb-1.5 block text-sm font-semibold text-ink" for="code">Код 2FA или резервный код</label>
                <input
                    id="code"
                    v-model="form.code"
                    type="text"
                    required
                    autofocus
                    autocomplete="one-time-code"
                    placeholder="123456"
                    class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-center text-lg tracking-widest text-ink outline-none transition placeholder:text-muted2 focus:border-brand"
                />
                <p v-if="form.errors.code" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.code }}</p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-full bg-brand bg-gradient-to-r from-brand to-violet-brand py-3 text-[15px] font-bold text-white shadow-lg shadow-[rgba(43,92,224,0.30)] transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-[rgba(43,92,224,0.35)] disabled:pointer-events-none disabled:opacity-50"
            >
                Подтвердить вход
            </button>
        </form>
    </AuthLayout>
</template>
