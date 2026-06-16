<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

defineProps<{
    enabled: boolean;
    pending: boolean;
    qr: string | null;
    secret: string | null;
    recoveryCodes: string[];
}>();

const enableForm = useForm({ current_password: '' });
const confirmForm = useForm({ code: '' });
const disableForm = useForm({ current_password: '' });

const enable = (): void => enableForm.post('/account/two-factor', { preserveScroll: true, onSuccess: () => enableForm.reset() });
const confirm = (): void => confirmForm.post('/account/two-factor/confirm', { preserveScroll: true, onSuccess: () => confirmForm.reset() });
const disable = (): void => disableForm.delete('/account/two-factor', { preserveScroll: true, onSuccess: () => disableForm.reset() });
</script>

<template>
    <Head title="Двухфакторная аутентификация" />

    <AppLayout title="Двухфакторная аутентификация">
        <Link href="/account" class="text-sm text-[#2E74B5] hover:underline dark:text-sky-300">← К настройкам</Link>

        <div class="mt-3 max-w-md space-y-5">
            <!-- Выключена: предложить включить -->
            <form v-if="!enabled && !pending" class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 dark:border-white/10 dark:bg-white/5" @submit.prevent="enable">
                <div class="font-semibold text-[#1F4E79] dark:text-sky-200">Защитите вход вторым фактором</div>
                <p class="text-sm text-slate-500">Подходит Google Authenticator, 1Password, Authy и любое TOTP-приложение. Подтвердите паролем, чтобы начать.</p>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Текущий пароль</label>
                    <input v-model="enableForm.current_password" type="password" autocomplete="current-password" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                    <p v-if="enableForm.errors.current_password" class="mt-1 text-sm text-red-600">{{ enableForm.errors.current_password }}</p>
                </div>
                <button type="submit" :disabled="enableForm.processing" class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50">
                    Включить 2FA
                </button>
            </form>

            <!-- Настройка: QR + подтверждение -->
            <div v-if="pending" class="space-y-4 rounded-xl border border-[#2E74B5]/40 bg-[#EAF2FB] p-6 dark:border-sky-400/30 dark:bg-white/5">
                <div class="font-semibold text-[#1F4E79] dark:text-sky-200">Отсканируйте QR-код</div>
                <p class="text-sm text-slate-600 dark:text-slate-300">Добавьте аккаунт в приложение-аутентификатор: отсканируйте код или введите ключ вручную.</p>
                <img v-if="qr" :src="qr" alt="QR-код 2FA" class="mx-auto h-44 w-44 rounded-lg bg-white p-2" />
                <div v-if="secret" class="break-all rounded-lg bg-white/70 px-3 py-2 text-center font-mono text-sm dark:bg-white/10">{{ secret }}</div>

                <form class="space-y-2" @submit.prevent="confirm">
                    <label class="block text-sm font-medium text-slate-700">Код из приложения</label>
                    <input v-model="confirmForm.code" inputmode="numeric" maxlength="6" class="w-40 rounded-lg border border-slate-300 px-3 py-2 text-center text-lg tracking-widest" />
                    <p v-if="confirmForm.errors.code" class="text-sm text-red-600">{{ confirmForm.errors.code }}</p>
                    <button type="submit" :disabled="confirmForm.processing" class="block rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50">
                        Подтвердить и включить
                    </button>
                </form>
            </div>

            <!-- Включена -->
            <div v-if="enabled" class="rounded-xl border border-emerald-300/70 bg-emerald-50 p-6 dark:border-emerald-400/30 dark:bg-emerald-500/10">
                <div class="font-semibold text-emerald-700 dark:text-emerald-300">✅ 2FA включена</div>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">При входе после пароля потребуется код из приложения.</p>
            </div>

            <!-- Резервные коды (показываем при настройке и когда включена) -->
            <div v-if="recoveryCodes.length" class="rounded-xl border border-slate-200 bg-white p-6 dark:border-white/10 dark:bg-white/5">
                <div class="mb-1 font-semibold text-[#1F4E79] dark:text-sky-200">Резервные коды</div>
                <p class="mb-3 text-sm text-slate-500">Сохраните их в надёжном месте. Каждый код одноразовый — поможет войти, если нет доступа к приложению.</p>
                <div class="grid grid-cols-2 gap-2 font-mono text-sm">
                    <div v-for="c in recoveryCodes" :key="c" class="rounded bg-slate-50 px-3 py-1.5 dark:bg-white/10">{{ c }}</div>
                </div>
            </div>

            <!-- Отключение -->
            <form v-if="enabled || pending" class="space-y-3 rounded-xl border border-slate-200 bg-white p-6 dark:border-white/10 dark:bg-white/5" @submit.prevent="disable">
                <div class="font-semibold text-slate-700 dark:text-slate-200">{{ enabled ? 'Отключить 2FA' : 'Отменить настройку' }}</div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Текущий пароль</label>
                    <input v-model="disableForm.current_password" type="password" autocomplete="current-password" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                    <p v-if="disableForm.errors.current_password" class="mt-1 text-sm text-red-600">{{ disableForm.errors.current_password }}</p>
                </div>
                <button type="submit" :disabled="disableForm.processing" class="rounded-lg border border-red-300 px-4 py-2 text-sm font-medium text-red-700 hover:bg-red-50 disabled:opacity-50">
                    {{ enabled ? 'Отключить' : 'Отменить' }}
                </button>
            </form>
        </div>
    </AppLayout>
</template>
