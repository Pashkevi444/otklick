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
    if (state === 'new') return 'bg-emerald-100 text-emerald-700 dark:bg-emerald-400/15 dark:text-emerald-300';
    if (state === 'updated') return 'bg-sky-100 text-sky-700 dark:bg-sky-400/15 dark:text-sky-300';
    if (state === 'maintenance') return 'bg-amber-100 text-amber-700 dark:bg-amber-400/15 dark:text-amber-300';
    return 'bg-slate-200 text-slate-500 dark:bg-white/10 dark:text-slate-300';
};

const submit = (): void => form.put('/admin/dashboard-cards', { preserveScroll: true });
</script>

<template>
    <Head title="Плашки дашборда" />

    <AppLayout title="Плашки дашборда">
        <form class="mx-auto max-w-2xl space-y-3" @submit.prevent="submit">
            <p class="rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-400/20 dark:bg-sky-400/10 dark:text-sky-100">
                Состояния плашек применяются <b>ко всем бизнесам</b> и не зависят от тарифа. «Тех. работы» серит плашку и
                закрывает раздел (прямой заход → 403).
            </p>

            <div
                v-for="c in props.cards"
                :key="c.key"
                class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-white/10 dark:bg-white/5"
            >
                <div class="flex items-center gap-2">
                    <span class="font-medium text-slate-800 dark:text-slate-100">{{ c.label }}</span>
                    <span v-if="form.states[c.key] !== 'none'" class="rounded-full px-2 py-0.5 text-[10px] font-semibold" :class="badgeClass(form.states[c.key])">
                        {{ props.stateOptions.find((o) => o.value === form.states[c.key])?.label }}
                    </span>
                </div>
                <select v-model="form.states[c.key]" class="rounded-lg border border-slate-300 px-2 py-1 text-sm dark:border-white/15 dark:bg-white/5">
                    <option v-for="o in props.stateOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
            </div>

            <button type="submit" class="rounded-lg bg-[#2E74B5] px-5 py-2 text-sm font-semibold text-white hover:bg-[#255f97] disabled:opacity-50" :disabled="form.processing">
                Сохранить
            </button>
            <span v-if="form.recentlySuccessful" class="ml-3 text-sm text-emerald-600">Сохранено ✓</span>
        </form>
    </AppLayout>
</template>
