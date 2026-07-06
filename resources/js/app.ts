import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { initializeTheme } from './composables/useAppearance';

const appName = 'TechDev Nexus';
const srcViews = import.meta.glob<DefineComponent>('./src/views/**/*.vue');
const moduleViews = import.meta.glob<DefineComponent>('./views/**/*.vue');
const legacyPages = import.meta.glob<DefineComponent>('./pages/**/*.vue');

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => {
        const srcView = srcViews[`./src/views/${name}.vue`];
        const moduleView = moduleViews[`./views/${name}.vue`];

        if (srcView) {
            return srcView();
        }

        if (moduleView) {
            return moduleView();
        }

        return resolvePageComponent(`./pages/${name}.vue`, legacyPages);
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#168447',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
