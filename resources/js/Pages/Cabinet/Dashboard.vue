<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const tenantName = computed(() => page.props.auth.user?.tenant?.name ?? 'ваш бизнес');

const cards = [
    { label: 'Каналы', text: 'Подключите Telegram-бота', href: '/cabinet/channels' },
    { label: 'Профиль бизнеса', text: 'Часы работы, контакты, эскалация', href: '/cabinet/profile' },
    { label: 'База знаний', text: 'Тексты, по которым отвечает бот', href: '/cabinet/knowledge' },
];
</script>

<template>
    <Head title="Дашборд" />

    <AppLayout title="Дашборд">
        <p class="text-slate-600 mb-6">Настройте контекст работы для «{{ tenantName }}».</p>

        <div class="grid sm:grid-cols-3 gap-4">
            <Link
                v-for="card in cards"
                :key="card.href"
                :href="card.href"
                class="block bg-white rounded-xl border border-slate-200 p-5 hover:border-[#2E74B5] transition"
            >
                <div class="font-semibold text-[#1F4E79]">{{ card.label }}</div>
                <div class="mt-1 text-sm text-slate-500">{{ card.text }}</div>
            </Link>
        </div>
    </AppLayout>
</template>
