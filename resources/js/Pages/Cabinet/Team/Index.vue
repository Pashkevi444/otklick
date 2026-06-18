<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import PermissionMatrix from '@/Components/PermissionMatrix.vue';

interface PermOption {
    key: string;
    label: string;
}
interface PermissionGroup {
    access: PermOption | null;
    actions: PermOption[];
}
interface Member {
    id: string;
    name: string;
    email: string;
    roleLabel: string;
    isOwner: boolean;
    permissions: string[];
}

const props = defineProps<{
    permissionGroups: PermissionGroup[];
    maxUsers: number;
    usedUsers: number;
    members: Member[];
}>();

const limitReached = (): boolean => props.usedUsers >= props.maxUsers;

const addForm = useForm({
    name: '',
    email: '',
    password: '',
    permissions: [] as string[],
});

const addMember = (): void => {
    addForm.post('/cabinet/team', { preserveScroll: true, onSuccess: () => addForm.reset() });
};

// Локальные права по каждому сотруднику (для редактирования чекбоксами).
const memberPerms: Record<string, string[]> = reactive({});
for (const m of props.members) {
    if (!m.isOwner) {
        memberPerms[m.id] = [...m.permissions];
    }
}

const saveMember = (id: string): void => {
    router.put(`/cabinet/team/${id}`, { permissions: memberPerms[id] }, { preserveScroll: true });
};

const removeMember = (id: string): void => {
    if (confirm('Удалить сотрудника?')) {
        router.delete(`/cabinet/team/${id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Команда" />

    <AppLayout title="Команда">
        <p class="mb-5 max-w-2xl text-sm text-slate-500">
            Сотрудники с доступом в кабинет. Для каждого можно ограничить разделы. Лимит по тарифу:
            <b>{{ usedUsers }} из {{ maxUsers }}</b>.
        </p>

        <!-- Добавить сотрудника -->
        <div class="mb-6 max-w-2xl rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
            <div class="mb-3 font-semibold text-[#1F4E79] dark:text-sky-200">Добавить сотрудника</div>

            <div v-if="limitReached()" class="rounded-lg border border-amber-300/50 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                Достигнут лимит пользователей по тарифу. Повысьте тариф, чтобы добавить больше.
            </div>

            <form v-else class="space-y-3" @submit.prevent="addMember">
                <div class="grid gap-3 sm:grid-cols-3">
                    <input v-model="addForm.name" placeholder="Имя" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    <input v-model="addForm.email" type="email" placeholder="Email" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    <input v-model="addForm.password" type="password" autocomplete="new-password" placeholder="Пароль" class="rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                </div>
                <p v-for="e in [addForm.errors.name, addForm.errors.email, addForm.errors.password]" v-show="e" :key="e" class="text-sm text-red-600">{{ e }}</p>

                <div>
                    <div class="mb-1 text-sm font-medium text-slate-700 dark:text-slate-300">Права сотрудника</div>
                    <PermissionMatrix v-model="addForm.permissions" :groups="permissionGroups" />
                </div>

                <button type="submit" :disabled="addForm.processing" class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50">
                    Добавить
                </button>
            </form>
        </div>

        <!-- Список -->
        <div class="max-w-2xl space-y-4">
            <div
                v-for="m in members"
                :key="m.id"
                class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5"
            >
                <div class="flex items-center justify-between">
                    <div>
                        <div class="font-medium text-[#1F4E79] dark:text-sky-200">{{ m.name }}</div>
                        <div class="text-xs text-slate-400">{{ m.email }} · {{ m.roleLabel }}</div>
                    </div>
                    <button v-if="!m.isOwner" type="button" class="text-sm text-red-600 hover:underline" @click="removeMember(m.id)">
                        Удалить
                    </button>
                </div>

                <div v-if="m.isOwner" class="mt-3 text-sm text-slate-500">Владелец — полный доступ ко всем разделам.</div>

                <div v-else class="mt-3">
                    <div class="mb-1 text-sm font-medium text-slate-700 dark:text-slate-300">Права сотрудника</div>
                    <PermissionMatrix v-model="memberPerms[m.id]" :groups="permissionGroups" />
                    <button type="button" class="mt-3 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 dark:border-white/10 dark:text-slate-200" @click="saveMember(m.id)">
                        Сохранить права
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
