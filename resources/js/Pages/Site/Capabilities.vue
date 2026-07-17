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
            <h1 data-reveal class="font-display mx-auto max-w-3xl text-4xl font-bold tracking-tight text-ink sm:text-5xl">Возможности «Отклик»</h1>
            <p data-reveal style="transition-delay: 100ms" class="mx-auto mt-5 max-w-2xl text-lg text-muted">Готовые шаблоны под вашу нишу, нужные каналы и CRM, запуск за один вечер.</p>
        </section>

        <!-- Что умеет «Отклик» — маркетинговый бенто по смыслу -->
        <section class="mx-auto max-w-6xl px-6 py-12">
            <div data-reveal class="mb-10 text-center">
                <h2 class="font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">Что умеет «Отклик»</h2>
                <p class="mx-auto mt-3 max-w-2xl text-muted">Не список галочек, а пять зон, где помощник закрывает работу администратора целиком.</p>
            </div>
            <div class="grid items-stretch gap-5 lg:grid-cols-3">
                <div v-for="(g, i) in featureGroups" :key="g.title" data-reveal :style="{ transitionDelay: (i % 3) * 70 + 'ms' }" :class="g.accent ? 'lg:col-span-2' : ''">
                    <!-- Флагман — синий градиент, как в макете -->
                    <div v-if="g.accent" class="card-hover group flex h-full flex-col rounded-2xl bg-gradient-to-br from-[#2B5CE0] to-[#7C5CFC] p-6 text-white shadow-xl shadow-[#2B5CE0]/30 sm:p-8">
                        <div class="mb-5 flex items-center gap-3">
                            <span class="flex h-12 w-12 flex-none items-center justify-center rounded-2xl bg-white/15 text-white transition group-hover:scale-110"><Icon :name="g.icon" class="ico h-6 w-6" /></span>
                            <div>
                                <div class="font-display text-xl font-semibold tracking-tight text-white">{{ g.title }}</div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-white/70">{{ g.tagline }}</div>
                            </div>
                        </div>
                        <ul class="grid gap-2.5 sm:grid-cols-2 sm:gap-x-6">
                            <li v-for="p in g.points" :key="p" class="flex items-start gap-2.5 text-sm leading-relaxed text-white/90">
                                <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-white/20 text-white"><Icon name="check" class="h-3 w-3" /></span>
                                <span>{{ p }}</span>
                            </li>
                        </ul>
                    </div>
                    <!-- Обычная карточка -->
                    <div v-else class="card-hover group flex h-full flex-col rounded-2xl border border-line bg-panel p-6 sm:p-7">
                        <div class="mb-4 flex items-center gap-3">
                            <span class="flex h-11 w-11 flex-none items-center justify-center rounded-2xl bg-active text-brand transition group-hover:scale-110"><Icon :name="g.icon" class="ico h-5 w-5" /></span>
                            <div>
                                <div class="font-display font-medium tracking-tight text-ink">{{ g.title }}</div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-brand">{{ g.tagline }}</div>
                            </div>
                        </div>
                        <ul class="space-y-2.5">
                            <li v-for="p in g.points" :key="p" class="flex items-start gap-2.5 text-sm leading-relaxed text-muted">
                                <span class="mt-0.5 flex h-5 w-5 flex-none items-center justify-center rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400"><Icon name="check" class="h-3 w-3" /></span>
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
                <div class="inline-flex items-center gap-2 glass rounded-full px-4 py-1.5 text-sm font-semibold text-brand">
                    <Icon name="template" class="h-4 w-4" /> Запуск не с нуля
                </div>
                <h2 class="font-display mt-4 text-3xl font-semibold tracking-tight text-ink sm:text-4xl">Готовые шаблоны под вашу нишу</h2>
                <p class="mx-auto mt-3 max-w-2xl text-muted">Десятки типов бизнеса — для каждого свои сценарии и база знаний. Выберите нишу, и останется заменить «…» на свои цены и контакты.</p>
            </div>

            <div data-reveal class="mb-8 flex flex-wrap justify-center gap-2.5">
                <button
                    v-for="n in niches"
                    :key="n.key"
                    type="button"
                    class="niche-chip glass inline-flex items-center gap-1.5 rounded-full px-4 py-2 text-sm font-semibold transition"
                    :class="activeNiche === n.key ? 'niche-chip-active' : 'text-muted'"
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
                                <Icon name="wand" class="h-5 w-5 text-brand" />
                                <span class="font-display font-medium tracking-tight text-ink">Сценарии</span>
                                <span class="ml-auto rounded-full bg-active px-2.5 py-0.5 text-xs font-semibold text-brand">в один клик</span>
                            </div>
                            <ul class="space-y-2.5">
                                <li v-for="(s, i) in activeNicheData.scenarios" :key="s" class="tpl-row flex items-center gap-3 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-ink" :style="{ animationDelay: i * 60 + 'ms' }">
                                    <span class="flex h-7 w-7 flex-none items-center justify-center rounded-lg bg-active text-brand">▸</span>{{ s }}
                                </li>
                            </ul>
                        </div>
                        <div class="glass rounded-3xl p-6">
                            <div class="mb-4 flex items-center gap-2">
                                <Icon name="book" class="h-5 w-5 text-brand" />
                                <span class="font-display font-medium tracking-tight text-ink">База знаний</span>
                                <span class="ml-auto rounded-full bg-active px-2.5 py-0.5 text-xs font-semibold text-brand">заготовки</span>
                            </div>
                            <ul class="space-y-2.5">
                                <li v-for="(k, i) in activeNicheData.knowledge" :key="k" class="tpl-row flex items-center gap-3 rounded-xl border border-line bg-panel px-4 py-3 text-sm text-ink" :style="{ animationDelay: i * 60 + 'ms' }">
                                    <span class="flex h-7 w-7 flex-none items-center justify-center rounded-lg bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"><Icon name="check" class="h-4 w-4" /></span>{{ k }}
                                </li>
                            </ul>
                        </div>
                    </div>
                </Transition>
            </div>

            <div data-reveal class="mt-10">
                <div class="mb-4 text-center text-sm font-medium text-muted">Готовые шаблоны уже есть для десятков ниш:</div>
                <div class="mx-auto flex max-w-4xl flex-wrap justify-center gap-2">
                    <span v-for="tag in nicheTags" :key="tag" class="glass rounded-full px-3.5 py-1.5 text-sm text-muted">{{ tag }}</span>
                </div>
            </div>
            <p data-reveal class="mx-auto mt-6 max-w-2xl text-center text-sm text-muted2">Не нашли свою? Добавим под вашу нишу. И ещё десятки общих шаблонов, подходящих любому бизнесу — всё редактируется под вас.</p>
        </section>

        <!-- ✨ Импорт базы знаний с сайта (выделенный блок) -->
        <section class="mx-auto max-w-6xl px-6 py-12">
            <div data-reveal class="glow-frame">
                <div class="glow-inner px-6 py-10 sm:px-10">
                    <div class="grid items-center gap-8 lg:grid-cols-2">
                        <div>
                            <div class="inline-flex items-center gap-2 rounded-full bg-active px-3 py-1 text-xs font-semibold text-brand">
                                <Icon name="rocket" class="h-4 w-4" /> Новинка
                            </div>
                            <h2 class="font-display mt-4 text-3xl font-semibold tracking-tight text-ink">База знаний — с вашего сайта за минуты</h2>
                            <p class="mt-3 max-w-xl text-muted">
                                Не хотите заполнять вручную? Дайте ссылку на свой сайт — AI пройдёт по ключевым страницам
                                (услуги, цены, доставка, контакты), сам соберёт записи и сохранит их <span class="font-semibold text-ink">черновиками</span>.
                                Вы проверяете и публикуете нужное в один клик.
                            </p>
                            <div class="mt-5 flex flex-wrap gap-2.5">
                                <span class="glass inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm text-muted"><Icon name="bolt" class="h-4 w-4 text-brand" /> Запуск за минуты</span>
                                <span class="glass inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm text-muted"><Icon name="check" class="h-4 w-4 text-emerald-500" /> Всё — черновиками</span>
                                <span class="glass inline-flex items-center gap-1.5 rounded-full px-3.5 py-1.5 text-sm text-muted"><Icon name="brain" class="h-4 w-4 text-brand" /> Понимает любой сайт</span>
                            </div>
                        </div>

                        <!-- Мини-визуал: сайт → черновики -->
                        <div class="glass rounded-3xl p-5">
                            <div class="flex items-center gap-2 rounded-xl border border-line bg-panel px-3 py-2.5">
                                <Icon name="link" class="h-4 w-4 flex-none text-brand" />
                                <span class="truncate text-sm text-muted2">https://ваш-сайт.рф</span>
                                <span class="ml-auto rounded-full bg-brand px-2.5 py-1 text-xs font-bold text-white">Собрать</span>
                            </div>
                            <div class="my-3 flex items-center justify-center text-brand"><Icon name="rocket" class="ico h-5 w-5" /></div>
                            <div class="space-y-2">
                                <div v-for="d in importDrafts" :key="d" class="flex items-center gap-2.5 rounded-xl border border-line bg-panel px-3 py-2.5 text-sm">
                                    <span class="flex h-6 w-6 flex-none items-center justify-center rounded-lg bg-amber-400/15 text-amber-600 dark:text-amber-400"><Icon name="pen" class="h-3.5 w-3.5" /></span>
                                    <span class="truncate text-ink">{{ d }}</span>
                                    <span class="ml-auto rounded-full bg-chip px-2 py-0.5 text-[11px] font-semibold text-muted">черновик</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Как работает -->
        <section class="mx-auto max-w-6xl px-6 py-16">
            <h2 data-reveal class="font-display mb-12 text-center text-3xl font-semibold tracking-tight text-ink sm:text-4xl">Запуск за один вечер</h2>
            <div class="grid gap-6 sm:grid-cols-3">
                <div v-for="(s, i) in steps" :key="s.n" data-reveal :style="{ transitionDelay: i * 90 + 'ms' }" class="card-hover rounded-2xl border border-line bg-panel p-7">
                    <div class="font-display text-4xl font-semibold text-warm">{{ s.n }}</div>
                    <div class="font-display mt-3 font-medium tracking-tight text-ink">{{ s.title }}</div>
                    <p class="mt-1.5 text-sm leading-relaxed text-muted">{{ s.text }}</p>
                </div>
            </div>
        </section>

        <!-- Интеграции -->
        <section class="mx-auto max-w-6xl px-6 py-16">
            <div data-reveal class="mb-10 text-center">
                <h2 class="font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">Работает там, где ваши клиенты</h2>
                <p class="mt-3 text-muted">Каналы общения и CRM подключаются в пару кликов.</p>
            </div>
            <div data-reveal class="flex flex-wrap justify-center gap-3">
                <span v-for="i in integrationsNow" :key="i" class="glass rounded-full px-5 py-2.5 text-sm font-semibold text-ink">{{ i }}</span>
            </div>
            <div data-reveal style="transition-delay: 200ms" class="mx-auto mt-8 max-w-2xl">
                <div class="glass rounded-2xl p-6 text-center">
                    <div class="font-display font-medium tracking-tight text-ink">Своя CRM? Подключим под вас</div>
                    <p class="mt-2 text-sm leading-relaxed text-muted">Интеграцию с вашей CRM настроим по договорённости. Сейчас поддерживается <span class="font-semibold text-ink">YClients</span> — остальные подключаем индивидуально.</p>
                </div>
            </div>
        </section>

        <!-- Планы -->
        <section class="mx-auto max-w-6xl px-6 py-16">
            <div data-reveal class="mb-10 text-center">
                <h2 class="font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">Планы по внедрению инструментов</h2>
                <p class="mx-auto mt-3 max-w-2xl text-muted">Над чем работаем дальше. Этого пока нет в продукте — добавляем по мере готовности.</p>
            </div>
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="(r, i) in roadmap" :key="r.title" data-reveal :style="{ transitionDelay: i * 70 + 'ms' }" class="rounded-2xl border border-line bg-panel p-6">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-active text-brand"><Icon :name="r.icon" class="ico h-5 w-5" /></span>
                        <span class="font-display font-medium tracking-tight text-ink">{{ r.title }}</span>
                        <span class="ml-auto rounded-full bg-chip px-2.5 py-0.5 text-[11px] font-semibold text-muted">в планах</span>
                    </div>
                    <p class="mt-2 text-sm leading-relaxed text-muted">{{ r.text }}</p>
                </div>
            </div>
            <div data-reveal class="mt-10 text-center">
                <Link href="/tarify" class="inline-flex items-center gap-1.5 font-semibold text-brand hover:underline">Посмотреть тарифы →</Link>
            </div>
        </section>
    </SiteLayout>
</template>
