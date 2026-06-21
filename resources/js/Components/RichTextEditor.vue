<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Image from '@tiptap/extension-image';
import Link from '@tiptap/extension-link';

const props = defineProps<{ modelValue: string; uploadUrl: string }>();
const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const fileInput = ref<HTMLInputElement | null>(null);
const uploading = ref(false);

const editor = useEditor({
    content: props.modelValue,
    extensions: [
        StarterKit,
        Image.configure({ inline: false }),
        Link.configure({ openOnClick: false, autolink: true }),
    ],
    editorProps: { attributes: { class: 'rte rte-editable focus:outline-none' } },
    onUpdate: ({ editor }) => emit('update:modelValue', editor.getHTML()),
});

// Синхронизация при сбросе формы извне.
watch(
    () => props.modelValue,
    (val) => {
        if (editor.value && val !== editor.value.getHTML()) {
            editor.value.commands.setContent(val, { emitUpdate: false });
        }
    },
);

onBeforeUnmount(() => editor.value?.destroy());

const xsrf = (): string =>
    decodeURIComponent(document.cookie.split('; ').find((c) => c.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '');

const pickImage = (): void => fileInput.value?.click();

const onImagePicked = async (e: Event): Promise<void> => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file || !editor.value) return;

    uploading.value = true;
    try {
        const body = new FormData();
        body.append('image', file);
        const res = await fetch(props.uploadUrl, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf() },
            credentials: 'same-origin',
            body,
        });
        const data: { url?: string } = await res.json();
        if (data.url) editor.value.chain().focus().setImage({ src: data.url }).run();
    } finally {
        uploading.value = false;
        if (fileInput.value) fileInput.value.value = '';
    }
};

const setLink = (): void => {
    if (!editor.value) return;
    const prev = editor.value.getAttributes('link').href as string | undefined;
    const url = window.prompt('Ссылка (URL):', prev ?? 'https://');
    if (url === null) return;
    if (url === '') {
        editor.value.chain().focus().unsetLink().run();
        return;
    }
    editor.value.chain().focus().extendMarkRange('link').setLink({ href: url }).run();
};

const isActive = (name: string, attrs?: Record<string, unknown>): boolean => editor.value?.isActive(name, attrs) ?? false;
</script>

<template>
    <div class="overflow-hidden rounded-lg border border-slate-300 dark:border-white/15">
        <!-- Тулбар -->
        <div v-if="editor" class="flex flex-wrap items-center gap-1 border-b border-slate-200 bg-slate-50 p-1.5 dark:border-white/10 dark:bg-white/5">
            <button type="button" class="rte-btn" :class="{ 'rte-on': isActive('bold') }" title="Жирный" @click="editor.chain().focus().toggleBold().run()"><b>Ж</b></button>
            <button type="button" class="rte-btn italic" :class="{ 'rte-on': isActive('italic') }" title="Курсив" @click="editor.chain().focus().toggleItalic().run()">К</button>
            <button type="button" class="rte-btn line-through" :class="{ 'rte-on': isActive('strike') }" title="Зачёркнутый" @click="editor.chain().focus().toggleStrike().run()">З</button>
            <span class="mx-1 h-5 w-px bg-slate-300 dark:bg-white/15"></span>
            <button type="button" class="rte-btn" :class="{ 'rte-on': isActive('heading', { level: 2 }) }" title="Заголовок" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()">H2</button>
            <button type="button" class="rte-btn" :class="{ 'rte-on': isActive('heading', { level: 3 }) }" title="Подзаголовок" @click="editor.chain().focus().toggleHeading({ level: 3 }).run()">H3</button>
            <button type="button" class="rte-btn" :class="{ 'rte-on': isActive('bulletList') }" title="Список" @click="editor.chain().focus().toggleBulletList().run()">•—</button>
            <button type="button" class="rte-btn" :class="{ 'rte-on': isActive('orderedList') }" title="Нумерованный список" @click="editor.chain().focus().toggleOrderedList().run()">1.</button>
            <button type="button" class="rte-btn" :class="{ 'rte-on': isActive('blockquote') }" title="Цитата" @click="editor.chain().focus().toggleBlockquote().run()">”</button>
            <span class="mx-1 h-5 w-px bg-slate-300 dark:bg-white/15"></span>
            <button type="button" class="rte-btn" :class="{ 'rte-on': isActive('link') }" title="Ссылка" @click="setLink">🔗</button>
            <button type="button" class="rte-btn" title="Картинка" :disabled="uploading" @click="pickImage">{{ uploading ? '…' : '🖼' }}</button>
            <span class="mx-1 h-5 w-px bg-slate-300 dark:bg-white/15"></span>
            <button type="button" class="rte-btn" title="Отменить" @click="editor.chain().focus().undo().run()">↶</button>
            <button type="button" class="rte-btn" title="Повторить" @click="editor.chain().focus().redo().run()">↷</button>
        </div>

        <EditorContent :editor="editor" class="max-h-[420px] overflow-y-auto bg-white px-3 py-2 text-sm dark:bg-transparent" />
        <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onImagePicked" />
    </div>
</template>

<style>
.rte-btn {
    display: inline-flex;
    height: 1.75rem;
    min-width: 1.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.375rem;
    padding: 0 0.4rem;
    font-size: 0.8rem;
    color: #475569;
}
.rte-btn:hover {
    background: rgba(46, 116, 181, 0.1);
}
.rte-on {
    background: #2e74b5;
    color: #fff;
}
</style>
