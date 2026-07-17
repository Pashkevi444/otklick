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
    feature?: 'analytics' | 'clientBase'; // возможность тарифа
    owner?: boolean; // только владелец
}

// Главные плашки бизнеса — с гейтингом (тариф/роль/доступ), чтобы недоступные
// не показывались и не давали 403.
const allShortcuts: Shortcut[] = [
    { label: 'Лиды', icon: 'chat', accent: 'bg-active text-brand', href: '/cabinet/conversations', section: 'conversations' },
    { label: 'База клиентов', icon: 'users', accent: 'bg-violet-500/12 text-violet-600 dark:text-violet-300', href: '/cabinet/clients', section: 'clients', feature: 'clientBase' },
    { label: 'Аналитика', icon: 'chart', accent: 'bg-emerald-500/12 text-emerald-600 dark:text-emerald-400', href: '/cabinet/analytics', section: 'analytics', feature: 'analytics' },
    { label: 'Команда', icon: 'users', accent: 'bg-[#EE8A5C]/15 text-warm', href: '/cabinet/team', owner: true },
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

    <AppLayout title="Карточка бизнеса">
        <!-- Карточка бизнеса -->
        <div class="overflow-hidden rounded-3xl border border-line bg-panel">
            <!-- Шапка: градиент бренд→фиолет, мягкие орбы + точечная сетка -->
            <div
                class="relative h-28 overflow-hidden sm:h-32"
                style="background: linear-gradient(135deg, #2b5ce0 0%, #4d6ef0 45%, #7c5cfc 100%)"
            >
                <span
                    aria-hidden="true"
                    class="pointer-events-none absolute -top-28 right-[10%] h-64 w-64 rounded-full"
                    style="background: radial-gradient(circle, rgba(255, 255, 255, 0.22), transparent 66%)"
                ></span>
                <span
                    aria-hidden="true"
                    class="pointer-events-none absolute -bottom-32 left-[38%] h-56 w-56 rounded-full"
                    style="background: radial-gradient(circle, rgba(238, 138, 92, 0.4), transparent 64%)"
                ></span>
                <span
                    aria-hidden="true"
                    class="pointer-events-none absolute inset-0"
                    style="
                        background-image: radial-gradient(rgba(255, 255, 255, 0.16) 1px, transparent 1.4px);
                        background-size: 16px 16px;
                        -webkit-mask-image: linear-gradient(180deg, #000, transparent);
                        mask-image: linear-gradient(180deg, #000, transparent);
                    "
                ></span>
                <Icon name="sparkle" class="absolute right-5 top-4 h-16 w-16 text-white/15 sm:right-8" />
            </div>
            <div class="px-5 pb-6 sm:px-8">
                <div class="flex flex-col gap-x-5 gap-y-3 sm:flex-row sm:items-start">
                    <div class="relative z-10 -mt-12 flex h-24 w-24 flex-none items-center justify-center overflow-hidden rounded-2xl border-4 border-panel bg-active text-2xl font-bold text-brand shadow-md sm:h-28 sm:w-28">
                        <img v-if="business.avatar_url" :src="business.avatar_url" alt="Аватар бизнеса" class="h-full w-full object-cover" />
                        <span v-else>{{ initials }}</span>
                    </div>
                    <div class="min-w-0 flex-1 pt-1 sm:pt-5">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2 class="min-w-0 max-w-full break-words font-display text-xl font-semibold text-ink sm:text-2xl">{{ business.name }}</h2>
                            <span class="flex-none rounded-full bg-active px-2.5 py-0.5 text-xs font-semibold text-brand">Тариф «{{ business.planLabel }}»</span>
                        </div>
                        <p v-if="business.description" class="mt-1.5 max-w-2xl break-words text-sm leading-relaxed text-muted">{{ business.description }}</p>
                        <p v-else class="mt-1.5 text-sm text-muted2">Добавьте описание бизнеса — клиенты увидят, чем вы занимаетесь.</p>
                    </div>
                    <Link
                        href="/cabinet/profile"
                        class="otk-btn-primary flex-none self-start text-center transition hover:-translate-y-0.5 sm:mt-5"
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
                        class="group flex items-center gap-3 rounded-xl border border-line bg-chip px-4 py-3"
                        :class="f.href ? 'transition hover:-translate-y-0.5 hover:border-brand/40 hover:shadow-sm' : ''"
                    >
                        <span class="flex h-9 w-9 flex-none items-center justify-center rounded-xl bg-panel text-brand shadow-sm transition group-hover:scale-110"><Icon :name="f.icon" class="h-5 w-5" /></span>
                        <span class="min-w-0">
                            <span class="block text-xs text-muted2">{{ f.label }}</span>
                            <span class="block truncate text-sm font-medium text-ink">{{ f.value }}</span>
                        </span>
                    </component>
                </div>
                <p v-else class="mt-6 text-sm text-muted2">Заполните контакты в профиле, чтобы бот мог делиться ими с клиентами.</p>
            </div>
        </div>

        <!-- Быстрые переходы -->
        <div v-if="shortcuts.length" class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <Link
                v-for="s in shortcuts"
                :key="s.href"
                :href="s.href"
                class="group flex flex-col items-start gap-3 rounded-2xl border border-line bg-panel p-4 transition hover:-translate-y-0.5 hover:border-brand/40 hover:shadow-lg hover:shadow-[rgba(16,28,51,0.06)]"
            >
                <span class="flex h-[42px] w-[42px] items-center justify-center rounded-xl transition group-hover:scale-110" :class="s.accent"><Icon :name="s.icon" class="h-6 w-6" /></span>
                <span class="text-sm font-semibold text-ink">{{ s.label }}</span>
            </Link>
        </div>
    </AppLayout>
</template>
