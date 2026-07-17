<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Icon from '@/Components/Icon.vue';

interface ChannelRow {
    id: string;
    type: string;
    type_value: string;
    external_id: string | null;
    is_active: boolean;
    detail: string;
    created_at: string | null;
}

defineProps<{ channels: ChannelRow[] }>();

const form = useForm({ type: 'telegram', bot_token: '', access_token: '', group_id: '', id_instance: '', api_token: '' });

const connect = (): void => {
    form.post('/cabinet/channels', {
        onSuccess: () => form.reset('bot_token', 'access_token', 'group_id', 'id_instance', 'api_token'),
    });
};

const disconnect = (id: string): void => {
    if (confirm('Отключить канал?')) {
        router.delete(`/cabinet/channels/${id}`);
    }
};

// Оформление карточки канала (иконка в цветном квадрате — по макету), чисто презентационное.
const CHANNEL_ICONS: Record<string, string> = { telegram: 'send', vk: 'chat', max: 'bolt', whatsapp: 'phone', web: 'globe' };
const CHANNEL_TINTS: Record<string, string> = {
    telegram: 'bg-sky-500/15 text-sky-600 dark:text-sky-400',
    vk: 'bg-active text-brand',
    max: 'bg-violet-500/15 text-violet-600 dark:text-violet-400',
    whatsapp: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
    web: 'bg-active text-brand',
};

const channelIcon = (type: string): string => CHANNEL_ICONS[type] ?? 'radio';
const channelTint = (type: string): string => CHANNEL_TINTS[type] ?? 'bg-chip text-muted';
</script>

<template>
    <Head title="Каналы" />

    <AppLayout title="Каналы">
        <form class="otk-card p-6 mb-6" @submit.prevent="connect">
            <div class="no-scrollbar -mx-1 mb-4 flex gap-2 overflow-x-auto px-1 pb-1">
                <button
                    v-for="tab in [
                        { value: 'telegram', label: 'Telegram' },
                        { value: 'vk', label: 'ВКонтакте' },
                        { value: 'max', label: 'MAX' },
                        { value: 'whatsapp', label: 'WhatsApp' },
                    ]"
                    :key="tab.value"
                    type="button"
                    class="shrink-0 whitespace-nowrap rounded-full px-3.5 py-1.5 text-sm font-semibold border transition"
                    :class="form.type === tab.value
                        ? 'bg-brand text-white border-brand'
                        : 'bg-panel text-muted border-line hover:bg-hoverbg'"
                    @click="form.type = tab.value"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- Telegram -->
            <template v-if="form.type === 'telegram'">
                <div class="font-display text-base font-semibold text-ink mb-1">Подключить Telegram-бота</div>
                <ol class="text-sm text-muted mb-3 list-decimal list-inside space-y-0.5">
                    <li>Откройте <a href="https://t.me/BotFather" target="_blank" class="text-brand hover:underline">@BotFather</a> в Telegram, команда <code>/newbot</code>.</li>
                    <li>Скопируйте выданный токен вида <code>123456789:ABCdef...</code> и вставьте ниже.</li>
                    <li>После подключения бот начнёт принимать сообщения клиентов.</li>
                </ol>
                <div class="flex gap-3 items-start">
                    <div class="flex-1">
                        <input
                            v-model="form.bot_token"
                            type="text"
                            placeholder="123456:ABCdef-токен от @BotFather"
                            class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none placeholder:text-muted2 focus:border-brand"
                        />
                        <p v-if="form.errors.bot_token" class="mt-1 text-sm text-red-500">{{ form.errors.bot_token }}</p>
                    </div>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="otk-btn-primary disabled:opacity-50"
                    >
                        Подключить
                    </button>
                </div>
            </template>

            <!-- ВКонтакте -->
            <template v-else-if="form.type === 'vk'">
                <div class="font-display text-base font-semibold text-ink mb-1">Подключить сообщество ВКонтакте</div>
                <ol class="text-sm text-muted mb-3 list-decimal list-inside space-y-0.5">
                    <li>В сообществе: <b>Управление → Настройки → Работа с API → Ключи доступа</b> — создайте ключ с правами на сообщения.</li>
                    <li>Там же включите <b>Long Poll API</b> (последняя версия) и события <code>message_new</code>.</li>
                    <li>Вставьте ключ и числовой id сообщества ниже.</li>
                </ol>

                <details class="mb-3 rounded-xl border border-line bg-chip p-4 text-sm text-muted">
                    <summary class="cursor-pointer font-semibold text-ink select-none">Подробная инструкция: как создать и подключить бота ВКонтакте</summary>
                    <div class="mt-3 space-y-3">
                        <div>
                            <div class="font-semibold text-ink">1. Сообщество</div>
                            <p>Нужно сообщество ВКонтакте (группа или публичная страница). Если его нет — создайте на <a href="https://vk.com/groups?w=groups_create" target="_blank" class="text-brand hover:underline">vk.com/groups</a>. Ботом управляет именно сообщество, не личная страница.</p>
                        </div>
                        <div>
                            <div class="font-semibold text-ink">2. Включите сообщения сообщества</div>
                            <p><b>Управление → Сообщения → Сообщения сообщества: Включены</b>. Без этого клиенты не смогут вам написать, и бот не получит обращений.</p>
                        </div>
                        <div>
                            <div class="font-semibold text-ink">3. Создайте ключ доступа (токен)</div>
                            <p><b>Управление → Настройки → Работа с API → Ключи доступа → Создать ключ</b>. Отметьте права <b>«Управление сообщениями сообщества»</b> (и «Сообщения сообщества»). Скопируйте ключ вида <code>vk1.a.AbCd…</code> — это и есть токен сообщества. Храните его как пароль.</p>
                        </div>
                        <div>
                            <div class="font-semibold text-ink">4. Включите Long Poll API</div>
                            <p><b>Работа с API → Long Poll API</b>: включите, версия — последняя. На вкладке <b>«Типы событий»</b> включите <b>«Входящее сообщение»</b> (<code>message_new</code>). Так наш сервер сам забирает новые сообщения — публичный адрес/вебхук не нужен.</p>
                        </div>
                        <div>
                            <div class="font-semibold text-ink">5. Найдите числовой id сообщества</div>
                            <p>Это число (без «club»/«public»). Видно в адресе страницы: <code>vk.com/club<b>123456789</b></code> или <code>public<b>123456789</b></code>. Если задан короткий адрес — откройте <b>Управление</b>, число будет в адресе страницы. Либо <b>Управление → Настройки → Адрес страницы</b>.</p>
                        </div>
                        <div>
                            <div class="font-semibold text-ink">6. Подключите ниже</div>
                            <p>Вставьте токен и id, нажмите «Подключить». Мы проверим сообщество и сразу начнём принимать сообщения.</p>
                        </div>
                        <div class="rounded-xl bg-panel border border-line p-3">
                            <div class="font-semibold text-ink">Как это работает дальше</div>
                            <p>Бот отвечает клиентам в личных сообщениях сообщества по вашей <b>базе знаний</b> (раздел «База знаний»), записывает на услуги (если подключён YClients) и передаёт сложные вопросы администратору. Чтобы ответы были точными — заполните базу знаний. Проверить можно так: напишите в сообщество с другого аккаунта — бот ответит, а обращение появится в разделе «Лиды».</p>
                        </div>
                    </div>
                </details>

                <div class="space-y-3">
                    <div>
                        <input
                            v-model="form.access_token"
                            type="text"
                            placeholder="Токен сообщества (vk1.a....)"
                            class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none placeholder:text-muted2 focus:border-brand"
                        />
                        <p v-if="form.errors.access_token" class="mt-1 text-sm text-red-500">{{ form.errors.access_token }}</p>
                    </div>
                    <div class="flex gap-3 items-start">
                        <div class="flex-1">
                            <input
                                v-model="form.group_id"
                                type="text"
                                placeholder="id сообщества, например 123456789"
                                class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none placeholder:text-muted2 focus:border-brand"
                            />
                            <p v-if="form.errors.group_id" class="mt-1 text-sm text-red-500">{{ form.errors.group_id }}</p>
                        </div>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="otk-btn-primary disabled:opacity-50"
                        >
                            Подключить
                        </button>
                    </div>
                </div>
            </template>

            <!-- MAX -->
            <template v-else-if="form.type === 'max'">
                <div class="font-display text-base font-semibold text-ink mb-1">Подключить бота MAX</div>
                <ol class="text-sm text-muted mb-3 list-decimal list-inside space-y-0.5">
                    <li>Откройте <b>@MasterBot</b> в мессенджере MAX, команда <code>/newbot</code> — создайте бота.</li>
                    <li>Скопируйте выданный токен и вставьте ниже.</li>
                    <li>После подключения бот начнёт принимать сообщения клиентов.</li>
                </ol>

                <details class="mb-3 rounded-xl border border-line bg-chip p-4 text-sm text-muted">
                    <summary class="cursor-pointer font-semibold text-ink select-none">Подробная инструкция: как создать и подключить бота MAX</summary>
                    <div class="mt-3 space-y-3">
                        <div>
                            <div class="font-semibold text-ink">1. Установите MAX и найдите @MasterBot</div>
                            <p>MAX — российский мессенджер (<a href="https://max.ru" target="_blank" class="text-brand hover:underline">max.ru</a>). Установите приложение, войдите и в поиске найдите официального бота <b>@MasterBot</b> — он управляет созданием ботов.</p>
                        </div>
                        <div>
                            <div class="font-semibold text-ink">2. Создайте бота</div>
                            <p>Напишите @MasterBot команду <code>/newbot</code> и следуйте подсказкам (имя бота и его @username). По завершении бот выдаст <b>токен доступа</b> — длинную строку. Храните её как пароль.</p>
                        </div>
                        <div>
                            <div class="font-semibold text-ink">3. Подключите ниже</div>
                            <p>Вставьте токен и нажмите «Подключить». Мы проверим его и сразу начнём принимать сообщения (бот работает через long polling — публичный адрес/вебхук не нужен).</p>
                        </div>
                        <div class="rounded-xl bg-panel border border-line p-3">
                            <div class="font-semibold text-ink">Как это работает дальше</div>
                            <p>Бот отвечает клиентам в MAX по вашей <b>базе знаний</b> (раздел «База знаний»), записывает на услуги (если подключён YClients) и передаёт сложные вопросы администратору. Проверить: напишите боту с другого аккаунта — он ответит, а обращение появится в разделе «Лиды».</p>
                        </div>
                    </div>
                </details>

                <div class="flex gap-3 items-start">
                    <div class="flex-1">
                        <input
                            v-model="form.access_token"
                            type="text"
                            placeholder="Токен бота MAX (от @MasterBot)"
                            class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none placeholder:text-muted2 focus:border-brand"
                        />
                        <p v-if="form.errors.access_token" class="mt-1 text-sm text-red-500">{{ form.errors.access_token }}</p>
                    </div>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="otk-btn-primary disabled:opacity-50"
                    >
                        Подключить
                    </button>
                </div>
            </template>

            <!-- WhatsApp (через Green API) -->
            <template v-else-if="form.type === 'whatsapp'">
                <div class="font-display text-base font-semibold text-ink mb-1">Подключить WhatsApp</div>
                <ol class="text-sm text-muted mb-3 list-decimal list-inside space-y-0.5">
                    <li>Зарегистрируйтесь в <a href="https://green-api.com" target="_blank" class="text-brand hover:underline">Green API</a> и создайте инстанс.</li>
                    <li>В инстансе отсканируйте <b>QR-код</b> телефоном с WhatsApp (как WhatsApp Web) — статус станет «authorized».</li>
                    <li>Скопируйте <b>idInstance</b> и <b>apiTokenInstance</b> и вставьте ниже.</li>
                </ol>

                <details class="mb-3 rounded-xl border border-line bg-chip p-4 text-sm text-muted">
                    <summary class="cursor-pointer font-semibold text-ink select-none">Подробная инструкция: как сделать бота в WhatsApp</summary>
                    <div class="mt-3 space-y-3">
                        <div>
                            <div class="font-semibold text-ink">Почему через Green API</div>
                            <p>У WhatsApp нет «токена бота», как у Telegram. Чтобы бот отвечал с вашего номера, его подключают через провайдера-шлюз. Мы используем <b>Green API</b> — он привязывает реальный аккаунт WhatsApp по QR-коду (как WhatsApp Web) и даёт API для приёма/отправки сообщений.</p>
                        </div>
                        <div>
                            <div class="font-semibold text-ink">1. Аккаунт и инстанс</div>
                            <p>Зарегистрируйтесь на <a href="https://green-api.com" target="_blank" class="text-brand hover:underline">green-api.com</a>, в личном кабинете создайте <b>инстанс</b> (есть бесплатный тариф для теста). У инстанса будут <b>idInstance</b> (число) и <b>apiTokenInstance</b> (длинная строка).</p>
                        </div>
                        <div>
                            <div class="font-semibold text-ink">2. Привяжите номер по QR</div>
                            <p>Лучше отдельный номер/телефон для бота. В инстансе откройте QR-код и отсканируйте его в приложении WhatsApp: <b>Настройки → Связанные устройства → Привязать устройство</b>. Статус инстанса должен стать <b>authorized</b>.</p>
                        </div>
                        <div>
                            <div class="font-semibold text-ink">3. Подключите ниже</div>
                            <p>Вставьте idInstance и apiTokenInstance и нажмите «Подключить». Мы проверим, что аккаунт привязан, и начнём принимать сообщения (long polling — публичный адрес/вебхук не нужен).</p>
                        </div>
                        <div class="rounded-xl bg-panel border border-line p-3">
                            <div class="font-semibold text-ink">Как это работает дальше</div>
                            <p>Бот отвечает клиентам в WhatsApp по вашей <b>базе знаний</b>, понимает <b>голосовые</b>, записывает на услуги (если подключён YClients) и передаёт сложное администратору. Проверка: напишите на номер бота с другого телефона — он ответит, а обращение появится в «Лидах». Телефон с привязанным WhatsApp должен оставаться онлайн.</p>
                        </div>
                    </div>
                </details>

                <div class="space-y-3">
                    <div>
                        <input
                            v-model="form.id_instance"
                            type="text"
                            placeholder="idInstance (число)"
                            class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none placeholder:text-muted2 focus:border-brand"
                        />
                        <p v-if="form.errors.id_instance" class="mt-1 text-sm text-red-500">{{ form.errors.id_instance }}</p>
                    </div>
                    <div class="flex gap-3 items-start">
                        <div class="flex-1">
                            <input
                                v-model="form.api_token"
                                type="text"
                                placeholder="apiTokenInstance"
                                class="w-full rounded-xl border border-line bg-panel px-3.5 py-2.5 text-sm text-ink outline-none placeholder:text-muted2 focus:border-brand"
                            />
                            <p v-if="form.errors.api_token" class="mt-1 text-sm text-red-500">{{ form.errors.api_token }}</p>
                        </div>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="otk-btn-primary disabled:opacity-50"
                        >
                            Подключить
                        </button>
                    </div>
                </div>
            </template>
        </form>

        <div v-if="channels.length === 0" class="text-muted2 text-center py-8">
            Каналов пока нет. Подключите Telegram, ВКонтакте, MAX или WhatsApp выше.
        </div>

        <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="channel in channels"
                :key="channel.id"
                class="otk-card p-5 flex flex-col"
            >
                <div class="flex items-center justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <span
                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl"
                            :class="channelTint(channel.type_value)"
                        >
                            <Icon :name="channelIcon(channel.type_value)" class="h-[18px] w-[18px]" />
                        </span>
                        <span class="truncate font-semibold text-ink">{{ channel.type }}</span>
                    </div>
                    <span
                        class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                        :class="channel.is_active ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-chip text-muted'"
                    >
                        {{ channel.is_active ? 'активен' : 'отключён' }}
                    </span>
                </div>
                <div class="text-xs text-muted2 mt-2 truncate">{{ channel.detail }}</div>
                <button
                    type="button"
                    class="mt-4 w-full rounded-xl border border-red-500/30 px-3 py-2 text-sm font-semibold text-red-600 transition hover:bg-red-500/10 dark:text-red-400"
                    @click="disconnect(channel.id)"
                >
                    Отключить
                </button>
            </div>
        </div>
    </AppLayout>
</template>
