<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Toggle from '@/Components/Toggle.vue';

const page = usePage();
const hasReminders = computed(() => page.props.auth.user?.tenant?.features?.reminders ?? false);

interface Field {
    key: string;
    label: string;
    secret: boolean;
    hint: string | null;
}
interface Reminders {
    enabled: boolean;
    offsets_hours: number[];
}
interface Connection {
    id: string;
    is_active: boolean;
    connected_at: string | null;
    summary: Record<string, string | null>;
    reminders: Reminders;
}
interface Integration {
    provider: string;
    label: string;
    fields: Field[];
    connection: Connection | null;
}

const props = defineProps<{ integrations: Integration[] }>();

// Локальное состояние формы напоминаний на каждое подключение.
const reminderForms: Record<string, Reminders> = reactive({});
for (const integration of props.integrations) {
    if (integration.connection) {
        reminderForms[integration.connection.id] = {
            enabled: integration.connection.reminders.enabled,
            offsets_hours: [...integration.connection.reminders.offsets_hours],
        };
    }
}

const addOffset = (id: string): void => {
    if (reminderForms[id].offsets_hours.length < 5) {
        reminderForms[id].offsets_hours.push(24);
    }
};

const removeOffset = (id: string, index: number): void => {
    reminderForms[id].offsets_hours.splice(index, 1);
};

const saveReminders = (id: string): void => {
    const form = reminderForms[id];
    router.put(
        `/cabinet/integrations/${id}/reminders`,
        { enabled: form.enabled, offsets_hours: [...form.offsets_hours] },
        { preserveScroll: true },
    );
};

const verify = (id: string): void => {
    router.post(`/cabinet/integrations/${id}/verify`);
};

const disconnect = (id: string): void => {
    if (confirm('Отключить интеграцию?')) {
        router.delete(`/cabinet/integrations/${id}`);
    }
};
</script>

<template>
    <Head title="YClients" />

    <AppLayout title="YClients">
        <p class="text-muted text-sm mb-6 max-w-2xl">
            Подключите YClients, чтобы бот мог записывать, переносить и отменять клиентов.
            Подключение — из вашего YClients (маркетплейс), без ручного ввода токенов.
        </p>

        <div class="space-y-4 max-w-2xl">
            <div
                v-for="integration in integrations"
                :key="integration.provider"
                class="otk-card p-6"
            >
                <div class="flex items-center justify-between mb-4">
                    <div class="font-display text-base font-semibold text-ink">{{ integration.label }}</div>
                    <span
                        v-if="integration.connection"
                        class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                        :class="integration.connection.is_active ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-chip text-muted'"
                    >
                        {{ integration.connection.is_active ? 'подключён' : 'отключён' }}
                    </span>
                </div>

                <!-- Подключено -->
                <div v-if="integration.connection" class="space-y-4">
                    <div class="rounded-xl border border-[#EE8A5C]/30 bg-[#EE8A5C]/10 p-3.5 text-sm text-ink">
                        <b>Важно:</b> после подключения YClients кнопка <b>«Записаться»</b> автоматически добавляется в
                        <a href="/cabinet/menu" class="underline">Главное меню бота</a>. <b>Проверьте и поправьте главное меню</b> — если у вас уже была своя кнопка «Записаться», уберите дубль.
                    </div>
                    <dl class="text-sm grid grid-cols-2 gap-2">
                        <template v-for="(value, label) in integration.connection.summary" :key="label">
                            <dt class="text-muted">{{ label }}</dt>
                            <dd class="font-medium text-ink">{{ value }}</dd>
                        </template>
                        <dt class="text-muted">Подключён</dt>
                        <dd class="font-medium text-ink">{{ integration.connection.connected_at }}</dd>
                    </dl>
                    <div class="flex flex-wrap items-center gap-3">
                        <button
                            type="button"
                            class="otk-btn-ghost"
                            @click="verify(integration.connection.id)"
                        >
                            Проверить связь
                        </button>
                        <a
                            href="/cabinet/knowledge-crm"
                            class="otk-btn-primary transition hover:-translate-y-0.5"
                        >
                            📚 База знаний из YClients
                        </a>
                        <button
                            type="button"
                            class="text-sm font-semibold text-red-600 hover:underline dark:text-red-400"
                            @click="disconnect(integration.connection.id)"
                        >
                            Отключить
                        </button>
                    </div>

                    <!-- Напоминания клиенту о записи (в рамках этой интеграции) -->
                    <div v-if="hasReminders" class="mt-5 rounded-2xl border border-line p-4">
                        <label class="flex items-center gap-2 text-sm font-semibold text-ink">
                            <Toggle v-model="reminderForms[integration.connection.id].enabled" />
                            Напоминать клиентам о записи
                        </label>
                        <p class="mt-1 text-xs text-muted2">
                            Бот напомнит клиенту о визите за указанное время. Можно добавить несколько напоминаний.
                        </p>

                        <div v-if="reminderForms[integration.connection.id].enabled" class="mt-3 space-y-2">
                            <div
                                v-for="(_, i) in reminderForms[integration.connection.id].offsets_hours"
                                :key="i"
                                class="flex items-center gap-2"
                            >
                                <span class="text-sm text-muted">За</span>
                                <input
                                    v-model.number="reminderForms[integration.connection.id].offsets_hours[i]"
                                    type="number"
                                    min="0.25"
                                    max="168"
                                    step="0.25"
                                    class="w-24 rounded-xl border border-line bg-panel px-2.5 py-1.5 text-sm text-ink outline-none focus:border-brand"
                                />
                                <span class="text-sm text-muted">ч до визита</span>
                                <button type="button" class="text-sm text-red-600 hover:underline dark:text-red-400" @click="removeOffset(integration.connection.id, i)">
                                    убрать
                                </button>
                            </div>
                            <button
                                v-if="reminderForms[integration.connection.id].offsets_hours.length < 5"
                                type="button"
                                class="text-sm font-semibold text-brand hover:underline"
                                @click="addOffset(integration.connection.id)"
                            >
                                + добавить напоминание
                            </button>
                        </div>

                        <button
                            type="button"
                            class="otk-btn-primary mt-3 transition hover:-translate-y-0.5"
                            @click="saveReminders(integration.connection.id)"
                        >
                            Сохранить напоминания
                        </button>
                    </div>
                </div>

                <!-- Не подключено -->
                <div v-else class="space-y-4">
                    <!-- Рекомендуемый путь: маркетплейс YClients (без токенов) -->
                    <div class="rounded-2xl border border-brand/30 bg-active p-4 text-sm">
                        <div class="font-semibold text-ink">Как подключить</div>
                        <ol class="mt-2 list-decimal space-y-1 pl-5 text-muted">
                            <li>Откройте свой YClients → «Интеграции» → найдите приложение «Отклик».</li>
                            <li>Нажмите «Подключить» и подтвердите доступ.</li>
                            <li>Вы вернётесь сюда — связь активируется автоматически, токены вводить не нужно.</li>
                        </ol>
                    </div>

                </div>
            </div>
        </div>
    </AppLayout>
</template>
