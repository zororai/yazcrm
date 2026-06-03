/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],
    theme: {
        extend: {
            keyframes: {
                shrink: {
                    '0%':   { width: '100%' },
                    '100%': { width: '0%' },
                },
                flicker: {
                    '0%, 100%': { opacity: '1',    transform: 'scale(1)' },
                    '25%':      { opacity: '0.1',  transform: 'scale(1.15)' },
                    '50%':      { opacity: '1',    transform: 'scale(1)' },
                    '75%':      { opacity: '0.1',  transform: 'scale(1.15)' },
                },
            },
            animation: {
                'shrink':  'shrink 10s linear forwards',
                'flicker': 'flicker 0.8s ease-in-out infinite',
            },
            colors: {
                brand: {
                    50:  '#eef2ff',
                    100: '#e0e7ff',
                    500: '#6366f1',
                    600: '#4f46e5',
                    700: '#4338ca',
                    900: '#312e81',
                },
            },
        },
    },
    plugins: [],
};
