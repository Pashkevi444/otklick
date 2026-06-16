<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

interface Slice {
    key: string;
    label: string;
    value: number;
    pct: number;
    color: string;
}

const props = withDefaults(defineProps<{ slices: Slice[]; centerLabel?: string; centerValue?: number | string }>(), {
    centerLabel: '',
    centerValue: '',
});

const R = 54;
const C = 2 * Math.PI * R;
const shown = ref(false);

const total = computed<number>(() => props.slices.reduce((s, x) => s + x.value, 0));

const arcs = computed<{ slice: Slice; len: number; offset: number; delay: number }[]>(() => {
    let cumulative = 0;
    return props.slices.map((slice, i) => {
        const len = total.value > 0 ? (slice.value / total.value) * C : 0;
        const arc = { slice, len, offset: -cumulative, delay: i * 0.12 };
        cumulative += len;
        return arc;
    });
});

onMounted(() => requestAnimationFrame(() => (shown.value = true)));
</script>

<template>
    <div class="flex flex-col items-center gap-3 sm:flex-row sm:items-center sm:gap-5">
        <div class="relative flex-none">
            <svg viewBox="0 0 140 140" class="h-36 w-36 -rotate-90">
                <circle cx="70" cy="70" :r="R" fill="none" stroke="currentColor" stroke-opacity="0.08" stroke-width="18" />
                <circle
                    v-for="a in arcs"
                    :key="a.slice.key"
                    cx="70"
                    cy="70"
                    :r="R"
                    fill="none"
                    :stroke="a.slice.color"
                    stroke-width="18"
                    stroke-linecap="round"
                    :stroke-dasharray="shown ? `${a.len} ${C - a.len}` : `0 ${C}`"
                    :stroke-dashoffset="a.offset"
                    :style="{ transition: `stroke-dasharray 0.9s cubic-bezier(0.2,0.7,0.2,1) ${a.delay}s` }"
                />
            </svg>
            <div class="absolute inset-0 flex rotate-0 flex-col items-center justify-center">
                <span class="text-2xl font-bold text-[#1F4E79] dark:text-sky-200">{{ centerValue }}</span>
                <span class="text-[11px] text-slate-400">{{ centerLabel }}</span>
            </div>
        </div>

        <ul class="w-full space-y-1.5">
            <li v-for="s in slices" :key="s.key" class="flex items-center gap-2 text-sm">
                <span class="h-2.5 w-2.5 flex-none rounded-full" :style="{ background: s.color }" />
                <span class="flex-1 truncate text-slate-600 dark:text-slate-300">{{ s.label }}</span>
                <span class="font-semibold text-[#1F4E79] dark:text-sky-200">{{ s.value }}</span>
                <span class="w-12 text-right text-xs text-slate-400">{{ s.pct }}%</span>
            </li>
            <li v-if="slices.length === 0" class="text-sm text-slate-400">Нет данных за период</li>
        </ul>
    </div>
</template>
