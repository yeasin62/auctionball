<script setup>
defineProps({
    modelValue: [String, Number],
    type: { type: String, default: 'text' },
    placeholder: String,
    autocomplete: String,
    autofocus: Boolean,
    disabled: Boolean,
    leading: String,
    trailing: String,
});
defineEmits(['update:modelValue']);
</script>

<template>
    <div class="relative">
        <span v-if="leading"
              class="absolute left-3.5 top-1/2 -translate-y-1/2 font-mono text-[13px] text-ink-400 pointer-events-none select-none">
            {{ leading }}
        </span>
        <input
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            :autocomplete="autocomplete"
            :autofocus="autofocus"
            :disabled="disabled"
            @input="$emit('update:modelValue', $event.target.value)"
            class="w-full rounded-xl border border-ink-200/70 bg-white/80 py-2.5 text-[14px]
                   placeholder:text-ink-400 focus:outline-none focus:ring-2 focus:ring-brand-indigo/30
                   focus:border-brand-indigo transition-shadow shadow-sm"
            :class="[leading ? 'pl-20' : 'pl-4', trailing ? 'pr-20' : 'pr-4']"
        />
        <span v-if="trailing"
              class="absolute right-3.5 top-1/2 -translate-y-1/2 font-mono text-[13px] text-ink-400 pointer-events-none select-none">
            {{ trailing }}
        </span>
    </div>
</template>
