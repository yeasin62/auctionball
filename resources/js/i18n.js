/**
 * vue-i18n setup for AuctionBall.
 *
 * Strategy:
 *  - Translation JSONs are bundled by Vite (small enough — <50 kB total).
 *  - Initial locale comes from Inertia shared props (`page.props.locale`),
 *    which the SetLocale middleware resolves server-side. So the first paint
 *    is correct without a flash.
 *  - Switching locales: POST /lang/{code} (server persists for guest+user),
 *    Inertia auto-refreshes the page → new shared `locale` → vue-i18n reactive.
 */
import { createI18n } from 'vue-i18n';
import en from './locales/en.json';
import bn from './locales/bn.json';

export const SUPPORTED_LOCALES = ['en', 'bn'];

export function makeI18n(initialLocale = 'en') {
    return createI18n({
        legacy: false,                  // Composition API friendly
        locale: SUPPORTED_LOCALES.includes(initialLocale) ? initialLocale : 'en',
        fallbackLocale: 'en',
        globalInjection: true,          // gives us $t() in templates without import
        messages: { en, bn },
        warnHtmlMessage: false,
        missingWarn: false,             // silence dev console for partially-translated strings
        fallbackWarn: false,
    });
}
