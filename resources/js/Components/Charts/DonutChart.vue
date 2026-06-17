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
const hovered = ref<string | null>(null);
const pinned = ref<string | null>(null);

// Активный сектор: наведение приоритетнее «закреплённого» кликом.
const activeKey = computed<string | null>(() => hovered.value ?? pinned.value);
const activeSlice = computed<Slice | null>(() => props.slices.find((s) => s.key === activeKey.value) ?? null);

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

const select = (key: string): void => {
    pinned.value = pinned.value === key ? null : key;
};

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
                    :stroke-width="activeKey === a.slice.key ? 24 : 18"
                    stroke-linecap="round"
                    :stroke-dasharray="shown ? `${a.len} ${C - a.len}` : `0 ${C}`"
                    :stroke-dashoffset="a.offset"
                    class="cursor-pointer"
                    :style="{
                        transition: `stroke-dasharray 0.9s cubic-bezier(0.2,0.7,0.2,1) ${a.delay}s, stroke-width 0.2s ease, opacity 0.2s ease`,
                        opacity: activeKey === null || activeKey === a.slice.key ? 1 : 0.3,
                    }"
                    @mouseenter="hovered = a.slice.key"
                    @mouseleave="hovered = null"
                    @click="select(a.slice.key)"
                />
            </svg>
            <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center px-7 text-center">
                <span class="text-2xl font-bold text-[#1F4E79] dark:text-sky-200">{{ activeSlice ? activeSlice.value : centerValue }}</span>
                <span class="text-[11px] leading-tight text-slate-400">{{ activeSlice ? `${activeSlice.label} · ${activeSlice.pct}%` : centerLabel }}</span>
            </div>
        </div>

        <ul class="w-full space-y-1">
            <li
                v-for="s in slices"
                :key="s.key"
                class="flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1 text-sm transition"
                :class="[
                    activeKey === s.key ? 'bg-slate-100 dark:bg-white/10' : '',
                    activeKey !== null && activeKey !== s.key ? 'opacity-50' : '',
                ]"
                @mouseenter="hovered = s.key"
                @mouseleave="hovered = null"
                @click="select(s.key)"
            >
                <span
                    class="h-2.5 w-2.5 flex-none rounded-full ring-2 ring-transparent transition"
                    :class="{ 'ring-offset-1 ring-current': activeKey === s.key }"
                    :style="{ background: s.color, color: s.color }"
                />
                <span class="flex-1 truncate text-slate-600 dark:text-slate-300">{{ s.label }}</span>
                <span class="font-semibold text-[#1F4E79] dark:text-sky-200">{{ s.value }}</span>
                <span class="w-12 text-right text-xs text-slate-400">{{ s.pct }}%</span>
            </li>
            <li v-if="slices.length === 0" class="text-sm text-slate-400">Нет данных за период</li>
        </ul>
    </div>
</template>
