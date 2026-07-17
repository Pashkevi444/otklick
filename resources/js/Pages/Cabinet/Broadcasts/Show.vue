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
    contact: string | null;
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
const skippedRows = computed(() => props.deliveries.filter((d) => d.status === 'skipped').length);

const statusBadge = (status: string): { cls: string; label: string } => {
    if (status === 'sent') return { cls: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400', label: 'Доставлено' };
    if (status === 'failed') return { cls: 'bg-red-500/15 text-red-600 dark:text-red-400', label: 'Ошибка' };
    return { cls: 'bg-chip text-muted', label: 'Пропущен' };
};
</script>

<template>
    <Head :title="`Рассылка — ${broadcast.title}`" />

    <AppLayout :title="broadcast.title">
        <Link href="/cabinet/broadcasts" class="mb-4 inline-block text-sm font-semibold text-brand hover:underline">← К рассылкам</Link>

        <!-- Шапка -->
        <div class="otk-card mb-6 p-6">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="font-display text-lg font-semibold text-ink">{{ broadcast.title }}</div>
                    <p class="mt-1 whitespace-pre-line text-sm text-muted">{{ broadcast.body }}</p>
                </div>
                <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="broadcast.status === 'sent' ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-chip text-muted'">
                    {{ broadcast.status_label }}
                </span>
            </div>
            <div class="mt-4 flex flex-wrap gap-1.5">
                <span v-for="c in broadcast.channels" :key="c" class="rounded-full bg-chip px-2 py-0.5 text-xs text-muted">{{ c }}</span>
            </div>
            <dl class="mt-4 grid grid-cols-2 gap-x-6 gap-y-2 text-sm text-ink sm:grid-cols-4">
                <div><dt class="text-xs text-muted2">Повтор</dt><dd>{{ broadcast.recurrence_label }}</dd></div>
                <div><dt class="text-xs text-muted2">Последний запуск</dt><dd>{{ fmt(broadcast.last_run_at) }}</dd></div>
                <div><dt class="text-xs text-muted2">Отправлено (всего)</dt><dd class="font-medium text-emerald-600 dark:text-emerald-400">{{ broadcast.sent_count }}</dd></div>
                <div><dt class="text-xs text-muted2">Ошибок (всего)</dt><dd class="font-medium" :class="broadcast.failed_count > 0 ? 'text-red-600 dark:text-red-400' : ''">{{ broadcast.failed_count }}</dd></div>
            </dl>
        </div>

        <!-- Журнал доставки -->
        <div class="otk-card overflow-x-auto">
            <div class="flex items-center justify-between border-b border-line px-5 py-3">
                <div class="font-display text-base font-semibold text-ink">Журнал доставки</div>
                <div class="text-xs text-muted2">✓ {{ sentRows }} · ✕ {{ failedRows }} · ⊘ {{ skippedRows }}</div>
            </div>

            <div v-if="deliveries.length === 0" class="p-8 text-center text-sm text-muted2">
                Доставок пока нет — рассылка ещё не запускалась.
            </div>

            <table v-else class="w-full min-w-[520px] text-sm">
                <thead>
                    <tr class="text-left text-[11px] font-bold uppercase tracking-[0.09em] text-muted2">
                        <th class="px-5 py-2.5">Получатель</th>
                        <th class="px-5 py-2.5">Канал</th>
                        <th class="px-5 py-2.5">Статус</th>
                        <th class="px-5 py-2.5">Ошибка</th>
                        <th class="px-5 py-2.5">Время</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="d in deliveries" :key="d.id" class="border-t border-line hover:bg-hoverbg">
                        <td class="px-5 py-2">
                            <div class="font-medium text-ink">{{ d.recipient }}</div>
                            <div v-if="d.contact" class="text-xs text-muted2">{{ d.contact }}</div>
                        </td>
                        <td class="px-5 py-2 text-muted">{{ d.channel_label }}</td>
                        <td class="px-5 py-2">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="statusBadge(d.status).cls">{{ statusBadge(d.status).label }}</span>
                        </td>
                        <td class="px-5 py-2 max-w-md whitespace-pre-wrap break-words text-xs" :class="d.status === 'failed' ? 'text-red-600 dark:text-red-400' : 'text-muted2'">{{ d.error ?? '—' }}</td>
                        <td class="px-5 py-2 text-muted2">{{ fmt(d.at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AppLayout>
</template>
