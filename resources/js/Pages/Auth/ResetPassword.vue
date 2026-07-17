<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';

const props = defineProps<{ email: string }>();

const page = usePage();
const status = computed(() => page.props.flash.status);

const form = useForm({
    email: props.email,
    code: '',
    password: '',
    password_confirmation: '',
});

const submit = (): void => {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Новый пароль" />

    <AuthLayout title="Новый пароль" subtitle="Введите код из письма и придумайте пароль">
        <div v-if="status" class="mb-5 rounded-xl border border-brand/25 bg-active px-4 py-2.5 text-sm font-medium text-brand">
            {{ status }}
        </div>

        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label class="mb-1.5 block text-sm font-semibold text-ink" for="email">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="username"
                    class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none transition placeholder:text-muted2 focus:border-brand"
                />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.email }}</p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-semibold text-ink" for="code">Код из письма</label>
                <input
                    id="code"
                    v-model="form.code"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    required
                    placeholder="6 цифр"
                    class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm tracking-widest text-ink outline-none transition placeholder:text-muted2 focus:border-brand"
                />
                <p v-if="form.errors.code" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.code }}</p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-semibold text-ink" for="password">Новый пароль</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none transition placeholder:text-muted2 focus:border-brand"
                />
                <p v-if="form.errors.password" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ form.errors.password }}</p>
            </div>

            <div>
                <label class="mb-1.5 block text-sm font-semibold text-ink" for="password_confirmation">Повторите пароль</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none transition placeholder:text-muted2 focus:border-brand"
                />
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-full bg-brand bg-gradient-to-r from-brand to-violet-brand py-3 text-[15px] font-bold text-white shadow-lg shadow-[rgba(43,92,224,0.30)] transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-[rgba(43,92,224,0.35)] disabled:pointer-events-none disabled:opacity-50"
            >
                Сменить пароль
            </button>

            <p class="text-center text-sm text-muted">
                <Link href="/forgot-password" class="font-semibold text-brand hover:underline">Запросить новый код</Link>
            </p>
        </form>
    </AuthLayout>
</template>
