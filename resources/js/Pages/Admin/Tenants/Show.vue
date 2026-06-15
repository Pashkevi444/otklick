<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface TenantInfo {
    id: string;
    name: string;
    slug: string;
    plan: string;
    created_at: string | null;
}

interface UserRow {
    id: string;
    name: string;
    email: string;
    role: string;
}

defineProps<{ tenant: TenantInfo; users: UserRow[] }>();
</script>

<template>
    <Head :title="tenant.name" />

    <AppLayout>
        <Link href="/admin/tenants" class="text-sm text-[#2E74B5] hover:underline">← К списку</Link>

        <div class="mt-2 mb-6">
            <h1 class="text-2xl font-bold text-[#1F4E79]">{{ tenant.name }}</h1>
            <div class="text-sm text-slate-500">{{ tenant.slug }} · {{ tenant.plan }}</div>
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
