<script setup lang="ts">
import { reactive, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Pagination from '@/Components/Pagination.vue';
import { useCan } from '@/composables/useCan';

interface Row {
    id: string;
    name: string | null;
    phone: string | null;
    email: string | null;
    telegram_username: string | null;
    channel: string | null;
    conversations_count: number;
    has_summary: boolean;
    last_seen_at: string | null;
    banned: boolean;
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
    channel: string;
    sort: string;
    dir: string;
}
interface ChannelOption {
    value: string;
    label: string;
}

const props = defineProps<{
    clients: Row[];
    pagination: Pagination;
    filters: Filters;
    channels: ChannelOption[];
    newClientIds?: string[];
}>();

const state = reactive<Filters>({ ...props.filters });

// Клиенты с непрочитанным уведомлением — подсвечиваем «Новый» при заходе в базу.
const newIds = new Set(props.newClientIds ?? []);
const isNew = (id: string): boolean => newIds.has(id);
const newCount = newIds.size;

// «Прочитать всё» — гасит подсветку «Новый» у всех клиентов (и бейдж секции).
// POST без preserveState → страница перерисуется со свежим (пустым) newClientIds.
const markAllRead = (): void => {
    router.post('/cabinet/clients/read-all', {}, { preserveScroll: true });
};

const sorts = [
    { value: 'last', label: 'Последняя активность' },
    { value: 'name', label: 'Имя' },
    { value: 'first', label: 'Первое обращение' },
];

const go = (page = 1): void => {
    router.get(
        '/cabinet/clients',
        {
            search: state.search || undefined,
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

const open = (id: string): void => {
    router.get(`/cabinet/clients/${id}`);
};

// Инициалы для аватара-кружка (как в макете: «АК» из «Анна Ковалёва»).
const initials = (name: string | null): string => {
    const parts = (name ?? '').trim().split(/\s+/).filter(Boolean);
    if (parts.length === 0) return '?';
    return parts
        .slice(0, 2)
        .map((w) => w[0]!.toUpperCase())
        .join('');
};

const can = useCan();
const remove = (id: string): void => {
    if (confirm('Удалить карточку клиента? Связанные диалоги останутся.')) {
        router.delete(`/cabinet/clients/${id}`, { preserveScroll: true });
    }
};

// Бан/разбан: от забаненного клиента бот не ведёт диалог (отвечает фиксированным
// уведомлением без LLM). Право — «Редактирование клиентов» (clients.edit).
const toggleBan = (row: Row): void => {
    if (row.banned) {
        router.post(`/cabinet/clients/${row.id}/unban`, {}, { preserveScroll: true });
    } else if (confirm('Заблокировать клиента? Бот перестанет вести с ним диалог.')) {
        router.post(`/cabinet/clients/${row.id}/ban`, {}, { preserveScroll: true });
    }
};
</script>

<template>
    <Head title="База клиентов" />

    <AppLayout title="База клиентов">
        <!-- Фильтры: поиск + канал + сортировка -->
        <div class="mb-4 space-y-3">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative flex-1">
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted2">🔍</span>
                    <input
                        v-model="state.search"
                        type="text"
                        placeholder="Поиск по имени, телефону, email или нику…"
                        class="w-full rounded-xl border border-line bg-panel py-2.5 pl-9 pr-3.5 text-sm text-ink outline-none placeholder:text-muted2 focus:border-brand"
                    />
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="text-xs text-muted2">Сортировка:</span>
                    <button
                        v-for="s in sorts"
                        :key="s.value"
                        type="button"
                        class="rounded-full px-3.5 py-1.5 text-sm font-semibold transition"
                        :class="state.sort === s.value ? 'bg-brand text-white' : 'border border-line bg-panel text-muted hover:bg-hoverbg'"
                        @click="sortBy(s.value)"
                    >
                        {{ s.label }}
                        <span v-if="state.sort === s.value">{{ state.dir === 'asc' ? '↑' : '↓' }}</span>
                    </button>
                </div>
            </div>

            <div v-if="channels.length" class="flex flex-wrap items-center gap-1.5">
                <span class="text-xs text-muted2">Откуда пришёл:</span>
                <button
                    type="button"
                    class="rounded-full px-3.5 py-1.5 text-sm font-semibold transition"
                    :class="state.channel === '' ? 'bg-brand text-white' : 'border border-line bg-panel text-muted hover:bg-hoverbg'"
                    @click="setChannel('')"
                >
                    Все
                </button>
                <button
                    v-for="c in channels"
                    :key="c.value"
                    type="button"
                    class="rounded-full px-3.5 py-1.5 text-sm font-semibold transition"
                    :class="state.channel === c.value ? 'bg-brand text-white' : 'border border-line bg-panel text-muted hover:bg-hoverbg'"
                    @click="setChannel(c.value)"
                >
                    {{ c.label }}
                </button>
            </div>
        </div>

        <!-- Есть новые клиенты — кнопка массово погасить подсветку «Новый». -->
        <div v-if="newCount > 0" class="mb-3 flex items-center justify-between rounded-xl border border-brand/20 bg-active px-4 py-2.5">
            <span class="text-sm text-muted"><span class="font-bold text-brand">{{ newCount }}</span> новых</span>
            <button type="button" class="text-sm font-semibold text-brand hover:underline" @click="markAllRead">Прочитать всё</button>
        </div>

        <div v-if="clients.length === 0" class="otk-card py-12 text-center text-muted2">
            Клиентов пока нет. Карточка заводится автоматически, когда бот узнаёт телефон клиента.
        </div>

        <div v-else class="overflow-x-auto rounded-2xl border border-line bg-panel">
            <table class="w-full min-w-[560px] text-sm">
                <thead class="text-left text-[11px] font-bold uppercase tracking-[0.09em] text-muted2">
                    <tr>
                        <th class="px-4 py-3 font-bold">Клиент</th>
                        <th class="px-4 py-3 font-bold">Контакты</th>
                        <th class="px-4 py-3 font-bold">Откуда</th>
                        <th class="px-4 py-3 text-center font-bold">Диалогов</th>
                        <th class="px-4 py-3 font-bold">Активность</th>
                        <th v-if="can('clients.edit') || can('clients.delete')" class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="c in clients"
                        :key="c.id"
                        class="cursor-pointer border-t border-line transition hover:bg-hoverbg"
                        :class="isNew(c.id) ? 'bg-active' : ''"
                        @click="open(c.id)"
                    >
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-active text-sm font-bold text-brand">{{ initials(c.name) }}</span>
                                <div class="min-w-0">
                                    <div class="font-semibold text-ink">
                                        <span v-if="isNew(c.id)" title="Новый клиент" class="mr-1.5 inline-block h-2 w-2 rounded-full bg-brand align-middle"></span>
                                        {{ c.name || 'Без имени' }}
                                        <span v-if="isNew(c.id)" class="ml-1 rounded-full bg-active px-2.5 py-0.5 text-xs font-semibold text-brand">Новый</span>
                                        <span v-if="c.has_summary" title="Есть резюме" class="ml-1">📝</span>
                                        <span v-if="c.banned" class="ml-1 rounded-full bg-red-500/15 px-2.5 py-0.5 text-xs font-semibold text-red-600 dark:text-red-400">заблокирован</span>
                                    </div>
                                    <div v-if="c.phone" class="text-xs text-muted2">{{ c.phone }}</div>
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-muted">
                            <div v-if="c.email" class="text-xs">{{ c.email }}</div>
                            <div v-if="c.telegram_username" class="text-xs">@{{ c.telegram_username }}</div>
                            <span v-if="!c.email && !c.telegram_username" class="text-xs text-muted2">—</span>
                        </td>
                        <td class="px-4 py-3 text-muted">{{ c.channel || '—' }}</td>
                        <td class="px-4 py-3 text-center text-muted">{{ c.conversations_count }}</td>
                        <td class="px-4 py-3 text-muted2">{{ c.last_seen_at || '—' }}</td>
                        <td v-if="can('clients.edit') || can('clients.delete')" class="px-4 py-3 text-right" @click.stop>
                            <div class="flex items-center justify-end gap-3">
                                <button
                                    v-if="can('clients.edit')"
                                    type="button"
                                    class="text-sm font-semibold hover:underline"
                                    :class="c.banned ? 'text-emerald-600 dark:text-emerald-400' : 'text-warm'"
                                    @click="toggleBan(c)"
                                >
                                    {{ c.banned ? 'Разбанить' : 'Забанить' }}
                                </button>
                                <button v-if="can('clients.delete')" type="button" class="text-sm font-semibold text-red-600 hover:underline dark:text-red-400" @click="remove(c.id)">Удалить</button>
                            </div>
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
    </AppLayout>
</template>
