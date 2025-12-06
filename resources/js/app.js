import './bootstrap';
import '../css/app.css';

import { createApp, h } from 'vue';
import { createI18n } from 'vue-i18n';
import frMessages from './i18n/fr.json';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy/dist/vue.m';
import Toast, { POSITION } from "vue-toastification";
import "vue-toastification/dist/index.css";

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        // load translations (default to French)
        const messages = {
            fr: frMessages || {}
        };

        const i18n = createI18n({
            legacy: false,
            locale: 'fr',
            fallbackLocale: 'fr',
            messages,
        });

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(Toast, { position: POSITION.BOTTOM_CENTER })
            .use(i18n)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
