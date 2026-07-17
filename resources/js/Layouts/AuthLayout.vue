<script setup lang="ts">
defineProps<{ title: string; subtitle?: string }>();
</script>

<template>
    <div class="relative flex min-h-screen items-center justify-center overflow-x-clip bg-page p-6 text-ink">
        <!-- Мягкие цветовые пятна фона -->
        <div class="pointer-events-none absolute inset-0 overflow-hidden dark:opacity-35" aria-hidden="true">
            <div
                class="absolute -top-[18%] -left-[10%] h-[56vw] w-[56vw] rounded-full blur-[90px]"
                style="background: radial-gradient(circle at 40% 40%, rgba(43, 92, 224, 0.2), transparent 66%)"
            ></div>
            <div
                class="absolute -right-[12%] -bottom-[22%] h-[60vw] w-[60vw] rounded-full blur-[90px]"
                style="background: radial-gradient(circle at 60% 40%, rgba(238, 138, 92, 0.18), transparent 64%)"
            ></div>
            <div class="absolute top-[16%] right-[12%] h-[110px] w-[110px] rounded-full border-[1.5px] border-dashed border-[rgba(43,92,224,0.35)]"></div>
            <div class="absolute bottom-[14%] left-[10%] h-16 w-16 rotate-[18deg] rounded-[20px] border-[6px] border-[rgba(238,138,92,0.45)]"></div>
        </div>

        <div class="ui-fade-in relative z-10 w-full max-w-md">
            <div class="mb-7 text-center">
                <span class="inline-flex items-center gap-2.5">
                    <span class="inline-flex h-[34px] w-[34px] items-center justify-center rounded-[10px] bg-brand shadow-lg shadow-[rgba(43,92,224,0.32)]">
                        <span class="h-3 w-3 rounded-[999px_999px_999px_2px] bg-white"></span>
                    </span>
                    <span class="font-display text-xl font-semibold tracking-tight text-ink">Отклик</span>
                </span>
                <h1 class="mt-4 font-display text-2xl font-semibold tracking-tight text-ink">{{ title }}</h1>
                <p v-if="subtitle" class="mt-2 text-[15px] text-muted">{{ subtitle }}</p>
            </div>

            <div class="ui-scope otk-card rounded-3xl p-8 shadow-xl shadow-[rgba(16,28,51,0.10)]">
                <slot />
            </div>

            <p class="mt-6 text-center text-[13px] text-muted2">
                <a href="/" class="transition hover:text-muted">← Вернуться на сайт</a>
            </p>
        </div>
    </div>
</template>
