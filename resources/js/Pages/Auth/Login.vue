<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Toggle from '@/Components/Toggle.vue';

defineProps<{ requestAccessUrl: string }>();

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

            <div>
                <div class="mb-1.5 flex items-baseline justify-between">
                    <label class="block text-sm font-semibold text-ink" for="password">Пароль</label>
                    <Link href="/forgot-password" class="text-[13px] font-semibold text-brand hover:underline">Забыли пароль?</Link>
                </div>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="current-password"
                    class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none transition placeholder:text-muted2 focus:border-brand"
                />
                <p v-if="form.errors.password" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.password }}</p>
            </div>

            <label class="flex items-center gap-2.5 text-sm text-muted">
                <Toggle v-model="form.remember" />
                Запомнить меня
            </label>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-full bg-brand bg-gradient-to-r from-brand to-violet-brand py-3 text-[15px] font-bold text-white shadow-lg shadow-[rgba(43,92,224,0.30)] transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-[rgba(43,92,224,0.35)] disabled:pointer-events-none disabled:opacity-50"
            >
                Войти
            </button>
        </form>

        <p class="mt-6 text-center text-sm text-muted">
            Нет аккаунта в «Отклике»?
            <a :href="requestAccessUrl" class="font-bold text-brand hover:underline">Оставьте заявку</a>
        </p>
    </AuthLayout>
</template>
