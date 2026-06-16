<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface Support {
    email: string | null;
    phone: string | null;
    telegram: string | null;
}

defineProps<{ support: Support }>();

const page = usePage();
const tenant = computed(() => page.props.auth.user?.tenant ?? null);
</script>

<template>
    <Head title="Оплата подписки" />

    <AppLayout title="Оплата подписки">
        <div class="max-w-xl">
            <div class="rounded-2xl border border-slate-200 bg-white p-8 text-center">
                <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-[#EAF2FB] text-3xl dark:bg-white/10">💳</div>
                <h2 class="mt-4 text-xl font-bold text-[#1F4E79]">Онлайн-оплата скоро</h2>
                <p class="mx-auto mt-2 max-w-md text-sm text-slate-500">
                    Мы готовим оплату подписки прямо из кабинета. Пока доступ продлевается по договорённости —
                    напишите нам, и мы всё оформим.
                </p>

                <div v-if="tenant" class="mt-6 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                    Ваш тариф: <span class="font-semibold text-[#1F4E79]">{{ tenant.plan }}</span>
                    <template v-if="tenant.accessExpiresAt"> · доступ до {{ tenant.accessExpiresAt }}</template>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                    <Link
                        href="/cabinet/subscription"
                        class="rounded-xl border border-[#2E74B5]/30 bg-white px-5 py-2.5 text-sm font-medium text-[#1F4E79] transition hover:-translate-y-0.5"
                    >
                        Что входит в тариф
                    </Link>
                    <a
                        v-if="support.email"
                        :href="`mailto:${support.email}`"
                        class="rounded-xl bg-[#2E74B5] px-5 py-2.5 text-sm font-medium text-white shadow-lg shadow-[#2E74B5]/25 transition hover:-translate-y-0.5 hover:bg-[#255f96]"
                    >
                        Связаться для оплаты
                    </a>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-center gap-x-4 gap-y-1 text-sm text-slate-500">
                    <a v-if="support.email" :href="`mailto:${support.email}`" class="hover:text-[#2E74B5]">✉️ {{ support.email }}</a>
                    <a v-if="support.phone" :href="`tel:${support.phone}`" class="hover:text-[#2E74B5]">📞 {{ support.phone }}</a>
                    <a v-if="support.telegram" :href="`https://t.me/${support.telegram}`" target="_blank" rel="noopener" class="hover:text-[#2E74B5]">✈️ Telegram</a>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
