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
            // Royal Amethyst & Gold: deep violet brand with gold accents.
            colors: {
                brand: {
                    50:  '#f6f3ff',
                    100: '#ede7fe',
                    200: '#dcd0fd',
                    300: '#c2acfb',
                    400: '#a37ef6',
                    500: '#8b5cf6',
                    600: '#7c3aed',
                    700: '#6527d1',
                    800: '#5320ab',
                    900: '#431b87',
                    950: '#2a0f5c',
                },
                gold: {
                    50:  '#fdf9ec',
                    100: '#f9efcc',
                    200: '#f2dd94',
                    300: '#e9c75c',
                    400: '#deb33a',
                    500: '#d4a72c',
                    600: '#b8861f',
                    700: '#93651b',
                    800: '#78511d',
                    900: '#65431d',
                },
                // App shell backgrounds
                ink: {
                    DEFAULT: '#0f0a1e', // dark page
                    800:     '#160e2b', // dark header / panels
                    700:     '#1d1338', // dark sidebar top
                    600:     '#2a1d4d', // dark borders / hovers
                },
                lilac: '#f7f5fb', // light page
            },
            boxShadow: {
                'glow':      '0 6px 20px -6px rgba(124, 58, 237, 0.55)',
                'glow-lg':   '0 10px 30px -8px rgba(124, 58, 237, 0.6)',
                'glow-gold': '0 4px 16px -4px rgba(212, 167, 44, 0.55)',
                'glass':     '0 1px 0 rgba(255, 255, 255, 0.6) inset, 0 8px 24px -12px rgba(67, 27, 135, 0.18)',
            },
        },
    },
    plugins: [],
};
