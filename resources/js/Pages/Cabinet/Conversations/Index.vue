<script setup lang="ts">
import { computed, reactive, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCan } from '@/composables/useCan';

interface Row {
    id: string;
    contact: string;
    phone: string | null;
    channel: string;
    source: string;
    outcome: string;
    outcomeLabel: string;
    messagesCount: number;
    lastMessage: string | null;
    lastMessageAt: string | null;
}
interface Pagination {
    current: number;
    last: number;
    total: number;
    from: number | null;
    to: number | null;
}
interface Filters {
    search: string;
    status: string;
    channel: string;
    sort: string;
    dir: string;
}
interface Option {
    value: string;
    label: string;
}

const props = defineProps<{
    conversations: Row[];
    pagination: Pagination;
    filters: Filters;
    statuses: Option[];
    channels: Option[];
}>();

const state = reactive<Filters>({ ...props.filters });

const go = (page = 1): void => {
    router.get(
        '/cabinet/conversations',
        {
            search: state.search || undefined,
            status: state.status || undefined,
            channel: state.channel || undefined,
            sort: state.sort,
            dir: state.dir,
            page,
        },
        { preserveState: true, preserveScroll: true, replace: true },
    );
};

let timer: ReturnType<typeof setTimeout>;
watch(
    () => state.search,
    () => {
        clearTimeout(timer);
        timer = setTimeout(() => go(), 350);
    },
);

const setStatus = (v: string): void => {
    state.status = v;
    go();
};

const setChannel = (v: string): void => {
    state.channel = v;
    go();
};

const sortBy = (col: string): void => {
    if (state.sort === col) state.dir = state.dir === 'asc' ? 'desc' : 'asc';
    else {
        state.sort = col;
        state.dir = 'desc';
    }
    go();
};

const arrow = (col: string): string => (state.sort !== col ? '' : state.dir === 'asc' ? ' ↑' : ' ↓');

const pages = computed<number[]>(() => {
    const { current, last } = props.pagination;
    const from = Math.max(1, current - 2);
    const to = Math.min(last, current + 2);
    const out: number[] = [];
    for (let i = from; i <= to; i++) out.push(i);
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

const outcomeIcon = (o: string): string =>
    ({ booked: '✅', lost: '✖', cancelled: '🚫', spam: '🗑', needs_human: '🙋', open: '⏳' })[o] ?? '';

const initials = (name: string): string =>
    name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0].toUpperCase())
        .join('');

const open = (id: string): void => {
    router.visit(`/cabinet/conversations/${id}`);
};

const can = useCan();
const remove = (id: string): void => {
    if (confirm('Удалить лид? Диалог и переписка удалятся безвозвратно.')) {
        router.delete(`/cabinet/conversations/${id}`, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="Диалоги" />

    <AppLayout title="Диалоги">
        <p class="mb-5 max-w-2xl text-sm text-slate-500">
            Журнал переписок бота с клиентами — 100% диалогов сохраняется здесь. Ищите, фильтруйте и сортируйте.
        </p>

        <!-- Тулбар: поиск + фильтр статуса -->
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="relative flex-1">
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">🔍</span>
                <input
                    v-model="state.search"
                    type="text"
                    placeholder="Поиск по имени, телефону или тексту сообщений…"
                    class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-9 pr-3 text-sm outline-none focus:border-[#2E74B5]"
                />
            </div>
            <div class="flex flex-wrap gap-1.5">
                <button
                    type="button"
                    class="rounded-lg px-3 py-2 text-sm font-medium transition"
                    :class="state.status === '' ? 'bg-[#2E74B5] text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'"
                    @click="setStatus('')"
                >
                    Все
                </button>
                <button
                    v-for="s in statuses"
                    :key="s.value"
                    type="button"
                    class="rounded-lg px-3 py-2 text-sm font-medium transition"
                    :class="state.status === s.value ? 'bg-[#2E74B5] text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'"
                    @click="setStatus(s.value)"
                >
                    {{ s.label }}
                </button>
            </div>
        </div>

        <!-- Фильтр по каналу -->
        <div class="mb-4 flex flex-wrap items-center gap-1.5">
            <span class="text-xs text-slate-400">Канал:</span>
            <button
                type="button"
                class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                :class="state.channel === '' ? 'bg-[#2E74B5] text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'"
                @click="setChannel('')"
            >
                Все
            </button>
            <button
                v-for="c in channels"
                :key="c.value"
                type="button"
                class="rounded-lg px-3 py-1.5 text-sm font-medium transition"
                :class="state.channel === c.value ? 'bg-[#2E74B5] text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'"
                @click="setChannel(c.value)"
            >
                {{ c.label }}
            </button>
        </div>

        <div v-if="conversations.length === 0" class="rounded-xl border border-slate-200 bg-white p-10 text-center text-slate-400">
            {{ state.search || state.status ? 'Ничего не найдено. Измените поиск или фильтр.' : 'Пока нет диалогов. Как только клиент напишет боту — переписка появится здесь.' }}
        </div>

        <template v-else>
            <!-- Мобильные карточки -->
            <div class="space-y-3 md:hidden">
                <Link
                    v-for="c in conversations"
                    :key="c.id"
                    :href="`/cabinet/conversations/${c.id}`"
                    class="block rounded-xl border border-slate-200 bg-white p-4"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-medium text-slate-800">{{ c.contact }}</span>
                        <span class="flex-none rounded-full px-2 py-0.5 text-xs" :class="outcomeClass(c.outcome)">{{ outcomeIcon(c.outcome) }} {{ c.outcomeLabel }}</span>
                    </div>
                    <p v-if="c.phone" class="mt-1 text-sm font-medium text-[#2E74B5]">📞 {{ c.phone }}</p>
                    <p class="mt-1 truncate text-sm text-slate-500">{{ c.lastMessage ?? '—' }}</p>
                    <div class="mt-1 flex justify-between text-xs text-slate-400">
                        <span>{{ c.source }} · {{ c.messagesCount }} сообщ.</span>
                        <span>{{ c.lastMessageAt }}</span>
                    </div>
                    <button v-if="can('conversations.delete')" type="button" class="mt-2 text-xs text-red-600 hover:underline" @click.prevent.stop="remove(c.id)">Удалить лид</button>
                </Link>
            </div>

            <!-- Таблица (десктоп) -->
            <div class="hidden overflow-x-auto rounded-xl border border-slate-200 bg-white md:block">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="cursor-pointer select-none px-5 py-3 font-medium hover:text-[#1F4E79]" @click="sortBy('contact')">Клиент{{ arrow('contact') }}</th>
                            <th class="px-5 py-3 font-medium">Телефон</th>
                            <th class="px-5 py-3 font-medium">Источник</th>
                            <th class="px-5 py-3 font-medium">Последнее сообщение</th>
                            <th class="cursor-pointer select-none px-5 py-3 font-medium hover:text-[#1F4E79]" @click="sortBy('messages')">Сообщений{{ arrow('messages') }}</th>
                            <th class="px-5 py-3 font-medium">Статус</th>
                            <th class="cursor-pointer select-none px-5 py-3 font-medium hover:text-[#1F4E79]" @click="sortBy('last')">Обновлён{{ arrow('last') }}</th>
                            <th v-if="can('conversations.delete')" class="px-5 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr v-for="c in conversations" :key="c.id" class="cursor-pointer transition hover:bg-slate-50" @click="open(c.id)">
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-[#EAF2FB] text-xs font-semibold text-[#1F4E79]">{{ initials(c.contact) }}</span>
                                    <span class="font-medium text-slate-800">{{ c.contact }}</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 font-medium" :class="c.phone ? 'text-[#2E74B5]' : 'text-slate-300'">{{ c.phone ?? '—' }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ c.source }}</td>
                            <td class="max-w-xs truncate px-5 py-3 text-slate-500">{{ c.lastMessage ?? '—' }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ c.messagesCount }}</td>
                            <td class="px-5 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs" :class="outcomeClass(c.outcome)">{{ outcomeIcon(c.outcome) }} {{ c.outcomeLabel }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-400">{{ c.lastMessageAt }}</td>
                            <td v-if="can('conversations.delete')" class="px-5 py-3 text-right" @click.stop>
                                <button type="button" class="text-sm text-red-600 hover:underline" @click="remove(c.id)">Удалить</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Пагинация -->
            <div class="mt-4 flex flex-col items-center justify-between gap-3 sm:flex-row">
                <div class="text-sm text-slate-400">Показано {{ pagination.from }}–{{ pagination.to }} из {{ pagination.total }}</div>
                <div v-if="pagination.last > 1" class="flex items-center gap-1">
                    <button
                        type="button"
                        :disabled="pagination.current === 1"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-600 transition hover:bg-slate-50 disabled:opacity-40"
                        @click="go(pagination.current - 1)"
                    >
                        ←
                    </button>
                    <button
                        v-for="p in pages"
                        :key="p"
                        type="button"
                        class="min-w-9 rounded-lg px-3 py-1.5 text-sm font-medium transition"
                        :class="p === pagination.current ? 'bg-[#2E74B5] text-white' : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50'"
                        @click="go(p)"
                    >
                        {{ p }}
                    </button>
                    <button
                        type="button"
                        :disabled="pagination.current === pagination.last"
                        class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-600 transition hover:bg-slate-50 disabled:opacity-40"
                        @click="go(pagination.current + 1)"
                    >
                        →
                    </button>
                </div>
            </div>
        </template>
    </AppLayout>
</template>
