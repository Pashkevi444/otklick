<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface Business {
    name: string;
    plan: string;
    planLabel: string;
    phone: string | null;
    address: string | null;
    working_hours: string | null;
    description: string | null;
    website: string | null;
    avatar_url: string | null;
}

const props = defineProps<{ business: Business }>();

const initials = computed<string>(() =>
    props.business.name
        .split(/\s+/)
        .slice(0, 2)
        .map((w) => w.charAt(0))
        .join('')
        .toUpperCase() || '🏪',
);

const websiteHref = computed<string | null>(() => {
    const w = props.business.website;
    if (!w) return null;
    return /^https?:\/\//i.test(w) ? w : `https://${w}`;
});

interface Fact {
    icon: string;
    label: string;
    value: string;
    href?: string;
}

const facts = computed<Fact[]>(() => {
    const out: Fact[] = [];
    if (props.business.phone) out.push({ icon: '📞', label: 'Телефон', value: props.business.phone, href: `tel:${props.business.phone}` });
    if (props.business.working_hours) out.push({ icon: '🕑', label: 'Часы работы', value: props.business.working_hours });
    if (props.business.address) out.push({ icon: '📍', label: 'Адрес', value: props.business.address });
    if (websiteHref.value) out.push({ icon: '🌐', label: 'Сайт', value: props.business.website as string, href: websiteHref.value });
    return out;
});

const shortcuts = [
    { label: 'База знаний', icon: '📚', href: '/cabinet/knowledge' },
    { label: 'Диалоги', icon: '💬', href: '/cabinet/conversations' },
    { label: 'Каналы', icon: '🔌', href: '/cabinet/channels' },
    { label: 'Виджет на сайт', icon: '🪟', href: '/cabinet/widget' },
];
</script>

<template>
    <Head title="Карточка бизнеса" />

    <AppLayout>
        <!-- Карточка бизнеса -->
        <div class="ui-scope overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-white/10">
            <div class="h-24 bg-gradient-to-r from-[#2E74B5] to-[#1F4E79] sm:h-28"></div>
            <div class="px-5 pb-6 sm:px-8">
                <div class="flex flex-col gap-x-5 gap-y-3 sm:flex-row sm:items-start">
                    <div class="-mt-12 flex h-24 w-24 flex-none items-center justify-center overflow-hidden rounded-2xl border-4 border-white bg-[#EAF2FB] text-2xl font-bold text-[#1F4E79] shadow-md sm:h-28 sm:w-28 dark:border-slate-800 dark:bg-white/10 dark:text-sky-200">
                        <img v-if="business.avatar_url" :src="business.avatar_url" alt="Аватар бизнеса" class="h-full w-full object-cover" />
                        <span v-else>{{ initials }}</span>
                    </div>
                    <div class="min-w-0 flex-1 pt-1 sm:pt-5">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="min-w-0 max-w-full break-words text-xl font-bold text-[#1F4E79] sm:text-2xl dark:text-sky-200">{{ business.name }}</h2>
                            <span class="flex-none rounded-full bg-[#EAF2FB] px-2.5 py-0.5 text-xs font-medium text-[#2E74B5] dark:bg-white/10 dark:text-sky-200">Тариф «{{ business.planLabel }}»</span>
                        </div>
                        <p v-if="business.description" class="mt-1.5 max-w-2xl break-words text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ business.description }}</p>
                        <p v-else class="mt-1.5 text-sm text-slate-400">Добавьте описание бизнеса — клиенты увидят, чем вы занимаетесь.</p>
                    </div>
                    <Link
                        href="/cabinet/profile"
                        class="flex-none self-start rounded-xl bg-[#2E74B5] px-4 py-2 text-center text-sm font-medium text-white shadow-sm transition hover:-translate-y-0.5 hover:bg-[#255f96] sm:mt-5"
                    >
                        Редактировать профиль
                    </Link>
                </div>

                <!-- Контакты -->
                <div v-if="facts.length" class="mt-6 grid gap-3 sm:grid-cols-2">
                    <component
                        :is="f.href ? 'a' : 'div'"
                        v-for="f in facts"
                        :key="f.label"
                        :href="f.href"
                        :target="f.href && f.href.startsWith('http') ? '_blank' : undefined"
                        class="flex items-start gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-white/10 dark:bg-white/5"
                        :class="f.href ? 'transition hover:border-[#2E74B5]/40' : ''"
                    >
                        <span class="text-lg leading-none">{{ f.icon }}</span>
                        <span class="min-w-0">
                            <span class="block text-xs text-slate-400">{{ f.label }}</span>
                            <span class="block truncate text-sm font-medium text-slate-700 dark:text-slate-200">{{ f.value }}</span>
                        </span>
                    </component>
                </div>
                <p v-else class="mt-6 text-sm text-slate-400">Заполните контакты в профиле, чтобы бот мог делиться ими с клиентами.</p>
            </div>
        </div>

        <!-- Быстрые переходы -->
        <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Link
                v-for="s in shortcuts"
                :key="s.href"
                :href="s.href"
                class="ui-scope flex flex-col items-start gap-2 rounded-2xl border border-slate-200 bg-white p-4 transition hover:-translate-y-0.5 hover:border-[#2E74B5]/40 dark:border-white/10"
            >
                <span class="text-2xl">{{ s.icon }}</span>
                <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ s.label }}</span>
            </Link>
        </div>
    </AppLayout>
</template>
