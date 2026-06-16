<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{ value: number; decimals?: number; suffix?: string; duration?: number }>(),
    { decimals: 0, suffix: '', duration: 900 },
);

const display = ref(0);
let raf = 0;

const prefersReduced = (): boolean =>
    typeof window !== 'undefined' && !!window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

function animate(to: number): void {
    if (prefersReduced()) {
        display.value = to;
        return;
    }
    const from = display.value;
    const start = performance.now();
    cancelAnimationFrame(raf);
    const step = (now: number): void => {
        const t = Math.min(1, (now - start) / props.duration);
        const eased = 1 - Math.pow(1 - t, 3);
        display.value = from + (to - from) * eased;
        if (t < 1) raf = requestAnimationFrame(step);
        else display.value = to;
    };
    raf = requestAnimationFrame(step);
}

const formatted = computed<string>(() =>
    display.value.toLocaleString('ru-RU', {
        minimumFractionDigits: props.decimals,
        maximumFractionDigits: props.decimals,
    }),
);

onMounted(() => animate(props.value));
watch(() => props.value, (v) => animate(v));
onBeforeUnmount(() => cancelAnimationFrame(raf));
</script>

<template>
    <span>{{ formatted }}{{ suffix }}</span>
</template>
