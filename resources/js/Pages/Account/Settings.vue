<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface Account {
    name: string;
    email: string;
}

defineProps<{ account: Account }>();

const items = [
    { icon: '🔑', label: 'Сменить пароль', text: 'Обновите пароль для входа', href: '/account/password' },
    { icon: '✉️', label: 'Сменить почту', text: 'С подтверждением на новый адрес', href: '/account/email' },
];
</script>

<template>
    <Head title="Настройки аккаунта" />

    <AppLayout title="Настройки аккаунта">
        <p class="mb-6 text-sm text-slate-500">{{ account.name }} · {{ account.email }}</p>

        <div class="grid max-w-2xl gap-4 sm:grid-cols-2">
            <Link
                v-for="item in items"
                :key="item.href"
                :href="item.href"
                class="group block rounded-2xl border border-slate-200 bg-white p-5 transition hover:-translate-y-1 hover:border-[#2E74B5] hover:shadow-lg hover:shadow-slate-100 dark:border-white/10 dark:bg-white/5"
            >
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#EAF2FB] text-2xl transition group-hover:scale-110 dark:bg-white/10">
                    {{ item.icon }}
                </div>
                <div class="mt-4 font-semibold text-[#1F4E79] dark:text-sky-200">{{ item.label }}</div>
                <div class="mt-1 text-sm text-slate-500">{{ item.text }}</div>
            </Link>
        </div>

        <p class="mt-6 max-w-2xl text-xs text-slate-400">Двухфакторная аутентификация появится здесь позже.</p>
    </AppLayout>
</template>
