<script setup lang="ts">
import { reactive, watch } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
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
}>();

const state = reactive<Filters>({ ...props.filters });

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
                        <th v-if="can('clients.delete')" class="px-4 py-3" />
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="c in clients"
                        :key="c.id"
                        class="cursor-pointer border-t border-slate-100 transition hover:bg-slate-50"
                        @click="open(c.id)"
                    >
                        <td class="px-4 py-3">
                            <div class="font-medium text-slate-700">
                                {{ c.name || 'Без имени' }}
                                <span v-if="c.has_summary" title="Есть резюме" class="ml-1">📝</span>
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
                        <td v-if="can('clients.delete')" class="px-4 py-3 text-right" @click.stop>
                            <button type="button" class="text-sm text-red-600 hover:underline" @click="remove(c.id)">Удалить</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Пагинация -->
        <div v-if="pagination.total > 0" class="mt-4 flex items-center justify-between text-sm text-slate-500">
            <span>{{ pagination.from }}–{{ pagination.to }} из {{ pagination.total }}</span>
            <div class="flex gap-1.5">
                <button
                    type="button"
                    :disabled="pagination.current <= 1"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 disabled:opacity-40"
                    @click="go(pagination.current - 1)"
                >
                    Назад
                </button>
                <button
                    type="button"
                    :disabled="pagination.current >= pagination.last"
                    class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 disabled:opacity-40"
                    @click="go(pagination.current + 1)"
                >
                    Вперёд
                </button>
            </div>
        </div>
    </AppLayout>
</template>
