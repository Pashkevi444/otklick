<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const open = (id: string): void => {
    router.visit(`/cabinet/conversations/${id}`);
};

interface Row {
    id: string;
    contact: string;
    channel: string;
    status: string;
    statusLabel: string;
    messagesCount: number;
    lastMessage: string | null;
    lastMessageAt: string | null;
}

defineProps<{ conversations: Row[] }>();

const statusClass = (s: string): string =>
    s === 'needs_human'
        ? 'bg-amber-100 text-amber-700'
        : s === 'closed'
          ? 'bg-slate-100 text-slate-500'
          : 'bg-green-100 text-green-700';

const initials = (name: string): string =>
    name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((w) => w[0].toUpperCase())
        .join('');
</script>

<template>
    <Head title="Диалоги" />

    <AppLayout title="Диалоги">
        <p class="mb-5 max-w-2xl text-sm text-slate-500">
            Журнал переписок бота с клиентами — 100% диалогов сохраняется здесь. Нажмите на клиента, чтобы открыть переписку.
        </p>

        <div v-if="conversations.length === 0" class="rounded-xl border border-slate-200 bg-white p-10 text-center text-slate-400">
            Пока нет ни одного диалога. Как только клиент напишет боту — переписка появится здесь.
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
                        <span class="flex-none rounded-full px-2 py-0.5 text-xs" :class="statusClass(c.status)">{{ c.statusLabel }}</span>
                    </div>
                    <p class="mt-1 truncate text-sm text-slate-500">{{ c.lastMessage ?? '—' }}</p>
                    <div class="mt-1 flex justify-between text-xs text-slate-400">
                        <span>{{ c.channel }} · {{ c.messagesCount }} сообщ.</span>
                        <span>{{ c.lastMessageAt }}</span>
                    </div>
                </Link>
            </div>

            <!-- Таблица (десктоп) -->
            <div class="hidden overflow-hidden rounded-xl border border-slate-200 bg-white md:block">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-5 py-3 font-medium">Клиент</th>
                            <th class="px-5 py-3 font-medium">Канал</th>
                            <th class="px-5 py-3 font-medium">Последнее сообщение</th>
                            <th class="px-5 py-3 font-medium">Сообщений</th>
                            <th class="px-5 py-3 font-medium">Статус</th>
                            <th class="px-5 py-3 font-medium">Обновлён</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr
                            v-for="c in conversations"
                            :key="c.id"
                            class="cursor-pointer transition hover:bg-slate-50"
                            @click="open(c.id)"
                        >
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 flex-none items-center justify-center rounded-full bg-[#EAF2FB] text-xs font-semibold text-[#1F4E79]">{{ initials(c.contact) }}</span>
                                    <span class="font-medium text-slate-800">{{ c.contact }}</span>
                                </div>
                            </td>
                            <td class="px-5 py-3 text-slate-500">{{ c.channel }}</td>
                            <td class="max-w-xs truncate px-5 py-3 text-slate-500">{{ c.lastMessage ?? '—' }}</td>
                            <td class="px-5 py-3 text-slate-500">{{ c.messagesCount }}</td>
                            <td class="px-5 py-3">
                                <span class="rounded-full px-2 py-0.5 text-xs" :class="statusClass(c.status)">{{ c.statusLabel }}</span>
                            </td>
                            <td class="whitespace-nowrap px-5 py-3 text-slate-400">{{ c.lastMessageAt }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>
    </AppLayout>
</template>
