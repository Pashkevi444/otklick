<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps<{ buttons: string[]; bookingButton: string; bookingAutoAdded: boolean }>();

const form = useForm<{ buttons: string[] }>({ buttons: props.buttons.length ? [...props.buttons] : [''] });

const add = (): void => {
    if (form.buttons.length < 12) form.buttons.push('');
};
const remove = (i: number): void => {
    form.buttons.splice(i, 1);
    if (form.buttons.length === 0) form.buttons.push('');
};
const move = (i: number, dir: -1 | 1): void => {
    const j = i + dir;
    if (j < 0 || j >= form.buttons.length) return;
    [form.buttons[i], form.buttons[j]] = [form.buttons[j], form.buttons[i]];
};
const submit = (): void => {
    form.transform((d) => ({ buttons: d.buttons.map((b) => b.trim()).filter((b) => b !== '') })).put('/cabinet/menu', { preserveScroll: true });
};
</script>

<template>
    <Head title="Главное меню бота" />

    <AppLayout title="Главное меню бота">
        <div class="mx-auto max-w-xl space-y-4">
            <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 dark:border-sky-400/20 dark:bg-sky-400/10 dark:text-sky-100">
                <p>Это кнопки-подсказки, которые бот покажет клиенту после приветствия. Нажатие отправляет подпись кнопки — бот ответит по базе знаний, сценарию или начнёт запись.</p>
                <p class="mt-2 text-slate-500 dark:text-slate-300">Если меню пустое — бот не показывает ни кнопок, ни возврата в меню.</p>
            </div>

            <div v-if="props.bookingAutoAdded" class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900 dark:border-emerald-400/20 dark:bg-emerald-400/10 dark:text-emerald-100">
                YClients подключён → кнопка <b>«{{ props.bookingButton }}»</b> добавляется в меню автоматически. Свою такую же кнопку можно убрать, чтобы не было дубля.
            </div>

            <form class="space-y-2 rounded-2xl border border-slate-200 bg-white p-5 dark:border-white/10 dark:bg-white/5" @submit.prevent="submit">
                <div v-for="(_, i) in form.buttons" :key="i" class="flex items-center gap-2">
                    <input
                        v-model="form.buttons[i]"
                        type="text"
                        maxlength="40"
                        placeholder="Например: Цены и услуги"
                        class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/15 dark:bg-white/5"
                        @keydown.enter.prevent="add"
                    />
                    <button type="button" class="rounded-md px-2 py-1 text-slate-400 hover:text-slate-700 disabled:opacity-30" :disabled="i === 0" title="Выше" @click="move(i, -1)">↑</button>
                    <button type="button" class="rounded-md px-2 py-1 text-slate-400 hover:text-slate-700 disabled:opacity-30" :disabled="i === form.buttons.length - 1" title="Ниже" @click="move(i, 1)">↓</button>
                    <button type="button" class="rounded-md px-2 py-1 text-rose-500 hover:text-rose-700" title="Удалить" @click="remove(i)">✕</button>
                </div>

                <button type="button" class="text-sm text-[#2E74B5] hover:underline" :disabled="form.buttons.length >= 12" @click="add">+ Добавить кнопку</button>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" class="rounded-lg bg-[#2E74B5] px-5 py-2 text-sm font-semibold text-white hover:bg-[#255f97] disabled:opacity-50" :disabled="form.processing">
                        Сохранить
                    </button>
                    <span v-if="form.recentlySuccessful" class="text-sm text-emerald-600">Сохранено ✓</span>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
