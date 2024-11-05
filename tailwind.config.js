import defaultTheme from 'tailwindcss/defaultTheme'
import forms from '@tailwindcss/forms'
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
