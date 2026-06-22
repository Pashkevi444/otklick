<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface Widget {
    id: string;
    isActive: boolean;
    allowedOrigins: string[];
    color: string;
    scriptUrl: string;
    snippet: string;
}

const props = defineProps<{ widget: Widget | null }>();

// Готовые акценты + затемнённый край градиента для живого превью (как в рантайме
// виджета: бизнес выбирает один цвет, тёмный край вычисляется автоматически).
const PRESETS = ['#2E74B5', '#7C3AED', '#0EA5E9', '#10B981', '#F59E0B', '#EF4444', '#EC4899', '#0F172A'];

const colorForm = useForm({ color: props.widget?.color ?? '#2E74B5' });

const darken = (hex: string, f: number): string => {
    const m = /^#?([0-9a-f]{6})$/i.exec(hex);
    if (!m) return hex;
    const n = parseInt(m[1], 16);
    const c = (v: number): number => Math.round(v * f);
    const r = c((n >> 16) & 255);
    const g = c((n >> 8) & 255);
    const b = c(n & 255);
    return '#' + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
};

const gradient = (hex: string): string => `linear-gradient(135deg, ${hex}, ${darken(hex, 0.74)})`;

const saveColor = (): void => {
    if (props.widget) {
        colorForm.put(`/cabinet/widget/${props.widget.id}/appearance`, { preserveScroll: true });
    }
};

const connect = (): void => {
    router.post('/cabinet/widget', {}, { preserveScroll: true });
};

const disconnect = (): void => {
    if (props.widget && confirm('Отключить виджет? Код на сайте перестанет работать.')) {
        router.delete(`/cabinet/widget/${props.widget.id}`);
    }
};

const originsForm = useForm({
    origins: props.widget ? props.widget.allowedOrigins.join('\n') : '',
});

const saveOrigins = (): void => {
    if (props.widget) {
        originsForm.put(`/cabinet/widget/${props.widget.id}`, { preserveScroll: true });
    }
};

const copied = ref(false);
const copySnippet = (): void => {
    if (!props.widget) return;
    navigator.clipboard.writeText(props.widget.snippet).then(() => {
        copied.value = true;
        setTimeout(() => (copied.value = false), 2000);
    });
};
</script>

<template>
    <Head title="Виджет на сайт" />

    <AppLayout title="Виджет на сайт">
        <p class="mb-6 max-w-2xl text-sm text-slate-500">
            Встройте чат с вашим AI-администратором на сайт. Посетитель пишет в виджет — бот отвечает по базе знаний,
            а сложные вопросы передаёт вам.
        </p>

        <!-- Не подключён -->
        <div v-if="!widget" class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6">
            <div class="font-semibold text-[#1F4E79]">Виджет ещё не подключён</div>
            <p class="mt-2 text-sm text-slate-500">Создайте виджет — мы выдадим код для вставки на сайт.</p>
            <button
                type="button"
                class="mt-4 rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96]"
                @click="connect"
            >
                Подключить виджет
            </button>
        </div>

        <template v-else>
            <!-- Код для вставки -->
            <div class="max-w-2xl rounded-xl border border-slate-200 bg-white p-6">
                <div class="font-semibold text-[#1F4E79]">Код для вставки</div>
                <p class="mt-1 text-sm text-slate-500">
                    Вставьте этот код на свой сайт перед закрывающим тегом <code class="rounded bg-slate-100 px-1">&lt;/body&gt;</code>.
                </p>
                <pre class="mt-3 overflow-x-auto rounded-lg bg-slate-900 p-4 text-xs text-slate-100"><code>{{ widget.snippet }}</code></pre>
                <button
                    type="button"
                    class="mt-3 rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                    @click="copySnippet"
                >
                    {{ copied ? '✓ Скопировано' : 'Скопировать код' }}
                </button>
            </div>

            <!-- Цвет виджета -->
            <div class="mt-6 max-w-2xl rounded-xl border border-slate-200 bg-white p-6">
                <div class="font-semibold text-[#1F4E79]">Цвет виджета</div>
                <p class="mt-1 text-sm text-slate-500">
                    Выберите фирменный цвет — в него окрасятся кнопка чата, шапка и кнопка отправки. Тёмный край градиента
                    подберётся автоматически.
                </p>

                <form class="mt-4 flex flex-col gap-5 sm:flex-row sm:items-start" @submit.prevent="saveColor">
                    <!-- Превью -->
                    <div class="flex flex-col items-center gap-3">
                        <div class="w-52 overflow-hidden rounded-2xl border border-slate-200 shadow-sm">
                            <div class="flex items-center gap-2 px-3 py-2.5 text-white" :style="{ background: gradient(colorForm.color) }">
                                <span class="flex h-7 w-7 items-center justify-center rounded-full bg-white/20">
                                    <svg viewBox="0 0 24 24" class="h-3.5 w-3.5 fill-white"><path d="M12 3C6.9 3 2.8 6.3 2.8 10.5c0 2 .95 3.8 2.5 5.2-.1.95-.5 2-.95 2.7-.2.3 0 .7.4.65 1.4-.2 2.6-.7 3.5-1.3.85.2 1.75.3 2.7.3 5.1 0 9.2-3.3 9.2-7.5S17.1 3 12 3z"/></svg>
                                </span>
                                <div class="leading-tight">
                                    <div class="text-xs font-bold">Отклик</div>
                                    <div class="flex items-center gap-1 text-[10px] opacity-90"><span class="h-1.5 w-1.5 rounded-full bg-emerald-300"></span>Администратор в сети</div>
                                </div>
                            </div>
                            <div class="space-y-1.5 bg-slate-50 p-3">
                                <div class="ml-auto w-fit max-w-[80%] rounded-xl rounded-br-sm px-2.5 py-1.5 text-[11px] text-white" :style="{ background: gradient(colorForm.color) }">Здравствуйте! Запишите меня</div>
                                <div class="w-fit max-w-[80%] rounded-xl rounded-bl-sm bg-white px-2.5 py-1.5 text-[11px] text-slate-700 shadow-sm">Конечно! На какое время удобно?</div>
                            </div>
                        </div>
                        <div
                            class="flex h-11 w-11 items-center justify-center rounded-full text-white shadow-lg"
                            :style="{ background: gradient(colorForm.color) }"
                        >
                            <svg viewBox="0 0 24 24" class="h-5 w-5 fill-white"><path d="M12 3C6.9 3 2.8 6.3 2.8 10.5c0 2 .95 3.8 2.5 5.2-.1.95-.5 2-.95 2.7-.2.3 0 .7.4.65 1.4-.2 2.6-.7 3.5-1.3.85.2 1.75.3 2.7.3 5.1 0 9.2-3.3 9.2-7.5S17.1 3 12 3z"/></svg>
                        </div>
                    </div>

                    <!-- Палитра -->
                    <div class="flex-1">
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="c in PRESETS"
                                :key="c"
                                type="button"
                                class="h-9 w-9 rounded-full ring-offset-2 transition hover:scale-110"
                                :class="colorForm.color.toLowerCase() === c.toLowerCase() ? 'ring-2 ring-slate-400' : ''"
                                :style="{ background: c }"
                                :aria-label="c"
                                @click="colorForm.color = c"
                            />
                        </div>

                        <div class="mt-4 flex items-center gap-3">
                            <input
                                v-model="colorForm.color"
                                type="color"
                                class="h-10 w-12 cursor-pointer rounded-lg border border-slate-300 bg-white p-1"
                                aria-label="Выбрать цвет"
                            />
                            <input
                                v-model="colorForm.color"
                                type="text"
                                maxlength="7"
                                placeholder="#2E74B5"
                                class="w-32 rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm uppercase"
                            />
                        </div>
                        <p v-if="colorForm.errors.color" class="mt-1 text-sm text-red-600">{{ colorForm.errors.color }}</p>

                        <div class="mt-4 flex items-center gap-3">
                            <button
                                type="submit"
                                :disabled="colorForm.processing"
                                class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50"
                            >
                                Сохранить цвет
                            </button>
                            <button type="button" class="text-sm text-slate-500 hover:underline" @click="colorForm.color = '#2E74B5'">
                                Сбросить
                            </button>
                            <span v-if="colorForm.recentlySuccessful" class="text-sm text-green-600">Сохранено</span>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Разрешённые домены -->
            <div class="mt-6 max-w-2xl rounded-xl border border-slate-200 bg-white p-6">
                <div class="font-semibold text-[#1F4E79]">Разрешённые домены</div>
                <p class="mt-1 text-sm text-slate-500">
                    С каких сайтов можно открывать чат — по одному домену в строке (например, <code class="rounded bg-slate-100 px-1">https://mysite.ru</code>).
                    Это защищает виджет от использования на чужих сайтах. Пусто — разрешено везде (не рекомендуется).
                </p>
                <form class="mt-4" @submit.prevent="saveOrigins">
                    <textarea
                        v-model="originsForm.origins"
                        rows="4"
                        placeholder="https://mysite.ru&#10;https://www.mysite.ru"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm"
                    />
                    <p v-if="originsForm.errors.origins" class="mt-1 text-sm text-red-600">{{ originsForm.errors.origins }}</p>
                    <div class="mt-3 flex items-center gap-3">
                        <button
                            type="submit"
                            :disabled="originsForm.processing"
                            class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50"
                        >
                            Сохранить
                        </button>
                        <span v-if="originsForm.recentlySuccessful" class="text-sm text-green-600">Сохранено</span>
                    </div>
                </form>
            </div>

            <!-- Отключение -->
            <div class="mt-6 max-w-2xl">
                <button type="button" class="text-sm font-medium text-red-600 hover:underline" @click="disconnect">
                    Отключить виджет
                </button>
            </div>
        </template>
    </AppLayout>
</template>
