<script setup lang="ts">
// Анимированный переключатель (switch) с v-model. Замена обычным чекбоксам.
const props = defineProps<{ modelValue: boolean; disabled?: boolean }>();
const emit = defineEmits<{ 'update:modelValue': [boolean] }>();

const toggle = (): void => {
    if (!props.disabled) {
        emit('update:modelValue', !props.modelValue);
    }
};
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="modelValue"
        :disabled="disabled"
        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full border outline-none transition-colors duration-200 focus-visible:ring-2 focus-visible:ring-[rgba(43,92,224,0.5)] disabled:cursor-not-allowed disabled:opacity-50"
        :class="modelValue ? 'border-brand bg-brand' : 'border-line bg-chip'"
        @click="toggle"
    >
        <span
            class="inline-block h-5 w-5 transform rounded-full bg-white shadow-sm transition-transform duration-200 ease-out"
            :class="modelValue ? 'translate-x-5' : 'translate-x-0.5'"
        />
    </button>
</template>
