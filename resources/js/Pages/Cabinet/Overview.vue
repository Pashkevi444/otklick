<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';

const page = usePage();
const features = computed(() => page.props.auth.user?.tenant?.features);
const isOwner = computed<boolean>(() => page.props.auth.user?.isOwner ?? false);
const allowedSections = computed<string[]>(() => page.props.auth.user?.allowedSections ?? []);

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
    if (props.business.phone) out.push({ icon: 'phone', label: 'Телефон', value: props.business.phone, href: `tel:${props.business.phone}` });
    if (props.business.working_hours) out.push({ icon: 'clock', label: 'Часы работы', value: props.business.working_hours });
    if (props.business.address) out.push({ icon: 'pin', label: 'Адрес', value: props.business.address });
    if (websiteHref.value) out.push({ icon: 'globe', label: 'Сайт', value: props.business.website as string, href: websiteHref.value });
    return out;
});

interface Shortcut {
    label: string;
    icon: string;
    accent: string;
    href: string;
    section?: string; // раздел из allowedSections (доступ оператора)
    feature?: 'analytics' | 'clientBase' | 'crm'; // возможность тарифа
    owner?: boolean; // только владелец
}

// Главные плашки бизнеса — с гейтингом (тариф/роль/доступ), чтобы недоступные
// не показывались и не давали 403.
const allShortcuts: Shortcut[] = [
    { label: 'Лиды', icon: 'bolt', accent: 'bg-[#2E74B5]/12 text-[#2E74B5] dark:bg-sky-400/15 dark:text-sky-300', href: '/cabinet/leads', section: 'leads', feature: 'crm' },
    { label: 'Сделки', icon: 'target', accent: 'bg-emerald-500/12 text-emerald-600 dark:bg-emerald-400/15 dark:text-emerald-300', href: '/cabinet/deals', section: 'deals', feature: 'crm' },
    { label: 'Диалоги', icon: 'chat', accent: 'bg-violet-500/12 text-violet-600 dark:bg-violet-400/15 dark:text-violet-300', href: '/cabinet/conversations', section: 'conversations' },
    { label: 'База клиентов', icon: 'users', accent: 'bg-violet-500/12 text-violet-600 dark:bg-violet-400/15 dark:text-violet-300', href: '/cabinet/clients', section: 'clients', feature: 'clientBase' },
    { label: 'Аналитика', icon: 'chart', accent: 'bg-emerald-500/12 text-emerald-600 dark:bg-emerald-400/15 dark:text-emerald-300', href: '/cabinet/analytics', section: 'analytics', feature: 'analytics' },
    { label: 'Команда', icon: 'users', accent: 'bg-amber-500/12 text-amber-600 dark:bg-amber-400/15 dark:text-amber-300', href: '/cabinet/team', owner: true },
];

const shortcuts = computed<Shortcut[]>(() =>
    allShortcuts.filter((s) => {
        if (s.owner && !isOwner.value) return false;
        if (s.feature && !features.value?.[s.feature]) return false;
        if (s.section && !allowedSections.value.includes(s.section)) return false;
        return true;
    }),
);
</script>

<template>
    <Head title="Карточка бизнеса" />

    <AppLayout>
        <!-- Карточка бизнеса -->
        <div class="ui-scope overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-white/10">
            <div class="profile-banner relative h-28 overflow-hidden sm:h-32">
                <span class="pb-orb pb-orb-1"></span>
                <span class="pb-orb pb-orb-2"></span>
                <span class="pb-orb pb-orb-3"></span>
                <span class="pb-grid"></span>
                <span class="pb-sheen"></span>
                <Icon name="sparkle" class="absolute right-5 top-4 h-16 w-16 text-white/15 sm:right-8" />
            </div>
            <div class="px-5 pb-6 sm:px-8">
                <div class="flex flex-col gap-x-5 gap-y-3 sm:flex-row sm:items-start">
                    <div class="relative z-10 -mt-12 flex h-24 w-24 flex-none items-center justify-center overflow-hidden rounded-2xl border-4 border-white bg-[#EAF2FB] text-2xl font-bold text-[#1F4E79] shadow-md sm:h-28 sm:w-28 dark:border-slate-800 dark:bg-white/10 dark:text-sky-200">
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
                        class="group flex items-center gap-3 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3 dark:border-white/10 dark:bg-white/5"
                        :class="f.href ? 'transition hover:-translate-y-0.5 hover:border-[#2E74B5]/40 hover:shadow-sm' : ''"
                    >
                        <span class="flex h-9 w-9 flex-none items-center justify-center rounded-xl bg-white text-[#2E74B5] shadow-sm transition group-hover:scale-110 dark:bg-white/10 dark:text-sky-300"><Icon :name="f.icon" class="h-5 w-5" /></span>
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
        <div v-if="shortcuts.length" class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Link
                v-for="s in shortcuts"
                :key="s.href"
                :href="s.href"
                class="ui-scope group flex flex-col items-start gap-3 rounded-2xl border border-slate-200 bg-white p-4 transition hover:-translate-y-1 hover:border-[#2E74B5]/40 hover:shadow-lg hover:shadow-slate-200/60 dark:border-white/10 dark:hover:shadow-black/30"
            >
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl transition group-hover:scale-110" :class="s.accent"><Icon :name="s.icon" class="h-6 w-6" /></span>
                <span class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ s.label }}</span>
            </Link>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Живая шапка карточки бизнеса: переливающийся градиент + плавающие свечения +
   точечная сетка + диагональный блик. */
.profile-banner {
    background: linear-gradient(120deg, #2e74b5, #1f4e79 42%, #4f46e5 78%, #2e74b5);
    background-size: 240% 240%;
    animation: pbPan 14s ease infinite;
}
.pb-orb {
    position: absolute;
    border-radius: 9999px;
    filter: blur(26px);
    opacity: 0.55;
    pointer-events: none;
    will-change: transform;
}
.pb-orb-1 { width: 170px; height: 170px; background: #7cc0ff; top: -70px; left: 6%; animation: pbFloat 9s ease-in-out infinite; }
.pb-orb-2 { width: 130px; height: 130px; background: #a78bfa; top: -30px; right: 16%; animation: pbFloat 12s ease-in-out infinite reverse; }
.pb-orb-3 { width: 110px; height: 110px; background: #5eead4; bottom: -60px; left: 44%; animation: pbFloat 10s ease-in-out infinite; }
.pb-grid {
    position: absolute;
    inset: 0;
    background-image: radial-gradient(rgba(255, 255, 255, 0.16) 1px, transparent 1.4px);
    background-size: 16px 16px;
    mask-image: linear-gradient(180deg, #000, transparent);
    -webkit-mask-image: linear-gradient(180deg, #000, transparent);
    pointer-events: none;
}
.pb-sheen {
    position: absolute;
    top: 0;
    left: -120%;
    width: 55%;
    height: 100%;
    background: linear-gradient(115deg, transparent, rgba(255, 255, 255, 0.22), transparent);
    transform: skewX(-18deg);
    animation: pbSheen 6s ease-in-out infinite;
    pointer-events: none;
}
@keyframes pbPan {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}
@keyframes pbFloat {
    0%, 100% { transform: translate(0, 0); }
    50% { transform: translate(20px, 14px); }
}
@keyframes pbSheen {
    0% { left: -120%; }
    55%, 100% { left: 160%; }
}
@media (prefers-reduced-motion: reduce) {
    .profile-banner, .pb-orb, .pb-sheen { animation: none; }
    .pb-sheen { display: none; }
}
</style>
