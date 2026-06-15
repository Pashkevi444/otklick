<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface Plan {
    value: string;
    label: string;
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
}
interface UserRow {
    id: string;
    name: string;
    email: string;
    role: string;
}

const props = defineProps<{ tenant: TenantInfo; plans: Plan[]; users: UserRow[] }>();

const form = useForm({
    plan: props.tenant.plan,
    access_expires_at: props.tenant.access_expires_at ?? '',
});

const save = (): void => {
    form.put(`/admin/tenants/${props.tenant.id}`, { preserveScroll: true });
};

const block = (): void => router.post(`/admin/tenants/${props.tenant.id}/block`);
const unblock = (): void => router.post(`/admin/tenants/${props.tenant.id}/unblock`);
</script>

<template>
    <Head :title="tenant.name" />

    <AppLayout>
        <Link href="/admin/tenants" class="text-sm text-[#2E74B5] hover:underline">← К списку</Link>

        <div class="mt-2 mb-6 flex items-center gap-3">
            <h1 class="text-2xl font-bold text-[#1F4E79]">{{ tenant.name }}</h1>
            <span
                class="text-xs rounded-full px-2 py-0.5"
                :class="tenant.has_active_access && !tenant.is_blocked ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
            >
                {{ tenant.is_blocked ? 'заблокирован' : tenant.has_active_access ? 'активен' : 'доступ истёк' }}
            </span>
        </div>

        <!-- Подписка -->
        <div class="bg-white rounded-xl border border-slate-200 p-6 max-w-xl mb-6">
            <div class="font-semibold text-[#1F4E79] mb-4">Подписка</div>
            <form class="grid sm:grid-cols-2 gap-4" @submit.prevent="save">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Тариф</label>
                    <select v-model="form.plan" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option v-for="p in plans" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Доступ оплачен до</label>
                    <input v-model="form.access_expires_at" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                    <p class="mt-1 text-xs text-slate-400">Пусто — без ограничения.</p>
                </div>
                <div class="sm:col-span-2 flex items-center gap-3">
                    <button type="submit" :disabled="form.processing" class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50">
                        Сохранить
                    </button>
                    <span v-if="form.recentlySuccessful" class="text-sm text-green-600">Сохранено</span>
                </div>
            </form>

            <div class="mt-5 pt-5 border-t border-slate-100">
                <button v-if="tenant.is_blocked" type="button" class="rounded-lg border border-green-300 text-green-700 px-4 py-2 text-sm font-medium hover:bg-green-50" @click="unblock">
                    Разблокировать бизнес
                </button>
                <button v-else type="button" class="rounded-lg border border-red-300 text-red-700 px-4 py-2 text-sm font-medium hover:bg-red-50" @click="block">
                    Заблокировать бизнес
                </button>
            </div>
        </div>

        <h2 class="font-semibold text-slate-700 mb-2">Пользователи</h2>
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-5 py-3 font-medium">Имя</th>
                        <th class="px-5 py-3 font-medium">Email</th>
                        <th class="px-5 py-3 font-medium">Роль</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="user in users" :key="user.id">
                        <td class="px-5 py-3 font-medium text-slate-700">{{ user.name }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ user.email }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ user.role }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
