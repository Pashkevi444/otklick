<script setup lang="ts">
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCan } from '@/composables/useCan';

interface Msg {
    id: string;
    direction: string;
    text: string;
    time: string | null;
    date: string | null;
}
interface Conv {
    id: string;
    contact: string;
    phone: string | null;
    channel: string;
    source: string;
    contactRef: string | null;
    status: string;
    statusLabel: string;
    outcome: string;
    outcomeLabel: string;
    createdAt: string | null;
    crmRecordId: string | null;
    crmProvider: string | null;
    operatorActive: boolean;
    operatorName: string | null;
}
interface Outcome {
    value: string;
    label: string;
}

const props = defineProps<{ conversation: Conv; messages: Msg[]; outcomes: Outcome[]; canReply: boolean }>();

const lightbox = ref<string | null>(null);

// --- Живой чат: лайв-поллинг + перехват диалога оператором ---
const messages = ref<Msg[]>([...props.messages]);
const operatorActive = ref(props.conversation.operatorActive);
const operatorName = ref<string | null>(props.conversation.operatorName);
const replyText = ref('');
const busy = ref(false);
let lastId = messages.value.length ? messages.value[messages.value.length - 1].id : '';

const base = `/cabinet/conversations/${props.conversation.id}`;
const xsrf = (): string =>
    decodeURIComponent(document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');

async function poll(): Promise<void> {
    if (busy.value) return;
    try {
        const res = await fetch(`${base}/messages?after=${encodeURIComponent(lastId)}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (!res.ok) return;
        const d = await res.json();
        if (Array.isArray(d.messages) && d.messages.length) {
            const known = new Set(messages.value.map((m) => m.id));
            for (const m of d.messages as Msg[]) {
                lastId = m.id;
                if (known.has(m.id)) continue; // страховка от дублей
                known.add(m.id);
                messages.value.push(m);
            }
        }
        operatorActive.value = d.operatorActive;
        operatorName.value = d.operatorName;
    } catch {
        /* сеть моргнула — повторим на следующем тике */
    }
}

async function action(path: string, body?: object): Promise<Record<string, unknown>> {
    const res = await fetch(`${base}/${path}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': xsrf() },
        credentials: 'same-origin',
        body: JSON.stringify(body ?? {}),
    });
    if (!res.ok) throw res;
    return res.json();
}

const takeOver = async (): Promise<void> => {
    busy.value = true;
    try {
        const d = await action('takeover');
        operatorActive.value = true;
        operatorName.value = (d.operatorName as string) ?? null;
    } finally {
        busy.value = false;
    }
    void poll();
};

const release = async (): Promise<void> => {
    busy.value = true;
    try {
        await action('release');
        operatorActive.value = false;
        operatorName.value = null;
    } finally {
        busy.value = false;
    }
    void poll();
};

const sendReply = async (): Promise<void> => {
    const text = replyText.value.trim();
    if (!text || busy.value) return;
    busy.value = true;
    try {
        const d = await action('reply', { text });
        if (d.message) {
            const m = d.message as Msg;
            messages.value.push(m);
            lastId = m.id;
        }
        replyText.value = '';
    } finally {
        busy.value = false;
    }
};

let timer: number | undefined;
onMounted(() => {
    timer = window.setInterval(poll, 3000);
});
onUnmounted(() => {
    if (timer) window.clearInterval(timer);
});

const IMG_RE = /(https?:\/\/[^\s<>"']+\.(?:png|jpe?g|gif|webp)(?:\?[^\s<>"']*)?)/gi;

interface ParsedMsg {
    id: string;
    direction: string;
    text: string;
    images: string[];
    time: string | null;
    date: string | null;
}

function parse(text: string): { text: string; images: string[] } {
    const images: string[] = [];
    const clean = text
        .replace(IMG_RE, (u) => {
            images.push(u);
            return '';
        })
        .replace(/\n{3,}/g, '\n\n')
        .trim();
    return { text: clean, images };
}

// Группируем сообщения по дате; ссылки на фото рендерим как картинки.
const groups = computed(() => {
    const out: { date: string | null; items: ParsedMsg[] }[] = [];
    for (const m of messages.value) {
        const p = parse(m.text);
        const item: ParsedMsg = { id: m.id, direction: m.direction, time: m.time, date: m.date, text: p.text, images: p.images };
        const last = out[out.length - 1];
        if (last && last.date === m.date) last.items.push(item);
        else out.push({ date: m.date, items: [item] });
    }
    return out;
});

const outcomeClass = (o: string): string =>
    ({
        booked: 'bg-green-100 text-green-700',
        lost: 'bg-red-100 text-red-700',
        cancelled: 'bg-amber-100 text-amber-700',
        spam: 'bg-slate-100 text-slate-500',
        needs_human: 'bg-amber-100 text-amber-700',
        open: 'bg-green-100 text-green-700',
    })[o] ?? 'bg-slate-100 text-slate-500';

const setOutcome = (outcome: string): void => {
    router.put(`/cabinet/conversations/${props.conversation.id}/status`, { outcome }, { preserveScroll: true });
};

const can = useCan();
const removeLead = (): void => {
    if (confirm('Удалить лид? Диалог и переписка удалятся безвозвратно.')) {
        router.delete(`/cabinet/conversations/${props.conversation.id}`);
    }
};
</script>

<template>
    <Head :title="`Переписка — ${conversation.contact}`" />

    <AppLayout>
        <Link href="/cabinet/conversations" class="text-sm text-[#2E74B5] hover:underline dark:text-sky-300">← К списку лидов</Link>

        <!-- Шапка диалога -->
        <div class="mt-3 mb-5 flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4">
            <span class="flex h-11 w-11 flex-none items-center justify-center rounded-full bg-gradient-to-br from-[#2E74B5] to-[#1F4E79] text-sm font-semibold text-white">
                {{ conversation.contact.slice(0, 2).toUpperCase() }}
            </span>
            <div class="min-w-0">
                <div class="font-semibold text-[#1F4E79] dark:text-sky-200">{{ conversation.contact }}</div>
                <div class="text-xs text-slate-400">{{ conversation.source }} · диалог от {{ conversation.createdAt }}</div>
                <div v-if="conversation.contactRef" class="mt-0.5 text-xs text-slate-400">
                    <a
                        v-if="conversation.contactRef.startsWith('http')"
                        :href="conversation.contactRef"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="font-medium text-[#2E74B5] hover:underline dark:text-sky-300"
                        >👤 {{ conversation.contactRef.replace(/^https?:\/\//, '') }}</a
                    >
                    <span v-else>IP: {{ conversation.contactRef }}</span>
                </div>
                <a v-if="conversation.phone" :href="`tel:${conversation.phone}`" class="mt-0.5 inline-block text-sm font-medium text-[#2E74B5] dark:text-sky-300">📞 {{ conversation.phone }}</a>
                <div v-if="conversation.crmRecordId" class="mt-0.5 text-xs text-slate-400">
                    🗂 Запись в {{ conversation.crmProvider }}: <span class="font-medium text-slate-600 dark:text-slate-300">#{{ conversation.crmRecordId }}</span>
                </div>
            </div>
            <div class="ml-auto flex items-center gap-2">
                <template v-if="canReply">
                    <button
                        v-if="!operatorActive"
                        type="button"
                        :disabled="busy"
                        class="rounded-xl bg-[#2E74B5] px-3 py-1.5 text-xs font-medium text-white transition hover:bg-[#255f96] disabled:opacity-50"
                        @click="takeOver"
                    >
                        💬 Перехватить диалог
                    </button>
                    <template v-else>
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-medium text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>{{ operatorName || 'Оператор' }} на связи
                        </span>
                        <button type="button" :disabled="busy" class="rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:text-[#1F4E79] disabled:opacity-50 dark:border-white/10 dark:text-slate-300" @click="release">
                            Вернуть боту
                        </button>
                    </template>
                </template>
                <span class="rounded-full px-2.5 py-1 text-xs" :class="outcomeClass(conversation.outcome)">{{ conversation.outcomeLabel }}</span>
                <select
                    v-if="can('conversations.edit')"
                    :value="conversation.outcome"
                    class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 outline-none transition hover:text-[#1F4E79] focus:border-[#2E74B5] dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                    title="Статус лида"
                    @change="setOutcome(($event.target as HTMLSelectElement).value)"
                >
                    <option v-for="o in outcomes" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
                <button v-if="can('conversations.delete')" type="button" class="text-sm text-red-600 hover:underline" @click="removeLead">Удалить</button>
            </div>
        </div>

        <!-- Переписка -->
        <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-6">
            <div v-if="messages.length === 0" class="py-10 text-center text-slate-400">В этом диалоге пока нет сообщений.</div>

            <template v-for="(g, gi) in groups" :key="gi">
                <div class="my-4 flex justify-center first:mt-0">
                    <span class="rounded-full bg-slate-200/70 px-3 py-1 text-xs text-slate-500 dark:bg-white/10 dark:text-slate-300">{{ g.date }}</span>
                </div>
                <div class="space-y-2.5">
                    <div
                        v-for="m in g.items"
                        :key="m.id"
                        class="flex"
                        :class="m.direction === 'inbound' ? 'justify-start' : 'justify-end'"
                    >
                        <div
                            class="max-w-[78%] rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed whitespace-pre-wrap"
                            :class="m.direction === 'inbound'
                                ? 'rounded-bl-md border border-slate-200 bg-white text-slate-800 dark:border-white/10 dark:bg-white/5 dark:text-slate-100'
                                : 'rounded-br-md bg-gradient-to-br from-[#2E74B5] to-[#1F4E79] text-white'"
                        >
                            <div v-if="m.text">{{ m.text }}</div>
                            <img
                                v-for="(src, ii) in m.images"
                                :key="ii"
                                :src="src"
                                alt="Фото"
                                loading="lazy"
                                class="mt-2 block max-h-52 max-w-[220px] cursor-zoom-in rounded-xl object-cover shadow-sm transition hover:scale-[1.03]"
                                @click="lightbox = src"
                            />
                            <div
                                class="mt-1 text-right text-[11px]"
                                :class="m.direction === 'inbound' ? 'text-slate-400' : 'text-blue-100/80'"
                            >
                                {{ m.direction === 'inbound' ? 'Клиент' : 'Бот' }} · {{ m.time }}
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <!-- Ответ оператора (живой чат): доступен, пока диалог перехвачен -->
        <div
            v-if="canReply && operatorActive"
            class="mt-3 flex items-end gap-2 rounded-2xl border border-slate-200 bg-white p-3 dark:border-white/10 dark:bg-white/5"
        >
            <textarea
                v-model="replyText"
                rows="1"
                placeholder="Ответьте клиенту…"
                class="max-h-32 flex-1 resize-none rounded-xl border border-slate-200 px-3 py-2 text-sm outline-none focus:border-[#2E74B5] dark:border-white/10 dark:bg-white/5 dark:text-slate-100"
                @keydown.enter.exact.prevent="sendReply"
            ></textarea>
            <button
                type="button"
                :disabled="busy || !replyText.trim()"
                class="rounded-xl bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#255f96] disabled:opacity-50"
                @click="sendReply"
            >
                Отправить
            </button>
        </div>
        <p v-else-if="canReply" class="mt-3 text-center text-xs text-slate-400">
            Нажмите «Перехватить диалог», чтобы ответить клиенту лично — бот при этом замолчит.
        </p>

        <Teleport to="body">
            <Transition name="lb">
                <div
                    v-if="lightbox"
                    class="fixed inset-0 z-[2000] flex cursor-zoom-out items-center justify-center bg-black/85 p-6"
                    @click="lightbox = null"
                >
                    <img :src="lightbox" alt="Фото" class="max-h-[88vh] max-w-[92vw] rounded-2xl shadow-2xl" />
                    <button class="absolute right-5 top-5 flex h-10 w-10 items-center justify-center rounded-full bg-white/15 text-white" aria-label="Закрыть">✕</button>
                </div>
            </Transition>
        </Teleport>
    </AppLayout>
</template>

<style scoped>
.lb-enter-active,
.lb-leave-active {
    transition: opacity 0.25s ease;
}
.lb-enter-from,
.lb-leave-to {
    opacity: 0;
}
</style>
