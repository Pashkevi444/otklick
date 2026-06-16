<script setup lang="ts">
import { useToast, type ToastType } from '@/composables/useToast';

const { items, dismiss } = useToast();

const accent: Record<ToastType, string> = {
    error: 'before:bg-red-500',
    success: 'before:bg-green-500',
    info: 'before:bg-[#2E74B5]',
};

const icon: Record<ToastType, string> = {
    error: '⚠️',
    success: '✓',
    info: 'ℹ️',
};
</script>

<template>
    <div class="pointer-events-none fixed inset-x-0 bottom-0 z-[9999] flex flex-col items-center gap-2.5 p-4">
        <TransitionGroup name="toast">
            <div
                v-for="t in items"
                :key="t.id"
                class="toast pointer-events-auto relative flex w-full max-w-md items-start gap-3 overflow-hidden rounded-2xl border border-white/60 bg-white/85 px-4 py-3 pl-5 text-sm text-slate-800 shadow-xl backdrop-blur-xl before:absolute before:inset-y-0 before:left-0 before:w-1.5 dark:border-white/10 dark:bg-slate-800/85 dark:text-slate-100"
                :class="accent[t.type]"
                role="alert"
            >
                <span class="mt-0.5 flex-none text-base" :class="t.type === 'success' ? 'text-green-600 dark:text-green-400' : ''">{{ icon[t.type] }}</span>
                <span class="flex-1 leading-snug">{{ t.message }}</span>
                <button
                    type="button"
                    class="-mr-1 flex-none rounded-md px-1 text-slate-400 transition hover:text-slate-700 dark:hover:text-slate-200"
                    aria-label="Закрыть"
                    @click="dismiss(t.id)"
                >
                    ✕
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>

<style scoped>
.toast {
    transition:
        transform 0.4s cubic-bezier(0.2, 0.8, 0.2, 1),
        opacity 0.4s ease;
}
.toast-enter-from {
    transform: translateY(24px) scale(0.97);
    opacity: 0;
}
.toast-leave-to {
    transform: translateY(12px) scale(0.97);
    opacity: 0;
}
.toast-leave-active {
    position: absolute;
}

@media (prefers-reduced-motion: reduce) {
    .toast,
    .toast-enter-from,
    .toast-leave-to {
        transition: none;
        transform: none;
    }
}
</style>
