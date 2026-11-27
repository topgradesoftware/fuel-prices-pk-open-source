import { createApp, h, type DefineComponent } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import '../css/app.css';
import { initPwa } from './pwa';

void initPwa();

const pages = import.meta.glob('./Pages/**/*.vue') as Record<
    string,
    () => Promise<{ default: DefineComponent }>
>;

createInertiaApp({
    title: (title) => {
        const brand = import.meta.env.VITE_APP_NAME ?? 'Fuel Prices PK';
        if (!title) return brand;
        // SeoHead already sends full branded titles — don't double-append.
        if (title.includes(brand) || title.includes('Fuel Prices PK')) return title;
        return `${title} | ${brand}`;
    },
    resolve: async (name) => {
        const loader = pages[`./Pages/${name}.vue`];
        if (!loader) {
            throw new Error(`Page not found: ./Pages/${name}.vue`);
        }
        const page = await loader();
        return page.default;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#f59e0b',
    },
});
