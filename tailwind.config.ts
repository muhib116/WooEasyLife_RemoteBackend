import defaultTheme from 'tailwindcss/defaultTheme'
import forms from '@tailwindcss/forms'
import * as themeOption from '@/theme'
import plugin from 'tailwindcss/plugin'
import Typography from '@tailwindcss/typography'
import theme from 'tailwindcss/defaultTheme'

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
        './resources/js/**/*',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                brand: {
                    gold: '#ffc107',
                    'gold-light': '#ffd54f',
                    'gold-dark': '#e6a800',
                    black: '#0a0a0a',
                    surface: '#111111',
                    panel: '#161616',
                },
                'gray': {
                    '50': '#f4f6f7',
                    '100': '#e3e7ea',
                    '200': '#cbd3d6',
                    '300': '#a6b4ba',
                    '400': '#7a8c96',
                    '500': '#5f717b',
                    '600': '#515f69',
                    '700': '#465058',
                    '800': '#3e454c',
                    '900': '#373d42',
                    '950': '#212529',
                },
                'primary': {
                    // '50': '#fff9eb',
                    // '100': '#ffedc6',
                    // '200': '#fedb89',
                    // '300': '#fec14a',
                    // '400': '#fda922',
                    // '500': '#f88608',
                    // '600': '#db6104',
                    // '700': '#b64107',
                    // '800': '#94320c',
                    // '900': '#792a0e',
                    // '950': '#461302',
                    '50': 'var(--p-primary-50)',
                    '100': 'var(--p-primary-100)',
                    '200': 'var(--p-primary-200)',
                    '300': 'var(--p-primary-300)',
                    '400': 'var(--p-primary-400)',
                    '500': 'var(--p-primary-500)',
                    '600': 'var(--p-primary-600)',
                    '700': 'var(--p-primary-700)',
                    '800': 'var(--p-primary-800)',
                    '900': 'var(--p-primary-900)',
                    '950': 'var(--p-primary-950)',
                    // --p-primary-50: #fff9eb;
                    // --p-primary-100: #ffedc6;
                    // --p-primary-200: #fedb89;
                    // --p-primary-300: #fec14a;
                    // --p-primary-400: #fda922;
                    // --p-primary-500: #f88608;
                    // --p-primary-600: #db6104;
                    // --p-primary-700: #b64107;
                    // --p-primary-800: #94320c;
                    // --p-primary-900: #792a0e;
                    // --p-primary-950: #461302;
                },
                'success': {
                    '50': '#f0fdf6',
                    '100': '#ddfbec',
                    '200': '#bcf6d9',
                    '300': '#88edbd',
                    '400': '#4ddb98',
                    '500': '#25c279',
                    '600': '#19a061',
                    '700': '#198754',
                    '800': '#186340',
                    '900': '#155237',
                    '950': '#062d1c',
                },

            },
            boxShadow: {
                'box': '0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1)',
            }
        },
    },

    plugins: [
        plugin(function ({ addUtilities, theme }) {
            addUtilities({
                '.box-border': {
                    'border-color': theme('colors.gray.200'),
                },
                '.dark .box-border': {
                    'border-color': theme('colors.gray.800'),
                },
                '.box-bg': {
                    'background-color': theme('colors.white'),
                    'box-shadow': '0 1px 2px 0 rgb(0 0 0 / 0.04)',
                },
                '.dark .box-bg': {
                    'background-color': theme('colors.slate.800'),
                    'box-shadow': '0 1px 2px 0 rgb(0 0 0 / 0.2)',
                },
                '.box-color': {
                    'color': theme('colors.black')
                },
                '.dark .box-color': {
                    'color': theme('colors.white')
                },
            });
        }),
        forms,
        Typography,

        // border-gray-200 bg-white p-4 dark:border-gray-800
        // plugin(function({ addComponents }) {
        //     addComponents(applicationComponents)
        // })
    ],
};
