<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const tenantName = computed(() => page.props.auth.user?.tenant?.name ?? 'ваш бизнес');
const hasCrm = computed(() => page.props.auth.user?.tenant?.features?.crm ?? false);

interface Card {
    icon: string;
    label: string;
    text: string;
    href: string;
}

const cards = computed<Card[]>(() => {
    const list: Card[] = [
        { icon: '📈', label: 'Аналитика', text: 'Лиды, конверсия и что улучшить', href: '/cabinet/analytics' },
        { icon: '💬', label: 'Диалоги', text: 'Журнал переписок бота с клиентами', href: '/cabinet/conversations' },
        { icon: '📡', label: 'Каналы', text: 'Подключите Telegram-бота', href: '/cabinet/channels' },
        { icon: '🌐', label: 'Виджет на сайт', text: 'Чат с ботом для вашего сайта', href: '/cabinet/widget' },
        { icon: '🏢', label: 'Профиль бизнеса', text: 'Часы работы, контакты, эскалация', href: '/cabinet/profile' },
        { icon: '📚', label: 'База знаний', text: 'Тексты, по которым отвечает бот', href: '/cabinet/knowledge' },
        { icon: '🔔', label: 'Уведомления и эскалация', text: 'Лиды и записи на почту/в Telegram + ответ клиенту через бота', href: '/cabinet/notifications' },
    ];

    if (hasCrm.value) {
        list.push({ icon: '🔗', label: 'Интеграции', text: 'Подключение CRM и автозапись', href: '/cabinet/integrations' });
    }

    list.push({ icon: '⭐', label: 'Подписка', text: 'Ваш тариф и возможности', href: '/cabinet/subscription' });

    return list;
});
</script>

<template>
    <Head title="Дашборд" />

    <AppLayout title="Дашборд">
        <p class="mb-6 text-slate-600 dark:text-slate-300">Управляйте «{{ tenantName }}» — выберите раздел.</p>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <Link
                v-for="card in cards"
                :key="card.href"
                :href="card.href"
                class="group block rounded-2xl border border-slate-200 bg-white p-5 transition hover:-translate-y-1 hover:border-[#2E74B5] hover:shadow-lg hover:shadow-slate-100 dark:border-white/10 dark:bg-white/5"
            >
                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#EAF2FB] text-2xl transition group-hover:scale-110 dark:bg-white/10">
                    {{ card.icon }}
                </div>
                <div class="mt-4 font-semibold text-[#1F4E79] dark:text-sky-200">{{ card.label }}</div>
                <div class="mt-1 text-sm text-slate-500">{{ card.text }}</div>
            </Link>
        </div>
    </AppLayout>
</template>
