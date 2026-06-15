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

    <div class="min-h-screen bg-slate-50 flex items-center justify-center p-6">
        <div class="max-w-md w-full bg-white rounded-2xl border border-slate-200 shadow-sm p-8 text-center">
            <div class="text-4xl">🔒</div>
            <h1 class="mt-3 text-xl font-bold text-[#1F4E79]">Доступ к кабинету приостановлен</h1>
            <p class="mt-2 text-sm text-slate-600">
                <template v-if="reason === 'blocked'">Бизнес заблокирован администратором.</template>
                <template v-else>Срок оплаченного доступа истёк<span v-if="expiredAt"> ({{ expiredAt }})</span>.</template>
                Чтобы продлить — свяжитесь с нами.
            </p>

            <div class="mt-6 space-y-2 text-sm">
                <a v-if="contacts.phone" :href="`tel:${contacts.phone}`" class="block rounded-lg bg-slate-50 hover:bg-slate-100 px-4 py-2">📞 {{ contacts.phone }}</a>
                <a v-if="contacts.email" :href="`mailto:${contacts.email}`" class="block rounded-lg bg-slate-50 hover:bg-slate-100 px-4 py-2">✉️ {{ contacts.email }}</a>
                <a v-if="tgUrl" :href="tgUrl" target="_blank" class="block rounded-lg bg-slate-50 hover:bg-slate-100 px-4 py-2">✈️ Telegram @{{ contacts.telegram }}</a>
            </div>

            <button type="button" class="mt-6 text-sm text-slate-400 hover:text-slate-600" @click="logout">Выйти</button>
        </div>
    </div>
</template>
