<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface Broadcast {
    id: string;
    title: string;
    body: string;
    channels: string[];
    status: string;
    status_label: string;
    recurrence_label: string;
    next_run_at: string | null;
    last_run_at: string | null;
    sent_count: number;
    failed_count: number;
}
interface Delivery {
    id: string;
    recipient: string;
    channel: string;
    channel_label: string;
    status: string;
    error: string | null;
    at: string;
}

const props = defineProps<{ broadcast: Broadcast; deliveries: Delivery[] }>();

const fmt = (iso: string | null): string => (iso ? new Date(iso).toLocaleString('ru-RU', { dateStyle: 'short', timeStyle: 'short' }) : '—');

const sentRows = computed(() => props.deliveries.filter((d) => d.status === 'sent').length);
const failedRows = computed(() => props.deliveries.filter((d) => d.status === 'failed').length);
</script>

<template>
    <Head :title="`Рассылка — ${broadcast.title}`" />

    <AppLayout :title="broadcast.title">
        <Link href="/cabinet/broadcasts" class="mb-4 inline-block text-sm text-[#2E74B5] hover:underline dark:text-sky-300">← К рассылкам</Link>

        <!-- Шапка -->
        <div class="mb-6 rounded-xl border border-slate-200 bg-white p-6 dark:border-white/10 dark:bg-white/5">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="text-lg font-semibold text-[#1F4E79] dark:text-sky-200">{{ broadcast.title }}</div>
                    <p class="mt-1 whitespace-pre-line text-sm text-slate-500">{{ broadcast.body }}</p>
                </div>
                <span class="shrink-0 rounded-full px-2 py-0.5 text-xs" :class="broadcast.status === 'sent' ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'">
                    {{ broadcast.status_label }}
                </span>
            </div>
            <div class="mt-4 flex flex-wrap gap-1.5">
                <span v-for="c in broadcast.channels" :key="c" class="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-600 dark:bg-white/10 dark:text-slate-300">{{ c }}</span>
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-sm sm:grid-cols-4">
                <div><dt class="text-xs text-slate-400">Повтор</dt><dd>{{ broadcast.recurrence_label }}</dd></div>
                <div><dt class="text-xs text-slate-400">Последний запуск</dt><dd>{{ fmt(broadcast.last_run_at) }}</dd></div>
                <div><dt class="text-xs text-slate-400">Отправлено (всего)</dt><dd class="font-medium text-green-700">{{ broadcast.sent_count }}</dd></div>
                <div><dt class="text-xs text-slate-400">Ошибок (всего)</dt><dd class="font-medium" :class="broadcast.failed_count > 0 ? 'text-red-600' : ''">{{ broadcast.failed_count }}</dd></div>
            </dl>
        </div>

        <!-- Журнал доставки -->
        <div class="rounded-xl border border-slate-200 bg-white dark:border-white/10 dark:bg-white/5">
            <div class="flex items-center justify-between border-b border-slate-100 px-5 py-3 dark:border-white/10">
                <div class="font-semibold text-[#1F4E79] dark:text-sky-200">Журнал доставки</div>
                <div class="text-xs text-slate-400">✓ {{ sentRows }} · ✕ {{ failedRows }}</div>
            </div>

            <div v-if="deliveries.length === 0" class="p-8 text-center text-sm text-slate-400">
                Доставок пока нет — рассылка ещё не запускалась.
            </div>

            <table v-else class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-slate-400">
                        <th class="px-5 py-2 font-medium">Получатель</th>
                        <th class="px-5 py-2 font-medium">Канал</th>
                        <th class="px-5 py-2 font-medium">Статус</th>
                        <th class="px-5 py-2 font-medium">Ошибка</th>
                        <th class="px-5 py-2 font-medium">Время</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in deliveries" :key="d.id" class="border-t border-slate-100 dark:border-white/5">
                        <td class="px-5 py-2 font-medium text-slate-700 dark:text-slate-200">{{ d.recipient }}</td>
                        <td class="px-5 py-2 text-slate-500">{{ d.channel_label }}</td>
                        <td class="px-5 py-2">
                            <span
                                class="rounded-full px-2 py-0.5 text-xs"
                                :class="d.status === 'sent' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                            >
                                {{ d.status === 'sent' ? 'Доставлено' : 'Ошибка' }}
                            </span>
                        </td>
                        <td class="px-5 py-2 max-w-md whitespace-pre-wrap break-words text-xs text-red-600">{{ d.error ?? '—' }}</td>
                        <td class="px-5 py-2 text-slate-400">{{ fmt(d.at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
