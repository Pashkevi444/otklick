<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface ProfileData {
    name: string;
    phone: string | null;
    email: string | null;
    address: string | null;
    working_hours: string | null;
    escalation_note: string | null;
    description: string | null;
    website: string | null;
    avatar_url: string | null;
}

interface BizType {
    value: string;
    label: string;
}

const props = defineProps<{ profile: ProfileData; businessType: string | null; businessTypes: BizType[] }>();

// Тип бизнеса — отдельная мини-форма (плашка): смена ниши влияет на подбор
// шаблонов сценариев и базы знаний.
const btForm = useForm<{ business_type: string }>({ business_type: props.businessType ?? '' });
const businessTypeLabel = computed(
    () => props.businessTypes.find((t) => t.value === props.businessType)?.label ?? null,
);
const saveBusinessType = (): void => {
    btForm.transform((d) => ({ business_type: d.business_type === '' ? null : d.business_type })).put('/cabinet/profile/business-type', { preserveScroll: true });
};

const form = useForm<{
    _method: string;
    name: string;
    phone: string;
    email: string;
    address: string;
    working_hours: string;
    escalation_note: string;
    description: string;
    website: string;
    avatar: File | null;
    remove_avatar: boolean;
}>({
    _method: 'put',
    name: props.profile.name,
    phone: props.profile.phone ?? '',
    email: props.profile.email ?? '',
    address: props.profile.address ?? '',
    working_hours: props.profile.working_hours ?? '',
    escalation_note: props.profile.escalation_note ?? '',
    description: props.profile.description ?? '',
    website: props.profile.website ?? '',
    avatar: null,
    remove_avatar: false,
});

const localPreview = ref<string | null>(null);
const previewUrl = computed<string | null>(() => {
    if (form.remove_avatar) return null;
    return localPreview.value ?? props.profile.avatar_url;
});

const initials = computed<string>(() =>
    (form.name || '?')
        .split(/\s+/)
        .slice(0, 2)
        .map((w) => w.charAt(0))
        .join('')
        .toUpperCase(),
);

const onAvatarPick = (e: Event): void => {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null;
    form.avatar = file;
    form.remove_avatar = false;
    localPreview.value = file ? URL.createObjectURL(file) : null;
};

const removeAvatar = (): void => {
    form.avatar = null;
    localPreview.value = null;
    form.remove_avatar = true;
};

const submit = (): void => {
    // POST + _method=put: PUT с файлом требует multipart и спуфинга метода.
    form.post('/cabinet/profile', { preserveScroll: true, forceFormData: true });
};
</script>

<template>
    <Head title="Профиль бизнеса" />

    <AppLayout title="Профиль бизнеса">
        <p class="mb-4 max-w-2xl text-sm text-slate-500">
            Это «контекст работы» — данные о бизнесе, которые бот использует в ответах, и витрина для карточки бизнеса.
        </p>

        <!-- Тип бизнеса (ниша) — влияет на подбор шаблонов сценариев и базы знаний -->
        <div class="ui-scope mb-4 max-w-3xl rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-white/10">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex-1">
                    <div class="text-sm font-semibold text-[#1F4E79] dark:text-sky-200">Тип бизнеса</div>
                    <p class="mt-0.5 text-xs text-slate-500">
                        Сейчас:
                        <span class="font-medium text-slate-700 dark:text-slate-200">{{ businessTypeLabel ?? 'не задан' }}</span>.
                        От него зависит, какие готовые шаблоны и элементы базы знаний вам показываются.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <select v-model="btForm.business_type" class="rounded-lg border border-slate-300 px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5">
                        <option value="">Не задан</option>
                        <option v-for="bt in businessTypes" :key="bt.value" :value="bt.value">{{ bt.label }}</option>
                    </select>
                    <button
                        type="button"
                        :disabled="btForm.processing || btForm.business_type === (businessType ?? '')"
                        class="rounded-lg bg-[#2E74B5] px-4 py-2 text-sm font-medium text-white hover:bg-[#255f96] disabled:opacity-40"
                        @click="saveBusinessType"
                    >
                        Сохранить
                    </button>
                </div>
            </div>
        </div>

        <form class="ui-scope max-w-3xl space-y-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm sm:p-8 dark:border-white/10" @submit.prevent="submit">
            <!-- Аватар -->
            <div class="flex items-center gap-4">
                <div class="flex h-20 w-20 flex-none items-center justify-center overflow-hidden rounded-2xl bg-[#EAF2FB] text-xl font-bold text-[#1F4E79] dark:bg-white/10 dark:text-sky-200">
                    <img v-if="previewUrl" :src="previewUrl" alt="Аватар" class="h-full w-full object-cover" />
                    <span v-else>{{ initials }}</span>
                </div>
                <div class="space-y-2">
                    <label class="inline-block cursor-pointer rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm font-medium text-slate-700 transition hover:border-[#2E74B5]/50 dark:border-white/15 dark:bg-white/5 dark:text-slate-200">
                        Загрузить аватар
                        <input type="file" accept="image/*" class="hidden" @change="onAvatarPick" />
                    </label>
                    <button v-if="previewUrl" type="button" class="ml-2 text-sm text-slate-400 hover:text-red-500" @click="removeAvatar">Удалить</button>
                    <p class="text-xs text-slate-400">PNG/JPG до 2 МБ.</p>
                    <p v-if="form.errors.avatar" class="text-sm text-red-600">{{ form.errors.avatar }}</p>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Название бизнеса</label>
                <input v-model="form.name" type="text" placeholder="Барбершоп «Бруно»" class="w-full rounded-lg border border-slate-300 px-3 py-2 transition focus:border-[#2E74B5] focus:outline-none focus:ring-2 focus:ring-[#2E74B5]/15 dark:border-white/15 dark:bg-white/5" />
                <p v-if="form.errors.name" class="mt-1 text-sm text-red-600">{{ form.errors.name }}</p>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Описание</label>
                <textarea v-model="form.description" rows="3" placeholder="Чем занимается бизнес, ключевые услуги и преимущества. Пара предложений." class="w-full rounded-lg border border-slate-300 px-3 py-2 transition focus:border-[#2E74B5] focus:outline-none focus:ring-2 focus:ring-[#2E74B5]/15 dark:border-white/15 dark:bg-white/5" />
                <p class="mt-1 text-xs text-slate-400">Показывается в карточке бизнеса и помогает боту отвечать в контексте.</p>
                <p v-if="form.errors.description" class="mt-1 text-sm text-red-600">{{ form.errors.description }}</p>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Телефон</label>
                    <input v-model="form.phone" type="text" placeholder="+7 900 123-45-67" class="w-full rounded-lg border border-slate-300 px-3 py-2 transition focus:border-[#2E74B5] focus:outline-none focus:ring-2 focus:ring-[#2E74B5]/15 dark:border-white/15 dark:bg-white/5" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Почта</label>
                    <input v-model="form.email" type="email" placeholder="hello@business.ru" class="w-full rounded-lg border border-slate-300 px-3 py-2 transition focus:border-[#2E74B5] focus:outline-none focus:ring-2 focus:ring-[#2E74B5]/15 dark:border-white/15 dark:bg-white/5" />
                    <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Часы работы</label>
                    <input v-model="form.working_hours" type="text" placeholder="Пн–Пт 9:00–20:00, Сб 10:00–18:00" class="w-full rounded-lg border border-slate-300 px-3 py-2 transition focus:border-[#2E74B5] focus:outline-none focus:ring-2 focus:ring-[#2E74B5]/15 dark:border-white/15 dark:bg-white/5" />
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Адрес</label>
                    <input v-model="form.address" type="text" placeholder="Москва, ул. Ленина 1, 2 этаж" class="w-full rounded-lg border border-slate-300 px-3 py-2 transition focus:border-[#2E74B5] focus:outline-none focus:ring-2 focus:ring-[#2E74B5]/15 dark:border-white/15 dark:bg-white/5" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Сайт</label>
                    <input v-model="form.website" type="text" placeholder="example.ru" class="w-full rounded-lg border border-slate-300 px-3 py-2 transition focus:border-[#2E74B5] focus:outline-none focus:ring-2 focus:ring-[#2E74B5]/15 dark:border-white/15 dark:bg-white/5" />
                    <p v-if="form.errors.website" class="mt-1 text-sm text-red-600">{{ form.errors.website }}</p>
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700 dark:text-slate-200">Правила эскалации на администратора</label>
                <textarea v-model="form.escalation_note" rows="3" placeholder="Когда передавать диалог человеку. Например: клиент просит позвать администратора, жалоба, вопрос про возврат денег, нестандартная просьба." class="w-full rounded-lg border border-slate-300 px-3 py-2 transition focus:border-[#2E74B5] focus:outline-none focus:ring-2 focus:ring-[#2E74B5]/15 dark:border-white/15 dark:bg-white/5" />
                <p class="mt-1 text-xs text-slate-400">Бот переключит диалог на администратора в этих случаях.</p>
                <p v-if="form.errors.escalation_note" class="mt-1 text-sm text-red-600">{{ form.errors.escalation_note }}</p>
            </div>

            <div class="flex items-center gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="rounded-lg bg-[#2E74B5] px-5 py-2.5 text-sm font-medium text-white shadow-sm shadow-[#2E74B5]/25 transition hover:-translate-y-0.5 hover:bg-[#255f96] disabled:translate-y-0 disabled:opacity-50"
                >
                    Сохранить
                </button>
                <span v-if="form.recentlySuccessful" class="text-sm text-green-600">Сохранено</span>
            </div>
        </form>
    </AppLayout>
</template>
