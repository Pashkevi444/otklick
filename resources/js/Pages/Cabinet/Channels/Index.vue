<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

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

const form = useForm({ type: 'telegram', bot_token: '', access_token: '', group_id: '' });

const connect = (): void => {
    form.post('/cabinet/channels', {
        onSuccess: () => form.reset('bot_token', 'access_token', 'group_id'),
    });
};

const disconnect = (id: string): void => {
    if (confirm('Отключить канал?')) {
        router.delete(`/cabinet/channels/${id}`);
    }
};
</script>

<template>
    <Head title="Каналы" />

    <AppLayout title="Каналы">
        <form class="bg-white rounded-xl border border-slate-200 p-6 mb-6" @submit.prevent="connect">
            <div class="flex gap-2 mb-4">
                <button
                    v-for="tab in [
                        { value: 'telegram', label: 'Telegram' },
                        { value: 'vk', label: 'ВКонтакте' },
                        { value: 'max', label: 'MAX' },
                    ]"
                    :key="tab.value"
                    type="button"
                    class="rounded-lg px-4 py-1.5 text-sm font-medium border"
                    :class="form.type === tab.value
                        ? 'bg-[#2E74B5] text-white border-[#2E74B5]'
                        : 'bg-white text-slate-600 border-slate-300 hover:border-[#2E74B5]'"
                    @click="form.type = tab.value"
                >
                    {{ tab.label }}
                </button>
            </div>

            <!-- Telegram -->
            <template v-if="form.type === 'telegram'">
                <div class="font-semibold text-[#1F4E79] mb-1">Подключить Telegram-бота</div>
                <ol class="text-sm text-slate-500 mb-3 list-decimal list-inside space-y-0.5">
                    <li>Откройте <a href="https://t.me/BotFather" target="_blank" class="text-[#2E74B5] hover:underline">@BotFather</a> в Telegram, команда <code>/newbot</code>.</li>
                    <li>Скопируйте выданный токен вида <code>123456789:ABCdef...</code> и вставьте ниже.</li>
                    <li>После подключения бот начнёт принимать сообщения клиентов.</li>
                </ol>
                <div class="flex gap-3 items-start">
                    <div class="flex-1">
                        <input
                            v-model="form.bot_token"
                            type="text"
                            placeholder="123456:ABCdef-токен от @BotFather"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-[#2E74B5] focus:ring-1 focus:ring-[#2E74B5] outline-none"
                        />
                        <p v-if="form.errors.bot_token" class="mt-1 text-sm text-red-600">{{ form.errors.bot_token }}</p>
                    </div>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50"
                    >
                        Подключить
                    </button>
                </div>
            </template>

            <!-- ВКонтакте -->
            <template v-else-if="form.type === 'vk'">
                <div class="font-semibold text-[#1F4E79] mb-1">Подключить сообщество ВКонтакте</div>
                <ol class="text-sm text-slate-500 mb-3 list-decimal list-inside space-y-0.5">
                    <li>В сообществе: <b>Управление → Настройки → Работа с API → Ключи доступа</b> — создайте ключ с правами на сообщения.</li>
                    <li>Там же включите <b>Long Poll API</b> (последняя версия) и события <code>message_new</code>.</li>
                    <li>Вставьте ключ и числовой id сообщества ниже.</li>
                </ol>

                <details class="mb-3 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                    <summary class="cursor-pointer font-medium text-[#1F4E79] select-none">Подробная инструкция: как создать и подключить бота ВКонтакте</summary>
                    <div class="mt-3 space-y-3">
                        <div>
                            <div class="font-medium text-slate-700">1. Сообщество</div>
                            <p>Нужно сообщество ВКонтакте (группа или публичная страница). Если его нет — создайте на <a href="https://vk.com/groups?w=groups_create" target="_blank" class="text-[#2E74B5] hover:underline">vk.com/groups</a>. Ботом управляет именно сообщество, не личная страница.</p>
                        </div>
                        <div>
                            <div class="font-medium text-slate-700">2. Включите сообщения сообщества</div>
                            <p><b>Управление → Сообщения → Сообщения сообщества: Включены</b>. Без этого клиенты не смогут вам написать, и бот не получит обращений.</p>
                        </div>
                        <div>
                            <div class="font-medium text-slate-700">3. Создайте ключ доступа (токен)</div>
                            <p><b>Управление → Настройки → Работа с API → Ключи доступа → Создать ключ</b>. Отметьте права <b>«Управление сообщениями сообщества»</b> (и «Сообщения сообщества»). Скопируйте ключ вида <code>vk1.a.AbCd…</code> — это и есть токен сообщества. Храните его как пароль.</p>
                        </div>
                        <div>
                            <div class="font-medium text-slate-700">4. Включите Long Poll API</div>
                            <p><b>Работа с API → Long Poll API</b>: включите, версия — последняя. На вкладке <b>«Типы событий»</b> включите <b>«Входящее сообщение»</b> (<code>message_new</code>). Так наш сервер сам забирает новые сообщения — публичный адрес/вебхук не нужен.</p>
                        </div>
                        <div>
                            <div class="font-medium text-slate-700">5. Найдите числовой id сообщества</div>
                            <p>Это число (без «club»/«public»). Видно в адресе страницы: <code>vk.com/club<b>123456789</b></code> или <code>public<b>123456789</b></code>. Если задан короткий адрес — откройте <b>Управление</b>, число будет в адресе страницы. Либо <b>Управление → Настройки → Адрес страницы</b>.</p>
                        </div>
                        <div>
                            <div class="font-medium text-slate-700">6. Подключите ниже</div>
                            <p>Вставьте токен и id, нажмите «Подключить». Мы проверим сообщество и сразу начнём принимать сообщения.</p>
                        </div>
                        <div class="rounded-md bg-white border border-slate-200 p-3">
                            <div class="font-medium text-slate-700">Как это работает дальше</div>
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
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-[#2E74B5] focus:ring-1 focus:ring-[#2E74B5] outline-none"
                        />
                        <p v-if="form.errors.access_token" class="mt-1 text-sm text-red-600">{{ form.errors.access_token }}</p>
                    </div>
                    <div class="flex gap-3 items-start">
                        <div class="flex-1">
                            <input
                                v-model="form.group_id"
                                type="text"
                                placeholder="id сообщества, например 123456789"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-[#2E74B5] focus:ring-1 focus:ring-[#2E74B5] outline-none"
                            />
                            <p v-if="form.errors.group_id" class="mt-1 text-sm text-red-600">{{ form.errors.group_id }}</p>
                        </div>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50"
                        >
                            Подключить
                        </button>
                    </div>
                </div>
            </template>

            <!-- MAX -->
            <template v-else-if="form.type === 'max'">
                <div class="font-semibold text-[#1F4E79] mb-1">Подключить бота MAX</div>
                <ol class="text-sm text-slate-500 mb-3 list-decimal list-inside space-y-0.5">
                    <li>Откройте <b>@MasterBot</b> в мессенджере MAX, команда <code>/newbot</code> — создайте бота.</li>
                    <li>Скопируйте выданный токен и вставьте ниже.</li>
                    <li>После подключения бот начнёт принимать сообщения клиентов.</li>
                </ol>

                <details class="mb-3 rounded-lg border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
                    <summary class="cursor-pointer font-medium text-[#1F4E79] select-none">Подробная инструкция: как создать и подключить бота MAX</summary>
                    <div class="mt-3 space-y-3">
                        <div>
                            <div class="font-medium text-slate-700">1. Установите MAX и найдите @MasterBot</div>
                            <p>MAX — российский мессенджер (<a href="https://max.ru" target="_blank" class="text-[#2E74B5] hover:underline">max.ru</a>). Установите приложение, войдите и в поиске найдите официального бота <b>@MasterBot</b> — он управляет созданием ботов.</p>
                        </div>
                        <div>
                            <div class="font-medium text-slate-700">2. Создайте бота</div>
                            <p>Напишите @MasterBot команду <code>/newbot</code> и следуйте подсказкам (имя бота и его @username). По завершении бот выдаст <b>токен доступа</b> — длинную строку. Храните её как пароль.</p>
                        </div>
                        <div>
                            <div class="font-medium text-slate-700">3. Подключите ниже</div>
                            <p>Вставьте токен и нажмите «Подключить». Мы проверим его и сразу начнём принимать сообщения (бот работает через long polling — публичный адрес/вебхук не нужен).</p>
                        </div>
                        <div class="rounded-md bg-white border border-slate-200 p-3">
                            <div class="font-medium text-slate-700">Как это работает дальше</div>
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
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-[#2E74B5] focus:ring-1 focus:ring-[#2E74B5] outline-none"
                        />
                        <p v-if="form.errors.access_token" class="mt-1 text-sm text-red-600">{{ form.errors.access_token }}</p>
                    </div>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50"
                    >
                        Подключить
                    </button>
                </div>
            </template>
        </form>

        <div v-if="channels.length === 0" class="text-slate-400 text-center py-8">
            Каналов пока нет. Подключите Telegram, ВКонтакте или MAX выше.
        </div>

        <div v-else class="space-y-3">
            <div
                v-for="channel in channels"
                :key="channel.id"
                class="bg-white rounded-xl border border-slate-200 p-5 flex items-center justify-between"
            >
                <div class="min-w-0">
                    <div class="font-medium text-slate-700">
                        {{ channel.type }}
                        <span
                            class="ml-2 text-xs rounded-full px-2 py-0.5"
                            :class="channel.is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-500'"
                        >
                            {{ channel.is_active ? 'активен' : 'отключён' }}
                        </span>
                    </div>
                    <div class="text-xs text-slate-400 mt-1 truncate">{{ channel.detail }}</div>
                </div>
                <button
                    type="button"
                    class="text-sm text-red-600 hover:underline shrink-0 ml-4"
                    @click="disconnect(channel.id)"
                >
                    Отключить
                </button>
            </div>
        </div>
    </AppLayout>
</template>
