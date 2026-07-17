<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Toggle from '@/Components/Toggle.vue';

interface Recipient {
    id: string;
    type: string;
    typeLabel: string;
    value: string | null;
    label: string | null;
    isActive: boolean;
    verified: boolean;
    role: string;
    events: string[];
}
interface Limits {
    email: number;
    telegram: number;
    emailUsed: number;
    telegramUsed: number;
}
interface Option {
    value: string;
    label: string;
}

interface WeeklyDigest {
    available: boolean;
    enabled: boolean;
}

const props = defineProps<{
    recipients: Recipient[];
    limits: Limits;
    hasTelegramBot: boolean;
    weeklyDigest: WeeklyDigest;
    eventOptions: Option[];
    roleOptions: Option[];
}>();

const roleLabel = (role: string): string => props.roleOptions.find((r) => r.value === role)?.label ?? role;

// Настройка получателя: какой развёрнут + черновик роли/типов.
const editing = ref<string | null>(null);
const draft = reactive<{ role: string; events: string[] }>({ role: 'director', events: [] });

const startEdit = (r: Recipient): void => {
    editing.value = editing.value === r.id ? null : r.id;
    draft.role = r.role;
    draft.events = [...r.events];
};
const toggleEvent = (value: string): void => {
    const i = draft.events.indexOf(value);
    if (i === -1) draft.events.push(value);
    else draft.events.splice(i, 1);
};
const savePrefs = (id: string): void => {
    router.put(`/cabinet/notifications/${id}/preferences`, { role: draft.role, events: draft.events }, {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    });
};

const digestEnabled = ref(props.weeklyDigest.enabled);
const setDigest = (value: boolean): void => {
    digestEnabled.value = value;
    router.put('/cabinet/notifications/weekly-digest', { enabled: value }, { preserveScroll: true });
};

const page = usePage();
const telegramLink = computed<string | null>(() => (page.props.flash as { telegramLink?: string | null })?.telegramLink ?? null);
// Серверные ошибки вне полей форм (лимит тарифа, отсутствие бота).
const errors = computed<Record<string, string>>(() => (page.props.errors as Record<string, string>) ?? {});

const emailForm = useForm({ email: '', label: '', role: 'director' });
const tgForm = useForm({ label: '', role: 'director' });

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
        <p class="mb-3 max-w-2xl text-sm text-muted">
            Получайте уведомления о событиях (новый лид, нужен оператор, запись) на почту и в Telegram.
        </p>
        <div class="mb-5 max-w-2xl rounded-2xl border border-[#EE8A5C]/30 bg-[#EE8A5C]/10 p-4 text-sm text-muted">
            <div class="mb-1 font-semibold text-warm">🔔 Эскалация на человека</div>
            Когда клиент просит оператора, бот замолкает, а получатели в Telegram получают его сообщение и
            могут отвечать клиенту <b>прямо через бота бизнеса</b> — ответом («Ответить») на пересланное сообщение.
            Команды в чате: <b>/close</b> — закрыть диалог (дальше отвечает бот), <b>/bot</b> — вернуть диалог боту.
        </div>

        <!-- Диплинк после «Подключить Telegram» -->
        <div
            v-if="telegramLink"
            class="mb-5 rounded-2xl border border-brand/30 bg-active p-4"
        >
            <div class="mb-2 text-sm font-semibold text-ink">Откройте ссылку в Telegram и нажмите «Старт» — чат привяжется к уведомлениям:</div>
            <a :href="telegramLink" target="_blank" rel="noopener" class="otk-btn-primary transition hover:-translate-y-0.5">
                ✈️ Открыть в Telegram
            </a>
            <div class="mt-2 break-all text-xs text-muted2">{{ telegramLink }}</div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Почта -->
            <div class="otk-card p-5">
                <div class="mb-1 font-display text-base font-semibold text-ink">Почта</div>
                <div class="mb-3 text-xs text-muted2">Использовано {{ limits.emailUsed }} из {{ limits.email }}</div>
                <form class="space-y-2" @submit.prevent="addEmail">
                    <input
                        v-model="emailForm.email"
                        type="email"
                        placeholder="owner@example.com"
                        class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none placeholder:text-muted2 focus:border-brand"
                    />
                    <input
                        v-model="emailForm.label"
                        type="text"
                        placeholder="Подпись (необязательно)"
                        class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none placeholder:text-muted2 focus:border-brand"
                    />
                    <div class="flex gap-2">
                        <button
                            v-for="ro in roleOptions"
                            :key="ro.value"
                            type="button"
                            class="rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                            :class="emailForm.role === ro.value ? 'border-brand bg-brand text-white' : 'border-line bg-panel text-muted hover:bg-hoverbg'"
                            @click="emailForm.role = ro.value"
                        >
                            {{ ro.label }}
                        </button>
                    </div>
                    <p v-if="emailForm.errors.email" class="text-xs text-red-500">{{ emailForm.errors.email }}</p>
                    <p v-if="errors.limit" class="text-xs text-red-500">{{ errors.limit }}</p>
                    <button
                        type="submit"
                        :disabled="emailFull || emailForm.processing"
                        class="otk-btn-primary transition hover:-translate-y-0.5 disabled:opacity-50"
                    >
                        Добавить почту
                    </button>
                    <p v-if="emailFull" class="text-xs text-muted2">Достигнут лимит тарифа.</p>
                </form>
            </div>

            <!-- Telegram -->
            <div class="otk-card p-5">
                <div class="mb-1 font-display text-base font-semibold text-ink">Telegram</div>
                <div class="mb-3 text-xs text-muted2">Использовано {{ limits.telegramUsed }} из {{ limits.telegram }}</div>
                <form class="space-y-2" @submit.prevent="connectTelegram">
                    <input
                        v-model="tgForm.label"
                        type="text"
                        placeholder="Подпись (например, «Директор»)"
                        class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none placeholder:text-muted2 focus:border-brand"
                    />
                    <div class="flex gap-2">
                        <button
                            v-for="ro in roleOptions"
                            :key="ro.value"
                            type="button"
                            class="rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                            :class="tgForm.role === ro.value ? 'border-brand bg-brand text-white' : 'border-line bg-panel text-muted hover:bg-hoverbg'"
                            @click="tgForm.role = ro.value"
                        >
                            {{ ro.label }}
                        </button>
                    </div>
                    <p v-if="errors.telegram" class="text-xs text-red-500">{{ errors.telegram }}</p>
                    <p v-if="errors.limit" class="text-xs text-red-500">{{ errors.limit }}</p>
                    <button
                        type="submit"
                        :disabled="!hasTelegramBot || telegramFull || tgForm.processing"
                        class="otk-btn-primary transition hover:-translate-y-0.5 disabled:opacity-50"
                    >
                        Подключить Telegram
                    </button>
                    <p v-if="!hasTelegramBot" class="text-xs text-muted2">Сначала подключите Telegram-бота в разделе «Каналы».</p>
                    <p v-else-if="telegramFull" class="text-xs text-muted2">Достигнут лимит тарифа.</p>
                </form>
            </div>
        </div>

        <!-- Список получателей -->
        <div class="mt-5 otk-card p-5">
            <div class="mb-3 font-display text-base font-semibold text-ink">Получатели</div>
            <div v-if="recipients.length === 0" class="py-6 text-center text-sm text-muted2">Пока нет получателей — добавьте почту или Telegram выше.</div>
            <ul v-else class="divide-y divide-[color:var(--otk-border)]">
                <li v-for="r in recipients" :key="r.id" class="py-3">
                    <div class="flex items-center gap-3">
                        <span class="text-lg">{{ icon(r.type) }}</span>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold text-ink">
                                {{ r.value ?? 'Ожидает подключения…' }}
                            </div>
                            <div class="text-xs text-muted2">
                                {{ r.typeLabel }}<template v-if="r.label"> · {{ r.label }}</template> ·
                                <span class="font-semibold text-brand">{{ roleLabel(r.role) }}</span>
                            </div>
                        </div>
                        <button type="button" class="rounded-xl px-2 py-1 text-xs font-semibold text-muted transition hover:bg-hoverbg" @click="startEdit(r)">
                            Настроить
                        </button>
                        <button
                            type="button"
                            class="rounded-full px-2.5 py-1 text-xs font-semibold transition"
                            :class="r.isActive ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-chip text-muted'"
                            @click="toggle(r.id)"
                        >
                            {{ r.isActive ? 'Активен' : 'Выключен' }}
                        </button>
                        <button type="button" class="rounded-xl px-2 py-1 text-xs font-semibold text-red-600 transition hover:bg-red-500/10 dark:text-red-400" @click="remove(r.id)">
                            Удалить
                        </button>
                    </div>

                    <!-- Настройка получателя: роль + типы уведомлений -->
                    <div v-if="editing === r.id" class="mt-3 rounded-xl border border-line bg-chip p-4">
                        <div class="mb-1 text-[11px] font-bold uppercase tracking-[0.09em] text-muted2">Роль</div>
                        <div class="mb-3 flex gap-2">
                            <button
                                v-for="ro in roleOptions"
                                :key="ro.value"
                                type="button"
                                class="rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                                :class="draft.role === ro.value ? 'border-brand bg-brand text-white' : 'border-line bg-panel text-muted hover:bg-hoverbg'"
                                @click="draft.role = ro.value"
                            >
                                {{ ro.label }}
                            </button>
                        </div>
                        <div class="mb-1 text-[11px] font-bold uppercase tracking-[0.09em] text-muted2">Какие уведомления получать</div>
                        <div class="space-y-2">
                            <label v-for="e in eventOptions" :key="e.value" class="flex items-center gap-2 text-sm text-muted">
                                <Toggle :model-value="draft.events.includes(e.value)" @update:model-value="toggleEvent(e.value)" />
                                {{ e.label }}
                            </label>
                        </div>
                        <div class="mt-3 flex items-center gap-2">
                            <button type="button" class="rounded-xl bg-brand px-3 py-1.5 text-xs font-bold text-white transition hover:bg-brand-hover" @click="savePrefs(r.id)">
                                Сохранить
                            </button>
                            <button type="button" class="rounded-xl px-3 py-1.5 text-xs font-semibold text-muted transition hover:bg-hoverbg" @click="editing = null">
                                Отмена
                            </button>
                        </div>
                        <p class="mt-2 text-xs text-muted2">Недельный «директорский» дайджест получают только получатели с ролью «Директор».</p>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Недельный AI-дайджест («директор») -->
        <div v-if="weeklyDigest.available" class="mt-5 max-w-3xl otk-card p-5">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="font-display text-base font-semibold text-ink">📊 Недельный отчёт «директор»</div>
                    <p class="mt-1 max-w-xl text-sm text-muted">
                        Раз в неделю (по понедельникам утром) присылаем владельцу короткую сводку прямо в подключённые
                        Telegram/почту: <b>сколько пришло лидов, конверсия в запись, что мешает записям и что улучшить</b> —
                        с рекомендациями от ИИ. Как личный аналитик, без захода в кабинет. Отправляем, только когда за неделю
                        были обращения.
                    </p>
                </div>
                <Toggle :model-value="digestEnabled" @update:model-value="setDigest" />
            </div>
        </div>
    </AppLayout>
</template>
