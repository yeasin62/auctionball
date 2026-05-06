<script setup>
/**
 * Polished iOS-style toggle switch.
 *
 *   <Toggle v-model="open" on-label="OPEN" off-label="CLOSED" />
 *
 * Replaces the brittle `<input type=checkbox class="before:...">` pattern that
 * had thumb-alignment issues. Uses an absolute-positioned thumb so the white
 * pill always sits centered regardless of font-size / layout context.
 */
defineProps({
    modelValue: { type: Boolean, default: false },
    onLabel:    { type: String,  default: '' },
    offLabel:   { type: String,  default: '' },
    /** When given, replaces the on/off labels — e.g. "PUBLIC REGISTRATION". */
    label:      { type: String,  default: '' },
    disabled:   { type: Boolean, default: false },
    /** "emerald" (default) | "indigo" — track color when on. */
    tone:       { type: String,  default: 'emerald' },
});
defineEmits(['update:modelValue']);
</script>

<template>
    <label class="inline-flex items-center gap-2.5 select-none"
           :class="disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer'">
        <button type="button"
                role="switch"
                :aria-checked="modelValue"
                :disabled="disabled"
                @click="!disabled && $emit('update:modelValue', !modelValue)"
                class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-offset-white"
                :class="[
                    modelValue
                        ? (tone === 'indigo' ? 'bg-brand-indigo focus:ring-brand-indigo' : 'bg-emerald-500 focus:ring-emerald-500')
                        : 'bg-ink-300 focus:ring-ink-400',
                ]">
            <span class="absolute left-0.5 top-1/2 -translate-y-1/2 h-5 w-5 rounded-full bg-white shadow-md transition-transform duration-200 ease-out"
                  :class="modelValue ? 'translate-x-5' : 'translate-x-0'"></span>
        </button>
        <span v-if="label || onLabel || offLabel"
              class="font-mono text-[11.5px] tracking-widest"
              :class="modelValue
                  ? (tone === 'indigo' ? 'text-brand-indigo' : 'text-emerald-700')
                  : 'text-ink-500'">
            {{ label || (modelValue ? onLabel : offLabel) }}
        </span>
    </label>
</template>
