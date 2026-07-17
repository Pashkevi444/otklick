<script setup lang="ts">
import { computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';

interface Contacts {
    phone: string | null;
    email: string | null;
    telegram: string | null;
}

const props = defineProps<{ reason: 'blocked' | 'expired'; expiredAt: string | null; contacts: Contacts }>();

const tgUrl = computed(() => (props.contacts.telegram ? `https://t.me/${props.contacts.telegram}` : null));
const logout = (): void => router.post('/logout');
</script>

<template>
    <Head title="Доступ приостановлен" />

    <div class="min-h-screen bg-page flex items-center justify-center p-6">
        <div class="otk-card max-w-md w-full p-8 text-center">
            <div class="text-4xl">🔒</div>
            <h1 class="mt-3 font-display text-xl font-semibold text-ink">Доступ к кабинету приостановлен</h1>
            <p class="mt-2 text-sm text-muted">
                <template v-if="reason === 'blocked'">Бизнес заблокирован администратором.</template>
                <template v-else>Срок оплаченного доступа истёк<span v-if="expiredAt"> ({{ expiredAt }})</span>.</template>
                Чтобы продлить — свяжитесь с нами.
            </p>

            <div class="mt-6 space-y-2 text-sm">
                <a v-if="contacts.phone" :href="`tel:${contacts.phone}`" class="block rounded-xl bg-chip hover:bg-hoverbg px-4 py-2.5 text-ink">📞 {{ contacts.phone }}</a>
                <a v-if="contacts.email" :href="`mailto:${contacts.email}`" class="block rounded-xl bg-chip hover:bg-hoverbg px-4 py-2.5 text-ink">✉️ {{ contacts.email }}</a>
                <a v-if="tgUrl" :href="tgUrl" target="_blank" class="block rounded-xl bg-chip hover:bg-hoverbg px-4 py-2.5 text-ink">✈️ Telegram @{{ contacts.telegram }}</a>
            </div>

            <button type="button" class="otk-btn-primary mt-6 w-full" @click="logout">Выйти</button>
        </div>
    </div>
</template>
