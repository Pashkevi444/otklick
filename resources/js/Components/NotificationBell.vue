<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { realtime, type ReverbConfig } from '@/echo';

interface NItem {
    id: string;
    type: string;
    icon: string;
    title: string;
    body: string | null;
    url: string | null;
    read: boolean;
    at: string;
}
interface Feed {
    total: number;
    sections: Record<string, number>;
    items: NItem[];
}

const page = usePage();
const empty: Feed = { total: 0, sections: {}, items: [] };
const feed = ref<Feed>((page.props.notifications as Feed | null) ?? empty);
const open = ref(false);
let timer: ReturnType<typeof setInterval> | undefined;

const total = computed<number>(() => feed.value.total ?? 0);

// CSRF из cookie (как axios у Inertia) — для POST «прочитать всё».
const xsrf = (): string => {
    const m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : '';
};

const refresh = async (): Promise<void> => {
    try {
        const res = await fetch('/cabinet/notifications/feed', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });
        if (res.ok) feed.value = (await res.json()) as Feed;
    } catch {
        // сеть моргнула — попробуем на следующем тике
    }
};

const markAll = async (): Promise<void> => {
    try {
        await fetch('/cabinet/notifications/read', {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf(), 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
    } catch {
        // игнор — обновим выдачу ниже
    }
    await refresh();
};

const go = (item: NItem): void => {
    open.value = false;
    if (item.url) router.visit(item.url);
};

const showAll = (): void => {
    open.value = false;
    router.visit('/cabinet/notifications/history');
};

const when = (iso: string): string => {
    const d = new Date(iso);
    const mins = Math.round((Date.now() - d.getTime()) / 60000);
    if (mins < 1) return 'только что';
    if (mins < 60) return `${mins} мин`;
    if (mins < 1440) return `${Math.round(mins / 60)} ч`;
    return d.toLocaleDateString('ru-RU');
};

// Подхватываем свежую выдачу при навигации (shared-prop пересчитывается с учётом прав).
watch(
    () => page.props.notifications as Feed | null,
    (v) => {
        if (v) feed.value = v;
    },
);

const reverbConfig = computed<ReverbConfig | null>(() => (page.props.reverb as ReverbConfig | null) ?? null);
const tenantId = computed<string | null>(
    () => ((page.props.auth as { user?: { tenant?: { id?: string } } } | undefined)?.user?.tenant?.id) ?? null,
);
let channel: string | null = null;

onMounted(() => {
    // Фолбэк-поллинг (и единственный механизм, пока WS не настроен).
    timer = setInterval(() => void refresh(), 20000);

    // Реалтайм: пинг по приватному каналу тенанта → мгновенный рефетч.
    const echo = realtime(reverbConfig.value);
    if (echo && tenantId.value) {
        channel = `tenant.${tenantId.value}`;
        echo.private(channel).listen('.notifications.updated', () => void refresh());
    }
});
onBeforeUnmount(() => {
    if (timer) clearInterval(timer);
    const echo = realtime(reverbConfig.value);
    if (echo && channel) echo.leave(channel);
});
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="relative flex h-9 w-9 items-center justify-center rounded-xl border border-white/50 bg-white/40 text-lg transition hover:-translate-y-0.5 dark:border-white/10 dark:bg-white/10"
            :aria-label="`Уведомления${total ? ': ' + total + ' новых' : ''}`"
            @click="open = !open"
        >
            🔔
            <span
                v-if="total > 0"
                class="absolute -right-1 -top-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white"
            >{{ total > 99 ? '99+' : total }}</span>
        </button>

        <!-- Подложка для закрытия по клику вне -->
        <div v-if="open" class="fixed inset-0 z-40" @click="open = false"></div>

        <div
            v-if="open"
            class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl dark:border-white/10 dark:bg-[#141d33]"
        >
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-2.5 dark:border-white/10">
                <span class="text-sm font-semibold text-[#1F4E79] dark:text-sky-200">Уведомления</span>
                <button
                    v-if="total > 0"
                    type="button"
                    class="text-xs text-[#2E74B5] hover:underline dark:text-sky-300"
                    @click="markAll"
                >Прочитать всё</button>
            </div>

            <div class="max-h-96 overflow-y-auto">
                <p v-if="feed.items.length === 0" class="px-4 py-8 text-center text-sm text-slate-400">
                    Пока пусто
                </p>
                <component
                    :is="item.url ? 'button' : 'div'"
                    v-for="item in feed.items"
                    :key="item.id"
                    type="button"
                    class="flex w-full items-start gap-2.5 px-4 py-2.5 text-left transition hover:bg-slate-50 dark:hover:bg-white/5"
                    :class="!item.read ? 'bg-[#EAF2FB]/60 dark:bg-white/5' : ''"
                    @click="go(item)"
                >
                    <span class="text-base leading-5">{{ item.icon }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center justify-between gap-2">
                            <span class="truncate text-sm font-medium text-slate-800 dark:text-slate-100">{{ item.title }}</span>
                            <span class="shrink-0 text-[11px] text-slate-400">{{ when(item.at) }}</span>
                        </span>
                        <span v-if="item.body" class="mt-0.5 block truncate text-xs text-slate-500 dark:text-slate-400">{{ item.body }}</span>
                    </span>
                    <span v-if="!item.read" class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-rose-500"></span>
                </component>
            </div>

            <button
                type="button"
                class="block w-full border-t border-slate-100 px-4 py-2.5 text-center text-xs font-medium text-[#2E74B5] transition hover:bg-slate-50 dark:border-white/10 dark:text-sky-300 dark:hover:bg-white/5"
                @click="showAll"
            >Показать все</button>
        </div>
    </div>
</template>
