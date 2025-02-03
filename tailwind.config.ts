import defaultTheme from 'tailwindcss/defaultTheme'
import forms from '@tailwindcss/forms'
import * as themeOption from '@/theme'
import plugin from 'tailwindcss'
import Typography from '@tailwindcss/typography'

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
                    '50': '#fff9eb',
                    '100': '#ffedc6',
                    '200': '#fedb89',
                    '300': '#fec14a',
                    '400': '#fda922',
                    '500': '#f88608',
                    '600': '#db6104',
                    '700': '#b64107',
                    '800': '#94320c',
                    '900': '#792a0e',
                    '950': '#461302',
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
        forms,
        Typography
        // plugin(function({ addComponents }) {
        //     addComponents(applicationComponents)
        // })
    ],
};
