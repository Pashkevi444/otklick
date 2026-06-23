<script setup lang="ts">
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Единая пагинация со страничками (1 … 4 5 6 … 20) + стрелки. Навигирует по
 * текущему URL, меняя только параметр `page` и сохраняя остальные (фильтры/поиск)
 * — поэтому работает в любом разделе без передачи маршрута.
 */
const props = withDefaults(
    defineProps<{
        current: number;
        last: number;
        total?: number;
        from?: number;
        to?: number;
        preserveState?: boolean;
    }>(),
    { preserveState: true },
);

const go = (page: number): void => {
    if (page < 1 || page > props.last || page === props.current) {
        return;
    }
    const url = new URL(window.location.href);
    url.searchParams.set('page', String(page));
    router.get(url.pathname + url.search, {}, { preserveScroll: true, preserveState: props.preserveState });
};

// Окно вокруг текущей страницы + первая/последняя, с многоточиями.
const items = computed<(number | '…')[]>(() => {
    const keep = new Set<number>([1, props.last]);
    for (let p = props.current - 2; p <= props.current + 2; p++) {
        if (p >= 1 && p <= props.last) keep.add(p);
    }
    const sorted = [...keep].sort((a, b) => a - b);
    const out: (number | '…')[] = [];
    let prev = 0;
    for (const p of sorted) {
        if (prev && p - prev > 1) out.push('…');
        out.push(p);
        prev = p;
    }
    return out;
});

const hasRange = computed(
    () => props.total !== undefined && props.from !== undefined && props.to !== undefined,
);
</script>

<template>
    <div v-if="last > 1" class="mt-5 flex flex-col items-center justify-between gap-3 sm:flex-row">
        <div v-if="hasRange" class="text-sm text-slate-400">Показано {{ from }}–{{ to }} из {{ total }}</div>
        <div v-else class="hidden sm:block"></div>

        <div class="flex flex-wrap items-center justify-center gap-1">
            <button
                type="button"
                :disabled="current <= 1"
                class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-600 transition hover:bg-slate-50 disabled:opacity-40 dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                @click="go(current - 1)"
            >
                ←
            </button>

            <template v-for="(p, i) in items" :key="i">
                <span v-if="p === '…'" class="px-1.5 text-sm text-slate-400">…</span>
                <button
                    v-else
                    type="button"
                    class="min-w-9 rounded-lg px-3 py-1.5 text-sm font-medium transition"
                    :class="p === current
                        ? 'bg-[#2E74B5] text-white'
                        : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:bg-white/5 dark:text-slate-300'"
                    @click="go(p)"
                >
                    {{ p }}
                </button>
            </template>

            <button
                type="button"
                :disabled="current >= last"
                class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm text-slate-600 transition hover:bg-slate-50 disabled:opacity-40 dark:border-white/10 dark:bg-white/5 dark:text-slate-300"
                @click="go(current + 1)"
            >
                →
            </button>
        </div>
    </div>
</template>
