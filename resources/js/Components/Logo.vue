<script setup lang="ts">
withDefaults(defineProps<{ size?: number }>(), { size: 24 });
</script>

<template>
    <span class="inline-flex items-center gap-2.5 leading-none">
        <!-- Лого-знак: скруглённый квадрат бренд-синего с белой «каплей» (реплика) -->
        <span
            class="relative inline-flex flex-none items-center justify-center"
            :style="{
                width: `${size}px`,
                height: `${size}px`,
                borderRadius: `${Math.round(size * 0.3)}px`,
                background: '#2B5CE0',
                boxShadow: '0 6px 16px rgba(43, 92, 224, 0.32)',
            }"
            aria-hidden="true"
        >
            <span
                class="block bg-white"
                :style="{
                    width: `${Math.round(size * 0.37)}px`,
                    height: `${Math.round(size * 0.37)}px`,
                    borderRadius: '999px 999px 999px 2px',
                }"
            ></span>
        </span>
        <span class="font-display font-semibold tracking-tight">Отклик</span>
    </span>
</template>
