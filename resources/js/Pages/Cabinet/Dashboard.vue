<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const page = usePage();
const tenantName = computed(() => page.props.auth.user?.tenant?.name ?? 'ваш бизнес');
const features = computed(() => page.props.auth.user?.tenant?.features);
const isOwner = computed(() => page.props.auth.user?.isOwner ?? false);
const allowed = computed<string[]>(() => page.props.auth.user?.allowedSections ?? []);
// Состояния плашек, заданные супер-админом глобально: ключ → 'new'|'updated'|'maintenance'.
const cardStates = computed<Record<string, string>>(() => (page.props.cardStates as Record<string, string>) ?? {});

interface Card {
    key: string;
    icon: string;
    label: string;
    text: string;
    href: string;
    section?: string; // ограничиваемый сотруднику раздел; нет доступа — плашку скрываем
    feature?: 'analytics' | 'crm' | 'clientBase' | 'broadcasts' | 'flows'; // зависит от тарифа
}

interface DecoratedCard extends Card {
    disabled: boolean;
    maintenance: boolean;
    planLocked: boolean;
    statusLabel: string | null;
    badge: string | null;
    to: string | null;
}

const allCards: Card[] = [
    { key: 'analytics', icon: '📈', label: 'Аналитика', text: 'Лиды, конверсия и что улучшить', href: '/cabinet/analytics', section: 'analytics', feature: 'analytics' },
    { key: 'conversations', icon: '💬', label: 'Лиды', text: 'Обращения клиентов и переписка бота', href: '/cabinet/conversations', section: 'conversations' },
    { key: 'clients', icon: '👤', label: 'База клиентов', text: 'Карточки клиентов, история и краткое резюме', href: '/cabinet/clients', section: 'clients', feature: 'clientBase' },
    { key: 'broadcasts', icon: '📨', label: 'Рассылки', text: 'Сообщения по базе клиентов: мессенджеры и почта, по расписанию', href: '/cabinet/broadcasts', section: 'broadcasts', feature: 'broadcasts' },
    { key: 'scenarios', icon: '🪄', label: 'Сценарии', text: 'No-code воронки: «если клиент написал X → ответь Y, предложи Z»', href: '/cabinet/scenarios', section: 'scenarios', feature: 'flows' },
    { key: 'testing', icon: '🧪', label: 'Тестирование бота', text: 'Поговорите с ботом как клиент — проверьте ответы, не создавая лидов', href: '/cabinet/testing', section: 'testing' },
    { key: 'channels', icon: '📡', label: 'Каналы', text: 'Telegram и другие каналы общения с клиентами', href: '/cabinet/channels', section: 'channels' },
    { key: 'widget', icon: '🌐', label: 'Виджет на сайт', text: 'Чат с ботом для вашего сайта', href: '/cabinet/widget', section: 'widget' },
    { key: 'profile', icon: '🏢', label: 'Профиль бизнеса', text: 'Часы работы, контакты, эскалация', href: '/cabinet/profile', section: 'profile' },
    { key: 'knowledge', icon: '📚', label: 'База знаний', text: 'Тексты, по которым отвечает бот', href: '/cabinet/knowledge', section: 'knowledge' },
    { key: 'notifications', icon: '🔔', label: 'Уведомления и эскалация', text: 'Лиды и записи на почту/в Telegram + ответ клиенту через бота', href: '/cabinet/notifications', section: 'notifications' },
    { key: 'integrations', icon: '🔗', label: 'YClients', text: 'Запись клиентов в YClients', href: '/cabinet/integrations', section: 'integrations', feature: 'crm' },
];

const decorate = (c: Card): DecoratedCard => {
    const state = cardStates.value[c.key];
    const maintenance = state === 'maintenance';
    const planLocked = !!(c.feature && !features.value?.[c.feature]);
    const disabled = maintenance || planLocked;

    return {
        ...c,
        disabled,
        maintenance,
        planLocked,
        statusLabel: maintenance ? 'Тех. работы' : planLocked ? 'Не в тарифе' : null,
        // Бейдж «новое/обновлено» — только у доступных плашек.
        badge: !disabled && state === 'new' ? 'Новое' : !disabled && state === 'updated' ? 'Обновлено' : null,
        // Тех. работы — некликабельно; недоступно по тарифу — ведём на «Подписку» (можно купить).
        to: maintenance ? null : planLocked ? '/cabinet/subscription' : c.href,
    };
};

const cards = computed<DecoratedCard[]>(() => {
    const list: Card[] = allCards.filter((c) => !c.section || allowed.value.includes(c.section));

    if (isOwner.value) {
        list.push({ key: 'team', icon: '👥', label: 'Команда', text: 'Сотрудники и их доступы', href: '/cabinet/team' });
    }

    const decorated = list.map(decorate);

    // Disabled-плашки (тариф/тех. работы) — пачкой вниз; доступные сохраняют порядок.
    return [...decorated.filter((c) => !c.disabled), ...decorated.filter((c) => c.disabled)];
});

const cardClass = (c: DecoratedCard): string =>
    c.disabled
        ? 'cursor-not-allowed opacity-60 grayscale'
        : 'transition hover:-translate-y-1 hover:border-[#2E74B5] hover:shadow-lg hover:shadow-slate-100';

const badgeClass = (label: string): string => {
    if (label === 'Новое') return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300';
    if (label === 'Обновлено') return 'bg-sky-100 text-sky-700 dark:bg-sky-400/15 dark:text-sky-300';
    if (label === 'Тех. работы') return 'bg-amber-100 text-amber-700 dark:bg-amber-400/15 dark:text-amber-300';
    return 'bg-slate-200 text-slate-600 dark:bg-white/10 dark:text-slate-300'; // Не в тарифе
};
</script>

<template>
    <Head title="Дашборд" />

    <AppLayout title="Дашборд">
        <p class="mb-6 text-slate-600 dark:text-slate-300">Управляйте «{{ tenantName }}» — выберите раздел.</p>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <component
                :is="c.to ? Link : 'div'"
                v-for="c in cards"
                :key="c.key"
                :href="c.to ?? undefined"
                class="group relative block rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5"
                :class="cardClass(c)"
            >
                <!-- Статус-бейдж в правом верхнем углу -->
                <span
                    v-if="c.statusLabel || c.badge"
                    class="absolute right-3 top-3 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                    :class="badgeClass(c.statusLabel ?? c.badge ?? '')"
                >
                    {{ c.statusLabel ?? c.badge }}
                </span>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#EAF2FB] text-2xl transition dark:bg-white/10" :class="!c.disabled && 'group-hover:scale-110'">
                    {{ c.icon }}
                </div>
                <div class="mt-4 font-semibold text-[#1F4E79] dark:text-sky-200">{{ c.label }}</div>
                <div class="mt-1 text-sm text-slate-500">{{ c.text }}</div>
                <div v-if="c.planLocked" class="mt-2 text-xs font-medium text-[#2E74B5]">Открыть в подписке →</div>
            </component>
        </div>
    </AppLayout>
</template>
