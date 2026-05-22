import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { makeI18n } from './i18n';
import ConfirmProvider from './Components/ConfirmProvider.vue';
import WhatsAppChatButton from './Components/WhatsAppChatButton.vue';

const appName = import.meta.env.VITE_APP_NAME || 'AuctionBall';
const realtimePagePrefixes = [
    'Dashboard/',
    'SuperAdmin/',
    'TeamDevice/',
];

const needsRealtime = (component) => realtimePagePrefixes.some((prefix) => component?.startsWith(prefix));

const bootRealtime = async () => {
    const { bootEcho } = await import('./echo');
    bootEcho();
};

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        // Initial locale comes from server (SetLocale middleware → Inertia shared prop)
        const initialLocale = props.initialPage?.props?.locale ?? 'en';
        const i18n = makeI18n(initialLocale);

        // Wrap App with ConfirmProvider so useConfirm/useAlert/usePrompt work
        // on every page (auth, public, team-device) — not just DashboardLayout.
        const mountApp = () => {
            const app = createApp({ render: () => h('div', [h(App, props), h(ConfirmProvider), h(WhatsAppChatButton)]) })
                .use(plugin)
                .use(ZiggyVue)
                .use(i18n);

            // Keep i18n locale in sync with the shared prop after Inertia visits.
            app.mixin({
                mounted() {
                    const next = this.$page?.props?.locale;
                    if (next && next !== i18n.global.locale.value) {
                        i18n.global.locale.value = next;
                    }
                },
                updated() {
                    const next = this.$page?.props?.locale;
                    if (next && next !== i18n.global.locale.value) {
                        i18n.global.locale.value = next;
                    }
                },
            });

            return app.mount(el);
        };

        if (needsRealtime(props.initialPage?.component)) {
            bootRealtime().finally(mountApp);
            return;
        }

        return mountApp();
    },
    progress: {
        color: '#6366f1',
    },
});
