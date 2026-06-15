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
        <div v-if="status" class="mb-5 rounded-lg bg-blue-50 border border-blue-200 text-blue-700 px-4 py-2 text-sm">
            {{ status }}
        </div>

        <form class="space-y-5" @submit.prevent="submit">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="email">Email</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    required
                    autocomplete="username"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-[#2E74B5] focus:ring-1 focus:ring-[#2E74B5] outline-none"
                />
                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="code">Код из письма</label>
                <input
                    id="code"
                    v-model="form.code"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    required
                    placeholder="6 цифр"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 tracking-widest focus:border-[#2E74B5] focus:ring-1 focus:ring-[#2E74B5] outline-none"
                />
                <p v-if="form.errors.code" class="mt-1 text-sm text-red-600">{{ form.errors.code }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="password">Новый пароль</label>
                <input
                    id="password"
                    v-model="form.password"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-[#2E74B5] focus:ring-1 focus:ring-[#2E74B5] outline-none"
                />
                <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1" for="password_confirmation">Повторите пароль</label>
                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-[#2E74B5] focus:ring-1 focus:ring-[#2E74B5] outline-none"
                />
            </div>

            <button
                type="submit"
                :disabled="form.processing"
                class="w-full rounded-lg bg-[#2E74B5] py-2.5 font-medium text-white hover:bg-[#255f96] disabled:opacity-50 transition"
            >
                Сменить пароль
            </button>

            <p class="text-center text-sm text-slate-500">
                <Link href="/forgot-password" class="text-[#2E74B5] hover:underline">Запросить новый код</Link>
            </p>
        </form>
    </AuthLayout>
</template>
