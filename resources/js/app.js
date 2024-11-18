import './bootstrap';
import '../css/app.css';
import 'primeicons/primeicons.css'
import 'ckeditor5/ckeditor5.css'
import '../css/ckeditorOverride.css'

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from './ziggy';
import { vClickOutside } from '@/plugins/directives'
import PrimeVue from 'primevue/config';
import Aura from './aura';
import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';
import { CkeditorPlugin } from '@ckeditor/ckeditor5-vue'
import DialogService from 'primevue/dialogservice';

const appName = import.meta.env.VITE_APP_NAME || 'Natural Care';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(vClickOutside)
            .use(ConfirmationService)
            .use(ToastService)
            .use(CkeditorPlugin)
            .use(DialogService)
            .use(PrimeVue, {
                theme: {
                    preset: Aura,
                    options: {
                        // prefix: 'p',
                        darkModeSelector: '.my-app-dark',
                        cssLayer: false,
                    }
                },
                ripple: true,
            })
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
