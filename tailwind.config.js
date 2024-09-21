import defaultTheme from 'tailwindcss/defaultTheme'
import forms from '@tailwindcss/forms'
import plugin from 'tailwindcss'
import applicationComponents from '@/theme/Index'

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
        './resources/js/**/*.js',
        './resources/js/**/*.ts',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                'primary': {
                    '50': '#efeeff',
                    '100': '#e2e0ff',
                    '200': '#cac7fe',
                    '300': '#aaa5fc',
                    '400': '#8881f8',
                    '500': '#6b63f1',
                    '600': '#4f46e5',
                    '700': '#4038ca',
                    '800': '#3730a3',
                    '900': '#332e81',
                    '950': '#1e1b4b',
                }
            }
        },
    },

    plugins: [
        forms,
        plugin(function({ addComponents }) {
            addComponents(applicationComponents)
        })
    ],
};
