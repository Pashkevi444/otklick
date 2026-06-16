<script setup lang="ts">
import Logo from '@/Components/Logo.vue';

defineProps<{ title: string; subtitle?: string }>();
</script>

<template>
    <div class="relative flex min-h-screen items-center justify-center overflow-hidden p-6 text-slate-800 dark:text-slate-200">
        <div class="bg-base"></div>
        <div class="orbs" aria-hidden="true">
            <span class="orb orb-1"></span>
            <span class="orb orb-2"></span>
            <span class="orb orb-3"></span>
        </div>

        <div class="auth-enter w-full max-w-md">
            <div class="mb-8 text-center">
                <Logo :size="30" class="justify-center text-2xl text-[#1F4E79] dark:text-white" />
                <h1 class="mt-3 text-2xl font-bold text-[#1F4E79] dark:text-sky-200">{{ title }}</h1>
                <p v-if="subtitle" class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ subtitle }}</p>
            </div>
            <div class="ui-scope glass rounded-3xl p-8">
                <slot />
            </div>
        </div>
    </div>
</template>

<style scoped>
.glass {
    background: rgba(255, 255, 255, 0.6);
    backdrop-filter: blur(18px) saturate(170%);
    -webkit-backdrop-filter: blur(18px) saturate(170%);
    border: 1px solid rgba(255, 255, 255, 0.6);
    box-shadow: 0 8px 32px rgba(31, 78, 121, 0.14);
}
html.dark .glass {
    background: rgba(20, 30, 48, 0.6);
    border-color: rgba(255, 255, 255, 0.1);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
}

.bg-base {
    position: fixed;
    inset: 0;
    z-index: -2;
    pointer-events: none;
    background: linear-gradient(125deg, #eaf1fe 0%, #f6faff 45%, #e7f6ff 100%);
    background-size: 200% 200%;
    animation: bgpan 22s ease infinite;
}
html.dark .bg-base {
    background: linear-gradient(125deg, #0b1220 0%, #0e1828 45%, #0a1a26 100%);
    background-size: 200% 200%;
}
html.dark .orb {
    opacity: 0.25;
}

.orbs {
    position: fixed;
    inset: 0;
    z-index: -1;
    overflow: hidden;
    pointer-events: none;
}
.orb {
    position: absolute;
    border-radius: 9999px;
    filter: blur(70px);
    opacity: 0.5;
}
.orb-1 {
    width: 380px;
    height: 380px;
    background: #7cc0ff;
    top: -80px;
    left: -60px;
    animation: floaty 18s ease-in-out infinite;
}
.orb-2 {
    width: 320px;
    height: 320px;
    background: #b9a8ff;
    bottom: -90px;
    right: -70px;
    animation: floaty 24s ease-in-out infinite reverse;
}
.orb-3 {
    width: 260px;
    height: 260px;
    background: #7df3e1;
    bottom: 20%;
    left: 8%;
    animation: floaty 20s ease-in-out infinite;
}

.auth-enter {
    animation: enter 0.7s cubic-bezier(0.2, 0.7, 0.2, 1) both;
}

@keyframes bgpan {
    0%,
    100% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
}
@keyframes floaty {
    0%,
    100% {
        transform: translate(0, 0) scale(1);
    }
    50% {
        transform: translate(24px, -30px) scale(1.06);
    }
}
@keyframes enter {
    from {
        opacity: 0;
        transform: translateY(22px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .bg-base,
    .orb {
        animation: none;
    }
    .auth-enter {
        animation: none;
    }
}
</style>
