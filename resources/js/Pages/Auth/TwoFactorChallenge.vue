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
                <label class="mb-1 block text-sm font-medium text-slate-700" for="code">Код 2FA или резервный код</label>
                <input
                    id="code"
                    v-model="form.code"
                    type="text"
                    required
                    autofocus
                    autocomplete="one-time-code"
                    placeholder="123456"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 text-center text-lg tracking-widest outline-none focus:border-[#2E74B5] focus:ring-1 focus:ring-[#2E74B5]"
                />
                <p v-if="form.errors.code" class="mt-1 text-sm text-red-600">{{ form.errors.code }}</p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-lg bg-[#2E74B5] px-4 py-2.5 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50"
            >
                Подтвердить вход
            </button>
        </form>
    </AuthLayout>
</template>
