<script setup lang="ts">
import { nextTick, onMounted, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface HistoryItem {
    direction: string;
    text: string;
}
interface ChatMessage {
    from: 'bot' | 'client';
    text: string;
    buttons?: string[];
    note?: string | null;
    escalate?: boolean;
    booked?: boolean;
    cancelled?: boolean;
}
interface BotReplyResponse {
    text: string;
    buttons: string[];
    escalate: boolean;
    booked: boolean;
    cancelled: boolean;
    note: string | null;
}

const props = defineProps<{ history: HistoryItem[] }>();

const messages = ref<ChatMessage[]>(
    props.history.map((m) => ({ from: m.direction === 'inbound' ? 'client' : 'bot', text: m.text })),
);
const input = ref('');
const busy = ref(false);
const scroller = ref<HTMLElement | null>(null);

const xsrf = (): string =>
    decodeURIComponent(document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');

const scrollToEnd = async (): Promise<void> => {
    await nextTick();
    if (scroller.value) scroller.value.scrollTop = scroller.value.scrollHeight;
};

const send = async (text?: string): Promise<void> => {
    const t = (text ?? input.value).trim();
    if (t === '' || busy.value) return;

    busy.value = true;
    input.value = '';
    messages.value.push({ from: 'client', text: t });
    void scrollToEnd();

    try {
        const res = await fetch('/cabinet/testing/message', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf() },
            credentials: 'same-origin',
            body: JSON.stringify({ text: t }),
        });
        const d: BotReplyResponse = await res.json();
        messages.value.push({
            from: 'bot',
            text: d.text,
            buttons: d.buttons,
            note: d.note,
            escalate: d.escalate,
            booked: d.booked,
            cancelled: d.cancelled,
        });
    } catch {
        messages.value.push({ from: 'bot', text: 'Не удалось получить ответ. Попробуйте ещё раз.', note: null });
    } finally {
        busy.value = false;
        void scrollToEnd();
    }
};

const reset = (): void => {
    router.post('/cabinet/testing/reset', {}, {
        preserveScroll: true,
        onSuccess: () => {
            messages.value = [];
        },
    });
};

onMounted(scrollToEnd);
</script>

<template>
    <Head title="Тестирование бота" />

    <AppLayout title="Тестирование бота">
        <div class="mb-5 rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-400/20 dark:bg-sky-400/10 dark:text-sky-100">
            <p class="font-semibold">🧪 Это тестовый чат с вашим ботом.</p>
            <p class="mt-1">
                Пишите как обычный клиент — бот отвечает по вашим настройкам (база знаний, сценарии, запись). Эти диалоги
                <b>не попадают</b> в «Лиды» и «Базу клиентов», а запись в YClients <b>не создаётся по-настоящему</b>.
                Тестовые данные стираются автоматически.
            </p>
        </div>

        <div class="mx-auto flex h-[60vh] max-w-2xl flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-white/10 dark:bg-white/5">
            <div ref="scroller" class="flex-1 space-y-3 overflow-y-auto p-4">
                <p v-if="messages.length === 0" class="mt-10 text-center text-sm text-slate-400">
                    Напишите первое сообщение — например, «Здравствуйте» или вопрос об услуге.
                </p>

                <template v-for="(m, i) in messages" :key="i">
                    <div class="flex" :class="m.from === 'client' ? 'justify-end' : 'justify-start'">
                        <div
                            class="max-w-[80%] rounded-2xl px-4 py-2 text-sm"
                            :class="m.from === 'client'
                                ? 'bg-[#2E74B5] text-white'
                                : 'bg-slate-100 text-slate-800 dark:bg-white/10 dark:text-slate-100'"
                        >
                            <p class="whitespace-pre-line">{{ m.text }}</p>

                            <div v-if="m.buttons && m.buttons.length" class="mt-2 flex flex-wrap gap-1.5">
                                <button
                                    v-for="(b, bi) in m.buttons"
                                    :key="bi"
                                    type="button"
                                    class="rounded-full border border-slate-300 bg-white px-3 py-1 text-xs text-slate-700 transition hover:border-[#2E74B5] hover:text-[#2E74B5] disabled:opacity-50 dark:border-white/15 dark:bg-white/5 dark:text-slate-200"
                                    :disabled="busy"
                                    @click="send(b)"
                                >
                                    {{ b }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <p
                        v-if="m.note"
                        class="text-center text-xs"
                        :class="m.booked ? 'text-emerald-600 dark:text-emerald-300' : m.escalate ? 'text-amber-600 dark:text-amber-300' : 'text-slate-400'"
                    >
                        {{ m.note }}
                    </p>
                </template>

                <p v-if="busy" class="text-left text-xs text-slate-400">Бот печатает…</p>
            </div>

            <form class="flex items-center gap-2 border-t border-slate-200 p-3 dark:border-white/10" @submit.prevent="send()">
                <input
                    v-model="input"
                    type="text"
                    placeholder="Напишите сообщение от лица клиента…"
                    class="flex-1 rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm focus:border-[#2E74B5] focus:outline-none dark:border-white/15 dark:bg-white/5 dark:text-slate-100"
                    :disabled="busy"
                />
                <button
                    type="submit"
                    class="rounded-xl bg-[#2E74B5] px-4 py-2 text-sm font-semibold text-white transition hover:bg-[#255f97] disabled:opacity-50"
                    :disabled="busy || input.trim() === ''"
                >
                    Отправить
                </button>
            </form>
        </div>

        <div class="mx-auto mt-3 max-w-2xl text-right">
            <button
                type="button"
                class="text-sm text-slate-500 underline-offset-2 hover:text-rose-600 hover:underline"
                @click="reset"
            >
                Начать заново
            </button>
        </div>
    </AppLayout>
</template>
