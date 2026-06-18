<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { useCan } from '@/composables/useCan';

interface Client {
    id: string;
    name: string | null;
    phone: string | null;
    email: string | null;
    telegram_username: string | null;
    channel: string | null;
    first_seen_at: string | null;
    last_seen_at: string | null;
    summary: string | null;
    summary_generated_at: string | null;
    notes: string | null;
}
interface ConversationRow {
    id: string;
    channel: string;
    outcome: string;
    booked: boolean;
    created_at: string | null;
}

const props = defineProps<{ client: Client; conversations: ConversationRow[] }>();

const form = useForm({
    name: props.client.name ?? '',
    phone: props.client.phone ?? '',
    email: props.client.email ?? '',
    telegram_username: props.client.telegram_username ?? '',
    notes: props.client.notes ?? '',
});

const save = (): void => {
    form.put(`/cabinet/clients/${props.client.id}`, { preserveScroll: true });
};

const refreshSummary = (): void => {
    router.post(`/cabinet/clients/${props.client.id}/summary`, {}, { preserveScroll: true });
};

const remove = (): void => {
    if (confirm('Удалить карточку клиента? Связанные диалоги останутся.')) {
        router.delete(`/cabinet/clients/${props.client.id}`);
    }
};

const can = useCan();
</script>

<template>
    <Head :title="client.name || 'Клиент'" />

    <AppLayout :title="client.name || 'Карточка клиента'">
        <div class="mb-4">
            <Link href="/cabinet/clients" class="text-sm text-[#2E74B5] hover:underline">← К базе клиентов</Link>
        </div>

        <div class="grid gap-5 lg:grid-cols-3">
            <!-- Карточка (редактируемые поля) -->
            <form class="lg:col-span-2 rounded-xl border border-slate-200 bg-white p-6 space-y-4" @submit.prevent="save">
                <div class="font-semibold text-[#1F4E79]">Карточка клиента</div>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Имя</label>
                        <input v-model="form.name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Телефон</label>
                        <input v-model="form.phone" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        <p v-if="form.errors.phone" class="mt-1 text-xs text-red-600">{{ form.errors.phone }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Email</label>
                        <input v-model="form.email" type="email" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-600">{{ form.errors.email }}</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-500 mb-1">Ник Telegram</label>
                        <input v-model="form.telegram_username" type="text" placeholder="без @" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-500 mb-1">Заметки</label>
                    <textarea v-model="form.notes" rows="3" placeholder="Что важно помнить об этом клиенте" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" />
                </div>
                <div class="flex items-center justify-between">
                    <button v-if="can('clients.edit')" type="submit" :disabled="form.processing" class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50">Сохранить</button>
                    <span v-else class="text-xs text-slate-400">Только просмотр</span>
                    <button v-if="can('clients.delete')" type="button" class="text-sm text-red-600 hover:underline" @click="remove">Удалить клиента</button>
                </div>
            </form>

            <!-- Сводка -->
            <div class="space-y-4">
                <div class="rounded-xl border border-slate-200 bg-white p-5 text-sm">
                    <div class="font-semibold text-[#1F4E79] mb-2">Откуда и когда</div>
                    <dl class="space-y-1.5 text-slate-600">
                        <div class="flex justify-between gap-2"><dt class="text-slate-400">Первый канал</dt><dd>{{ client.channel || '—' }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-slate-400">Впервые</dt><dd>{{ client.first_seen_at || '—' }}</dd></div>
                        <div class="flex justify-between gap-2"><dt class="text-slate-400">Последний контакт</dt><dd>{{ client.last_seen_at || '—' }}</dd></div>
                    </dl>
                </div>

                <div class="rounded-xl border border-[#2E74B5]/30 bg-gradient-to-br from-[#EAF2FB] to-white p-5 dark:border-sky-400/20 dark:bg-none dark:bg-white/5">
                    <div class="flex items-center justify-between mb-2">
                        <div class="font-semibold text-[#1F4E79] dark:text-sky-200">🧠 Краткое резюме</div>
                        <button v-if="can('clients.edit')" type="button" class="text-xs text-[#2E74B5] hover:underline dark:text-sky-300" @click="refreshSummary">Обновить</button>
                    </div>
                    <p v-if="client.summary" class="text-sm text-slate-600 whitespace-pre-line dark:text-slate-300">{{ client.summary }}</p>
                    <p v-else class="text-sm text-slate-400">Резюме появится после переписки клиента с ботом — или нажмите «Обновить».</p>
                    <p v-if="client.summary_generated_at" class="mt-2 text-[11px] text-slate-400">Обновлено: {{ client.summary_generated_at }}</p>
                </div>
            </div>
        </div>

        <!-- Лиды клиента -->
        <div class="mt-6 rounded-xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5">
            <div class="font-semibold text-[#1F4E79] mb-3 dark:text-sky-200">Лиды клиента ({{ conversations.length }})</div>
            <div v-if="conversations.length === 0" class="text-sm text-slate-400 py-4 text-center">Лидов пока нет.</div>
            <div v-else class="divide-y divide-slate-100">
                <div
                    v-for="conv in conversations"
                    :key="conv.id"
                    class="flex cursor-pointer items-center justify-between gap-3 py-2.5 transition hover:bg-slate-50"
                    @click="router.get(`/cabinet/conversations/${conv.id}`)"
                >
                    <div class="text-sm text-slate-600">{{ conv.channel }} · {{ conv.outcome }}<span v-if="conv.booked" class="ml-1">✅</span></div>
                    <div class="text-xs text-slate-400">{{ conv.created_at }}</div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
