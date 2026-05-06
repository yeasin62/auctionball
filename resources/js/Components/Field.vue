<script setup>
defineProps({
    label: String,
    error: String,
    hint:  String,
    required: Boolean,
});
</script>

<template>
    <label class="block">
        <div v-if="label" class="flex items-baseline justify-between mb-1.5">
            <span class="text-[13px] font-medium text-ink-700">
                {{ label }}<span v-if="required" class="text-rose-500 ml-0.5">*</span>
            </span>
            <span v-if="hint" class="text-[11.5px] font-mono text-ink-400">{{ hint }}</span>
        </div>
        <slot />
        <p v-if="error" class="mt-1.5 text-[12.5px] text-rose-500">{{ error }}</p>
    </label>
</template>
