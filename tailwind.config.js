import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        // Pick up dynamic class names returned from PHP (e.g. enum chipClasses()
        // in app/Enums/Status.php and app/Enums/Moscow.php).
        './app/**/*.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Override Tailwind's "indigo" palette with shades centred on
                // the brand red (#e53225). Breeze and our own templates use
                // indigo-* for accents (primary button, focus rings, links,
                // active nav states); this swaps them all in one place
                // without touching templates.
                //
                // Status chips (R/A/G/B, MoSCoW) use red-*/amber-*/etc. so
                // they're unaffected by this override.
                indigo: {
                    50:  '#fef2f1',
                    100: '#fde5e2',
                    200: '#fbcec8',
                    300: '#f7a59a',
                    400: '#f1715f',
                    500: '#e53225',
                    600: '#c41f12',
                    700: '#a01a10',
                    800: '#841a13',
                    900: '#6e1813',
                    950: '#3c0904',
                },
            },
        },
    },

    plugins: [forms],
};
