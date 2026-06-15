<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface TenantRow {
    id: string;
    name: string;
    slug: string;
    plan: string;
    created_at: string | null;
}

defineProps<{ tenants: TenantRow[] }>();

const showForm = ref(false);

const form = useForm({
    name: '',
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
</script>

<template>
    <Head title="Тенанты" />

    <AppLayout title="Тенанты">
        <div class="flex justify-end mb-4">
            <button
                type="button"
                class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96]"
                @click="showForm = !showForm"
            >
                {{ showForm ? 'Отмена' : 'Новый бизнес' }}
            </button>
        </div>

        <form
            v-if="showForm"
            class="bg-white rounded-xl border border-slate-200 p-6 mb-6 grid sm:grid-cols-2 gap-4"
            @submit.prevent="submit"
        >
            <div class="sm:col-span-2 font-semibold text-[#1F4E79]">Новый бизнес и владелец</div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Название бизнеса</label>
                <input v-model="form.name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
            </div>
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
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50"
                >
                    Создать
                </button>
            </div>
        </form>

        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-500 text-left">
                    <tr>
                        <th class="px-5 py-3 font-medium">Название</th>
                        <th class="px-5 py-3 font-medium">Slug</th>
                        <th class="px-5 py-3 font-medium">Тариф</th>
                        <th class="px-5 py-3 font-medium">Создан</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <tr v-for="tenant in tenants" :key="tenant.id" class="hover:bg-slate-50">
                        <td class="px-5 py-3 font-medium">
                            <Link :href="`/admin/tenants/${tenant.id}`" class="text-[#2E74B5] hover:underline">
                                {{ tenant.name }}
                            </Link>
                        </td>
                        <td class="px-5 py-3 text-slate-500">{{ tenant.slug }}</td>
                        <td class="px-5 py-3 text-slate-500">{{ tenant.plan }}</td>
                        <td class="px-5 py-3 text-slate-400">{{ tenant.created_at }}</td>
                    </tr>
                    <tr v-if="tenants.length === 0">
                        <td colspan="4" class="px-5 py-8 text-center text-slate-400">Тенантов пока нет.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
