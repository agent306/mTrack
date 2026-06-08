import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { createApp, h, type DefineComponent } from 'vue';

const appName = import.meta.env.VITE_APP_NAME ?? 'mTrack';
const pages = import.meta.glob<DefineComponent>('./Pages/**/*.vue');

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: async (name) => {
        const page = pages[`./Pages/${name}.vue`];

        if (!page) {
            throw new Error(`Page not found: ${name}`);
        }

        return page();
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) }).use(plugin).mount(el);
    },
    progress: {
        color: '#14B8A6',
    },
});
