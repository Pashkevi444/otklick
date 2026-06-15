<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{ modelValue: File[] }>();
const emit = defineEmits<{ 'update:modelValue': [File[]] }>();

interface Item {
    file: File;
    url: string;
}

const items = ref<Item[]>([]);
const dragging = ref(false);
const input = ref<HTMLInputElement | null>(null);

const sync = (): void => emit('update:modelValue', items.value.map((i) => i.file));

const addFiles = (files: FileList | null): void => {
    if (files === null) {
        return;
    }

    for (const file of Array.from(files)) {
        if (file.type.startsWith('image/')) {
            items.value.push({ file, url: URL.createObjectURL(file) });
        }
    }

    sync();
};

const onInput = (event: Event): void => {
    const el = event.target as HTMLInputElement;
    addFiles(el.files);
    el.value = ''; // позволяет выбрать тот же файл снова
};

const onDrop = (event: DragEvent): void => {
    dragging.value = false;
    addFiles(event.dataTransfer?.files ?? null);
};

const remove = (index: number): void => {
    URL.revokeObjectURL(items.value[index].url);
    items.value.splice(index, 1);
    sync();
};

const open = (): void => input.value?.click();

// Сброс превью, когда родитель очищает поле (например, form.reset() после отправки).
watch(
    () => props.modelValue,
    (value) => {
        if (value.length === 0 && items.value.length > 0) {
            items.value.forEach((i) => URL.revokeObjectURL(i.url));
            items.value = [];
        }
    },
);

onBeforeUnmount(() => items.value.forEach((i) => URL.revokeObjectURL(i.url)));
</script>

<template>
    <div>
        <div
            class="rounded-xl border-2 border-dashed px-4 py-6 text-center cursor-pointer transition"
            :class="dragging ? 'border-[#2E74B5] bg-blue-50' : 'border-slate-300 hover:border-slate-400'"
            @click="open"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <div class="text-sm text-slate-600">
                Перетащите картинки сюда или
                <span class="text-[#2E74B5] font-medium">выберите файлы</span>
            </div>
            <div class="text-xs text-slate-400 mt-1">JPG/PNG, можно несколько, до 5 МБ каждая</div>
            <input ref="input" type="file" multiple accept="image/*" class="hidden" @change="onInput" />
        </div>

        <div v-if="items.length" class="flex flex-wrap gap-2 mt-3">
            <div v-for="(item, i) in items" :key="i" class="relative">
                <img :src="item.url" class="h-20 w-20 object-cover rounded-lg border border-slate-200" alt="" />
                <button
                    type="button"
                    class="absolute -top-2 -right-2 bg-red-600 text-white rounded-full w-5 h-5 text-xs leading-none"
                    @click.stop="remove(i)"
                >
                    ×
                </button>
            </div>
        </div>
    </div>
</template>
