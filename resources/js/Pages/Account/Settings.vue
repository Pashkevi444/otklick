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
    { icon: '🛡️', label: 'Двухфакторная аутентификация', text: 'Google Authenticator и любые TOTP-приложения', href: '/account/two-factor' },
];
</script>

<template>
    <Head title="Настройки аккаунта" />

    <AppLayout title="Настройки аккаунта">
        <p class="mb-6 text-sm text-muted">{{ account.name }} · {{ account.email }}</p>

        <div class="grid max-w-2xl gap-4 sm:grid-cols-2">
            <Link
                v-for="item in items"
                :key="item.href"
                :href="item.href"
                class="group otk-card block p-5 transition hover:-translate-y-0.5 hover:border-brand hover:shadow-lg hover:shadow-[rgba(16,28,51,0.06)]"
            >
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-active text-2xl transition group-hover:scale-110">
                    {{ item.icon }}
                </div>
                <div class="mt-4 font-display text-base font-semibold text-ink">{{ item.label }}</div>
                <div class="mt-1 text-sm text-muted">{{ item.text }}</div>
            </Link>
        </div>
    </AppLayout>
</template>
