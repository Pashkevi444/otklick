<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

interface Point {
    label: string;
    value: number;
}

const props = withDefaults(defineProps<{ points: Point[]; color?: string; height?: number }>(), {
    color: '#2E74B5',
    height: 170,
});

const W = 640;
const PAD = 10;

const gid = `grad-${Math.random().toString(36).slice(2)}`;
const shown = ref(false);

const max = computed<number>(() => Math.max(1, ...props.points.map((p) => p.value)));

const coords = computed<{ x: number; y: number }[]>(() => {
    const n = props.points.length;
    return props.points.map((p, i) => {
        const x = n <= 1 ? W / 2 : (i / (n - 1)) * (W - 2 * PAD) + PAD;
        const y = props.height - PAD - (p.value / max.value) * (props.height - 2 * PAD);
        return { x, y };
    });
});

const line = computed<string>(() =>
    coords.value.map((c, i) => `${i === 0 ? 'M' : 'L'}${c.x.toFixed(1)},${c.y.toFixed(1)}`).join(' '),
);

const area = computed<string>(() => {
    const c = coords.value;
    if (c.length === 0) return '';
    const first = c[0]!;
    const last = c[c.length - 1]!;
    return `${line.value} L${last.x.toFixed(1)},${props.height - PAD} L${first.x.toFixed(1)},${props.height - PAD} Z`;
});

const lastPoint = computed<{ x: number; y: number } | null>(() => coords.value[coords.value.length - 1] ?? null);

const ticks = computed<{ label: string; left: number }[]>(() => {
    const n = props.points.length;
    if (n === 0) return [];
    const stepCount = Math.min(6, n);
    const step = Math.max(1, Math.floor(n / stepCount));
    const out: { label: string; left: number }[] = [];
    for (let i = 0; i < n; i += step) {
        out.push({ label: props.points[i]!.label, left: n <= 1 ? 50 : (i / (n - 1)) * 100 });
    }
    return out;
});

const peak = computed<number>(() => Math.max(...props.points.map((p) => p.value), 0));

onMounted(() => requestAnimationFrame(() => (shown.value = true)));
</script>

<template>
    <div>
        <svg :viewBox="`0 0 ${W} ${height}`" preserveAspectRatio="none" class="w-full" :style="{ height: `${height}px` }">
            <defs>
                <linearGradient :id="gid" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" :stop-color="color" stop-opacity="0.35" />
                    <stop offset="100%" :stop-color="color" stop-opacity="0" />
                </linearGradient>
            </defs>

            <!-- сетка -->
            <line v-for="g in 3" :key="g" x1="0" :x2="W" :y1="(height / 3) * (g - 1) + PAD / 2" :y2="(height / 3) * (g - 1) + PAD / 2"
                stroke="currentColor" stroke-opacity="0.08" stroke-width="1" />

            <path :d="area" :fill="`url(#${gid})`" class="area" :class="{ shown }" />
            <path :d="line" fill="none" :stroke="color" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"
                pathLength="1" class="line" :class="{ shown }" />
            <circle v-if="lastPoint" :cx="lastPoint.x" :cy="lastPoint.y" r="4" :fill="color" class="dot" :class="{ shown }" />
        </svg>

        <div class="relative mt-1 h-4 text-[10px] text-slate-400">
            <span v-for="t in ticks" :key="t.label + t.left" class="absolute -translate-x-1/2" :style="{ left: `${t.left}%` }">
                {{ t.label }}
            </span>
        </div>
        <div class="mt-1 text-right text-[11px] text-slate-400">пик: {{ peak }} / день</div>
    </div>
</template>

<style scoped>
.line {
    stroke-dasharray: 1;
    stroke-dashoffset: 1;
    transition: stroke-dashoffset 1.1s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.line.shown {
    stroke-dashoffset: 0;
}
.area,
.dot {
    opacity: 0;
    transition: opacity 0.8s ease 0.4s;
}
.area.shown,
.dot.shown {
    opacity: 1;
}
@media (prefers-reduced-motion: reduce) {
    .line,
    .area,
    .dot {
        transition: none;
        opacity: 1;
        stroke-dashoffset: 0;
    }
}
</style>
