<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

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

// Форма подключения на каждый ещё не подключённый провайдер.
const forms: Record<string, ReturnType<typeof useForm>> = {};
for (const integration of props.integrations) {
    if (integration.connection === null) {
        const credentials: Record<string, string> = {};
        integration.fields.forEach((f) => (credentials[f.key] = ''));
        forms[integration.provider] = useForm({ credentials });
    }
}

const connect = (provider: string): void => {
    forms[provider].post(`/cabinet/integrations/connect/${provider}`);
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
    <Head title="Интеграции" />

    <AppLayout title="Интеграции">
        <p class="text-slate-500 text-sm mb-6 max-w-2xl">
            Подключите CRM, чтобы бот мог записывать клиентов.
        </p>

        <div class="space-y-4 max-w-2xl">
            <div
                v-for="integration in integrations"
                :key="integration.provider"
                class="bg-white rounded-xl border border-slate-200 p-6"
            >
                <div class="flex items-center justify-between mb-4">
                    <div class="font-semibold text-[#1F4E79]">{{ integration.label }}</div>
                    <span
                        v-if="integration.connection"
                        class="text-xs rounded-full px-2 py-0.5"
                        :class="integration.connection.is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'"
                    >
                        {{ integration.connection.is_active ? 'подключён' : 'отключён' }}
                    </span>
                </div>

                <!-- Подключено -->
                <div v-if="integration.connection" class="space-y-4">
                    <dl class="text-sm grid grid-cols-2 gap-2">
                        <template v-for="(value, label) in integration.connection.summary" :key="label">
                            <dt class="text-slate-500">{{ label }}</dt>
                            <dd class="font-medium">{{ value }}</dd>
                        </template>
                        <dt class="text-slate-500">Подключён</dt>
                        <dd class="font-medium">{{ integration.connection.connected_at }}</dd>
                    </dl>
                    <div class="flex flex-wrap items-center gap-3">
                        <button
                            type="button"
                            class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                            @click="verify(integration.connection.id)"
                        >
                            Проверить связь
                        </button>
                        <a
                            href="/cabinet/knowledge-crm"
                            class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white transition hover:-translate-y-0.5"
                        >
                            📚 База знаний из CRM
                        </a>
                        <button
                            type="button"
                            class="text-sm text-red-600 hover:underline"
                            @click="disconnect(integration.connection.id)"
                        >
                            Отключить
                        </button>
                    </div>

                    <!-- Напоминания клиенту о записи (в рамках этой интеграции) -->
                    <div class="mt-5 rounded-xl border border-slate-200 p-4 dark:border-white/10">
                        <label class="flex items-center gap-2 text-sm font-medium text-[#1F4E79] dark:text-sky-200">
                            <input v-model="reminderForms[integration.connection.id].enabled" type="checkbox" class="rounded" />
                            Напоминать клиентам о записи
                        </label>
                        <p class="mt-1 text-xs text-slate-400">
                            Бот напомнит клиенту о визите за указанное время. Можно добавить несколько напоминаний.
                        </p>

                        <div v-if="reminderForms[integration.connection.id].enabled" class="mt-3 space-y-2">
                            <div
                                v-for="(_, i) in reminderForms[integration.connection.id].offsets_hours"
                                :key="i"
                                class="flex items-center gap-2"
                            >
                                <span class="text-sm text-slate-500">За</span>
                                <input
                                    v-model.number="reminderForms[integration.connection.id].offsets_hours[i]"
                                    type="number"
                                    min="0.25"
                                    max="168"
                                    step="0.25"
                                    class="w-24 rounded-lg border border-slate-300 px-2 py-1 text-sm"
                                />
                                <span class="text-sm text-slate-500">ч до визита</span>
                                <button type="button" class="text-sm text-red-600 hover:underline" @click="removeOffset(integration.connection.id, i)">
                                    убрать
                                </button>
                            </div>
                            <button
                                v-if="reminderForms[integration.connection.id].offsets_hours.length < 5"
                                type="button"
                                class="text-sm text-[#2E74B5] hover:underline"
                                @click="addOffset(integration.connection.id)"
                            >
                                + добавить напоминание
                            </button>
                        </div>

                        <button
                            type="button"
                            class="mt-3 rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white transition hover:-translate-y-0.5"
                            @click="saveReminders(integration.connection.id)"
                        >
                            Сохранить напоминания
                        </button>
                    </div>
                </div>

                <!-- Не подключено -->
                <form v-else class="space-y-4" @submit.prevent="connect(integration.provider)">
                    <div v-for="field in integration.fields" :key="field.key">
                        <label class="block text-sm font-medium text-slate-700 mb-1">{{ field.label }}</label>
                        <input
                            v-model="forms[integration.provider].credentials[field.key]"
                            :type="field.secret ? 'password' : 'text'"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2"
                        />
                        <p v-if="field.hint" class="mt-1 text-xs text-slate-400">{{ field.hint }}</p>
                        <p
                            v-if="forms[integration.provider].errors[`credentials.${field.key}`]"
                            class="mt-1 text-sm text-red-600"
                        >
                            {{ forms[integration.provider].errors[`credentials.${field.key}`] }}
                        </p>
                    </div>
                    <button
                        type="submit"
                        :disabled="forms[integration.provider].processing"
                        class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50"
                    >
                        Подключить
                    </button>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
