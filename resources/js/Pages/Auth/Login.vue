<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = (): void => {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <Head title="Вход" />

    <AuthLayout title="Вход в систему" subtitle="AI-администратор для локального бизнеса">
        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="email">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autofocus
                    autocomplete="username"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-[#2E74B5] focus:ring-1 focus:ring-[#2E74B5] outline-none"
                />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="password">Пароль</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-[#2E74B5] focus:ring-1 focus:ring-[#2E74B5] outline-none"
                />
                <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
            </div>

            <div class="flex items-center justify-between">
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input v-model="form.remember" type="checkbox" class="rounded border-slate-300" />
                    Запомнить меня
                </label>
                <Link href="/forgot-password" class="text-sm text-[#2E74B5] hover:underline">Забыли пароль?</Link>
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-lg bg-[#2E74B5] py-2.5 font-medium text-white hover:bg-[#255f96] disabled:opacity-50 transition"
            >
                Войти
            </button>
        </form>
    </AuthLayout>
</template>
