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
        <p class="mb-4 max-w-3xl text-sm text-muted">
            Промпт-инструкция бота под каждую нишу. Здесь редактируется только «голова» промпта (тон и правила
            поведения). Стандартный «хвост» (правила записи, эскалации, отмены и блоки данных) собирается в коде и
            одинаков для всех. Бизнес получает промпт своей ниши (по типу бизнеса); ниши без своего промпта берут
            «Универсальный».
        </p>

        <!-- Доступные переменные -->
        <div class="otk-card mb-6 p-4">
            <div class="mb-2 text-[11px] font-bold uppercase tracking-[0.09em] text-muted2">
                Переменные (подставляются ботом)
            </div>
            <div class="flex flex-wrap gap-2">
                <span
                    v-for="v in variables"
                    :key="v.token"
                    class="inline-flex items-center gap-1.5 rounded-lg bg-active px-2.5 py-1 text-xs"
                    :title="v.desc"
                >
                    <code class="font-mono text-brand">{{ v.token }}</code>
                    <span class="text-muted">— {{ v.desc }}</span>
                </span>
            </div>
        </div>

        <!-- Список ниш -->
        <div class="space-y-3">
            <div
                v-for="t in templates.data"
                :key="t.id"
                class="otk-card overflow-hidden"
            >
                <button
                    type="button"
                    class="flex w-full items-center gap-3 px-5 py-4 text-left transition hover:bg-hoverbg"
                    @click="toggle(t)"
                >
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-2">
                            <span class="font-semibold text-ink">{{ t.name }}</span>
                            <span
                                class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="t.business_type
                                    ? 'bg-active text-brand'
                                    : 'bg-[#EE8A5C]/15 text-warm'"
                            >
                                {{ t.business_type ?? 'по умолчанию' }}
                            </span>
                        </div>
                        <div class="mt-1 truncate text-sm text-muted">{{ preview(t.body) }}</div>
                    </div>
                    <span class="text-muted2 transition" :class="openId === t.id ? 'rotate-180' : ''">▾</span>
                </button>

                <!-- Редактор промпта ниши -->
                <div v-if="openId === t.id" class="border-t border-line px-5 py-4">
                    <label class="mb-1 block text-xs font-medium text-muted">Название</label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="mb-1 w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none placeholder:text-muted2 focus:border-brand"
                    />
                    <div v-if="form.errors.name" class="mb-2 text-xs text-rose-500">{{ form.errors.name }}</div>

                    <label class="mb-1 mt-3 block text-xs font-medium text-muted">
                        Тело промпта (можно вставлять переменные {{ }})
                    </label>
                    <div class="mb-1.5 flex flex-wrap gap-1.5">
                        <button
                            v-for="v in variables"
                            :key="v.token"
                            type="button"
                            class="rounded-lg bg-chip px-2 py-0.5 font-mono text-[11px] text-brand transition hover:bg-active"
                            @click="insertVar(v.token)"
                        >
                            + {{ v.token }}
                        </button>
                    </div>
                    <textarea
                        v-model="form.body"
                        rows="16"
                        class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 font-mono text-[13px] leading-relaxed text-ink outline-none focus:border-brand"
                    ></textarea>
                    <div v-if="form.errors.body" class="mt-1 text-xs text-rose-500">{{ form.errors.body }}</div>

                    <div class="mt-3 flex items-center gap-3">
                        <button
                            type="button"
                            class="otk-btn-primary disabled:opacity-60"
                            :disabled="form.processing"
                            @click="save(t)"
                        >
                            Сохранить
                        </button>
                        <button
                            type="button"
                            class="text-sm font-semibold text-muted hover:text-ink"
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
                class="min-w-9 rounded-xl px-3 py-1.5 text-center text-sm font-semibold"
                :class="l.active
                    ? 'bg-brand text-white'
                    : l.url
                        ? 'border border-line bg-panel text-muted hover:bg-hoverbg'
                        : 'text-muted2'"
                v-html="l.label"
            />
        </div>
    </AppLayout>
</template>
