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
        <Link href="/account" class="text-sm font-semibold text-brand hover:underline">← К настройкам</Link>

        <div class="mt-3 max-w-md space-y-5">
            <!-- Выключена: предложить включить -->
            <form v-if="!enabled && !pending" class="otk-card space-y-4 p-6" @submit.prevent="enable">
                <div class="font-display text-base font-semibold text-ink">Защитите вход вторым фактором</div>
                <p class="text-sm text-muted">Подходит Google Authenticator, 1Password, Authy и любое TOTP-приложение. Подтвердите паролем, чтобы начать.</p>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink">Текущий пароль</label>
                    <input v-model="enableForm.current_password" type="password" autocomplete="current-password" class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none transition placeholder:text-muted2 focus:border-brand" />
                    <p v-if="enableForm.errors.current_password" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ enableForm.errors.current_password }}</p>
                </div>
                <button type="submit" :disabled="enableForm.processing" class="otk-btn-primary disabled:pointer-events-none disabled:opacity-50">
                    Включить 2FA
                </button>
            </form>

            <!-- Настройка: QR + подтверждение -->
            <div v-if="pending" class="space-y-4 rounded-2xl border border-brand/40 bg-active p-6">
                <div class="font-display text-base font-semibold text-ink">Отсканируйте QR-код</div>
                <p class="text-sm text-muted">Добавьте аккаунт в приложение-аутентификатор: отсканируйте код или введите ключ вручную.</p>
                <img v-if="qr" :src="qr" alt="QR-код 2FA" class="mx-auto h-44 w-44 rounded-xl bg-white p-2" />
                <div v-if="secret" class="break-all rounded-xl bg-panel px-3 py-2 text-center font-mono text-sm text-ink">{{ secret }}</div>

                <form class="space-y-2" @submit.prevent="confirm">
                    <label class="block text-sm font-semibold text-ink">Код из приложения</label>
                    <input v-model="confirmForm.code" inputmode="numeric" maxlength="6" class="w-40 rounded-xl border border-line bg-panel px-3.5 py-2.5 text-center text-lg tracking-widest text-ink outline-none transition focus:border-brand" />
                    <p v-if="confirmForm.errors.code" class="text-sm text-red-600 dark:text-red-400">{{ confirmForm.errors.code }}</p>
                    <button type="submit" :disabled="confirmForm.processing" class="otk-btn-primary disabled:pointer-events-none disabled:opacity-50">
                        Подтвердить и включить
                    </button>
                </form>
            </div>

            <!-- Включена -->
            <div v-if="enabled" class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 p-6">
                <div class="font-semibold text-emerald-600 dark:text-emerald-400">✅ 2FA включена</div>
                <p class="mt-1 text-sm text-muted">При входе после пароля потребуется код из приложения.</p>
            </div>

            <!-- Резервные коды (показываем при настройке и когда включена) -->
            <div v-if="recoveryCodes.length" class="otk-card p-6">
                <div class="mb-1 font-display text-base font-semibold text-ink">Резервные коды</div>
                <p class="mb-3 text-sm text-muted">Сохраните их в надёжном месте. Каждый код одноразовый — поможет войти, если нет доступа к приложению.</p>
                <div class="grid grid-cols-2 gap-2 font-mono text-sm text-ink">
                    <div v-for="c in recoveryCodes" :key="c" class="rounded-lg bg-chip px-3 py-1.5">{{ c }}</div>
                </div>
            </div>

            <!-- Отключение -->
            <form v-if="enabled || pending" class="otk-card space-y-3 p-6" @submit.prevent="disable">
                <div class="font-display text-base font-semibold text-ink">{{ enabled ? 'Отключить 2FA' : 'Отменить настройку' }}</div>
                <div>
                    <label class="mb-1.5 block text-sm font-semibold text-ink">Текущий пароль</label>
                    <input v-model="disableForm.current_password" type="password" autocomplete="current-password" class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none transition placeholder:text-muted2 focus:border-brand" />
                    <p v-if="disableForm.errors.current_password" class="mt-1 text-sm text-red-600 dark:text-red-400">{{ disableForm.errors.current_password }}</p>
                </div>
                <button type="submit" :disabled="disableForm.processing" class="rounded-xl border border-red-500/40 px-4 py-2.5 text-sm font-semibold text-red-600 transition hover:bg-red-500/10 disabled:pointer-events-none disabled:opacity-50 dark:text-red-400">
                    {{ enabled ? 'Отключить' : 'Отменить' }}
                </button>
            </form>
        </div>
    </AppLayout>
</template>
