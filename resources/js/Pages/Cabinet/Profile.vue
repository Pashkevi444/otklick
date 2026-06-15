<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface ProfileData {
    name: string;
    phone: string | null;
    address: string | null;
    working_hours: string | null;
    escalation_note: string | null;
}

const props = defineProps<{ profile: ProfileData }>();

const form = useForm({
    name: props.profile.name,
    phone: props.profile.phone ?? '',
    address: props.profile.address ?? '',
    working_hours: props.profile.working_hours ?? '',
    escalation_note: props.profile.escalation_note ?? '',
});

const submit = (): void => {
    form.put('/cabinet/profile', { preserveScroll: true });
};
</script>

<template>
    <Head title="Профиль бизнеса" />

    <AppLayout title="Профиль бизнеса">
        <form class="bg-white rounded-xl border border-slate-200 p-6 max-w-2xl space-y-5" @submit.prevent="submit">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Название бизнеса</label>
                <input v-model="form.name" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Телефон</label>
                    <input v-model="form.phone" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Часы работы</label>
                    <input v-model="form.working_hours" type="text" placeholder="Пн–Пт 9:00–20:00" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Адрес</label>
                <input v-model="form.address" type="text" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Правила эскалации на администратора</label>
                <textarea v-model="form.escalation_note" rows="3" class="w-full rounded-lg border border-slate-300 px-3 py-2" />
                <p v-if="form.errors.escalation_note" class="mt-1 text-sm text-red-600">{{ form.errors.escalation_note }}</p>
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-50"
                >
                    Сохранить
                </button>
                <span v-if="form.recentlySuccessful" class="text-sm text-green-600">Сохранено</span>
            </div>
        </form>
    </AppLayout>
</template>
