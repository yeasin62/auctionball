<script setup>
/**
 * Singleton mount for the global dialog. Drop this once into a top-level
 * layout (we use DashboardLayout) and any descendant page can call
 * useConfirm() / useAlert() / usePrompt() to open the modal.
 */
import ConfirmDialog from './ConfirmDialog.vue';
import { _useConfirmState, _resolveConfirm } from '@/composables/useConfirm';

const state = _useConfirmState();
// value: true (confirm), false (cancel), string (prompt input), null (esc/backdrop)
const onResolve = (value) => _resolveConfirm(value);
</script>

<template>
    <ConfirmDialog v-bind="state.options || {}"
                   :open="state.open"
                   @resolve="onResolve" />
</template>
