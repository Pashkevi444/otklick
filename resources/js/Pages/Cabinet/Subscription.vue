<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface PlanFeatures {
    maxOperators: number;
    crm: boolean;
    analytics: boolean;
    broadcasts: boolean;
    clientBase: boolean;
    allChannels: boolean;
    webWidget: boolean;
    reminders: boolean;
    rag: boolean;
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
    { label: 'AI-ответы 24/7 в Telegram и ВКонтакте', on: true },
    { label: 'Виджет на сайт', on: f.value.webWidget },
    { label: 'База знаний (тексты, ссылки, фото)', on: true },
    { label: `Пользователи кабинета (сотрудники): до ${f.value.maxOperators}`, on: true },
    { label: 'Интеграция с любой CRM (например, YClients) и автозапись', on: f.value.crm },
    { label: 'Напоминания клиентам о записи', on: f.value.reminders },
    { label: 'Умный поиск по базе знаний (RAG)', on: f.value.rag },
    { label: 'Дополнительные каналы (MAX, Avito)', on: f.value.allChannels },
    { label: 'Рассылки и уведомления', on: f.value.broadcasts },
    { label: 'Расширенная аналитика и статистика', on: f.value.analytics },
    { label: 'База клиентов и маркетинговые рекомендации', on: f.value.clientBase },
]);
</script>

<template>
    <Head title="Подписка" />

    <AppLayout title="Подписка">
        <div class="max-w-3xl">
            <!-- Текущий тариф -->
            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm text-slate-500">Ваш тариф</div>
                        <div class="mt-1 flex items-center gap-2">
                            <span class="text-2xl font-bold text-[#1F4E79]">{{ plan.label }}</span>
                            <span v-if="plan.isTrial" class="rounded-full bg-amber-100 text-amber-700 text-xs px-2 py-0.5">пробный период</span>
                            <span v-else-if="plan.isMax" class="rounded-full bg-[#1F4E79] text-white text-xs px-2 py-0.5">премиум</span>
                        </div>
                    </div>
                    <span
                        class="text-xs rounded-full px-3 py-1"
                        :class="plan.hasActiveAccess ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                    >
                        {{ plan.hasActiveAccess ? 'активна' : 'доступ истёк' }}
                    </span>
                </div>
                <p v-if="plan.accessExpiresAt" class="mt-3 text-sm text-slate-500">
                    Доступ оплачен до <span class="font-medium text-slate-700">{{ plan.accessExpiresAt }}</span>
                </p>
                <p v-else class="mt-3 text-sm text-slate-400">Без ограничения по сроку.</p>
            </div>

            <!-- Что входит -->
            <h2 class="mt-8 mb-3 font-semibold text-slate-700">Возможности вашего тарифа</h2>
            <div class="bg-white rounded-xl border border-slate-200 divide-y divide-slate-100">
                <div v-for="r in rows" :key="r.label" class="flex items-center gap-3 px-5 py-3">
                    <span
                        class="flex h-5 w-5 items-center justify-center rounded-full text-xs"
                        :class="r.on ? 'bg-green-100 text-green-600' : 'bg-slate-100 text-slate-300'"
                    >
                        {{ r.on ? '✓' : '🔒' }}
                    </span>
                    <span class="text-sm" :class="r.on ? 'text-slate-700' : 'text-slate-400'">{{ r.label }}</span>
                </div>
            </div>

            <!-- Апгрейд на Макс -->
            <div v-if="!plan.isMax" class="mt-8 rounded-xl border border-[#2E74B5]/30 bg-gradient-to-br from-[#EAF2FB] to-white p-6 dark:border-sky-400/20 dark:bg-none dark:bg-white/5">
                <div class="font-semibold text-[#1F4E79]">Перейти на тариф «Макс»</div>
                <p class="mt-2 text-sm text-slate-600">
                    Дополнительные каналы (MAX, Avito), интеграция с CRM и автозапись, рассылки,
                    расширенная аналитика, база клиентов, до 5 операторов и приоритетная поддержка.
                </p>
                <p class="mt-3 text-sm text-slate-500">Тариф «Макс» подключается по договорённости — напишите нам, и мы всё настроим.</p>
            </div>
        </div>
    </AppLayout>
</template>
