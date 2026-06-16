<script setup lang="ts">
import { computed } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface Recipient {
    id: string;
    type: string;
    typeLabel: string;
    value: string | null;
    label: string | null;
    isActive: boolean;
    verified: boolean;
}
interface Limits {
    email: number;
    telegram: number;
    emailUsed: number;
    telegramUsed: number;
}

const props = defineProps<{ recipients: Recipient[]; limits: Limits; hasTelegramBot: boolean }>();

const page = usePage();
const telegramLink = computed<string | null>(() => (page.props.flash as { telegramLink?: string | null })?.telegramLink ?? null);
// Серверные ошибки вне полей форм (лимит тарифа, отсутствие бота).
const errors = computed<Record<string, string>>(() => (page.props.errors as Record<string, string>) ?? {});

const emailForm = useForm({ email: '', label: '' });
const tgForm = useForm({ label: '' });

const emailFull = computed(() => props.limits.emailUsed >= props.limits.email);
const telegramFull = computed(() => props.limits.telegramUsed >= props.limits.telegram);

const addEmail = (): void => {
    emailForm.post('/cabinet/notifications/email', { preserveScroll: true, onSuccess: () => emailForm.reset() });
};
const connectTelegram = (): void => {
    tgForm.post('/cabinet/notifications/telegram', { preserveScroll: true, onSuccess: () => tgForm.reset() });
};
const toggle = (id: string): void => {
    router.put(`/cabinet/notifications/${id}/toggle`, {}, { preserveScroll: true });
};
const remove = (id: string): void => {
    router.delete(`/cabinet/notifications/${id}`, { preserveScroll: true });
};

const icon = (type: string): string => (type === 'telegram' ? '✈️' : '📧');
</script>

<template>
    <Head title="Уведомления и эскалация" />

    <AppLayout title="Уведомления и эскалация">
        <p class="mb-3 max-w-2xl text-sm text-slate-500">
            Получайте уведомления о событиях (новый лид, нужен оператор, запись) на почту и в Telegram.
        </p>
        <div class="mb-5 max-w-2xl rounded-xl border border-amber-300/50 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-400/20 dark:bg-amber-400/5 dark:text-amber-200/90">
            <div class="mb-1 font-medium">🔔 Эскалация на человека</div>
            Когда клиент просит оператора, бот замолкает, а получатели в Telegram получают его сообщение и
            могут отвечать клиенту <b>прямо через бота бизнеса</b> — ответом («Ответить») на пересланное сообщение.
            Команды в чате: <b>/close</b> — закрыть диалог (дальше отвечает бот), <b>/bot</b> — вернуть диалог боту.
        </div>

        <!-- Диплинк после «Подключить Telegram» -->
        <div
            v-if="telegramLink"
            class="mb-5 rounded-xl border border-[#2E74B5]/40 bg-[#EAF2FB] p-4 dark:border-sky-400/30 dark:bg-white/5"
        >
            <div class="mb-2 text-sm font-medium text-[#1F4E79] dark:text-sky-200">Откройте ссылку в Telegram и нажмите «Старт» — чат привяжется к уведомлениям:</div>
            <a :href="telegramLink" target="_blank" rel="noopener" class="inline-block rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white transition hover:-translate-y-0.5">
                ✈️ Открыть в Telegram
            </a>
            <div class="mt-2 break-all text-xs text-slate-400">{{ telegramLink }}</div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Почта -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <div class="mb-1 font-semibold text-[#1F4E79] dark:text-sky-200">Почта</div>
                <div class="mb-3 text-xs text-slate-400">Использовано {{ limits.emailUsed }} из {{ limits.email }}</div>
                <form class="space-y-2" @submit.prevent="addEmail">
                    <input
                        v-model="emailForm.email"
                        type="email"
                        placeholder="owner@example.com"
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-[#2E74B5]"
                    />
                    <input
                        v-model="emailForm.label"
                        type="text"
                        placeholder="Подпись (необязательно)"
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-[#2E74B5]"
                    />
                    <p v-if="emailForm.errors.email" class="text-xs text-rose-500">{{ emailForm.errors.email }}</p>
                    <p v-if="errors.limit" class="text-xs text-rose-500">{{ errors.limit }}</p>
                    <button
                        type="submit"
                        :disabled="emailFull || emailForm.processing"
                        class="rounded-xl bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white transition hover:-translate-y-0.5 disabled:opacity-50"
                    >
                        Добавить почту
                    </button>
                    <p v-if="emailFull" class="text-xs text-slate-400">Достигнут лимит тарифа.</p>
                </form>
            </div>

            <!-- Telegram -->
            <div class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
                <div class="mb-1 font-semibold text-[#1F4E79] dark:text-sky-200">Telegram</div>
                <div class="mb-3 text-xs text-slate-400">Использовано {{ limits.telegramUsed }} из {{ limits.telegram }}</div>
                <form class="space-y-2" @submit.prevent="connectTelegram">
                    <input
                        v-model="tgForm.label"
                        type="text"
                        placeholder="Подпись (например, «Директор»)"
                        class="w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-[#2E74B5]"
                    />
                    <p v-if="errors.telegram" class="text-xs text-rose-500">{{ errors.telegram }}</p>
                    <p v-if="errors.limit" class="text-xs text-rose-500">{{ errors.limit }}</p>
                    <button
                        type="submit"
                        :disabled="!hasTelegramBot || telegramFull || tgForm.processing"
                        class="rounded-xl bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white transition hover:-translate-y-0.5 disabled:opacity-50"
                    >
                        Подключить Telegram
                    </button>
                    <p v-if="!hasTelegramBot" class="text-xs text-slate-400">Сначала подключите Telegram-бота в разделе «Каналы».</p>
                    <p v-else-if="telegramFull" class="text-xs text-slate-400">Достигнут лимит тарифа.</p>
                </form>
            </div>
        </div>

        <!-- Список получателей -->
        <div class="mt-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
            <div class="mb-3 font-semibold text-[#1F4E79] dark:text-sky-200">Получатели</div>
            <div v-if="recipients.length === 0" class="py-6 text-center text-sm text-slate-400">Пока нет получателей — добавьте почту или Telegram выше.</div>
            <ul v-else class="divide-y divide-slate-100 dark:divide-white/10">
                <li v-for="r in recipients" :key="r.id" class="flex items-center gap-3 py-3">
                    <span class="text-lg">{{ icon(r.type) }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium text-slate-700 dark:text-slate-200">
                            {{ r.value ?? 'Ожидает подключения…' }}
                        </div>
                        <div class="text-xs text-slate-400">{{ r.typeLabel }}<template v-if="r.label"> · {{ r.label }}</template></div>
                    </div>
                    <button
                        type="button"
                        class="rounded-lg px-2 py-1 text-xs font-medium transition"
                        :class="r.isActive ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'"
                        @click="toggle(r.id)"
                    >
                        {{ r.isActive ? 'Активен' : 'Выключен' }}
                    </button>
                    <button type="button" class="rounded-lg px-2 py-1 text-xs text-rose-500 transition hover:bg-rose-50" @click="remove(r.id)">
                        Удалить
                    </button>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
