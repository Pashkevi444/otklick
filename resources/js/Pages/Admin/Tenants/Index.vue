<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface Plan {
    value: string;
    label: string;
}
interface TenantRow {
    id: string;
    name: string;
    slug: string;
    plan_label: string;
    access_expires_at: string | null;
    is_blocked: boolean;
    has_active_access: boolean;
    created_at: string | null;
}

const props = defineProps<{ tenants: TenantRow[]; plans: Plan[] }>();

const showForm = ref(false);

const form = useForm({
    name: '',
    plan: props.plans[0]?.value ?? 'trial',
    access_expires_at: '',
    owner_name: '',
    owner_email: '',
    owner_password: '',
});

const submit = (): void => {
    form.post('/admin/tenants', {
        onSuccess: () => {
            form.reset();
            showForm.value = false;
        },
    });
};

const impersonate = (id: string): void => router.post(`/admin/tenants/${id}/impersonate`);

const statusLabel = (t: TenantRow): string =>
    t.is_blocked ? 'заблокирован' : t.has_active_access ? 'активен' : 'истёк';
const statusClass = (t: TenantRow): string =>
    t.has_active_access && !t.is_blocked
        ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'
        : 'bg-red-500/15 text-red-600 dark:text-red-400';

// Инициалы бизнеса для аватара (первые буквы двух первых слов).
const initials = (name: string): string =>
    name
        .split(/\s+/)
        .filter((w) => /[a-zA-Zа-яА-ЯёЁ0-9]/.test(w))
        .slice(0, 2)
        .map((w) => (w.match(/[a-zA-Zа-яА-ЯёЁ0-9]/)?.[0] ?? '').toUpperCase())
        .join('');
</script>

<template>
    <Head title="Бизнесы" />

    <AppLayout title="Бизнесы">
        <div class="flex justify-end mb-4">
            <button type="button" class="otk-btn-primary" @click="showForm = !showForm">
                {{ showForm ? 'Отмена' : 'Новый бизнес' }}
            </button>
        </div>

        <form v-if="showForm" class="otk-card p-6 mb-6 grid sm:grid-cols-2 gap-4" @submit.prevent="submit">
            <div class="sm:col-span-2 font-display text-base font-semibold text-ink">Новый бизнес и владелец</div>

            <div>
                <label class="block text-sm font-medium text-ink mb-1">Название бизнеса</label>
                <input v-model="form.name" type="text" class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none placeholder:text-muted2 focus:border-brand" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-ink mb-1">Тариф</label>
                <select v-model="form.plan" class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none focus:border-brand">
                    <option v-for="p in plans" :key="p.value" :value="p.value">{{ p.label }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-ink mb-1">Доступ оплачен до (необязательно)</label>
                <input v-model="form.access_expires_at" type="date" class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none focus:border-brand" />
                <p class="mt-1 text-xs text-muted2">После этой даты кабинет блокируется. Пусто — без ограничения.</p>
            </div>
            <div class="hidden sm:block"></div>
            <div>
                <label class="block text-sm font-medium text-ink mb-1">Имя владельца</label>
                <input v-model="form.owner_name" type="text" class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none placeholder:text-muted2 focus:border-brand" />
                <p v-if="form.errors.owner_name" class="mt-1 text-sm text-red-600">{{ form.errors.owner_name }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-ink mb-1">Email владельца</label>
                <input v-model="form.owner_email" type="email" class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none placeholder:text-muted2 focus:border-brand" />
                <p v-if="form.errors.owner_email" class="mt-1 text-sm text-red-600">{{ form.errors.owner_email }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-ink mb-1">Пароль владельца</label>
                <input v-model="form.owner_password" type="password" class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none placeholder:text-muted2 focus:border-brand" />
                <p v-if="form.errors.owner_password" class="mt-1 text-sm text-red-600">{{ form.errors.owner_password }}</p>
            </div>

            <div class="sm:col-span-2">
                <button type="submit" :disabled="form.processing" class="otk-btn-primary disabled:opacity-50">Создать</button>
            </div>
        </form>

        <!-- Мобильные карточки -->
        <div class="space-y-3 md:hidden">
            <Link
                v-for="tenant in tenants"
                :key="tenant.id"
                :href="`/admin/tenants/${tenant.id}`"
                class="block otk-card p-4"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="flex min-w-0 items-center gap-2.5">
                        <span class="inline-flex h-9 w-9 flex-none items-center justify-center rounded-full bg-active text-sm font-bold text-brand">{{ initials(tenant.name) }}</span>
                        <span class="truncate font-semibold text-ink">{{ tenant.name }}</span>
                    </span>
                    <span class="flex-none rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="statusClass(tenant)">{{ statusLabel(tenant) }}</span>
                </div>
                <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted">
                    <span>Тариф: {{ tenant.plan_label }}</span>
                    <span>Доступ до: {{ tenant.access_expires_at ?? '∞' }}</span>
                </div>
                <button
                    type="button"
                    class="mt-3 rounded-xl bg-brand px-3 py-1.5 text-xs font-bold text-white hover:bg-brand-hover"
                    @click.stop.prevent="impersonate(tenant.id)"
                >
                    ➜ Войти в бизнес
                </button>
            </Link>
            <div v-if="tenants.length === 0" class="otk-card p-8 text-center text-muted2">
                Бизнесов пока нет.
            </div>
        </div>

        <!-- Таблица (десктоп) -->
        <div class="hidden otk-card overflow-hidden md:block">
            <table class="w-full text-sm">
                <thead class="text-left text-[11px] font-bold uppercase tracking-[0.09em] text-muted2">
                    <tr>
                        <th class="px-5 py-3.5">Название</th>
                        <th class="px-5 py-3.5">Тариф</th>
                        <th class="px-5 py-3.5">Доступ до</th>
                        <th class="px-5 py-3.5">Статус</th>
                        <th class="px-5 py-3.5"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="tenant in tenants" :key="tenant.id" class="border-t border-line hover:bg-hoverbg">
                        <td class="px-5 py-3">
                            <Link :href="`/admin/tenants/${tenant.id}`" class="flex items-center gap-3 font-semibold text-ink hover:text-brand">
                                <span class="inline-flex h-9 w-9 flex-none items-center justify-center rounded-full bg-active text-sm font-bold text-brand">{{ initials(tenant.name) }}</span>
                                {{ tenant.name }}
                            </Link>
                        </td>
                        <td class="px-5 py-3 text-muted">{{ tenant.plan_label }}</td>
                        <td class="px-5 py-3 text-muted">{{ tenant.access_expires_at ?? '∞' }}</td>
                        <td class="px-5 py-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="statusClass(tenant)">{{ statusLabel(tenant) }}</span>
                        </td>
                        <td class="px-5 py-3 text-right">
                            <button
                                type="button"
                                class="rounded-xl border border-line px-3 py-1.5 text-xs font-semibold text-brand hover:bg-active"
                                @click="impersonate(tenant.id)"
                            >
                                ➜ Войти
                            </button>
                        </td>
                    </tr>
                    <tr v-if="tenants.length === 0">
                        <td colspan="5" class="px-5 py-8 text-center text-muted2">Бизнесов пока нет.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
