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
const max = computed<number>(() => Math.max(1, ...props.bars.map((b) => b.value)));

onMounted(() => requestAnimationFrame(() => (shown.value = true)));
</script>

<template>
    <div>
        <div class="flex items-end gap-[3px]" :style="{ height: `${height}px` }">
            <div
                v-for="(b, i) in bars"
                :key="b.label + i"
                class="group relative flex-1"
                :style="{ height: '100%' }"
            >
                <div class="flex h-full items-end">
                    <div
                        class="w-full rounded-t bg-gradient-to-t from-[#2E74B5]/40 to-[#2E74B5] transition-all duration-700 ease-out dark:from-sky-500/30 dark:to-sky-400"
                        :style="{
                            height: shown ? `${(b.value / max) * 100}%` : '0%',
                            transitionDelay: `${i * 18}ms`,
                            minHeight: b.value > 0 ? '3px' : '0',
                        }"
                    />
                </div>
                <div
                    class="pointer-events-none absolute -top-6 left-1/2 -translate-x-1/2 rounded-md bg-[#1F4E79] px-1.5 py-0.5 text-[10px] text-white opacity-0 transition group-hover:opacity-100"
                >
                    {{ b.value }}
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
