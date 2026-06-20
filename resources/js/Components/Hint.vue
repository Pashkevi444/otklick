<script setup lang="ts">
defineProps<{ text: string }>();
</script>

<template>
    <span class="group relative ml-1 inline-flex align-middle">
        <span
            class="inline-flex h-4 w-4 cursor-help items-center justify-center rounded-full bg-slate-200 text-[10px] font-bold text-slate-600 dark:bg-white/15 dark:text-slate-300"
            tabindex="0"
            aria-label="Подсказка"
            >?</span
        >
        <span
            class="pointer-events-none absolute bottom-full left-1/2 z-30 mb-1.5 hidden w-60 -translate-x-1/2 rounded-lg bg-slate-800 px-3 py-2 text-xs font-normal leading-snug text-white shadow-lg group-hover:block group-focus-within:block"
            role="tooltip"
            >{{ text }}</span
        >
    </span>
</template>
