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
                    <span class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">🔍</span>
                    <input
                        v-model="state.search"
                        type="text"
                        placeholder="Поиск по имени, телефону, email или нику…"
                        class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-9 pr-3 text-sm outline-none focus:border-[#2E74B5]"
                    />
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="text-xs text-slate-400">Сортировка:</span>
                    <button
                        v-for="s in sorts"
                        :key="s.value"
                        type="button"
                        class="rounded-lg px-3 py-2 text-sm font-medium transition"
                        :class="state.sort === s.value ? 'bg-[#2E74B5] text-white' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50'"
                        @click="sortBy(s.value)"
                    >
                        {{ s.label }}
                        <span v-if="state.sort === s.value">{{ state.dir === 'asc' ? '↑' : '↓' }}</span>
                    </button>
                </div>
            </div>

            <div v-if="channels.length" class="flex flex-wrap items-center gap-1.5">
                <span class="text-xs text-slate-400">Откуда пришёл:</span>
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
        </div>

        <div v-if="clients.length === 0" class="rounded-xl border border-slate-200 bg-white py-12 text-center text-slate-400">
            Клиентов пока нет. Карточка заводится автоматически, когда бот узнаёт телефон клиента.
        </div>

        <div v-else class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs text-slate-400">
                    <tr>
                        <th class="px-4 py-3 font-medium">Клиент</th>
                        <th class="px-4 py-3 font-medium">Контакты</th>
                        <th class="px-4 py-3 font-medium">Откуда</th>
                        <th class="px-4 py-3 text-center font-medium">Диалогов</th>
                        <th class="px-4 py-3 font-medium">Активность</th>
                        <th v-if="can('clients.edit') || can('clients.delete')" class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="c in clients"
                        :key="c.id"
                        class="cursor-pointer border-t border-slate-100 transition hover:bg-slate-50"
                        :class="isNew(c.id) ? 'bg-[#2E74B5]/5' : ''"
                        @click="open(c.id)"
                    >
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-700">
                                <span v-if="isNew(c.id)" title="Новый клиент" class="mr-1.5 inline-block h-2 w-2 rounded-full bg-[#2E74B5] align-middle"></span>
                                {{ c.name || 'Без имени' }}
                                <span v-if="isNew(c.id)" class="ml-1 rounded-full bg-[#2E74B5]/10 px-2 py-0.5 text-xs font-medium text-[#2E74B5]">Новый</span>
                                <span v-if="c.has_summary" title="Есть резюме" class="ml-1">📝</span>
                                <span v-if="c.banned" class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-medium text-red-700">заблокирован</span>
                            </div>
                            <div v-if="c.phone" class="text-xs text-slate-400">{{ c.phone }}</div>
                        </td>
                        <td class="px-4 py-3 text-slate-500">
                            <div v-if="c.email" class="text-xs">{{ c.email }}</div>
                            <div v-if="c.telegram_username" class="text-xs">@{{ c.telegram_username }}</div>
                            <span v-if="!c.email && !c.telegram_username" class="text-xs text-slate-300">—</span>
                        </td>
                        <td class="px-4 py-3 text-slate-500">{{ c.channel || '—' }}</td>
                        <td class="px-4 py-3 text-center text-slate-500">{{ c.conversations_count }}</td>
                        <td class="px-4 py-3 text-slate-400">{{ c.last_seen_at || '—' }}</td>
                        <td v-if="can('clients.edit') || can('clients.delete')" class="px-4 py-3 text-right" @click.stop>
                            <div class="flex items-center justify-end gap-3">
                                <button
                                    v-if="can('clients.edit')"
                                    type="button"
                                    class="text-sm hover:underline"
                                    :class="c.banned ? 'text-emerald-600' : 'text-amber-600'"
                                    @click="toggleBan(c)"
                                >
                                    {{ c.banned ? 'Разбанить' : 'Забанить' }}
                                </button>
                                <button v-if="can('clients.delete')" type="button" class="text-sm text-red-600 hover:underline" @click="remove(c.id)">Удалить</button>
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
