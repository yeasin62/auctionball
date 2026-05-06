/**
 * Locale + currency-aware money/number formatters.
 *
 * Reads `locale` and `currentOrg.display_currency / bdt_per_usd` from
 * Inertia shared props, so the moment a user toggles language or the
 * org admin changes display currency, every {{ fmt.money(...) }} call
 * re-renders correctly.
 *
 * Internal amounts are always BDT integers. fmt.money() converts to USD
 * at the org's stored rate when display_currency=USD.
 */
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useFmt() {
    const page = usePage();

    const locale   = computed(() => page.props.locale ?? 'en');
    const currency = computed(() => page.props.currentOrg?.display_currency ?? 'BDT');
    const rate     = computed(() => Math.max(1, page.props.currentOrg?.bdt_per_usd ?? 110));

    // Indian grouping (lakh) for both BN and EN — that's the convention
    // for prices in Bangladesh and India alike.
    const numberLocale = computed(() => locale.value === 'bn' ? 'bn-IN' : 'en-IN');

    const number = (n) =>
        new Intl.NumberFormat(numberLocale.value).format(Number(n) || 0);

    const money = (bdtAmount) => {
        const n = Number(bdtAmount) || 0;
        if (currency.value === 'USD') {
            const usd = Math.round(n / rate.value);
            return '$' + new Intl.NumberFormat(numberLocale.value).format(usd);
        }
        return '৳' + new Intl.NumberFormat(numberLocale.value).format(n);
    };

    /** Symbol-only (for sub-headers like "Total: ৳"). */
    const symbol = () => currency.value === 'USD' ? '$' : '৳';

    /**
     * Localise any pre-formatted string's digits (e.g. timer "00:04",
     * static stats like "42.8 BAT AVG", timestamps "14:32:14") so the
     * big-screen reads in Bengali end-to-end when locale=bn.
     */
    const BN_DIGITS = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
    const localizeDigits = (str) => {
        if (locale.value !== 'bn' || str == null) return str;
        return String(str).replace(/[0-9]/g, (d) => BN_DIGITS[+d]);
    };

    return { money, number, symbol, localizeDigits, locale, currency, rate };
}
