<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface ChannelRow {
    id: string;
    type: string;
    external_id: string | null;
    is_active: boolean;
    webhook_url: string;
    created_at: string | null;
}

defineProps<{ channels: ChannelRow[] }>();

const form = useForm({ bot_token: '' });

const connect = (): void => {
    form.post('/cabinet/channels', {
        onSuccess: () => form.reset(),
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
            <div class="font-semibold text-[#1F4E79] mb-3">Подключить Telegram-бота</div>
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
        </form>

        <div v-if="channels.length === 0" class="text-slate-400 text-center py-8">
            Каналов пока нет. Подключите Telegram-бота выше.
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
                    <div class="text-xs text-slate-400 mt-1 truncate">{{ channel.webhook_url }}</div>
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
