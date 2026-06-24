<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface Counts {
    tenants: number;
    scenarioTemplates: number;
    knowledgeTemplates: number;
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
    { key: 'cards', icon: '🧩', label: 'Плашки дашборда', text: 'Состояния разделов кабинета (новое/тех. работы)', href: '/admin/dashboard-cards' },
    { key: 'site', icon: '🌐', label: 'Сайт', text: 'Контент публичного лендинга', href: '/admin/site' },
    // Серая плашка на будущее — управление сотрудниками площадки (админ-роли).
    { key: 'staff', icon: '👥', label: 'Сотрудники', text: 'Команда площадки и их доступы — в разработке', href: null, disabled: true, badge: 'Скоро' },
]);

const cardClass = (c: Card): string =>
    c.disabled
        ? 'cursor-not-allowed opacity-60 grayscale'
        : 'transition hover:-translate-y-1 hover:border-[#2E74B5] hover:shadow-lg hover:shadow-slate-100';

const tag = (c: Card): typeof Link | 'a' | 'div' => (c.disabled || !c.href ? 'div' : c.external ? 'a' : Link);
</script>

<template>
    <Head title="Дашборд" />

    <AppLayout>
        <!-- Живая hero-плашка супер-админки -->
        <div class="dash-hero relative mb-7 overflow-hidden rounded-3xl px-6 py-7 sm:px-8 sm:py-9">
            <span class="dash-orb dash-orb-1"></span>
            <span class="dash-orb dash-orb-2"></span>
            <span class="dash-orb dash-orb-3"></span>
            <span class="dash-grid"></span>
            <div class="relative">
                <div class="text-xs font-semibold uppercase tracking-[0.22em] text-white/65">Площадка «Отклик»</div>
                <h1 class="mt-1.5 text-2xl font-extrabold text-white sm:text-3xl">Супер-админка</h1>
                <p class="mt-1.5 text-sm text-white/85 sm:text-base">Управление площадкой — выберите раздел.</p>
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
                class="group relative block rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5"
                :class="cardClass(c)"
            >
                <span
                    v-if="c.badge"
                    class="absolute right-3 top-3 rounded-full px-2 py-0.5 text-[11px] font-semibold"
                    :class="c.disabled ? 'bg-slate-200 text-slate-600 dark:bg-white/10 dark:text-slate-300' : 'bg-[#EAF2FB] text-[#1F4E79] dark:bg-white/10 dark:text-sky-200'"
                >
                    {{ c.badge }}
                </span>

                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-[#EAF2FB] text-2xl transition dark:bg-white/10" :class="!c.disabled && 'group-hover:scale-110'">
                    {{ c.icon }}
                </div>
                <div class="mt-4 font-semibold text-[#1F4E79] dark:text-sky-200">{{ c.label }}</div>
                <div class="mt-1 text-sm text-slate-500">{{ c.text }}</div>
            </component>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Сочная hero-плашка: переливающийся фиолетово-фуксиевый градиент + свечения + сетка. */
.dash-hero {
    background: linear-gradient(120deg, #7c3aed 0%, #9333ea 38%, #c026d3 72%, #db2777 100%);
    background-size: 240% 240%;
    animation: dashPan 16s ease infinite;
}
.dash-orb {
    position: absolute;
    border-radius: 9999px;
    filter: blur(30px);
    opacity: 0.5;
    pointer-events: none;
    will-change: transform;
}
.dash-orb-1 { width: 190px; height: 190px; background: #a78bfa; top: -85px; left: 4%; animation: dashFloat 9s ease-in-out infinite; }
.dash-orb-2 { width: 150px; height: 150px; background: #f0abfc; top: -45px; right: 13%; animation: dashFloat 12s ease-in-out infinite reverse; }
.dash-orb-3 { width: 140px; height: 140px; background: #67e8f9; bottom: -75px; left: 42%; animation: dashFloat 10s ease-in-out infinite; }
.dash-grid {
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255, 255, 255, 0.18) 1px, transparent 1.4px);
    background-size: 18px 18px;
    mask-image: linear-gradient(180deg, #000, transparent);
    -webkit-mask-image: linear-gradient(180deg, #000, transparent);
    pointer-events: none;
}
@keyframes dashPan {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}
@keyframes dashFloat {
    0%, 100% { transform: translate(0, 0); }
    50% { transform: translate(22px, 16px); }
}
@media (prefers-reduced-motion: reduce) {
    .dash-hero, .dash-orb { animation: none; }
}
</style>
