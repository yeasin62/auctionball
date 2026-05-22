/**
 * vue-i18n setup for AuctionBall.
 *
 * Strategy:
 *  - Translation JSONs are loaded on demand so public pages do not ship every
 *    locale in the first JavaScript payload.
 *  - Initial locale comes from Inertia shared props (`page.props.locale`),
 *    which the SetLocale middleware resolves server-side. So the first paint
 *    is correct without a flash.
 *  - Switching locales: POST /lang/{code} (server persists for guest+user),
 *    Inertia auto-refreshes the page → new shared `locale` → vue-i18n reactive.
 */
import { createI18n } from 'vue-i18n';

export const SUPPORTED_LOCALES = ['en', 'bn'];

const localeLoaders = {
    en: () => import('./locales/en.json'),
    bn: () => import('./locales/bn.json'),
};

const normalizeLocale = (locale) => SUPPORTED_LOCALES.includes(locale) ? locale : 'en';
const loadMessages = async (locale) => (await localeLoaders[normalizeLocale(locale)]()).default;

export async function ensureLocaleMessages(i18n, locale) {
    const normalized = normalizeLocale(locale);
    if (i18n.global.availableLocales.includes(normalized)) return normalized;

    i18n.global.setLocaleMessage(normalized, await loadMessages(normalized));
    return normalized;
}

export async function makeI18n(initialLocale = 'en') {
    const locale = normalizeLocale(initialLocale);

    return createI18n({
        legacy: false,                  // Composition API friendly
        locale,
        fallbackLocale: 'en',
        globalInjection: true,          // gives us $t() in templates without import
        messages: {
            [locale]: await loadMessages(locale),
        },
        warnHtmlMessage: false,
        missingWarn: false,             // silence dev console for partially-translated strings
        fallbackWarn: false,
    });
}
