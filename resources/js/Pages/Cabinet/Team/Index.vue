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
        <p class="mb-5 max-w-2xl text-sm text-muted">
            Сотрудники с доступом в кабинет. Для каждого можно ограничить разделы. Лимит по тарифу:
            <b>{{ usedUsers }} из {{ maxUsers }}</b>.
        </p>

        <!-- Добавить сотрудника -->
        <div class="otk-card mb-6 max-w-2xl p-5">
            <div class="mb-3 font-display text-base font-semibold text-ink">Добавить сотрудника</div>

            <div v-if="limitReached()" class="rounded-xl bg-warm/10 px-3.5 py-2.5 text-sm text-warm">
                Достигнут лимит пользователей по тарифу. Повысьте тариф, чтобы добавить больше.
            </div>

            <form v-else class="space-y-3" @submit.prevent="addMember">
                <div class="grid gap-3 sm:grid-cols-3">
                    <input v-model="addForm.name" placeholder="Имя" class="rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none placeholder:text-muted2 focus:border-brand" />
                    <input v-model="addForm.email" type="email" placeholder="Email" class="rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none placeholder:text-muted2 focus:border-brand" />
                    <input v-model="addForm.password" type="password" autocomplete="new-password" placeholder="Пароль" class="rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none placeholder:text-muted2 focus:border-brand" />
                </div>
                <p v-for="e in [addForm.errors.name, addForm.errors.email, addForm.errors.password]" v-show="e" :key="e" class="text-sm text-red-600">{{ e }}</p>

                <div>
                    <div class="mb-1 text-[11px] font-bold uppercase tracking-[0.09em] text-muted2">Права сотрудника</div>
                    <PermissionMatrix v-model="addForm.permissions" :groups="permissionGroups" />
                </div>

                <button type="submit" :disabled="addForm.processing" class="otk-btn-primary disabled:opacity-50">
                    Добавить
                </button>
            </form>
        </div>

        <!-- Список -->
        <div class="max-w-2xl space-y-4">
            <div
                v-for="m in members"
                :key="m.id"
                class="otk-card p-5"
            >
                <div class="flex items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <span class="inline-flex h-9 w-9 flex-none items-center justify-center rounded-full bg-active text-sm font-bold text-brand">{{ m.name.trim().split(/\s+/).map((w) => w[0]).slice(0, 2).join('').toUpperCase() }}</span>
                        <div class="min-w-0">
                            <div class="font-semibold text-ink">{{ m.name }}</div>
                            <div class="text-xs text-muted2">{{ m.email }} · {{ m.roleLabel }}</div>
                        </div>
                    </div>
                    <button v-if="!m.isOwner" type="button" class="flex-none text-sm font-semibold text-red-600 hover:underline" @click="removeMember(m.id)">
                        Удалить
                    </button>
                </div>

                <div v-if="m.isOwner" class="mt-3 text-sm text-muted">Владелец — полный доступ ко всем разделам.</div>

                <div v-else class="mt-3">
                    <div class="mb-1 text-[11px] font-bold uppercase tracking-[0.09em] text-muted2">Права сотрудника</div>
                    <PermissionMatrix v-model="memberPerms[m.id]" :groups="permissionGroups" />
                    <button type="button" class="otk-btn-ghost mt-3" @click="saveMember(m.id)">
                        Сохранить права
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
