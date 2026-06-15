<script setup lang="ts">
defineProps<{ title: string; subtitle?: string }>();
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-800 flex items-center justify-center p-6">
        <div class="max-w-md w-full">
            <div class="text-center mb-8">
                <div class="text-sm font-medium text-[#2E74B5] uppercase tracking-wide">Отклик</div>
                <h1 class="mt-1 text-2xl font-bold text-[#1F4E79]">{{ title }}</h1>
                <p v-if="subtitle" class="mt-1 text-sm text-slate-500">{{ subtitle }}</p>
            </div>
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-8">
                <slot />
            </div>
        </div>
    </div>
</template>
