<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

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
    status: string;
    statusLabel: string;
    createdAt: string | null;
}

const props = defineProps<{ conversation: Conv; messages: Msg[] }>();

const lightbox = ref<string | null>(null);

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
    for (const m of props.messages) {
        const p = parse(m.text);
        const item: ParsedMsg = { id: m.id, direction: m.direction, time: m.time, date: m.date, text: p.text, images: p.images };
        const last = out[out.length - 1];
        if (last && last.date === m.date) last.items.push(item);
        else out.push({ date: m.date, items: [item] });
    }
    return out;
});

const statusClass = (s: string): string =>
    s === 'needs_human'
        ? 'bg-amber-100 text-amber-700'
        : s === 'closed'
          ? 'bg-slate-100 text-slate-500'
          : 'bg-green-100 text-green-700';

const setStatus = (status: string): void => {
    router.put(`/cabinet/conversations/${props.conversation.id}/status`, { status }, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Переписка — ${conversation.contact}`" />

    <AppLayout>
        <Link href="/cabinet/conversations" class="text-sm text-[#2E74B5] hover:underline dark:text-sky-300">← К списку диалогов</Link>

        <!-- Шапка диалога -->
        <div class="mt-3 mb-5 flex flex-wrap items-center gap-3 rounded-2xl border border-slate-200 bg-white p-4">
            <span class="flex h-11 w-11 flex-none items-center justify-center rounded-full bg-gradient-to-br from-[#2E74B5] to-[#1F4E79] text-sm font-semibold text-white">
                {{ conversation.contact.slice(0, 2).toUpperCase() }}
            </span>
            <div class="min-w-0">
                <div class="font-semibold text-[#1F4E79] dark:text-sky-200">{{ conversation.contact }}</div>
                <div class="text-xs text-slate-400">{{ conversation.source }} · диалог от {{ conversation.createdAt }}</div>
                <a v-if="conversation.phone" :href="`tel:${conversation.phone}`" class="mt-0.5 inline-block text-sm font-medium text-[#2E74B5] dark:text-sky-300">📞 {{ conversation.phone }}</a>
            </div>
            <div class="ml-auto flex items-center gap-2">
                <span class="rounded-full px-2.5 py-1 text-xs" :class="statusClass(conversation.status)">{{ conversation.statusLabel }}</span>
                <button
                    v-if="conversation.status !== 'closed'"
                    type="button"
                    class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-medium text-slate-600 transition hover:-translate-y-0.5 hover:text-[#1F4E79] dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                    @click="setStatus('closed')"
                >
                    Закрыть диалог
                </button>
                <button
                    v-else
                    type="button"
                    class="rounded-xl border border-[#2E74B5]/30 bg-white px-3 py-1.5 text-xs font-medium text-[#1F4E79] transition hover:-translate-y-0.5 dark:border-sky-400/30 dark:bg-white/5 dark:text-sky-300"
                    @click="setStatus('open')"
                >
                    Вернуть в работу
                </button>
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
