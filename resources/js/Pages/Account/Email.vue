<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps<{ currentEmail: string; pendingEmail: string | null }>();

const requestForm = useForm({ new_email: '', current_password: '' });
const confirmForm = useForm({ code: '' });

const requestChange = (): void => {
    requestForm.post('/account/email', { preserveScroll: true, onSuccess: () => requestForm.reset('current_password') });
};
const confirm = (): void => {
    confirmForm.post('/account/email/confirm', { preserveScroll: true, onSuccess: () => confirmForm.reset() });
};
</script>

<template>
    <Head title="Смена почты" />

    <AppLayout title="Смена почты">
        <Link href="/account" class="text-sm text-[#2E74B5] hover:underline dark:text-sky-300">← К настройкам</Link>

        <p class="mt-2 mb-5 text-sm text-slate-500">Текущая почта: <span class="font-medium text-slate-700 dark:text-slate-200">{{ currentEmail }}</span></p>

        <!-- Шаг 1: запрос смены -->
        <form class="max-w-md space-y-5 rounded-xl border border-slate-200 bg-white p-6 dark:border-white/10 dark:bg-white/5" @submit.prevent="requestChange">
            <div class="font-semibold text-[#1F4E79] dark:text-sky-200">Новый адрес</div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Новая почта</label>
                <input v-model="requestForm.new_email" type="email" autocomplete="email" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                <p v-if="requestForm.errors.new_email" class="mt-1 text-sm text-red-600">{{ requestForm.errors.new_email }}</p>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Текущий пароль</label>
                <input v-model="requestForm.current_password" type="password" autocomplete="current-password" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                <p v-if="requestForm.errors.current_password" class="mt-1 text-sm text-red-600">{{ requestForm.errors.current_password }}</p>
            </div>
            <div class="flex items-center gap-3">
                <button type="submit" :disabled="requestForm.processing" class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50">
                    Отправить код
                </button>
                <span v-if="requestForm.recentlySuccessful" class="text-sm text-green-600">Код отправлен</span>
            </div>
        </form>

        <!-- Шаг 2: подтверждение кодом -->
        <form
            v-if="pendingEmail"
            class="mt-5 max-w-md space-y-5 rounded-xl border border-[#2E74B5]/40 bg-[#EAF2FB] p-6 dark:border-sky-400/30 dark:bg-white/5"
            @submit.prevent="confirm"
        >
            <div class="font-semibold text-[#1F4E79] dark:text-sky-200">Подтверждение</div>
            <p class="text-sm text-slate-600 dark:text-slate-300">
                Код отправлен на <span class="font-medium">{{ pendingEmail }}</span>. Введите его, чтобы сменить почту.
            </p>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Код из письма</label>
                <input v-model="confirmForm.code" inputmode="numeric" maxlength="6" class="w-40 rounded-lg border border-slate-300 px-3 py-2 text-center text-lg tracking-widest" />
                <p v-if="confirmForm.errors.code" class="mt-1 text-sm text-red-600">{{ confirmForm.errors.code }}</p>
            </div>
            <button type="submit" :disabled="confirmForm.processing" class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50">
                Подтвердить и сменить
            </button>
        </form>
    </AppLayout>
</template>
