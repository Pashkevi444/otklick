<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface PromptTemplate {
    id: string;
    business_type: string | null;
    name: string;
    body: string;
}
interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}
interface Paginator<T> {
    data: T[];
    links: PageLink[];
    total: number;
    last_page: number;
}
interface Variable {
    token: string;
    desc: string;
}

const props = defineProps<{ templates: Paginator<PromptTemplate>; variables: Variable[] }>();

const openId = ref<string | null>(null);
const form = useForm({ name: '', body: '' });

const toggle = (t: PromptTemplate): void => {
    if (openId.value === t.id) {
        openId.value = null;
        return;
    }
    openId.value = t.id;
    form.name = t.name;
    form.body = t.body;
    form.clearErrors();
};

const save = (t: PromptTemplate): void => {
    form.put(`/admin/prompt-templates/${t.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            openId.value = null;
        },
    });
};

const insertVar = (token: string): void => {
    form.body += (form.body.endsWith(' ') || form.body === '' ? '' : ' ') + token;
};

const preview = (body: string): string => {
    const flat = body.replace(/\s+/g, ' ').trim();
    return flat.length > 130 ? flat.slice(0, 130) + '…' : flat;
};
</script>

<template>
    <Head title="Промпты бота" />

    <AppLayout title="Промпты бота">
        <p class="mb-4 max-w-3xl text-sm text-slate-600 dark:text-slate-300">
            Промпт-инструкция бота под каждую нишу. Здесь редактируется только «голова» промпта (тон и правила
            поведения). Стандартный «хвост» (правила записи, эскалации, отмены и блоки данных) собирается в коде и
            одинаков для всех. Бизнес получает промпт своей ниши (по типу бизнеса); ниши без своего промпта берут
            «Универсальный».
        </p>

        <!-- Доступные переменные -->
        <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-4 dark:border-white/10 dark:bg-white/5">
            <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-500">
                Переменные (подставляются ботом)
            </div>
            <div class="flex flex-wrap gap-2">
                <span
                    v-for="v in variables"
                    :key="v.token"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-[#EAF2FB] px-2.5 py-1 text-xs dark:bg-white/10"
                    :title="v.desc"
                >
                    <code class="font-mono text-[#1F4E79] dark:text-sky-200">{{ v.token }}</code>
                    <span class="text-slate-500 dark:text-slate-400">— {{ v.desc }}</span>
                </span>
            </div>
        </div>

        <!-- Список ниш -->
        <div class="space-y-3">
            <div
                v-for="t in templates.data"
                :key="t.id"
                class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-white/10 dark:bg-white/5"
            >
                <button
                    type="button"
                    class="flex w-full items-center gap-3 px-5 py-4 text-left transition hover:bg-slate-50 dark:hover:bg-white/5"
                    @click="toggle(t)"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-[#1F4E79] dark:text-sky-200">{{ t.name }}</span>
                            <span
                                class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                                :class="t.business_type
                                    ? 'bg-[#EAF2FB] text-[#1F4E79] dark:bg-white/10 dark:text-sky-200'
                                    : 'bg-amber-100 text-amber-700 dark:bg-amber-400/15 dark:text-amber-300'"
                            >
                                {{ t.business_type ?? 'по умолчанию' }}
                            </span>
                        </div>
                        <div class="mt-1 truncate text-sm text-slate-500 dark:text-slate-400">{{ preview(t.body) }}</div>
                    </div>
                    <span class="text-slate-400 transition" :class="openId === t.id ? 'rotate-180' : ''">▾</span>
                </button>

                <!-- Редактор промпта ниши -->
                <div v-if="openId === t.id" class="border-t border-slate-200 px-5 py-4 dark:border-white/10">
                    <label class="mb-1 block text-xs font-medium text-slate-500 dark:text-slate-400">Название</label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="mb-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5"
                    />
                    <div v-if="form.errors.name" class="mb-2 text-xs text-rose-500">{{ form.errors.name }}</div>

                    <label class="mb-1 mt-3 block text-xs font-medium text-slate-500 dark:text-slate-400">
                        Тело промпта (можно вставлять переменные {{ }})
                    </label>
                    <div class="mb-1.5 flex flex-wrap gap-1.5">
                        <button
                            v-for="v in variables"
                            :key="v.token"
                            type="button"
                            class="rounded-md bg-slate-100 px-2 py-0.5 font-mono text-[11px] text-[#2E74B5] transition hover:bg-slate-200 dark:bg-white/10 dark:text-sky-200"
                            @click="insertVar(v.token)"
                        >
                            + {{ v.token }}
                        </button>
                    </div>
                    <textarea
                        v-model="form.body"
                        rows="16"
                        class="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 font-mono text-[13px] leading-relaxed dark:border-white/10 dark:bg-white/5"
                    ></textarea>
                    <div v-if="form.errors.body" class="mt-1 text-xs text-rose-500">{{ form.errors.body }}</div>

                    <div class="mt-3 flex items-center gap-3">
                        <button
                            type="button"
                            class="rounded-xl bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white transition hover:bg-[#255f96] disabled:opacity-60"
                            :disabled="form.processing"
                            @click="save(t)"
                        >
                            Сохранить
                        </button>
                        <button
                            type="button"
                            class="text-sm text-slate-500 hover:text-slate-700 dark:text-slate-400"
                            @click="openId = null"
                        >
                            Отмена
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Пагинация -->
        <div v-if="templates.last_page > 1" class="mt-6 flex flex-wrap gap-1">
            <component
                :is="l.url ? Link : 'span'"
                v-for="(l, i) in templates.links"
                :key="i"
                :href="l.url ?? undefined"
                preserve-scroll
                class="min-w-9 rounded-lg px-3 py-1.5 text-center text-sm"
                :class="l.active
                    ? 'bg-[#2E74B5] text-white'
                    : l.url
                        ? 'border border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-white/10 dark:text-slate-300'
                        : 'text-slate-300 dark:text-slate-600'"
                v-html="l.label"
            />
        </div>
    </AppLayout>
</template>
