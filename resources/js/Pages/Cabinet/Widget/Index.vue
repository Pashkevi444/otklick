<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface Widget {
    id: string;
    isActive: boolean;
    allowedOrigins: string[];
    scriptUrl: string;
    snippet: string;
}

const props = defineProps<{ widget: Widget | null }>();

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
