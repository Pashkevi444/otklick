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
            <div class="rounded-2xl bg-active p-4 text-sm text-ink">
                <p>Это кнопки-подсказки, которые бот покажет клиенту после приветствия. Нажатие отправляет подпись кнопки — бот ответит по базе знаний, сценарию или начнёт запись.</p>
                <p class="mt-2 text-muted">Если меню пустое — бот не показывает ни кнопок, ни возврата в меню.</p>
            </div>

            <div v-if="props.bookingAutoAdded" class="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-sm text-emerald-700 dark:text-emerald-300">
                YClients подключён → кнопка <b>«{{ props.bookingButton }}»</b> добавляется в меню автоматически. Свою такую же кнопку можно убрать, чтобы не было дубля.
            </div>

            <form class="otk-card space-y-2.5 p-5" @submit.prevent="submit">
                <div v-for="(_, i) in form.buttons" :key="i" class="flex items-center gap-2">
                    <input
                        v-model="form.buttons[i]"
                        type="text"
                        maxlength="40"
                        placeholder="Например: Цены и услуги"
                        class="flex-1 rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm font-semibold text-ink outline-none placeholder:font-normal placeholder:text-muted2 focus:border-brand"
                        @keydown.enter.prevent="add"
                    />
                    <button type="button" class="rounded-lg px-2 py-1 text-muted2 hover:text-ink disabled:opacity-30" :disabled="i === 0" title="Выше" @click="move(i, -1)">↑</button>
                    <button type="button" class="rounded-lg px-2 py-1 text-muted2 hover:text-ink disabled:opacity-30" :disabled="i === form.buttons.length - 1" title="Ниже" @click="move(i, 1)">↓</button>
                    <button type="button" class="rounded-lg px-2 py-1 text-red-500 hover:text-red-600" title="Удалить" @click="remove(i)">✕</button>
                </div>

                <button type="button" class="block w-full rounded-xl border border-dashed border-line px-4 py-3 text-sm font-bold text-brand transition hover:bg-hoverbg disabled:opacity-40" :disabled="form.buttons.length >= 12" @click="add">+ Добавить кнопку</button>

                <div class="flex items-center gap-3 pt-2">
                    <button type="submit" class="otk-btn-primary px-5 disabled:opacity-50" :disabled="form.processing">
                        Сохранить
                    </button>
                    <span v-if="form.recentlySuccessful" class="text-sm text-emerald-600 dark:text-emerald-400">Сохранено ✓</span>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
