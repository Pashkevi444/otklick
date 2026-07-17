<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const form = useForm({
    email: '',
});

const submit = (): void => {
    form.post('/forgot-password');
};
</script>

<template>
    <Head title="Восстановление пароля" />

    <AuthLayout title="Восстановление пароля" subtitle="Пришлём код на вашу почту">
        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label class="mb-1.5 block text-sm font-semibold text-ink" for="email">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autofocus
                    autocomplete="username"
                    class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none transition placeholder:text-muted2 focus:border-brand"
                />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.email }}</p>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-full bg-brand bg-gradient-to-r from-brand to-violet-brand py-3 text-[15px] font-bold text-white shadow-lg shadow-[rgba(43,92,224,0.30)] transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-[rgba(43,92,224,0.35)] disabled:pointer-events-none disabled:opacity-50"
            >
                Отправить код
            </button>

            <p class="text-center text-sm text-muted">
                <Link href="/login" class="font-semibold text-brand hover:underline">← Вернуться ко входу</Link>
            </p>
        </form>
    </AuthLayout>
</template>
