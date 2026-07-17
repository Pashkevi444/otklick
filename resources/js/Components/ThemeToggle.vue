<script setup lang="ts">
import { useTheme } from '@/composables/useTheme';

const { isDark, toggle } = useTheme();
</script>

<template>
    <button
        type="button"
        :aria-label="isDark ? 'Светлая тема' : 'Тёмная тема'"
        :title="isDark ? 'Светлая тема' : 'Тёмная тема'"
        class="flex h-10 w-10 items-center justify-center rounded-xl border border-line bg-panel text-base transition hover:bg-hoverbg"
        @click="toggle"
    >
        <span v-if="isDark">☀️</span>
        <span v-else>🌙</span>
    </button>
</template>
