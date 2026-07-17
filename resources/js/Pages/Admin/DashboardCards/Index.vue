<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface CardRow {
    key: string;
    label: string;
    state: string;
}
interface Option {
    value: string;
    label: string;
}

const props = defineProps<{ cards: CardRow[]; stateOptions: Option[] }>();

const form = useForm<{ states: Record<string, string> }>({
    states: Object.fromEntries(props.cards.map((c) => [c.key, c.state])),
});

const badgeClass = (state: string): string => {
    if (state === 'new') return 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400';
    if (state === 'updated') return 'bg-active text-brand';
    if (state === 'maintenance') return 'bg-[#EE8A5C]/15 text-warm';
    return 'bg-chip text-muted';
};

const submit = (): void => form.put('/admin/dashboard-cards', { preserveScroll: true });
</script>

<template>
    <Head title="Плашки дашборда" />

    <AppLayout title="Плашки дашборда">
        <form class="mx-auto max-w-2xl space-y-3" @submit.prevent="submit">
            <p class="rounded-2xl border border-brand/20 bg-active p-4 text-sm text-ink">
                Состояния плашек применяются <b>ко всем бизнесам</b> и не зависят от тарифа. «Тех. работы» серит плашку и
                закрывает раздел (прямой заход → 403).
            </p>

            <div
                v-for="c in props.cards"
                :key="c.key"
                class="flex items-center justify-between gap-3 rounded-2xl border border-line bg-panel px-4 py-3"
            >
                <div class="flex items-center gap-2">
                    <span class="font-medium text-ink">{{ c.label }}</span>
                    <span v-if="form.states[c.key] !== 'none'" class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="badgeClass(form.states[c.key])">
                        {{ props.stateOptions.find((o) => o.value === form.states[c.key])?.label }}
                    </span>
                </div>
                <select v-model="form.states[c.key]" class="rounded-xl border border-line bg-panel px-3 py-1.5 text-sm text-ink outline-none focus:border-brand">
                    <option v-for="o in props.stateOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
            </div>

            <button type="submit" class="otk-btn-primary disabled:opacity-50" :disabled="form.processing">
                Сохранить
            </button>
            <span v-if="form.recentlySuccessful" class="ml-3 text-sm text-emerald-600 dark:text-emerald-400">Сохранено ✓</span>
        </form>
    </AppLayout>
</template>
