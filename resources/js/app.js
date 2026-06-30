import './bootstrap';
import '../css/app.css';
import '../css/app-loader.css';
import 'primeicons/primeicons.css'
import 'ckeditor5/ckeditor5.css'
import '../css/ckeditorOverride.css'

import { createApp, h, ref, onMounted } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from './ziggy';
import { vClickOutside } from '@/plugins/directives'
import PrimeVue from 'primevue/config';
import Aura from './aura';
import ConfirmationService from 'primevue/confirmationservice';
import ToastService from 'primevue/toastservice';
import { CkeditorPlugin } from '@ckeditor/ckeditor5-vue'
import DialogService from 'primevue/dialogservice';
import axios from 'axios';
import AppLoader from '@/components/AppLoader.vue';

// set axios default Bearer token
axios.defaults.headers.common['Authorization'] = `Bearer Kod30eDnI1EFG9vaf9gBPsSwaD3IkklCIATZoSYz9cf733bd`;

const appName = import.meta.env.VITE_APP_NAME || 'WooEasyLife';

function hideInitialLoader() {
    const loader = document.getElementById('app-loader');
    if (!loader) {
        return;
    }

    loader.classList.add('is-hidden');
    window.setTimeout(() => loader.remove(), 300);
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const navigating = ref(false);

        router.on('start', () => {
            navigating.value = true;
        });

        router.on('finish', () => {
            navigating.value = false;
            hideInitialLoader();
        });

        router.on('error', () => {
            navigating.value = false;
            hideInitialLoader();
        });

        const app = createApp({
            setup() {
                onMounted(() => {
                    hideInitialLoader();
                });

                return () => [
                    h(AppLoader, { show: navigating.value }),
                    h(App, props),
                ];
            },
        });

        return app
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
                        darkModeSelector: '.dark',
                        cssLayer: false,
                    }
                },
                ripple: true,
            })
            .mount(el);
    },
    progress: false,
});
