<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

defineProps<{
    appName: string;
    laravelVersion: string;
    phpVersion: string;
}>();
</script>

<template>
    <Head title="Главная" />

    <div class="min-h-screen bg-slate-50 text-slate-800 flex items-center justify-center p-6">
        <div class="max-w-xl w-full bg-white rounded-2xl shadow-sm border border-slate-200 p-10">
            <div class="text-sm font-medium text-[#2E74B5] uppercase tracking-wide">
                AI-администратор для локального бизнеса
            </div>
            <h1 class="mt-2 text-4xl font-bold text-[#1F4E79]">{{ appName }}</h1>
            <p class="mt-4 text-slate-600 leading-relaxed">
                Мультиканальный SaaS: перехватывает входящие обращения, отвечает по базе
                знаний бизнеса и записывает клиента в CRM. Каркас (Фаза 0) развёрнут.
            </p>
            <dl class="mt-8 grid grid-cols-2 gap-4 text-sm">
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">Laravel</dt>
                    <dd class="font-semibold">{{ laravelVersion }}</dd>
                </div>
                <div class="rounded-lg bg-slate-50 p-4">
                    <dt class="text-slate-500">PHP</dt>
                    <dd class="font-semibold">{{ phpVersion }}</dd>
                </div>
            </dl>
        </div>
    </div>
</template>
