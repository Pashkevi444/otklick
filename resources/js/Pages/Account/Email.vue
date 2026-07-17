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
        <Link href="/account" class="text-sm font-semibold text-brand hover:underline">← К настройкам</Link>

        <p class="mt-2 mb-5 text-sm text-muted">Текущая почта: <span class="font-semibold text-ink">{{ currentEmail }}</span></p>

        <!-- Шаг 1: запрос смены -->
        <form class="otk-card max-w-md space-y-5 p-6" @submit.prevent="requestChange">
            <div class="font-display text-base font-semibold text-ink">Новый адрес</div>
            <div>
                <label class="mb-1.5 block text-sm font-semibold text-ink">Новая почта</label>
                <input v-model="requestForm.new_email" type="email" autocomplete="email" class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none transition placeholder:text-muted2 focus:border-brand" />
                <p v-if="requestForm.errors.new_email" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ requestForm.errors.new_email }}</p>
            </div>
            <div>
                <label class="mb-1.5 block text-sm font-semibold text-ink">Текущий пароль</label>
                <input v-model="requestForm.current_password" type="password" autocomplete="current-password" class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none transition placeholder:text-muted2 focus:border-brand" />
                <p v-if="requestForm.errors.current_password" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ requestForm.errors.current_password }}</p>
            </div>
            <div class="flex items-center gap-3">
                <button type="submit" :disabled="requestForm.processing" class="otk-btn-primary disabled:pointer-events-none disabled:opacity-50">
                    Отправить код
                </button>
                <span v-if="requestForm.recentlySuccessful" class="text-sm text-emerald-600 dark:text-emerald-400">Код отправлен</span>
            </div>
        </form>

        <!-- Шаг 2: подтверждение кодом -->
        <form
            v-if="pendingEmail"
            class="mt-5 max-w-md space-y-5 rounded-2xl border border-brand/40 bg-active p-6"
            @submit.prevent="confirm"
        >
            <div class="font-display text-base font-semibold text-ink">Подтверждение</div>
            <p class="text-sm text-muted">
                Код отправлен на <span class="font-semibold text-ink">{{ pendingEmail }}</span>. Введите его, чтобы сменить почту.
            </p>
            <div>
                <label class="mb-1.5 block text-sm font-semibold text-ink">Код из письма</label>
                <input v-model="confirmForm.code" inputmode="numeric" maxlength="6" class="w-40 rounded-xl border border-line bg-panel px-3.5 py-2.5 text-center text-lg tracking-widest text-ink outline-none transition focus:border-brand" />
                <p v-if="confirmForm.errors.code" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ confirmForm.errors.code }}</p>
            </div>
            <button type="submit" :disabled="confirmForm.processing" class="otk-btn-primary disabled:pointer-events-none disabled:opacity-50">
                Подтвердить и сменить
            </button>
        </form>
    </AppLayout>
</template>
