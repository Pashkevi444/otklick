<script setup lang="ts">
import { useTheme } from '@/composables/useTheme';

const { isDark, toggle } = useTheme();
</script>

<template>
    <button
        type="button"
        :aria-label="isDark ? 'Светлая тема' : 'Тёмная тема'"
        :title="isDark ? 'Светлая тема' : 'Тёмная тема'"
        class="flex h-9 w-9 items-center justify-center rounded-xl border border-white/50 bg-white/50 text-base backdrop-blur transition hover:-translate-y-0.5 dark:border-white/10 dark:bg-white/10"
        @click="toggle"
    >
        <span v-if="isDark">☀️</span>
        <span v-else>🌙</span>
    </button>
</template>
