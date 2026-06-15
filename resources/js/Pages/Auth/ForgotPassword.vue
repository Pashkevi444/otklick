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

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-lg bg-[#2E74B5] py-2.5 font-medium text-white hover:bg-[#255f96] disabled:opacity-50 transition"
            >
                Отправить код
            </button>

            <p class="text-center text-sm text-slate-500">
                <Link href="/login" class="text-[#2E74B5] hover:underline">← Вернуться ко входу</Link>
            </p>
        </form>
    </AuthLayout>
</template>
