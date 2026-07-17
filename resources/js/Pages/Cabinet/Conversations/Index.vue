<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
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
    createdAt: string | null;
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
    newConversationIds?: string[];
}>();

const state = reactive<Filters>({ ...props.filters });

// Новые лиды (с непрочитанным уведомлением) — подсветка «Новый», пока не открыли диалог.
// Открытые в этой вкладке лиды гасим сразу и ЛОКАЛЬНО (sessionStorage): сервер их тоже
// метит прочитанными в show(), но Inertia на «назад» отдаёт закешированный список со
// СТАРЫМ newConversationIds — из-за этого подсветка «залипала» до ручного обновления.
const newIds = new Set(props.newConversationIds ?? []);
const READ_KEY = 'convReadLocal';
const readStored = typeof sessionStorage !== 'undefined' ? sessionStorage.getItem(READ_KEY) : null;
const readLocal = ref<Set<string>>(new Set((readStored ? JSON.parse(readStored) : []) as string[]));
const isNew = (id: string): boolean => newIds.has(id) && !readLocal.value.has(id);
const newCount = computed((): number => [...newIds].filter((id) => !readLocal.value.has(id)).length);
const markRead = (id: string): void => {
    if (!newIds.has(id) || readLocal.value.has(id)) return;
    readLocal.value = new Set(readLocal.value).add(id);
    if (typeof sessionStorage !== 'undefined') sessionStorage.setItem(READ_KEY, JSON.stringify([...readLocal.value]));
};

// «Прочитать всё» — гасит подсветку «Новый» у всех лидов (и бейдж секции).
// POST без preserveState → страница перерисуется со свежим (пустым) newConversationIds.
const markAllRead = (): void => {
    router.post('/cabinet/conversations/read-all', {}, { preserveScroll: true });
};

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

const outcomeClass = (o: string): string =>
    ({
        booked: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
        lost: 'bg-red-500/15 text-red-600 dark:text-red-400',
        cancelled: 'bg-[#EE8A5C]/15 text-warm',
        spam: 'bg-chip text-muted',
        needs_human: 'bg-[#EE8A5C]/15 text-warm',
        open: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    })[o] ?? 'bg-chip text-muted';

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
    markRead(id);
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
    <Head title="Лиды" />

    <AppLayout title="Лиды">
        <p class="mb-5 max-w-2xl text-sm text-muted">
            Все обращения клиентов — 100% лидов и переписки бота сохраняется здесь. Ищите, фильтруйте и сортируйте.
        </p>

        <!-- Тулбар: поиск + фильтр статуса -->
        <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="relative flex-1">
                <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted2">🔍</span>
                <input
                    v-model="state.search"
                    type="text"
                    placeholder="Поиск по имени, телефону или тексту сообщений…"
                    class="w-full rounded-xl border border-line bg-panel py-2.5 pl-9 pr-3.5 text-sm text-ink outline-none placeholder:text-muted2 focus:border-brand"
                />
            </div>
            <div class="flex flex-wrap gap-1.5">
                <button
                    type="button"
                    class="rounded-full px-3.5 py-1.5 text-sm font-semibold transition"
                    :class="state.status === '' ? 'bg-brand text-white' : 'bg-panel border border-line text-muted hover:bg-hoverbg'"
                    @click="setStatus('')"
                >
                    Все
                </button>
                <button
                    v-for="s in statuses"
                    :key="s.value"
                    type="button"
                    class="rounded-full px-3.5 py-1.5 text-sm font-semibold transition"
                    :class="state.status === s.value ? 'bg-brand text-white' : 'bg-panel border border-line text-muted hover:bg-hoverbg'"
                    @click="setStatus(s.value)"
                >
                    {{ s.label }}
                </button>
            </div>
        </div>

        <!-- Фильтр по каналу -->
        <div class="mb-4 flex flex-wrap items-center gap-1.5">
            <span class="text-[11px] font-bold uppercase tracking-[0.09em] text-muted2">Канал:</span>
            <button
                type="button"
                class="rounded-full px-3.5 py-1.5 text-sm font-semibold transition"
                :class="state.channel === '' ? 'bg-brand text-white' : 'bg-panel border border-line text-muted hover:bg-hoverbg'"
                @click="setChannel('')"
            >
                Все
            </button>
            <button
                v-for="c in channels"
                :key="c.value"
                type="button"
                class="rounded-full px-3.5 py-1.5 text-sm font-semibold transition"
                :class="state.channel === c.value ? 'bg-brand text-white' : 'bg-panel border border-line text-muted hover:bg-hoverbg'"
                @click="setChannel(c.value)"
            >
                {{ c.label }}
            </button>
        </div>

        <!-- Есть новые лиды — кнопка массово погасить подсветку «Новый». -->
        <div v-if="newCount > 0" class="mb-3 flex items-center justify-between rounded-xl border border-brand/20 bg-active px-4 py-2.5">
            <span class="text-sm text-muted"><span class="font-bold text-brand">{{ newCount }}</span> новых</span>
            <button type="button" class="text-sm font-semibold text-brand hover:underline" @click="markAllRead">Прочитать всё</button>
        </div>

        <div v-if="conversations.length === 0" class="otk-card p-10 text-center text-muted2">
            {{ state.search || state.status ? 'Ничего не найдено. Измените поиск или фильтр.' : 'Пока нет лидов. Как только клиент напишет боту — обращение появится здесь.' }}
        </div>

        <template v-else>
            <!-- Мобильные карточки -->
            <div class="space-y-3 md:hidden">
                <Link
                    v-for="c in conversations"
                    :key="c.id"
                    :href="`/cabinet/conversations/${c.id}`"
                    class="otk-card block p-4"
                    :class="isNew(c.id) ? 'ring-1 ring-brand/40 bg-active' : ''"
                    @click="markRead(c.id)"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-semibold text-ink">
                            <span v-if="isNew(c.id)" class="mr-1 rounded-full bg-active px-2 py-0.5 text-xs font-semibold text-brand">Новый</span>
                            {{ c.contact }}
                        </span>
                        <span class="flex-none rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="outcomeClass(c.outcome)">{{ outcomeIcon(c.outcome) }} {{ c.outcomeLabel }}</span>
                    </div>
                    <p v-if="c.phone" class="mt-1 text-sm font-semibold text-brand">📞 {{ c.phone }}</p>
                    <p class="mt-1 truncate text-sm text-muted">{{ c.lastMessage ?? '—' }}</p>
                    <div class="mt-1 flex justify-between text-xs text-muted2">
                        <span>{{ c.source }} · {{ c.messagesCount }} сообщ.</span>
                        <span>{{ c.lastMessageAt }}</span>
                    </div>
                    <p v-if="c.createdAt" class="mt-0.5 text-xs text-muted2">Создан: {{ c.createdAt }}</p>
                    <button v-if="can('conversations.delete')" type="button" class="mt-2 text-xs font-semibold text-red-600 hover:underline dark:text-red-400" @click.prevent.stop="remove(c.id)">Удалить лид</button>
                </Link>
            </div>

            <!-- Таблица (десктоп) -->
            <div class="otk-card hidden overflow-x-auto md:block">
                <table class="w-full text-sm">
                    <thead class="border-b border-line text-left text-[11px] font-bold uppercase tracking-[0.09em] text-muted2">
                        <tr>
                            <th class="cursor-pointer select-none px-6 py-4 hover:text-brand" @click="sortBy('contact')">Клиент{{ arrow('contact') }}</th>
                            <th class="px-6 py-4">Телефон</th>
                            <th class="px-6 py-4">Источник</th>
                            <th class="px-6 py-4">Последнее сообщение</th>
                            <th class="cursor-pointer select-none px-6 py-4 hover:text-brand" @click="sortBy('messages')">Сообщений{{ arrow('messages') }}</th>
                            <th class="px-6 py-4">Статус</th>
                            <th class="cursor-pointer select-none px-6 py-4 hover:text-brand" @click="sortBy('created')">Создан{{ arrow('created') }}</th>
                            <th class="cursor-pointer select-none px-6 py-4 hover:text-brand" @click="sortBy('last')">Обновлён{{ arrow('last') }}</th>
                            <th v-if="can('conversations.delete')" class="px-6 py-4" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[color:var(--otk-border)]">
                        <tr v-for="c in conversations" :key="c.id" class="cursor-pointer transition hover:bg-hoverbg" :class="isNew(c.id) ? 'bg-active' : ''" @click="open(c.id)">
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <span v-if="isNew(c.id)" title="Новый лид" class="h-2 w-2 flex-none rounded-full bg-brand"></span>
                                    <span class="inline-flex h-9 w-9 flex-none items-center justify-center rounded-full bg-active text-sm font-bold text-brand">{{ initials(c.contact) }}</span>
                                    <span class="font-semibold text-ink">{{ c.contact }}</span>
                                    <span v-if="isNew(c.id)" class="rounded-full bg-active px-2 py-0.5 text-xs font-semibold text-brand">Новый</span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 font-semibold" :class="c.phone ? 'text-brand' : 'text-muted2'">{{ c.phone ?? '—' }}</td>
                            <td class="px-6 py-4 text-muted">{{ c.source }}</td>
                            <td class="max-w-xs truncate px-6 py-4 text-muted">{{ c.lastMessage ?? '—' }}</td>
                            <td class="px-6 py-4 text-muted">{{ c.messagesCount }}</td>
                            <td class="px-6 py-4">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="outcomeClass(c.outcome)">{{ outcomeIcon(c.outcome) }} {{ c.outcomeLabel }}</span>
                            </td>
                            <td class="whitespace-nowrap px-6 py-4 text-muted2">{{ c.createdAt ?? '—' }}</td>
                            <td class="whitespace-nowrap px-6 py-4 text-muted2">{{ c.lastMessageAt }}</td>
                            <td v-if="can('conversations.delete')" class="px-6 py-4 text-right" @click.stop>
                                <button type="button" class="text-sm font-semibold text-red-600 hover:underline dark:text-red-400" @click="remove(c.id)">Удалить</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Пагинация -->
            <Pagination
                :current="pagination.current"
                :last="pagination.last"
                :total="pagination.total"
                :from="pagination.from"
                :to="pagination.to"
            />
        </template>
    </AppLayout>
</template>
