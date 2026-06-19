<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import Logo from '@/Components/Logo.vue';

interface Site {
    phone: string | null;
    email: string | null;
    telegram: string | null;
    legalName: string | null;
    inn: string | null;
    ogrnip: string | null;
    accessNote: string;
}

const props = defineProps<{ site: Site; loginUrl: string }>();

// Реквизиты оператора берём из настроек сайта, с фолбэком на актуальные —
// чтобы политика всегда называла оператора, даже если настройки пусты.
const operatorName = computed(() => props.site.legalName || 'ИП Балаганский Павел Сергеевич');
const operatorInn = computed(() => props.site.inn || '543807917255');
const contactEmail = computed(() => props.site.email || 'support@otcl1ck.ru');
const updatedAt = '19 июня 2026 г.';
</script>

<template>
    <Head>
        <title>Политика конфиденциальности — Отклик</title>
        <meta name="description" content="Политика обработки персональных данных сервиса «Отклик» в соответствии с Федеральным законом 152-ФЗ." />
    </Head>

    <div class="relative min-h-screen overflow-x-clip text-slate-800 dark:text-slate-200">
        <div class="bg-base"></div>
        <div class="orbs" aria-hidden="true">
            <span class="orb orb-1"></span>
            <span class="orb orb-2"></span>
            <span class="orb orb-3"></span>
        </div>

        <header class="sticky top-0 z-30">
            <div class="mx-auto mt-3 max-w-6xl px-4">
                <div class="glass flex h-14 items-center justify-between rounded-2xl px-5">
                    <Link href="/"><Logo class="text-lg text-[#1F4E79] dark:text-white" /></Link>
                    <div class="flex items-center gap-2">
                        <ThemeToggle />
                        <a :href="loginUrl" class="rounded-xl bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white shadow-lg shadow-[#2E74B5]/25 transition hover:-translate-y-0.5 hover:bg-[#255f96]">Войти</a>
                    </div>
                </div>
            </div>
        </header>

        <main class="content-enter mx-auto max-w-3xl px-6 py-16">
            <h1 class="text-3xl font-bold text-[#1F4E79] dark:text-sky-200">Политика конфиденциальности</h1>
            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Редакция от {{ updatedAt }}</p>

            <div class="prose-policy mt-8 space-y-8 text-slate-700 dark:text-slate-300">
                <section>
                    <h2>1. Общие положения</h2>
                    <p>
                        Настоящая Политика определяет порядок обработки и защиты персональных данных в
                        сервисе «Отклик» (далее — «Сервис»). Оператор персональных данных —
                        {{ operatorName }}, ИНН {{ operatorInn }} (далее — «Оператор»).
                    </p>
                    <p>
                        Политика разработана в соответствии с Федеральным законом от 27.07.2006 № 152-ФЗ
                        «О персональных данных» и Федеральным законом от 13.03.2006 № 38-ФЗ «О рекламе».
                        Используя Сервис, пользователь подтверждает согласие с условиями настоящей Политики.
                    </p>
                </section>

                <section>
                    <h2>2. Какие данные обрабатываются</h2>
                    <p><strong>2.1. Данные пользователей Сервиса</strong> (владельцев и сотрудников бизнеса,
                        регистрирующих кабинет): имя, адрес электронной почты, номер телефона, данные для
                        входа, реквизиты подключённых каналов и интеграций. Оператор обрабатывает их как
                        оператор персональных данных.</p>
                    <p><strong>2.2. Данные клиентов бизнеса</strong> (обращающихся в чат-бот): имя, номер
                        телефона, адрес электронной почты, текст переписки и сведения о записи на услугу.
                        Эти данные Оператор обрабатывает <em>по поручению</em> соответствующего бизнеса
                        (ст. 6 ч. 3 152-ФЗ): оператором в отношении них является бизнес, а Оператор Сервиса
                        выступает лицом, осуществляющим обработку по поручению.</p>
                    <p><strong>2.3.</strong> Технические данные: IP-адрес, сведения об устройстве и браузере,
                        файлы cookie — для работоспособности и безопасности Сервиса.</p>
                </section>

                <section>
                    <h2>3. Цели обработки</h2>
                    <ul>
                        <li>предоставление доступа к Сервису и его функциям;</li>
                        <li>автоматическая обработка обращений клиентов бизнеса и запись на услуги;</li>
                        <li>интеграция с CRM, мессенджерами и системами онлайн-записи по поручению бизнеса;</li>
                        <li>направление сервисных уведомлений и информации об изменениях;</li>
                        <li>исполнение договора, биллинг, поддержка и обеспечение безопасности.</li>
                    </ul>
                </section>

                <section>
                    <h2>4. Правовые основания</h2>
                    <p>
                        Обработка осуществляется на основании согласия субъекта персональных данных,
                        договора, стороной которого является субъект, а также для достижения целей,
                        предусмотренных законом. Обработка данных клиентов бизнеса осуществляется на
                        основании поручения бизнеса и в рамках его правовых оснований.
                    </p>
                </section>

                <section>
                    <h2>5. Хранение и защита</h2>
                    <p>
                        Запись, систематизация, накопление, хранение, уточнение и извлечение персональных
                        данных граждан Российской Федерации осуществляются с использованием баз данных,
                        находящихся на территории Российской Федерации (ст. 18 ч. 5 152-ФЗ). Оператор
                        применяет правовые, организационные и технические меры защиты: шифрование данных,
                        разграничение доступа, изоляцию данных каждого бизнеса.
                    </p>
                </section>

                <section>
                    <h2>6. Передача третьим лицам</h2>
                    <p>
                        Для оказания услуг данные могут передаваться поставщикам, размещённым на территории
                        РФ: провайдерам мессенджеров и каналов связи, системам онлайн-записи и CRM (по
                        поручению бизнеса), провайдерам языковых моделей (LLM) для формирования ответов,
                        хостинг-провайдеру. Оператор не продаёт персональные данные и не передаёт их в
                        рекламных целях третьим лицам без согласия субъекта.
                    </p>
                </section>

                <section>
                    <h2>7. Сроки обработки</h2>
                    <p>
                        Персональные данные обрабатываются до достижения целей обработки, отзыва согласия
                        либо прекращения использования Сервиса, после чего удаляются или обезличиваются,
                        если иное не предусмотрено законом.
                    </p>
                </section>

                <section>
                    <h2>8. Права субъекта персональных данных</h2>
                    <p>
                        Субъект вправе получать информацию об обработке своих данных, требовать их уточнения,
                        блокирования или удаления, а также отозвать согласие на обработку. Для реализации прав
                        направьте запрос на адрес <a :href="`mailto:${contactEmail}`">{{ contactEmail }}</a>.
                    </p>
                </section>

                <section>
                    <h2>9. Файлы cookie</h2>
                    <p>
                        Сервис использует cookie для аутентификации, сохранения настроек и аналитики
                        работоспособности. Пользователь может отключить cookie в настройках браузера, однако
                        это может ограничить работу отдельных функций.
                    </p>
                </section>

                <section>
                    <h2>10. Изменения Политики</h2>
                    <p>
                        Оператор вправе изменять настоящую Политику. Актуальная редакция всегда доступна на
                        этой странице. Существенные изменения Оператор доводит до пользователей через Сервис.
                    </p>
                </section>

                <section>
                    <h2>11. Контакты</h2>
                    <p>
                        По вопросам обработки персональных данных: {{ operatorName }}, ИНН {{ operatorInn }},
                        электронная почта <a :href="`mailto:${contactEmail}`">{{ contactEmail }}</a>.
                    </p>
                </section>
            </div>

            <Link href="/" class="mt-10 inline-block text-sm text-[#2E74B5] hover:underline dark:text-sky-300">← На главную</Link>
        </main>
    </div>
</template>

<style scoped>
.prose-policy h2 {
    font-size: 1.125rem;
    font-weight: 600;
    color: #1f4e79;
    margin-bottom: 0.5rem;
}
html.dark .prose-policy h2 {
    color: #bae6fd;
}
.prose-policy p {
    line-height: 1.7;
    margin-top: 0.5rem;
}
.prose-policy ul {
    list-style: disc;
    padding-left: 1.25rem;
    margin-top: 0.5rem;
    line-height: 1.7;
}
.prose-policy a {
    color: #2e74b5;
    text-decoration: underline;
}
html.dark .prose-policy a {
    color: #7dd3fc;
}

.glass {
    background: rgba(255, 255, 255, 0.6);
    backdrop-filter: blur(18px) saturate(170%);
    -webkit-backdrop-filter: blur(18px) saturate(170%);
    border: 1px solid rgba(255, 255, 255, 0.6);
    box-shadow: 0 8px 32px rgba(31, 78, 121, 0.12);
}
html.dark .glass {
    background: rgba(20, 30, 48, 0.55);
    border-color: rgba(255, 255, 255, 0.1);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.45);
}

.bg-base {
    position: fixed;
    inset: 0;
    z-index: -2;
    pointer-events: none;
    background: linear-gradient(125deg, #eaf1fe 0%, #f6faff 45%, #e7f6ff 100%);
    background-size: 200% 200%;
    animation: bgpan 22s ease infinite;
}
html.dark .bg-base {
    background: linear-gradient(125deg, #0b1220 0%, #0e1828 45%, #0a1a26 100%);
    background-size: 200% 200%;
}

.orbs {
    position: fixed;
    inset: 0;
    z-index: -1;
    overflow: hidden;
    pointer-events: none;
}
.orb {
    position: absolute;
    border-radius: 9999px;
    filter: blur(70px);
    opacity: 0.5;
}
html.dark .orb {
    opacity: 0.28;
}
.orb-1 {
    width: 380px;
    height: 380px;
    background: #7cc0ff;
    top: -80px;
    left: -60px;
    animation: floaty 18s ease-in-out infinite;
}
.orb-2 {
    width: 320px;
    height: 320px;
    background: #b9a8ff;
    top: 30%;
    right: -80px;
    animation: floaty 24s ease-in-out infinite reverse;
}
.orb-3 {
    width: 260px;
    height: 260px;
    background: #7df3e1;
    bottom: 8%;
    left: 6%;
    animation: floaty 20s ease-in-out infinite;
}

.content-enter {
    animation: enter 0.7s cubic-bezier(0.2, 0.7, 0.2, 1) both;
}

@keyframes bgpan {
    0%,
    100% {
        background-position: 0% 50%;
    }
    50% {
        background-position: 100% 50%;
    }
}
@keyframes floaty {
    0%,
    100% {
        transform: translate(0, 0) scale(1);
    }
    50% {
        transform: translate(24px, -30px) scale(1.06);
    }
}
@keyframes enter {
    from {
        opacity: 0;
        transform: translateY(22px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .bg-base,
    .orb {
        animation: none;
    }
    .content-enter {
        animation: none;
    }
}
</style>
