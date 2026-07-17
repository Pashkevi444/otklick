<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface Counts {
    tenants: number;
    scenarioTemplates: number;
    knowledgeTemplates: number;
    promptTemplates: number;
}
const props = defineProps<{ counts: Counts }>();

const page = usePage();
const errorTrackingUrl = computed<string | null>(() => (page.props.errorTrackingUrl as string | null) ?? null);

interface Card {
    key: string;
    icon: string;
    label: string;
    text: string;
    href: string | null; // null + disabled = серая плашка «на будущее»
    external?: boolean;
    disabled?: boolean;
    badge?: string | null;
}

const cards = computed<Card[]>(() => [
    { key: 'tenants', icon: '🏢', label: 'Бизнесы', text: `Тенанты площадки, тарифы, доступы`, href: '/admin/tenants', badge: `${props.counts.tenants}` },
    { key: 'news', icon: '📰', label: 'Новости', text: 'Новости и обновления для всех бизнесов', href: '/admin/news' },
    {
        key: 'errors',
        icon: '🐞',
        label: 'Ошибки',
        text: errorTrackingUrl.value ? 'Трекер ошибок бота (GlitchTip)' : 'Трекер ошибок не настроен (ERROR_TRACKING_URL)',
        href: errorTrackingUrl.value,
        external: true,
        disabled: !errorTrackingUrl.value,
    },
    { key: 'scenario_templates', icon: '🪄', label: 'Шаблоны сценариев', text: 'Готовые воронки для бизнесов', href: '/admin/scenario-templates', badge: `${props.counts.scenarioTemplates}` },
    { key: 'knowledge_templates', icon: '📚', label: 'Шаблоны базы знаний', text: 'Готовые элементы БЗ по нишам', href: '/admin/knowledge-templates', badge: `${props.counts.knowledgeTemplates}` },
    { key: 'prompt_templates', icon: '🧠', label: 'Промпты бота', text: 'Промпт-инструкции под каждую нишу', href: '/admin/prompt-templates', badge: `${props.counts.promptTemplates}` },
    { key: 'cards', icon: '🧩', label: 'Плашки дашборда', text: 'Состояния разделов кабинета (новое/тех. работы)', href: '/admin/dashboard-cards' },
    { key: 'site', icon: '🌐', label: 'Сайт', text: 'Контент публичного лендинга', href: '/admin/site' },
    // Серая плашка на будущее — управление сотрудниками площадки (админ-роли).
    { key: 'staff', icon: '👥', label: 'Сотрудники', text: 'Команда площадки и их доступы — в разработке', href: null, disabled: true, badge: 'Скоро' },
]);

const cardClass = (c: Card): string =>
    c.disabled
        ? 'cursor-not-allowed opacity-60 grayscale'
        : 'transition hover:-translate-y-0.5 hover:shadow-lg hover:shadow-[rgba(16,28,51,0.06)]';

const tag = (c: Card): typeof Link | 'a' | 'div' => (c.disabled || !c.href ? 'div' : c.external ? 'a' : Link);
</script>

<template>
    <Head title="Дашборд" />

    <AppLayout title="Дашборд платформы">
        <!-- Hero-плашка супер-админки -->
        <div
            class="relative mb-7 overflow-hidden rounded-3xl p-7 text-white sm:p-9"
            style="background: linear-gradient(135deg, #2b5ce0 0%, #4d6ef0 45%, #7c5cfc 100%)"
        >
            <div class="relative">
                <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-white/70">Площадка «Отклик»</div>
                <h1 class="mt-1.5 font-display text-3xl font-semibold">Супер-админка</h1>
                <p class="mt-2 text-sm text-white/85 sm:text-base">Управление площадкой — выберите раздел.</p>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <component
                :is="tag(c)"
                v-for="c in cards"
                :key="c.key"
                :href="c.disabled || !c.href ? undefined : c.href"
                :target="c.external ? '_blank' : undefined"
                :rel="c.external ? 'noopener' : undefined"
                class="group relative block otk-card p-5"
                :class="cardClass(c)"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span :class="c.badge && !c.disabled ? 'text-[13px] font-semibold text-muted' : 'font-display text-base font-semibold text-ink'">{{ c.label }}</span>
                            <span v-if="c.badge && c.disabled" class="rounded-full bg-chip px-2.5 py-0.5 text-xs font-semibold text-muted">{{ c.badge }}</span>
                        </div>
                        <div v-if="c.badge && !c.disabled" class="mt-1 font-display text-3xl font-semibold text-ink">{{ c.badge }}</div>
                    </div>
                    <div class="flex h-10 w-10 flex-none items-center justify-center rounded-xl bg-active text-lg transition" :class="!c.disabled && 'group-hover:scale-110'">
                        {{ c.icon }}
                    </div>
                </div>
                <div class="mt-1.5 text-sm text-muted2">{{ c.text }}</div>
            </component>
        </div>
    </AppLayout>
</template>
