<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Toggle from '@/Components/Toggle.vue';

interface Plan {
    value: string;
    label: string;
}
interface Features {
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
    maxNotifyEmail: number;
    maxNotifyTelegram: number;
}
interface TenantInfo {
    id: string;
    name: string;
    slug: string;
    plan: string;
    plan_label: string;
    access_expires_at: string | null;
    is_blocked: boolean;
    has_active_access: boolean;
    created_at: string | null;
    features: Features;
    planDefaults: Features;
    hasOverrides: boolean;
    business_type: string | null;
    business_type_label: string | null;
}
interface UserRow {
    id: string;
    name: string;
    email: string;
    role: string;
}
interface BizType {
    value: string;
    label: string;
}

const props = defineProps<{
    tenant: TenantInfo;
    plans: Plan[];
    planDefaults: Record<string, Features>;
    businessTypes: BizType[];
    users: UserRow[];
}>();

// Тип бизнеса тенанта (ниша) — СУ задаёт вручную; влияет на подбор шаблонов/БЗ.
const btForm = useForm<{ business_type: string }>({ business_type: props.tenant.business_type ?? '' });
const saveBusinessType = (): void => {
    btForm.transform((d) => ({ business_type: d.business_type === '' ? null : d.business_type })).put(`/admin/tenants/${props.tenant.id}/business-type`, { preserveScroll: true });
};

const toggles: { key: keyof Features; label: string }[] = [
    { key: 'crm', label: 'YClients (запись)' },
    { key: 'analytics', label: 'Аналитика' },
    { key: 'broadcasts', label: 'Рассылки' },
    { key: 'flows', label: 'Конструктор сценариев' },
    { key: 'clientBase', label: 'База клиентов' },
    { key: 'allChannels', label: 'Все каналы' },
    { key: 'webWidget', label: 'Веб-виджет' },
    { key: 'reminders', label: 'Напоминания о записи' },
    { key: 'rag', label: 'Умный поиск (RAG)' },
    { key: 'aiInsights', label: 'ИИ-рекомендации' },
];
const numbers: { key: keyof Features; label: string }[] = [
    { key: 'maxOperators', label: 'Пользователей кабинета' },
    { key: 'maxNotifyEmail', label: 'Email-получателей' },
    { key: 'maxNotifyTelegram', label: 'Telegram-получателей' },
];

const ovForm = useForm<Features>({ ...props.tenant.features });
// Запись булева оверрайда по динамическому ключу (тип Features смешанный — обходим).
const setOverride = (key: keyof Features, value: boolean): void => {
    (ovForm as unknown as Record<string, boolean | number>)[key as string] = value;
};
const saveOverrides = (): void => {
    ovForm.put(`/admin/tenants/${props.tenant.id}/overrides`, { preserveScroll: true });
};
const resetOverrides = (): void => {
    router.delete(`/admin/tenants/${props.tenant.id}/overrides`, { preserveScroll: true });
};

const form = useForm({
    plan: props.tenant.plan,
    access_expires_at: props.tenant.access_expires_at ?? '',
});

// Дефолты выбранного тарифа — для подсказок «тариф: …».
const planDefaultsFor = computed<Features>(() => props.planDefaults[form.plan] ?? props.tenant.planDefaults);

// Кнопка «По тарифу» — заполнить форму дефолтами выбранного тарифа (удобно сбросить
// галочки к тарифу перед сохранением индивидуальных прав).
const applyPlanDefaults = (): void => {
    const def = props.planDefaults[form.plan];
    if (def) {
        Object.assign(ovForm, { ...def });
    }
};

const save = (): void => {
    form.put(`/admin/tenants/${props.tenant.id}`, { preserveScroll: true });
};

const block = (): void => router.post(`/admin/tenants/${props.tenant.id}/block`);
const unblock = (): void => router.post(`/admin/tenants/${props.tenant.id}/unblock`);
const impersonate = (): void => router.post(`/admin/tenants/${props.tenant.id}/impersonate`);

const pwForm = useForm({
    password: '',
    password_confirmation: '',
});

const savePassword = (): void => {
    pwForm.put(`/admin/tenants/${props.tenant.id}/owner-password`, {
        preserveScroll: true,
        onSuccess: () => pwForm.reset(),
    });
};
</script>

<template>
    <Head :title="tenant.name" />

    <AppLayout>
        <Link href="/admin/tenants" class="text-sm font-semibold text-brand hover:underline">← К списку</Link>

        <div class="mt-2 mb-6 flex flex-wrap items-center gap-3">
            <h1 class="font-display text-2xl font-semibold text-ink">{{ tenant.name }}</h1>
            <span
                class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                :class="tenant.has_active_access && !tenant.is_blocked
                    ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
                    : 'bg-red-500/15 text-red-600 dark:text-red-400'"
            >
                {{ tenant.is_blocked ? 'заблокирован' : tenant.has_active_access ? 'активен' : 'доступ истёк' }}
            </span>
            <button
                type="button"
                class="ml-auto otk-btn-primary"
                @click="impersonate"
            >
                ➜ Войти в кабинет бизнеса
            </button>
        </div>

        <!-- Тип бизнеса (ниша) -->
        <div class="otk-card p-6 max-w-xl mb-6">
            <div class="font-display text-base font-semibold text-ink mb-1">Тип бизнеса</div>
            <p class="mb-3 text-xs text-muted">
                Сейчас: <span class="font-medium text-ink">{{ tenant.business_type_label ?? 'не задан' }}</span>.
                Влияет на подбор шаблонов сценариев и базы знаний в кабинете бизнеса.
            </p>
            <div class="flex flex-wrap items-center gap-3">
                <select v-model="btForm.business_type" class="rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none focus:border-brand">
                    <option value="">Не задан</option>
                    <option v-for="bt in businessTypes" :key="bt.value" :value="bt.value">{{ bt.label }}</option>
                </select>
                <button
                    type="button"
                    :disabled="btForm.processing || btForm.business_type === (tenant.business_type ?? '')"
                    class="otk-btn-primary disabled:opacity-40"
                    @click="saveBusinessType"
                >
                    Сохранить
                </button>
                <span v-if="btForm.recentlySuccessful" class="text-sm text-emerald-600 dark:text-emerald-400">Сохранено</span>
            </div>
        </div>

        <!-- Подписка -->
        <div class="otk-card p-6 max-w-xl mb-6">
            <div class="font-display text-base font-semibold text-ink mb-4">Подписка</div>
            <form class="grid sm:grid-cols-2 gap-4" @submit.prevent="save">
                <div>
                    <label class="block text-sm font-medium text-ink mb-1">Тариф</label>
                    <select v-model="form.plan" class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none focus:border-brand">
                        <option v-for="p in plans" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink mb-1">Доступ оплачен до</label>
                    <input v-model="form.access_expires_at" type="date" class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none focus:border-brand" />
                    <p class="mt-1 text-xs text-muted2">Пусто — без ограничения.</p>
                </div>
                <div class="sm:col-span-2 flex items-center gap-3">
                    <button type="submit" :disabled="form.processing" class="otk-btn-primary disabled:opacity-50">
                        Сохранить
                    </button>
                    <span v-if="form.recentlySuccessful" class="text-sm text-emerald-600 dark:text-emerald-400">Сохранено</span>
                </div>
            </form>

            <div class="mt-5 pt-5 border-t border-line">
                <button v-if="tenant.is_blocked" type="button" class="rounded-xl border border-emerald-500/40 px-4 py-2.5 text-sm font-semibold text-emerald-600 hover:bg-emerald-500/10 dark:text-emerald-400" @click="unblock">
                    Разблокировать бизнес
                </button>
                <button v-else type="button" class="rounded-xl border border-red-500/40 px-4 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-500/10 dark:text-red-400" @click="block">
                    Заблокировать бизнес
                </button>
            </div>
        </div>

        <!-- Права и лимиты (по договорённости) -->
        <div class="otk-card p-6 max-w-xl mb-6">
            <div class="mb-1 flex items-center gap-2">
                <span class="font-display text-base font-semibold text-ink">Права и лимиты</span>
                <span v-if="tenant.hasOverrides" class="rounded-full bg-[#EE8A5C]/15 px-2.5 py-0.5 text-xs font-semibold text-warm">индивидуальные</span>
                <span v-else class="rounded-full bg-chip px-2.5 py-0.5 text-xs font-semibold text-muted">по тарифу</span>
            </div>
            <p class="mb-3 text-sm text-muted">
                Переопределяет возможности тарифа для этого бизнеса (для сделок по договорённости).
                «Сбросить к тарифу» возвращает возможности тарифа «{{ tenant.plan_label }}».
            </p>

            <form class="space-y-4" @submit.prevent="saveOverrides">
                <div class="grid gap-2 sm:grid-cols-2">
                    <label v-for="t in toggles" :key="t.key" class="flex items-center gap-2 text-sm text-ink">
                        <Toggle :model-value="Boolean(ovForm[t.key])" @update:model-value="setOverride(t.key, $event)" />
                        {{ t.label }}
                    </label>
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div v-for="n in numbers" :key="n.key">
                        <label class="block text-xs font-medium text-muted mb-1">{{ n.label }}</label>
                        <input v-model.number="ovForm[n.key] as number" type="number" min="0" max="999" class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none focus:border-brand" />
                        <p class="mt-0.5 text-xs text-muted2">тариф: {{ planDefaultsFor[n.key] }}</p>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" :disabled="ovForm.processing" class="otk-btn-primary disabled:opacity-50">
                        Сохранить права
                    </button>
                    <button type="button" class="otk-btn-ghost" @click="applyPlanDefaults">
                        Заполнить по тарифу
                    </button>
                    <button v-if="tenant.hasOverrides" type="button" class="otk-btn-ghost" @click="resetOverrides">
                        Сбросить к тарифу
                    </button>
                    <span v-if="ovForm.recentlySuccessful" class="text-sm text-emerald-600 dark:text-emerald-400">Сохранено</span>
                </div>
            </form>
        </div>

        <h2 class="mb-2 text-[11px] font-bold uppercase tracking-[0.09em] text-muted2">Пользователи</h2>
        <div class="otk-card overflow-x-auto">
            <table class="w-full min-w-[420px] text-sm">
                <thead class="text-left text-[11px] font-bold uppercase tracking-[0.09em] text-muted2">
                    <tr>
                        <th class="px-5 py-3.5 whitespace-nowrap">Имя</th>
                        <th class="px-5 py-3.5 whitespace-nowrap">Email</th>
                        <th class="px-5 py-3.5 whitespace-nowrap">Роль</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="user in users" :key="user.id" class="border-t border-line hover:bg-hoverbg">
                        <td class="px-5 py-3 font-medium text-ink whitespace-nowrap">{{ user.name }}</td>
                        <td class="px-5 py-3 text-muted whitespace-nowrap">{{ user.email }}</td>
                        <td class="px-5 py-3 text-muted whitespace-nowrap">{{ user.role }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Смена пароля владельцу -->
        <div class="otk-card p-6 max-w-xl mt-6">
            <div class="font-display text-base font-semibold text-ink mb-1">Пароль владельца</div>
            <p class="text-sm text-muted mb-4">Задайте новый пароль для входа владельца бизнеса.</p>
            <form class="grid sm:grid-cols-2 gap-4" @submit.prevent="savePassword">
                <div>
                    <label class="block text-sm font-medium text-ink mb-1">Новый пароль</label>
                    <input v-model="pwForm.password" type="password" autocomplete="new-password" class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none focus:border-brand" />
                    <p v-if="pwForm.errors.password" class="mt-1 text-sm text-red-600">{{ pwForm.errors.password }}</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-ink mb-1">Повторите пароль</label>
                    <input v-model="pwForm.password_confirmation" type="password" autocomplete="new-password" class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none focus:border-brand" />
                </div>
                <div class="sm:col-span-2 flex items-center gap-3">
                    <button type="submit" :disabled="pwForm.processing" class="otk-btn-primary disabled:opacity-50">
                        Сменить пароль
                    </button>
                    <span v-if="pwForm.recentlySuccessful" class="text-sm text-emerald-600 dark:text-emerald-400">Пароль обновлён</span>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
