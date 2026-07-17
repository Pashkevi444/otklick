<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';
import Toggle from '@/Components/Toggle.vue';

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
            return 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400';
        case 'scheduled':
            return 'bg-active text-brand';
        case 'sending':
            return 'bg-[#EE8A5C]/15 text-warm';
        case 'failed':
            return 'bg-red-500/15 text-red-600 dark:text-red-400';
        case 'canceled':
            return 'bg-chip text-muted';
        default:
            return 'bg-chip text-muted';
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
        <p class="mb-6 max-w-2xl text-sm text-muted">
            Отправляйте сообщения вашей базе клиентов по мессенджерам и почте — вручную или по расписанию.
            В аудитории сейчас <strong>{{ audienceCount }}</strong> клиент(ов) (без отписавшихся).
        </p>

        <div class="grid gap-6 lg:grid-cols-5">
            <!-- Форма создания -->
            <form class="otk-card space-y-4 p-6 lg:col-span-2" @submit.prevent="submit">
                <div class="font-display text-base font-semibold text-ink">Новая рассылка</div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Заголовок</label>
                    <input
                        v-model="form.title"
                        type="text"
                        maxlength="200"
                        class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none placeholder:text-muted2 focus:border-brand"
                        placeholder="Напр. Акция недели"
                    />
                    <p v-if="form.errors.title" class="mt-1 text-sm text-red-600">{{ form.errors.title }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Текст сообщения</label>
                    <textarea
                        v-model="form.body"
                        rows="5"
                        maxlength="4000"
                        class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none placeholder:text-muted2 focus:border-brand"
                        placeholder="Текст, который получат клиенты"
                    ></textarea>
                    <p v-if="form.errors.body" class="mt-1 text-sm text-red-600">{{ form.errors.body }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Каналы</label>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="c in channelOptions"
                            :key="c.value"
                            type="button"
                            class="rounded-full border px-3.5 py-1.5 text-sm font-semibold transition"
                            :class="form.channels.includes(c.value) ? 'border-brand bg-brand text-white' : 'border-line bg-panel text-muted hover:bg-hoverbg'"
                            @click="toggleChannel(c.value)"
                        >
                            {{ c.label }}
                        </button>
                    </div>
                    <p v-if="form.errors.channels" class="mt-1 text-sm text-red-600">{{ form.errors.channels }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Кому</label>
                    <div class="flex gap-4 text-sm text-ink">
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
                            class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none placeholder:text-muted2 focus:border-brand"
                        />
                        <div class="mt-2 max-h-48 overflow-y-auto rounded-xl border border-line">
                            <label
                                v-for="c in filteredClients"
                                :key="c.id"
                                class="flex items-center gap-2 border-b border-line px-3 py-1.5 text-sm last:border-0 hover:bg-hoverbg"
                                :class="c.opted_out ? 'opacity-50' : ''"
                            >
                                <Toggle :model-value="form.client_ids.includes(c.id)" @update:model-value="toggleClient(c.id)" />
                                <span class="font-medium text-ink">{{ c.name }}</span>
                                <span class="text-muted2">{{ c.phone }}</span>
                                <span v-if="c.opted_out" class="ml-auto text-xs text-red-600 dark:text-red-400">отписан</span>
                            </label>
                            <div v-if="filteredClients.length === 0" class="px-3 py-2 text-xs text-muted2">Никого не найдено</div>
                        </div>
                        <p class="mt-1 text-xs text-muted2">Выбрано: {{ form.client_ids.length }}. Отписавшиеся не получат рассылку, даже если выбраны.</p>
                        <p v-if="form.errors.client_ids" class="mt-1 text-sm text-red-600">{{ form.errors.client_ids }}</p>
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Когда отправить</label>
                    <div class="flex gap-4 text-sm text-ink">
                        <label class="flex items-center gap-2">
                            <input v-model="form.mode" type="radio" value="now" /> Сейчас
                        </label>
                        <label class="flex items-center gap-2">
                            <input v-model="form.mode" type="radio" value="schedule" /> По расписанию
                        </label>
                    </div>
                </div>

                <div v-if="form.mode === 'schedule'">
                    <label class="mb-1 block text-sm font-medium text-ink">Дата и время старта</label>
                    <input
                        v-model="form.scheduled_at"
                        type="datetime-local"
                        class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none placeholder:text-muted2 focus:border-brand"
                    />
                    <p v-if="form.errors.scheduled_at" class="mt-1 text-sm text-red-600">{{ form.errors.scheduled_at }}</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-ink">Повтор</label>
                    <select v-model="form.recurrence" class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none focus:border-brand">
                        <option v-for="r in recurrenceOptions" :key="r.value" :value="r.value">{{ r.label }}</option>
                    </select>
                </div>

                <button
                    type="submit"
                    :disabled="form.processing || !canSubmit"
                    class="otk-btn-primary w-full disabled:opacity-50"
                >
                    {{ form.mode === 'schedule' ? 'Запланировать' : 'Запустить сейчас' }}
                </button>
            </form>

            <!-- Список рассылок -->
            <div class="space-y-3 lg:col-span-3">
                <div v-if="broadcasts.length === 0" class="rounded-2xl border border-dashed border-line p-8 text-center text-sm text-muted2">
                    Рассылок пока нет. Создайте первую слева.
                </div>

                <div v-for="b in broadcasts" :key="b.id" class="otk-card p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#EE8A5C]/15 text-warm">
                                <Icon name="send" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <div class="font-semibold text-ink">{{ b.title }}</div>
                                <p class="mt-1 line-clamp-2 text-sm text-muted">{{ b.body }}</p>
                            </div>
                        </div>
                        <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="statusClass(b.status)">{{ b.status_label }}</span>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <span v-for="c in b.channels" :key="c" class="rounded-full bg-chip px-2 py-0.5 text-xs text-muted">{{ channelLabel(c) }}</span>
                    </div>

                    <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-1 text-xs text-muted sm:grid-cols-4">
                        <div><dt class="text-muted2">Повтор</dt><dd>{{ b.recurrence_label }}</dd></div>
                        <div><dt class="text-muted2">След. запуск</dt><dd>{{ fmt(b.next_run_at) }}</dd></div>
                        <div><dt class="text-muted2">Отправлено</dt><dd class="font-medium text-emerald-600 dark:text-emerald-400">{{ b.sent_count }}</dd></div>
                        <div><dt class="text-muted2">Ошибок</dt><dd class="font-medium" :class="b.failed_count > 0 ? 'text-red-600 dark:text-red-400' : ''">{{ b.failed_count }}</dd></div>
                    </dl>

                    <div class="mt-4 flex flex-wrap items-center gap-3 text-sm">
                        <button v-if="!b.is_scheduled" type="button" class="otk-btn-primary" @click="run(b)">
                            Запустить сейчас
                        </button>
                        <button v-if="b.is_scheduled" type="button" class="otk-btn-ghost" @click="cancel(b)">
                            Снять с расписания
                        </button>
                        <Link :href="`/cabinet/broadcasts/${b.id}`" class="font-semibold text-brand hover:underline">Отчёт</Link>
                        <button type="button" class="text-red-600 hover:underline dark:text-red-400" @click="destroy(b)">Удалить</button>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
