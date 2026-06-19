<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const tenantName = computed(() => page.props.auth.user?.tenant?.name ?? 'ваш бизнес');
const features = computed(() => page.props.auth.user?.tenant?.features);
const isOwner = computed(() => page.props.auth.user?.isOwner ?? false);
const allowed = computed<string[]>(() => page.props.auth.user?.allowedSections ?? []);

interface Card {
    icon: string;
    label: string;
    text: string;
    href: string;
    section?: string; // ограничиваемый раздел; если задан — показываем только при доступе оператора
    feature?: 'analytics' | 'crm' | 'clientBase' | 'broadcasts' | 'flows'; // зависит от возможности тарифа
}

const allCards: Card[] = [
    { icon: '📈', label: 'Аналитика', text: 'Лиды, конверсия и что улучшить', href: '/cabinet/analytics', section: 'analytics', feature: 'analytics' },
    { icon: '💬', label: 'Лиды', text: 'Обращения клиентов и переписка бота', href: '/cabinet/conversations', section: 'conversations' },
    { icon: '👤', label: 'База клиентов', text: 'Карточки клиентов, история и краткое резюме', href: '/cabinet/clients', section: 'clients', feature: 'clientBase' },
    { icon: '📨', label: 'Рассылки', text: 'Сообщения по базе клиентов: мессенджеры и почта, по расписанию', href: '/cabinet/broadcasts', section: 'broadcasts', feature: 'broadcasts' },
    { icon: '🪄', label: 'Сценарии', text: 'No-code воронки: «если клиент написал X → ответь Y, предложи Z»', href: '/cabinet/scenarios', section: 'scenarios', feature: 'flows' },
    { icon: '📡', label: 'Каналы', text: 'Telegram и другие каналы общения с клиентами', href: '/cabinet/channels', section: 'channels' },
    { icon: '🌐', label: 'Виджет на сайт', text: 'Чат с ботом для вашего сайта', href: '/cabinet/widget', section: 'widget' },
    { icon: '🏢', label: 'Профиль бизнеса', text: 'Часы работы, контакты, эскалация', href: '/cabinet/profile', section: 'profile' },
    { icon: '📚', label: 'База знаний', text: 'Тексты, по которым отвечает бот', href: '/cabinet/knowledge', section: 'knowledge' },
    { icon: '🔔', label: 'Уведомления и эскалация', text: 'Лиды и записи на почту/в Telegram + ответ клиенту через бота', href: '/cabinet/notifications', section: 'notifications' },
    { icon: '🔗', label: 'YClients', text: 'Запись клиентов в YClients', href: '/cabinet/integrations', section: 'integrations', feature: 'crm' },
];

const cards = computed<Card[]>(() => {
    const list = allCards.filter((c) => {
        if (c.feature && !features.value?.[c.feature]) {
            return false; // возможность не входит в тариф
        }
        if (c.section && !allowed.value.includes(c.section)) {
            return false; // раздел закрыт оператору
        }
        return true;
    });

    if (isOwner.value) {
        list.push({ icon: '👥', label: 'Команда', text: 'Сотрудники и их доступы', href: '/cabinet/team' });
    }

    // Подписка и оплата — не функционал, оставлены только в меню.
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
