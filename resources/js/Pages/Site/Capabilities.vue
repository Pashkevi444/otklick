<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import SiteLayout from '@/Layouts/SiteLayout.vue';
import Icon from '@/Components/Icon.vue';
import { featureGroups, integrationsNow, niches, nicheTags, roadmap, steps, type Niche } from '@/marketing';

interface Site {
    phone: string | null;
    email: string | null;
    telegram: string | null;
    legalName: string | null;
    inn: string | null;
    ogrnip: string | null;
    accessNote: string;
}
defineProps<{ site: Site; loginUrl: string }>();

const activeNiche = ref('barbershop');
const activeNicheData = computed<Niche>(() => niches.find((n) => n.key === activeNiche.value) ?? niches[0]);

// Демо-черновики для визуала «импорт с сайта».
const importDrafts = ['Услуги и цены', 'Часы работы и адрес', 'Условия доставки', 'Частые вопросы'];
</script>

<template>
    <Head>
        <title>Возможности «Отклик» — готовые шаблоны под нишу, интеграции, запуск за вечер</title>
        <meta name="description" content="Что умеет «Отклик»: готовые сценарии и база знаний под десятки типов бизнеса, подключение Telegram/ВКонтакте/MAX/WhatsApp и YClients, запуск за один вечер." />
        <meta property="og:title" content="Возможности «Отклик» — шаблоны под нишу и интеграции" />
    </Head>

    <SiteLayout :site="site" :login-url="loginUrl">
        <section class="mx-auto max-w-6xl px-6 pt-16 pb-6 text-center sm:pt-20">
            <h1 data-reveal class="mx-auto max-w-3xl text-4xl font-extrabold tracking-tight text-[#1F4E79] dark:text-sky-200 sm:text-5xl">Возможности «Отклик»</h1>
            <p data-reveal style="transition-delay: 100ms" class="mx-auto mt-5 max-w-2xl text-lg text-slate-600 dark:text-slate-300">Готовые шаблоны под вашу нишу, нужные каналы и CRM, запуск за один вечер.</p>
        </section>

        <!-- Что умеет «Отклик» — маркетинговый бенто по смыслу -->
        <section class="mx-auto max-w-6xl px-6 py-12">
            <div data-reveal class="mb-10 text-center">
                <h2 class="text-3xl font-bold text-[#1F4E79] dark:text-sky-200">Что умеет «Отклик»</h2>
                <p class="mx-auto mt-3 max-w-2xl text-slate-500 dark:text-slate-400">Не список галочек, а пять зон, где помощник закрывает работу администратора целиком.</p>
            </div>
            <div class="grid items-stretch gap-5 lg:grid-cols-3">
                <div v-for="(g, i) in featureGroups" :key="g.title" data-reveal :style="{ transitionDelay: (i % 3) * 70 + 'ms' }" :class="g.accent ? 'lg:col-span-2' : ''">
                    <!-- Флагман — с переливающейся рамкой -->
                    <div v-if="g.accent" class="glow-frame h-full" style="--gf-radius: 1.5rem">
                        <div class="glow-inner group flex h-full flex-col p-6 sm:p-8">
                            <div class="mb-5 flex items-center gap-3">
                                <span class="flex h-12 w-12 flex-none items-center justify-center rounded-2xl bg-gradient-to-br from-[#2E74B5] to-[#5b53c9] text-white shadow-lg shadow-[#2E74B5]/30 transition group-hover:scale-110"><Icon :name="g.icon" class="ico h-6 w-6" /></span>
                                <div>
                                    <div class="text-xl font-bold text-[#1F4E79] dark:text-sky-100">{{ g.title }}</div>
                                    <div class="text-xs font-semibold uppercase tracking-wide text-[#2E74B5] dark:text-sky-300">{{ g.tagline }}</div>
                                </div>
                            </div>
                            <ul class="grid gap-2.5 sm:grid-cols-2 sm:gap-x-6">
                                <li v-for="p in g.points" :key="p" class="flex items-start gap-2.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                                    <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400"><Icon name="check" class="h-3 w-3" /></span>
                                    <span>{{ p }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <!-- Обычная карточка -->
                    <div v-else class="card-hover glass group flex h-full flex-col rounded-3xl p-6 sm:p-7">
                        <div class="mb-4 flex items-center gap-3">
                            <span class="flex h-11 w-11 flex-none items-center justify-center rounded-2xl bg-white/70 text-[#2E74B5] shadow-sm transition group-hover:scale-110 dark:bg-white/10 dark:text-sky-300"><Icon :name="g.icon" class="ico h-5 w-5" /></span>
                            <div>
                                <div class="font-bold text-[#1F4E79] dark:text-sky-200">{{ g.title }}</div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-[#2E74B5]/80 dark:text-sky-300/80">{{ g.tagline }}</div>
                            </div>
                        </div>
                        <ul class="space-y-2.5">
                            <li v-for="p in g.points" :key="p" class="flex items-start gap-2.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                                <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-emerald-500/12 text-emerald-600 dark:text-emerald-400"><Icon name="check" class="h-3 w-3" /></span>
                                <span>{{ p }}</span>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- Готовые шаблоны под нишу -->
        <section class="mx-auto max-w-6xl px-6 py-12">
            <div data-reveal class="mb-10 text-center">
                <div class="inline-flex items-center gap-2 glass rounded-full px-4 py-1.5 text-sm font-medium text-[#2E74B5] dark:text-sky-300">
                    <Icon name="template" class="h-4 w-4" /> Запуск не с нуля
                </div>
                <h2 class="mt-4 text-3xl font-bold text-[#1F4E79] dark:text-sky-200">Готовые шаблоны под вашу нишу</h2>
                <p class="mx-auto mt-3 max-w-2xl text-slate-500 dark:text-slate-400">Десятки типов бизнеса — для каждого свои сценарии и база знаний. Выберите нишу, и останется заменить «…» на свои цены и контакты.</p>
            </div>

            <div data-reveal class="mb-8 flex flex-wrap justify-center gap-2.5">
                <button
                    v-for="n in niches"
                    :key="n.key"
                    type="button"
                    class="niche-chip glass inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-medium transition"
                    :class="activeNiche === n.key ? 'niche-chip-active' : 'text-slate-600 dark:text-slate-300'"
                    @click="activeNiche = n.key"
                >
                    <Icon :name="n.icon" class="h-4 w-4" /><span>{{ n.label }}</span>
                </button>
            </div>

            <div data-reveal class="relative">
                <Transition name="swap" mode="out-in">
                    <div :key="activeNiche" class="grid gap-5 md:grid-cols-2">
                        <div class="glass rounded-3xl p-6">
                            <div class="mb-4 flex items-center gap-2">
                                <Icon name="wand" class="h-5 w-5 text-[#2E74B5] dark:text-sky-300" />
                                <span class="font-semibold text-[#1F4E79] dark:text-sky-200">Сценарии</span>
                                <span class="ml-auto rounded-full bg-[#EAF2FB] px-2.5 py-0.5 text-xs font-medium text-[#1F4E79] dark:bg-white/10 dark:text-sky-200">в один клик</span>
                            </div>
                            <ul class="space-y-2.5">
                                <li v-for="(s, i) in activeNicheData.scenarios" :key="s" class="tpl-row flex items-center gap-3 rounded-xl border border-slate-200/70 bg-white/60 px-4 py-3 text-sm text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200" :style="{ animationDelay: i * 60 + 'ms' }">
                                    <span class="flex h-7 w-7 flex-none items-center justify-center rounded-lg bg-[#2E74B5]/10 text-[#2E74B5] dark:text-sky-300">▸</span>{{ s }}
                                </li>
                            </ul>
                        </div>
                        <div class="glass rounded-3xl p-6">
                            <div class="mb-4 flex items-center gap-2">
                                <Icon name="book" class="h-5 w-5 text-[#2E74B5] dark:text-sky-300" />
                                <span class="font-semibold text-[#1F4E79] dark:text-sky-200">База знаний</span>
                                <span class="ml-auto rounded-full bg-[#EAF2FB] px-2.5 py-0.5 text-xs font-medium text-[#1F4E79] dark:bg-white/10 dark:text-sky-200">заготовки</span>
                            </div>
                            <ul class="space-y-2.5">
                                <li v-for="(k, i) in activeNicheData.knowledge" :key="k" class="tpl-row flex items-center gap-3 rounded-xl border border-slate-200/70 bg-white/60 px-4 py-3 text-sm text-slate-700 dark:border-white/10 dark:bg-white/5 dark:text-slate-200" :style="{ animationDelay: i * 60 + 'ms' }">
                                    <span class="flex h-7 w-7 flex-none items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"><Icon name="check" class="h-4 w-4" /></span>{{ k }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </Transition>
            </div>

            <div data-reveal class="mt-10">
                <div class="mb-4 text-center text-sm font-medium text-slate-600 dark:text-slate-300">Готовые шаблоны уже есть для десятков ниш:</div>
                <div class="mx-auto flex max-w-4xl flex-wrap justify-center gap-2">
                    <span v-for="tag in nicheTags" :key="tag" class="glass rounded-full px-3.5 py-1.5 text-sm text-slate-600 dark:text-slate-300">{{ tag }}</span>
                </div>
            </div>
            <p data-reveal class="mx-auto mt-6 max-w-2xl text-center text-sm text-slate-400 dark:text-slate-500">Не нашли свою? Добавим под вашу нишу. И ещё десятки общих шаблонов, подходящих любому бизнесу — всё редактируется под вас.</p>
        </section>

        <!-- ✨ Импорт базы знаний с сайта (выделенный блок) -->
        <section class="mx-auto max-w-6xl px-6 py-12">
            <div data-reveal class="glow-frame">
                <div class="glow-inner px-6 py-10 sm:px-10">
                    <div class="grid items-center gap-8 lg:grid-cols-2">
                        <div>
                            <div class="inline-flex items-center gap-2 rounded-full bg-[#2E74B5]/10 px-3 py-1 text-xs font-semibold text-[#2E74B5] dark:text-sky-300">
                                <Icon name="rocket" class="h-4 w-4" /> Новинка
                            </div>
                            <h2 class="mt-4 text-3xl font-bold text-[#1F4E79] dark:text-sky-200">База знаний — с вашего сайта за минуты</h2>
                            <p class="mt-3 max-w-xl text-slate-600 dark:text-slate-300">
                                Не хотите заполнять вручную? Дайте ссылку на свой сайт — AI пройдёт по ключевым страницам
                                (услуги, цены, доставка, контакты), сам соберёт записи и сохранит их <span class="font-semibold text-[#1F4E79] dark:text-sky-200">черновиками</span>.
                                Вы проверяете и публикуете нужное в один клик.
                            </p>
                            <div class="mt-5 flex flex-wrap gap-2.5">
                                <span class="glass inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm text-slate-600 dark:text-slate-300"><Icon name="bolt" class="h-4 w-4 text-[#2E74B5] dark:text-sky-300" /> Запуск за минуты</span>
                                <span class="glass inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm text-slate-600 dark:text-slate-300"><Icon name="check" class="h-4 w-4 text-emerald-500" /> Всё — черновиками</span>
                                <span class="glass inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm text-slate-600 dark:text-slate-300"><Icon name="brain" class="h-4 w-4 text-[#2E74B5] dark:text-sky-300" /> Понимает любой сайт</span>
                            </div>
                        </div>

                        <!-- Мини-визуал: сайт → черновики -->
                        <div class="glass rounded-3xl p-5">
                            <div class="flex items-center gap-2 rounded-xl border border-slate-200/70 bg-white/70 px-3 py-2.5 dark:border-white/10 dark:bg-white/5">
                                <Icon name="link" class="h-4 w-4 flex-none text-[#2E74B5] dark:text-sky-300" />
                                <span class="truncate text-sm text-slate-500 dark:text-slate-400">https://ваш-сайт.рф</span>
                                <span class="ml-auto rounded-lg bg-[#2E74B5] px-2.5 py-1 text-xs font-medium text-white">Собрать</span>
                            </div>
                            <div class="my-3 flex items-center justify-center text-[#2E74B5] dark:text-sky-300"><Icon name="rocket" class="ico h-5 w-5" /></div>
                            <div class="space-y-2">
                                <div v-for="d in importDrafts" :key="d" class="flex items-center gap-2.5 rounded-xl border border-slate-200/70 bg-white/60 px-3 py-2.5 text-sm dark:border-white/10 dark:bg-white/5">
                                    <span class="flex h-6 w-6 flex-none items-center justify-center rounded-lg bg-amber-400/15 text-amber-600 dark:text-amber-400"><Icon name="pen" class="h-3.5 w-3.5" /></span>
                                    <span class="truncate text-slate-700 dark:text-slate-200">{{ d }}</span>
                                    <span class="ml-auto rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-500 dark:bg-white/10 dark:text-slate-400">черновик</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Как работает -->
        <section class="mx-auto max-w-6xl px-6 py-16">
            <h2 data-reveal class="mb-12 text-center text-3xl font-bold text-[#1F4E79] dark:text-sky-200">Запуск за один вечер</h2>
            <div class="grid gap-6 sm:grid-cols-3">
                <div v-for="(s, i) in steps" :key="s.n" data-reveal :style="{ transitionDelay: i * 90 + 'ms' }" class="card-hover glass rounded-3xl p-7">
                    <div class="text-4xl font-extrabold text-[#2E74B5]/30 dark:text-sky-300/30">{{ s.n }}</div>
                    <div class="mt-3 font-semibold text-slate-800 dark:text-slate-100">{{ s.title }}</div>
                    <p class="mt-1.5 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ s.text }}</p>
                </div>
            </div>
        </section>

        <!-- Интеграции -->
        <section class="mx-auto max-w-6xl px-6 py-16">
            <div data-reveal class="mb-10 text-center">
                <h2 class="text-3xl font-bold text-[#1F4E79] dark:text-sky-200">Работает там, где ваши клиенты</h2>
                <p class="mt-3 text-slate-500 dark:text-slate-400">Каналы общения и CRM подключаются в пару кликов.</p>
            </div>
            <div data-reveal class="flex flex-wrap justify-center gap-3">
                <span v-for="i in integrationsNow" :key="i" class="glass rounded-full px-5 py-2.5 text-sm font-medium text-[#1F4E79] dark:text-sky-200">{{ i }}</span>
            </div>
            <div data-reveal style="transition-delay: 200ms" class="mx-auto mt-8 max-w-2xl">
                <div class="glass rounded-2xl p-6 text-center">
                    <div class="font-semibold text-[#1F4E79] dark:text-sky-200">Своя CRM? Подключим под вас</div>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">Интеграцию с вашей CRM настроим по договорённости. Сейчас поддерживается <span class="font-medium text-[#1F4E79] dark:text-sky-200">YClients</span> — остальные подключаем индивидуально.</p>
                </div>
            </div>
        </section>

        <!-- Планы -->
        <section class="mx-auto max-w-6xl px-6 py-16">
            <div data-reveal class="mb-10 text-center">
                <h2 class="text-3xl font-bold text-[#1F4E79] dark:text-sky-200">Планы по внедрению инструментов</h2>
                <p class="mx-auto mt-3 max-w-2xl text-slate-500 dark:text-slate-400">Над чем работаем дальше. Этого пока нет в продукте — добавляем по мере готовности.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="(r, i) in roadmap" :key="r.title" data-reveal :style="{ transitionDelay: i * 70 + 'ms' }" class="glass rounded-3xl p-6">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/70 text-[#2E74B5] dark:bg-white/10 dark:text-sky-300"><Icon :name="r.icon" class="ico h-5 w-5" /></span>
                        <span class="font-semibold text-[#1F4E79] dark:text-sky-200">{{ r.title }}</span>
                        <span class="ml-auto rounded-full border border-white/60 bg-white/40 px-2.5 py-0.5 text-[11px] text-slate-400 backdrop-blur dark:border-white/10 dark:bg-white/5 dark:text-slate-500">в планах</span>
                    </div>
                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ r.text }}</p>
                </div>
            </div>
            <div data-reveal class="mt-10 text-center">
                <Link href="/tarify" class="inline-flex items-center gap-1.5 font-medium text-[#2E74B5] hover:underline dark:text-sky-300">Посмотреть тарифы →</Link>
            </div>
        </section>
    </SiteLayout>
</template>
