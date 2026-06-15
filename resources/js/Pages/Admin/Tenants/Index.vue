<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
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

const statusLabel = (t: TenantRow): string =>
    t.is_blocked ? 'заблокирован' : t.has_active_access ? 'активен' : 'истёк';
const statusClass = (t: TenantRow): string =>
    t.has_active_access && !t.is_blocked ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700';
</script>

<template>
    <Head title="Бизнесы" />

    <AppLayout title="Бизнесы">
        <div class="flex justify-end mb-4">
            <button type="button" class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96]" @click="showForm = !showForm">
                {{ showForm ? 'Отмена' : 'Новый бизнес' }}
            </button>
        </div>

        <form v-if="showForm" class="bg-white rounded-xl border border-slate-200 p-6 mb-6 grid sm:grid-cols-2 gap-4" @submit.prevent="submit">
            <div class="sm:col-span-2 font-semibold text-[#1F4E79]">Новый бизнес и владелец</div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Название бизнеса</label>
                <input v-model="form.name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Тариф</label>
                <select v-model="form.plan" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                    <option v-for="p in plans" :key="p.value" :value="p.value">{{ p.label }}</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Доступ оплачен до (необязательно)</label>
                <input v-model="form.access_expires_at" type="date" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                <p class="mt-1 text-xs text-slate-400">После этой даты кабинет блокируется. Пусто — без ограничения.</p>
            </div>
            <div class="hidden sm:block"></div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Имя владельца</label>
                <input v-model="form.owner_name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                <p v-if="form.errors.owner_name" class="mt-1 text-sm text-red-600">{{ form.errors.owner_name }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Email владельца</label>
                <input v-model="form.owner_email" type="email" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                <p v-if="form.errors.owner_email" class="mt-1 text-sm text-red-600">{{ form.errors.owner_email }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Пароль владельца</label>
                <input v-model="form.owner_password" type="password" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                <p v-if="form.errors.owner_password" class="mt-1 text-sm text-red-600">{{ form.errors.owner_password }}</p>
            </div>

            <div class="sm:col-span-2">
                <button type="submit" :disabled="form.processing" class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50">Создать</button>
            </div>
        </form>

        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-5 py-3 font-medium">Название</th>
                        <th class="px-5 py-3 font-medium">Тариф</th>
                        <th class="px-5 py-3 font-medium">Доступ до</th>
                        <th class="px-5 py-3 font-medium">Статус</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="tenant in tenants" :key="tenant.id" class="hover:bg-slate-50">
                        <td class="px-5 py-3 font-medium">
                            <Link :href="`/admin/tenants/${tenant.id}`" class="text-[#2E74B5] hover:underline">{{ tenant.name }}</Link>
                        </td>
                        <td class="px-5 py-3 text-slate-500">{{ tenant.plan_label }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ tenant.access_expires_at ?? '∞' }}</td>
                        <td class="px-5 py-3">
                            <span class="text-xs rounded-full px-2 py-0.5" :class="statusClass(tenant)">{{ statusLabel(tenant) }}</span>
                        </td>
                    </tr>
                    <tr v-if="tenants.length === 0">
                        <td colspan="4" class="px-5 py-8 text-center text-slate-400">Бизнесов пока нет.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
