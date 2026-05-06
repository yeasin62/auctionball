<script setup>
/**
 * Currency-aware money input.
 *
 * Internally everything is BDT (canonical storage). When the org's display_currency
 * is USD, this component:
 *   · shows "$" as the leading symbol
 *   · displays the value converted to USD (bdt / rate)
 *   · converts back to BDT on input before emitting
 *
 * So the form's v-model is always a BDT integer — backend doesn't need to know
 * the display unit. Switching display currency in Settings flips every CurrencyField
 * in every form on the next page render with no ambiguity about what got stored.
 */
import TextField from './TextField.vue';
import { useFmt } from '@/composables/useFmt';
import { computed } from 'vue';

const props = defineProps({
    modelValue: [Number, String],
    /** Pass-through props (placeholder, autofocus, disabled, etc.) handled via $attrs. */
});
const emit = defineEmits(['update:modelValue']);

const { currency, rate, symbol: getSymbol } = useFmt();

const symbol = computed(() => getSymbol());

const displayValue = computed(() => {
    const v = Number(props.modelValue) || 0;
    return currency.value === 'USD' ? Math.round(v / rate.value) : v;
});

const onInput = (input) => {
    const n = Number(input) || 0;
    emit('update:modelValue',
        currency.value === 'USD' ? Math.round(n * rate.value) : n
    );
};
</script>

<template>
    <TextField
        :modelValue="displayValue"
        @update:modelValue="onInput"
        type="number"
        :leading="symbol"
        v-bind="$attrs"
    />
</template>
