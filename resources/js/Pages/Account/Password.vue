<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const submit = (): void => {
    form.put('/account/password', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};
</script>

<template>
    <Head title="Смена пароля" />

    <AppLayout title="Смена пароля">
        <form class="bg-white rounded-xl border border-slate-200 p-6 max-w-md space-y-5" @submit.prevent="submit">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Текущий пароль</label>
                <input v-model="form.current_password" type="password" autocomplete="current-password" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                <p v-if="form.errors.current_password" class="mt-1 text-sm text-red-600">{{ form.errors.current_password }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Новый пароль</label>
                <input v-model="form.password" type="password" autocomplete="new-password" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                <p v-if="form.errors.password" class="mt-1 text-sm text-red-600">{{ form.errors.password }}</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Повторите новый пароль</label>
                <input v-model="form.password_confirmation" type="password" autocomplete="new-password" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
            </div>
            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50"
                >
                    Сменить пароль
                </button>
                <span v-if="form.recentlySuccessful" class="text-sm text-green-600">Обновлено</span>
            </div>
        </form>
    </AppLayout>
</template>
