/**
 * Imperative dialog API — replaces window.confirm / window.alert / window.prompt.
 *
 *   import { useConfirm, useAlert, usePrompt } from '@/composables/useConfirm';
 *
 *   const confirm = useConfirm();
 *   const alert   = useAlert();
 *   const prompt  = usePrompt();
 *
 *   const ok = await confirm({ title: 'Delete?', variant: 'danger' });
 *   await alert({ title: 'Copied!', variant: 'info' });
 *   const value = await prompt({ title: 'New BDT step', defaultValue: 1000, type: 'number' });
 *
 * Backed by a single reactive store consumed by <ConfirmProvider />, which is
 * mounted once in DashboardLayout. One dialog is reused across the whole app —
 * no per-page mounts needed.
 *
 * Resolver semantics:
 *   confirm → boolean
 *   alert   → true (after OK)
 *   prompt  → string | null (null when cancelled)
 */
import { reactive } from 'vue';

const state = reactive({
    open:     false,
    options:  null,
    resolver: null,
});

const confirmDefaults = {
    kind:          'confirm',
    title:         'Are you sure?',
    description:   '',
    confirmText:   'Confirm',
    cancelText:    'Cancel',
    variant:       'warning',
    typeToConfirm: null,
    inputType:     null,
    inputValue:    '',
    placeholder:   '',
    inputMin:      null,
    inputMax:      null,
};

const alertDefaults = {
    kind:        'alert',
    title:       '',
    description: '',
    confirmText: 'OK',
    cancelText:  null,
    variant:     'info',
};

const promptDefaults = {
    kind:          'prompt',
    title:         '',
    description:   '',
    confirmText:   'Save',
    cancelText:    'Cancel',
    variant:       'info',
    inputType:     'text',
    inputValue:    '',
    placeholder:   '',
    inputRequired: true,    // false → allow empty submission
};

const open = (defaults, options, resolve) => {
    if (state.resolver) state.resolver(null);
    state.options  = { ...defaults, ...options };
    state.resolver = resolve;
    state.open     = true;
};

export function useConfirm() {
    return (options = {}) => new Promise((resolve) => {
        open(confirmDefaults, options, (v) => resolve(!! v));
    });
}

export function useAlert() {
    return (options = {}) => new Promise((resolve) => {
        // alert resolves true on OK, false on Esc/backdrop dismiss
        open(alertDefaults, options, (v) => resolve(v !== null && v !== false));
    });
}

export function usePrompt() {
    return (options = {}) => new Promise((resolve) => {
        const merged = {
            ...promptDefaults,
            ...options,
            inputValue: options.defaultValue ?? options.inputValue ?? '',
        };
        open(merged, {}, (v) => {
            if (v === null || v === false) return resolve(null);
            resolve(typeof v === 'string' ? v : String(v));
        });
    });
}

/* ----- internal API used by ConfirmProvider only ----- */

export function _useConfirmState() {
    return state;
}
export function _resolveConfirm(value) {
    if (state.resolver) state.resolver(value);
    state.resolver = null;
    state.open     = false;
}
