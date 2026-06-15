<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';

interface Site {
    heroTitle: string;
    heroSubtitle: string;
    phone: string | null;
    email: string | null;
    telegram: string | null;
    accessNote: string;
}

const props = defineProps<{ site: Site; loginUrl: string }>();

const tgUrl = computed(() => (props.site.telegram ? `https://t.me/${props.site.telegram}` : null));

const benefits = [
    { title: 'Ни одной потерянной заявки', text: 'Бот отвечает 24/7 — вечером, в выходные и в пик, когда вы не у телефона.' },
    { title: 'Ответ за секунды', text: 'Клиент не ждёт и не уходит к конкуренту: AI отвечает мгновенно и по делу.' },
    { title: 'Отвечает по вашему бизнесу', text: 'Цены, услуги, часы работы, условия — строго из вашей базы знаний, без выдумок.' },
    { title: 'Записывает клиентов', text: 'Подбирает услугу и время и оформляет запись в вашей CRM (YClients).' },
];

const steps = [
    { n: '1', title: 'Подключите канал', text: 'Telegram или WhatsApp за пару минут — по токену бота.' },
    { n: '2', title: 'Заполните базу знаний', text: 'Услуги, цены, частые вопросы, примеры работ.' },
    { n: '3', title: 'Бот работает за вас', text: 'Отвечает клиентам и записывает, сложные случаи передаёт администратору.' },
];
</script>

<template>
    <Head>
        <title>Отклик — AI-администратор для локального бизнеса: автоответы в Telegram и WhatsApp, запись клиентов</title>
        <meta
            name="description"
            content="Отклик — AI-администратор для салонов, барбершопов, клиник и сервиса. Мгновенно отвечает клиентам в Telegram и WhatsApp по вашей базе знаний и записывает в CRM. Не теряйте заявки 24/7."
        />
        <meta name="keywords" content="AI-администратор, чат-бот для бизнеса, автоответы WhatsApp, автоответы Telegram, запись клиентов, не терять заявки, бот для записи, YClients" />
        <meta property="og:type" content="website" />
        <meta property="og:title" content="Отклик — AI-администратор для локального бизнеса" />
        <meta
            property="og:description"
            content="Мгновенные ответы клиентам в Telegram и WhatsApp по вашей базе знаний и запись в CRM. Не теряйте ни одного обращения."
        />
    </Head>

    <div class="min-h-screen bg-white text-slate-800">
        <!-- Шапка -->
        <header class="border-b border-slate-100">
            <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
                <span class="font-bold text-lg text-[#1F4E79]">Отклик</span>
                <nav class="hidden sm:flex items-center gap-6 text-sm text-slate-600">
                    <a href="#benefits" class="hover:text-[#1F4E79]">Возможности</a>
                    <a href="#how" class="hover:text-[#1F4E79]">Как работает</a>
                    <a href="#contacts" class="hover:text-[#1F4E79]">Контакты</a>
                </nav>
                <a :href="loginUrl" class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96]">
                    Войти
                </a>
            </div>
        </header>

        <!-- Hero -->
        <section class="max-w-6xl mx-auto px-6 py-20 text-center">
            <div class="text-sm font-medium text-[#2E74B5] uppercase tracking-wide mb-3">
                AI-администратор для локального бизнеса
            </div>
            <h1 class="text-4xl sm:text-5xl font-bold text-[#1F4E79] max-w-3xl mx-auto leading-tight">
                {{ site.heroTitle }}
            </h1>
            <p class="mt-6 text-lg text-slate-600 max-w-2xl mx-auto leading-relaxed">{{ site.heroSubtitle }}</p>
            <div class="mt-8 flex flex-col items-center gap-3">
                <a href="#contacts" class="rounded-xl bg-[#2E74B5] px-7 py-3 font-semibold text-white hover:bg-[#255f96]">
                    Получить доступ
                </a>
                <p class="text-sm text-slate-400">{{ site.accessNote }}</p>
            </div>
        </section>

        <!-- Возможности -->
        <section id="benefits" class="bg-slate-50 border-y border-slate-100">
            <div class="max-w-6xl mx-auto px-6 py-16">
                <h2 class="text-2xl font-bold text-[#1F4E79] text-center mb-10">Что делает «Отклик»</h2>
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-5">
                    <div v-for="b in benefits" :key="b.title" class="bg-white rounded-2xl border border-slate-200 p-6">
                        <div class="font-semibold text-[#1F4E79]">{{ b.title }}</div>
                        <p class="mt-2 text-sm text-slate-600 leading-relaxed">{{ b.text }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Как работает -->
        <section id="how" class="max-w-6xl mx-auto px-6 py-16">
            <h2 class="text-2xl font-bold text-[#1F4E79] text-center mb-10">Как это работает</h2>
            <div class="grid sm:grid-cols-3 gap-6">
                <div v-for="s in steps" :key="s.n" class="text-center">
                    <div class="w-10 h-10 rounded-full bg-[#2E74B5] text-white font-bold flex items-center justify-center mx-auto">
                        {{ s.n }}
                    </div>
                    <div class="mt-3 font-semibold text-slate-800">{{ s.title }}</div>
                    <p class="mt-1 text-sm text-slate-600">{{ s.text }}</p>
                </div>
            </div>
        </section>

        <!-- Контакты -->
        <section id="contacts" class="bg-[#1F4E79] text-white">
            <div class="max-w-6xl mx-auto px-6 py-16 text-center">
                <h2 class="text-2xl font-bold">Получить доступ к «Отклику»</h2>
                <p class="mt-2 text-blue-100">{{ site.accessNote }}</p>
                <div class="mt-8 flex flex-wrap items-center justify-center gap-4 text-sm">
                    <a v-if="site.phone" :href="`tel:${site.phone}`" class="rounded-xl bg-white/10 hover:bg-white/20 px-5 py-3">
                        📞 {{ site.phone }}
                    </a>
                    <a v-if="site.email" :href="`mailto:${site.email}`" class="rounded-xl bg-white/10 hover:bg-white/20 px-5 py-3">
                        ✉️ {{ site.email }}
                    </a>
                    <a v-if="tgUrl" :href="tgUrl" target="_blank" class="rounded-xl bg-white/10 hover:bg-white/20 px-5 py-3">
                        ✈️ Telegram @{{ site.telegram }}
                    </a>
                </div>
                <div class="mt-8">
                    <a :href="loginUrl" class="text-blue-100 underline hover:text-white">Уже есть доступ? Войти →</a>
                </div>
            </div>
        </section>

        <footer class="border-t border-slate-100">
            <div class="max-w-6xl mx-auto px-6 py-8 text-sm text-slate-400 flex flex-wrap justify-between gap-2">
                <span>© Отклик — AI-администратор для локального бизнеса</span>
                <Link href="/contacts" class="hover:text-slate-600">Контакты</Link>
            </div>
        </footer>
    </div>
</template>
