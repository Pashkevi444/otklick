<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface PlanFeatures {
    maxOperators: number;
    crm: boolean;
    analytics: boolean;
    broadcasts: boolean;
    flows: boolean;
    clientBase: boolean;
    allChannels: boolean;
    webWidget: boolean;
    reminders: boolean;
    rag: boolean;
    aiInsights: boolean;
}

interface Plan {
    key: string;
    label: string;
    tier: string;
    isTrial: boolean;
    isMax: boolean;
    features: PlanFeatures;
    accessExpiresAt: string | null;
    hasActiveAccess: boolean;
}

const props = defineProps<{ plan: Plan }>();

const f = computed(() => props.plan.features);

// Полный список возможностей: что доступно сейчас и что откроется на «Макс».
const rows = computed(() => [
    { label: 'AI-ответы 24/7 в Telegram, ВКонтакте и MAX', on: true },
    { label: 'Виджет на сайт', on: f.value.webWidget },
    { label: 'База знаний (тексты, ссылки, фото)', on: true },
    { label: `Пользователи кабинета (сотрудники): до ${f.value.maxOperators}`, on: true },
    { label: 'Интеграция с YClients (запись, отмена, напоминания)', on: f.value.crm },
    { label: 'Напоминания клиентам о записи', on: f.value.reminders },
    { label: 'Умный поиск по базе знаний (RAG)', on: f.value.rag },
    { label: 'Дополнительные каналы (Avito и др.)', on: f.value.allChannels },
    { label: 'Рассылки по базе клиентов (мессенджеры + почта, по расписанию)', on: f.value.broadcasts },
    { label: 'Конструктор сценариев (no-code воронки: «если X → ответь Y»)', on: f.value.flows },
    { label: 'Расширенная аналитика и статистика', on: f.value.analytics },
    { label: 'ИИ-рекомендации в аналитике (чего не хватает)', on: f.value.aiInsights },
    { label: 'База клиентов и маркетинговые рекомендации', on: f.value.clientBase },
]);
</script>

<template>
    <Head title="Подписка" />

    <AppLayout title="Подписка">
        <div class="max-w-3xl">
            <!-- Текущий тариф -->
            <div class="rounded-2xl border border-brand bg-panel p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-[11px] font-bold uppercase tracking-[0.09em] text-muted2">Ваш тариф</div>
                        <div class="mt-1 flex items-center gap-2">
                            <span class="font-display text-2xl font-semibold text-ink">{{ plan.label }}</span>
                            <span v-if="plan.isTrial" class="rounded-full bg-[#EE8A5C]/15 px-2.5 py-0.5 text-xs font-semibold text-warm">пробный период</span>
                            <span v-else-if="plan.isMax" class="rounded-full bg-brand px-2.5 py-0.5 text-xs font-semibold text-white">премиум</span>
                        </div>
                    </div>
                    <span
                        class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                        :class="plan.hasActiveAccess ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-red-500/15 text-red-600 dark:text-red-400'"
                    >
                        {{ plan.hasActiveAccess ? 'активна' : 'доступ истёк' }}
                    </span>
                </div>
                <p v-if="plan.accessExpiresAt" class="mt-3 text-sm text-muted">
                    Доступ оплачен до <span class="font-medium text-ink">{{ plan.accessExpiresAt }}</span>
                </p>
                <p v-else class="mt-3 text-sm text-muted2">Без ограничения по сроку.</p>
            </div>

            <!-- Что входит -->
            <h2 class="mt-8 mb-3 font-display text-base font-semibold text-ink">Возможности вашего тарифа</h2>
            <div class="otk-card divide-y divide-[color:var(--otk-border)] overflow-hidden">
                <div v-for="r in rows" :key="r.label" class="flex items-center gap-3 px-5 py-3">
                    <span
                        class="flex h-5 w-5 flex-none items-center justify-center rounded-full text-xs font-bold"
                        :class="r.on ? 'bg-active text-brand' : 'bg-chip text-muted2'"
                    >
                        {{ r.on ? '✓' : '🔒' }}
                    </span>
                    <span class="text-sm" :class="r.on ? 'text-ink' : 'text-muted2'">{{ r.label }}</span>
                </div>
            </div>

            <!-- Апгрейд на Макс -->
            <div v-if="!plan.isMax" class="mt-8 rounded-2xl border border-brand bg-active p-6">
                <div class="font-display text-base font-semibold text-ink">Перейти на тариф «Макс»</div>
                <p class="mt-2 text-sm text-muted">
                    Дополнительные каналы (Avito и др.), интеграция с YClients и автозапись, рассылки,
                    расширенная аналитика, база клиентов, до 5 операторов и приоритетная поддержка.
                </p>
                <p class="mt-3 text-sm text-muted">Тариф «Макс» подключается по договорённости — напишите нам, и мы всё настроим.</p>
            </div>
        </div>
    </AppLayout>
</template>
