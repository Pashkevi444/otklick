<script setup lang="ts">
import { computed } from 'vue';
import { Link, router, usePage } from '@inertiajs/vue3';

defineProps<{ title?: string }>();

const page = usePage();
const user = computed(() => page.props.auth.user);
const flash = computed(() => page.props.flash);

interface NavItem {
    label: string;
    href: string;
}

const navItems = computed<NavItem[]>(() => {
    if (user.value?.role === 'super_admin') {
        return [
            { label: 'Бизнесы', href: '/admin/tenants' },
            { label: 'Сайт', href: '/admin/site' },
        ];
    }

    return [
        { label: 'Дашборд', href: '/cabinet' },
        { label: 'Каналы', href: '/cabinet/channels' },
        { label: 'Профиль', href: '/cabinet/profile' },
        { label: 'База знаний', href: '/cabinet/knowledge' },
        { label: 'Интеграции', href: '/cabinet/integrations' },
    ];
});

const isActive = (href: string): boolean =>
    page.url === href || (href !== '/cabinet' && page.url.startsWith(href));

const logout = (): void => {
    router.post('/logout');
};
</script>

<template>
    <div class="min-h-screen bg-slate-50 text-slate-800">
        <header class="bg-white border-b border-slate-200">
            <div class="max-w-6xl mx-auto px-6 flex items-center justify-between h-16">
                <div class="flex items-center gap-8">
                    <span class="font-bold text-[#1F4E79]">Отклик</span>
                    <nav class="flex items-center gap-1">
                        <Link
                            v-for="item in navItems"
                            :key="item.href"
                            :href="item.href"
                            class="px-3 py-2 rounded-lg text-sm font-medium transition"
                            :class="isActive(item.href)
                                ? 'bg-slate-100 text-[#1F4E79]'
                                : 'text-slate-600 hover:bg-slate-50'"
                        >
                            {{ item.label }}
                        </Link>
                    </nav>
                </div>
                <div class="flex items-center gap-4 text-sm">
                    <Link href="/account/password" class="text-right hover:opacity-80">
                        <div class="font-medium text-slate-700">{{ user?.name }}</div>
                        <div class="text-slate-400 text-xs">
                            {{ user?.tenant?.name ?? user?.roleLabel }}
                        </div>
                    </Link>
                    <button
                        type="button"
                        class="text-slate-500 hover:text-slate-800 font-medium"
                        @click="logout"
                    >
                        Выйти
                    </button>
                </div>
            </div>
        </header>

        <main class="max-w-6xl mx-auto px-6 py-8">
            <div v-if="flash.success" class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-700 px-4 py-2 text-sm">
                {{ flash.success }}
            </div>
            <div v-if="flash.error" class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-2 text-sm">
                {{ flash.error }}
            </div>

            <h1 v-if="title" class="text-2xl font-bold text-[#1F4E79] mb-6">{{ title }}</h1>
            <slot />
        </main>
    </div>
</template>
