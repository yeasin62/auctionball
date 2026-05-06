<script setup>
/**
 * Polished dialog. Three modes via `kind`:
 *   - confirm : Yes / No question. Resolves boolean.
 *   - alert   : Single OK button — replaces window.alert(). No Cancel button.
 *   - prompt  : Text/number input — replaces window.prompt(). Resolves string or null.
 *
 * Variants (visual tone):
 *   - info     (blue)    : neutral / informational
 *   - warning  (amber)   : reversible-but-impactful
 *   - danger   (rose)    : destructive
 *
 * Optional `typeToConfirm` (confirm-mode only) adds a phrase the user must type
 * exactly before the confirm button enables — for high-blast-radius destructives.
 *
 * Almost always invoked through useConfirm() / useAlert() / usePrompt().
 */
import { computed, ref, watch, nextTick } from 'vue';

const props = defineProps({
    open:           { type: Boolean, default: false },
    kind:           { type: String,  default: 'confirm' },     // confirm | alert | prompt
    title:          { type: String,  default: 'Are you sure?' },
    description:    { type: String,  default: '' },
    confirmText:    { type: String,  default: 'Confirm' },
    cancelText:     { type: String,  default: 'Cancel' },
    variant:        { type: String,  default: 'warning' },
    typeToConfirm:  { type: String,  default: null },
    // prompt-only props
    inputType:      { type: String,  default: 'text' },        // text | number | url | email | tel
    inputValue:     { type: [String, Number], default: '' },
    placeholder:    { type: String,  default: '' },
    inputMin:       { type: [String, Number], default: null },
    inputMax:       { type: [String, Number], default: null },
    inputRequired:  { type: Boolean, default: true },
});
// Resolver gets: true (confirm), false (cancel), or a string (prompt value)
const emit = defineEmits(['resolve']);

const typed     = ref('');     // typeToConfirm input
const promptVal = ref('');
const inputRef  = ref(null);

watch(() => props.open, (v) => {
    if (! v) return;
    typed.value     = '';
    promptVal.value = String(props.inputValue ?? '');
    nextTick(() => inputRef.value?.focus());
});

const isPrompt  = computed(() => props.kind === 'prompt');
const isAlert   = computed(() => props.kind === 'alert');

const canConfirm = computed(() => {
    if (props.typeToConfirm) return typed.value.trim() === props.typeToConfirm;
    if (isPrompt.value) {
        if (props.inputType === 'number') {
            if (promptVal.value === '' && ! props.inputRequired) return true;
            const n = Number(promptVal.value);
            if (! Number.isFinite(n)) return false;
            if (props.inputMin !== null && n < Number(props.inputMin)) return false;
            if (props.inputMax !== null && n > Number(props.inputMax)) return false;
            return true;
        }
        return props.inputRequired ? promptVal.value.trim().length > 0 : true;
    }
    return true;
});

const onConfirm = () => {
    if (! canConfirm.value) return;
    emit('resolve', isPrompt.value ? promptVal.value : true);
};
const onCancel = () => emit('resolve', isPrompt.value ? null : false);
const onKey    = (e) => {
    if (e.key === 'Escape') onCancel();
    if (e.key === 'Enter' && canConfirm.value && ! props.typeToConfirm) {
        // Enter inside textarea or with Shift held shouldn't submit.
        if (e.target?.tagName === 'TEXTAREA' && ! e.ctrlKey && ! e.metaKey) return;
        onConfirm();
    }
};

const variantStyle = computed(() => ({
    info: {
        iconBg: 'bg-blue-100',     icon: 'text-blue-600',
        confirmBtn: 'btn-primary',
        title: 'text-ink-900',
        svg: '<path d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>',
    },
    warning: {
        iconBg: 'bg-amber-100',    icon: 'text-amber-700',
        confirmBtn: 'btn-primary',
        title: 'text-ink-900',
        svg: '<path d="M12 9v2m0 4h.01M5.07 19h13.86c1.54 0 2.5-1.67 1.73-3L13.73 4a2 2 0 00-3.46 0L3.34 16c-.77 1.33.19 3 1.73 3z"/>',
    },
    danger: {
        iconBg: 'bg-rose-100',     icon: 'text-rose-600',
        confirmBtn: 'bg-rose-600 hover:bg-rose-700 text-white shadow-sm rounded-xl font-medium inline-flex items-center justify-center gap-2',
        title: 'text-rose-900',
        svg: '<path d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6M1 7h22M9 7V4a1 1 0 011-1h4a1 1 0 011 1v3"/>',
    },
}[props.variant] || {}));
</script>

<template>
    <Teleport to="body">
        <Transition name="confirm-fade">
            <div v-if="open" class="fixed inset-0 z-[60] grid place-items-center bg-ink-900/55 backdrop-blur-sm p-4"
                 @click.self="onCancel" @keydown="onKey" tabindex="-1">
                <div class="glass-strong rounded-2xl max-w-md w-full p-6 shadow-glass-lg confirm-card">
                    <div class="flex items-start gap-4 mb-4">
                        <span class="grid place-items-center h-11 w-11 rounded-xl shrink-0" :class="variantStyle.iconBg">
                            <svg class="h-5 w-5" :class="variantStyle.icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" v-html="variantStyle.svg"></svg>
                        </span>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-[16px] font-bold tracking-tight" :class="variantStyle.title">{{ title }}</h3>
                            <p v-if="description" class="mt-1.5 text-[13.5px] text-ink-600 leading-relaxed whitespace-pre-line">{{ description }}</p>
                        </div>
                    </div>

                    <!-- Prompt input -->
                    <div v-if="isPrompt" class="mb-4">
                        <input ref="inputRef"
                               v-model="promptVal"
                               :type="inputType"
                               :placeholder="placeholder"
                               :min="inputMin ?? undefined"
                               :max="inputMax ?? undefined"
                               class="w-full rounded-xl border border-ink-200 bg-white px-4 py-2.5 text-[14px] focus:outline-none focus:ring-2 focus:ring-brand-indigo/30 focus:border-brand-indigo transition-shadow shadow-sm" />
                    </div>

                    <!-- Type-to-confirm input -->
                    <div v-if="typeToConfirm" class="mb-4 rounded-xl bg-rose-50/60 border border-rose-200/60 p-3">
                        <label class="font-mono text-[10.5px] tracking-widest text-rose-700">
                            TYPE <code class="bg-white px-1.5 py-0.5 rounded">{{ typeToConfirm }}</code> TO CONFIRM
                        </label>
                        <input ref="inputRef" v-model="typed" type="text" :placeholder="typeToConfirm"
                               class="mt-1.5 w-full rounded-lg border border-rose-200 bg-white px-3 py-2 text-[14px] font-mono focus:outline-none focus:ring-2 focus:ring-rose-300" />
                    </div>

                    <div class="flex gap-2 justify-end">
                        <button v-if="! isAlert"
                                type="button" @click="onCancel"
                                class="btn-ghost py-2 px-4 text-[13px]">{{ cancelText }}</button>
                        <button type="button" @click="onConfirm"
                                :disabled="! canConfirm"
                                :class="[variantStyle.confirmBtn, { 'opacity-40 pointer-events-none': ! canConfirm }]"
                                class="py-2 px-4 text-[13px]">
                            {{ confirmText }}
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.confirm-fade-enter-active,
.confirm-fade-leave-active { transition: opacity .2s ease, backdrop-filter .2s ease; }
.confirm-fade-enter-from,
.confirm-fade-leave-to     { opacity: 0; }
.confirm-fade-enter-active .confirm-card,
.confirm-fade-leave-active .confirm-card { transition: transform .25s cubic-bezier(.2,.9,.3,1.4); }
.confirm-fade-enter-from   .confirm-card { transform: scale(0.92) translateY(8px); }
.confirm-fade-leave-to     .confirm-card { transform: scale(0.95); }
</style>
