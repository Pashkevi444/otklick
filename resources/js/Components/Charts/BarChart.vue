<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

interface Bar {
    label: string;
    value: number;
}

const props = withDefaults(
    defineProps<{ bars: Bar[]; height?: number; labelStep?: number }>(),
    { height: 130, labelStep: 1 },
);

const shown = ref(false);
const hovered = ref<number | null>(null);
const max = computed<number>(() => Math.max(1, ...props.bars.map((b) => b.value)));

onMounted(() => requestAnimationFrame(() => (shown.value = true)));
</script>

<template>
    <div>
        <div class="flex items-end gap-[3px]" :style="{ height: `${height}px` }" @mouseleave="hovered = null">
            <div
                v-for="(b, i) in bars"
                :key="b.label + i"
                class="group relative flex-1 cursor-pointer"
                :style="{ height: '100%' }"
                @mouseenter="hovered = i"
            >
                <div class="flex h-full items-end">
                    <div
                        class="w-full rounded-t bg-gradient-to-t from-[#2E74B5]/40 to-[#2E74B5] transition-all duration-300 ease-out group-hover:from-[#2E74B5]/60 group-hover:to-[#1F4E79] dark:from-sky-500/30 dark:to-sky-400 dark:group-hover:to-sky-300"
                        :style="{
                            height: shown ? `${(b.value / max) * 100}%` : '0%',
                            transitionDelay: shown ? '0ms' : `${i * 18}ms`,
                            minHeight: b.value > 0 ? '3px' : '0',
                            opacity: hovered === null || hovered === i ? 1 : 0.35,
                        }"
                    />
                </div>
                <div
                    class="pointer-events-none absolute -top-7 left-1/2 z-10 -translate-x-1/2 rounded-md bg-[#1F4E79] px-1.5 py-0.5 text-center text-[10px] leading-tight text-white opacity-0 shadow-lg transition group-hover:opacity-100 dark:bg-sky-600"
                >
                    <div class="font-semibold">{{ b.value }}</div>
                    <div class="opacity-75">{{ b.label }}</div>
                </div>
            </div>
        </div>
        <div class="mt-1 flex gap-[3px] text-[10px] text-slate-400">
            <span v-for="(b, i) in bars" :key="'l' + i" class="flex-1 text-center">
                {{ i % labelStep === 0 ? b.label : '' }}
            </span>
        </div>
    </div>
</template>
