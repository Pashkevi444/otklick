<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

interface Broadcast {
    id: string;
    title: string;
    body: string;
    channels: string[];
    status: string;
    status_label: string;
    recurrence: string;
    recurrence_label: string;
    scheduled_at: string | null;
    next_run_at: string | null;
    last_run_at: string | null;
    sent_count: number;
    failed_count: number;
    is_scheduled: boolean;
}
interface Option {
    value: string;
    label: string;
}
interface ClientPick {
    id: string;
    name: string;
    phone: string | null;
    opted_out: boolean;
}

const props = defineProps<{
    broadcasts: Broadcast[];
    audienceCount: number;
    clients: ClientPick[];
    channelOptions: Option[];
    recurrenceOptions: Option[];
}>();

const form = useForm({
    title: '',
    body: '',
    channels: [] as string[],
    mode: 'now',
    scheduled_at: '',
    recurrence: 'none',
    audience: 'all',
    client_ids: [] as string[],
});

const clientSearch = ref('');
const filteredClients = computed(() => {
    const q = clientSearch.value.trim().toLowerCase();
    if (!q) return props.clients;
    return props.clients.filter((c) => c.name.toLowerCase().includes(q) || (c.phone ?? '').toLowerCase().includes(q));
});

const toggleClient = (id: string): void => {
    const i = form.client_ids.indexOf(id);
    if (i === -1) form.client_ids.push(id);
    else form.client_ids.splice(i, 1);
};

const toggleChannel = (value: string): void => {
    const i = form.channels.indexOf(value);
    if (i === -1) form.channels.push(value);
    else form.channels.splice(i, 1);
};

const submit = (): void => {
    form.post('/cabinet/broadcasts', {
        preserveScroll: true,
        onSuccess: () => form.reset(),
    });
};

const run = (b: Broadcast): void => {
    if (confirm(`Запустить рассылку «${b.title}» сейчас?`)) {
        router.post(`/cabinet/broadcasts/${b.id}/run`, {}, { preserveScroll: true });
    }
};

const cancel = (b: Broadcast): void => {
    router.post(`/cabinet/broadcasts/${b.id}/cancel`, {}, { preserveScroll: true });
};

const destroy = (b: Broadcast): void => {
    if (confirm('Удалить рассылку?')) {
        router.delete(`/cabinet/broadcasts/${b.id}`, { preserveScroll: true });
    }
};

const channelLabel = (value: string): string => props.channelOptions.find((c) => c.value === value)?.label ?? value;

const fmt = (iso: string | null): string => {
    if (!iso) return '—';
    return new Date(iso).toLocaleString('ru-RU', { dateStyle: 'short', timeStyle: 'short' });
};

const statusClass = (status: string): string => {
    switch (status) {
        case 'sent':
            return 'bg-green-100 text-green-700';
        case 'scheduled':
            return 'bg-blue-100 text-blue-700';
        case 'sending':
            return 'bg-amber-100 text-amber-700';
        case 'failed':
            return 'bg-red-100 text-red-700';
        case 'canceled':
            return 'bg-slate-200 text-slate-500';
        default:
            return 'bg-slate-100 text-slate-500';
    }
};

const canSubmit = computed(
    () =>
        form.title.trim() !== '' &&
        form.body.trim() !== '' &&
        form.channels.length > 0 &&
        (form.audience === 'all' || form.client_ids.length > 0),
);
</script>

<template>
    <Head title="Рассылки" />

    <AppLayout title="Рассылки">
        <p class="mb-6 max-w-2xl text-sm text-slate-500">
            Отправляйте сообщения вашей базе клиентов по мессенджерам и почте — вручную или по расписанию.
            В аудитории сейчас <strong>{{ audienceCount }}</strong> клиент(ов) (без отписавшихся).
        </p>

        <div class="grid gap-6 lg:grid-cols-5">
            <!-- Форма создания -->
            <form class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 lg:col-span-2" @submit.prevent="submit">
                <div class="font-semibold text-[#1F4E79]">Новая рассылка</div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Заголовок</label>
                    <input
                        v-model="form.title"
                        type="text"
                        maxlength="200"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                        placeholder="Напр. Акция недели"
                    />
                    <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Текст сообщения</label>
                    <textarea
                        v-model="form.body"
                        rows="5"
                        maxlength="4000"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                        placeholder="Текст, который получат клиенты"
                    ></textarea>
                    <p v-if="form.errors.body" class="mt-1 text-sm text-red-600">{{ form.errors.body }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Каналы</label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="c in channelOptions"
                            :key="c.value"
                            type="button"
                            class="rounded-lg border px-3 py-1.5 text-sm transition"
                            :class="form.channels.includes(c.value) ? 'border-[#2E74B5] bg-[#2E74B5] text-white' : 'border-slate-300 text-slate-600 hover:bg-slate-50'"
                            @click="toggleChannel(c.value)"
                        >
                            {{ c.label }}
                        </button>
                    </div>
                    <p v-if="form.errors.channels" class="mt-1 text-sm text-red-600">{{ form.errors.channels }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Кому</label>
                    <div class="flex gap-4 text-sm">
                        <label class="flex items-center gap-2">
                            <input v-model="form.audience" type="radio" value="all" /> Вся база ({{ audienceCount }})
                        </label>
                        <label class="flex items-center gap-2">
                            <input v-model="form.audience" type="radio" value="selected" /> Выбрать из базы
                        </label>
                    </div>

                    <div v-if="form.audience === 'selected'" class="mt-2">
                        <input
                            v-model="clientSearch"
                            type="text"
                            placeholder="Поиск по имени или телефону"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                        />
                        <div class="mt-2 max-h-48 overflow-y-auto rounded-lg border border-slate-200">
                            <label
                                v-for="c in filteredClients"
                                :key="c.id"
                                class="flex items-center gap-2 border-b border-slate-100 px-3 py-1.5 text-sm last:border-0"
                                :class="c.opted_out ? 'opacity-50' : ''"
                            >
                                <input type="checkbox" :checked="form.client_ids.includes(c.id)" @change="toggleClient(c.id)" />
                                <span class="font-medium text-slate-700">{{ c.name }}</span>
                                <span class="text-slate-400">{{ c.phone }}</span>
                                <span v-if="c.opted_out" class="ml-auto text-xs text-red-500">отписан</span>
                            </label>
                            <div v-if="filteredClients.length === 0" class="px-3 py-2 text-xs text-slate-400">Никого не найдено</div>
                        </div>
                        <p class="mt-1 text-xs text-slate-400">Выбрано: {{ form.client_ids.length }}. Отписавшиеся не получат рассылку, даже если выбраны.</p>
                        <p v-if="form.errors.client_ids" class="mt-1 text-sm text-red-600">{{ form.errors.client_ids }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Когда отправить</label>
                    <div class="flex gap-4 text-sm">
                        <label class="flex items-center gap-2">
                            <input v-model="form.mode" type="radio" value="now" /> Сейчас
                        </label>
                        <label class="flex items-center gap-2">
                            <input v-model="form.mode" type="radio" value="schedule" /> По расписанию
                        </label>
                    </div>
                </div>

                <div v-if="form.mode === 'schedule'">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Дата и время старта</label>
                    <input
                        v-model="form.scheduled_at"
                        type="datetime-local"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2"
                    />
                    <p v-if="form.errors.scheduled_at" class="mt-1 text-sm text-red-600">{{ form.errors.scheduled_at }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Повтор</label>
                    <select v-model="form.recurrence" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option v-for="r in recurrenceOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
                    </select>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing || !canSubmit"
                    class="w-full rounded-lg bg-[#2E74B5] py-2.5 font-medium text-white hover:bg-[#255f96] disabled:opacity-50"
                >
                    {{ form.mode === 'schedule' ? 'Запланировать' : 'Запустить сейчас' }}
                </button>
            </form>

            <!-- Список рассылок -->
            <div class="space-y-3 lg:col-span-3">
                <div v-if="broadcasts.length === 0" class="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-400">
                    Рассылок пока нет. Создайте первую слева.
                </div>

                <div v-for="b in broadcasts" :key="b.id" class="rounded-xl border border-slate-200 bg-white p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-semibold text-[#1F4E79]">{{ b.title }}</div>
                            <p class="mt-1 line-clamp-2 text-sm text-slate-500">{{ b.body }}</p>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-xs" :class="statusClass(b.status)">{{ b.status_label }}</span>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <span v-for="c in b.channels" :key="c" class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600">{{ channelLabel(c) }}</span>
                    </div>

                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-slate-500 sm:grid-cols-4">
                        <div><dt class="text-slate-400">Повтор</dt><dd>{{ b.recurrence_label }}</dd></div>
                        <div><dt class="text-slate-400">След. запуск</dt><dd>{{ fmt(b.next_run_at) }}</dd></div>
                        <div><dt class="text-slate-400">Отправлено</dt><dd class="font-medium text-green-700">{{ b.sent_count }}</dd></div>
                        <div><dt class="text-slate-400">Ошибок</dt><dd class="font-medium" :class="b.failed_count > 0 ? 'text-red-600' : ''">{{ b.failed_count }}</dd></div>
                    </dl>

                    <div class="mt-4 flex flex-wrap items-center gap-3 text-sm">
                        <button v-if="!b.is_scheduled" type="button" class="rounded-lg bg-[#2E74B5] px-3 py-1.5 font-medium text-white hover:bg-[#255f96]" @click="run(b)">
                            Запустить сейчас
                        </button>
                        <button v-if="b.is_scheduled" type="button" class="rounded-lg border border-slate-300 px-3 py-1.5 text-slate-700 hover:bg-slate-50" @click="cancel(b)">
                            Снять с расписания
                        </button>
                        <Link :href="`/cabinet/broadcasts/${b.id}`" class="text-[#2E74B5] hover:underline dark:text-sky-300">Отчёт</Link>
                        <button type="button" class="text-red-600 hover:underline" @click="destroy(b)">Удалить</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
