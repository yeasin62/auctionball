import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

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
            fontFamily: {
                // Mona Sans for Latin, Anek Bangla for Bangla — browsers fall through
                // per-character, so ব / ং / া land on Anek Bangla while Latin stays Mona.
                sans: ['"Mona Sans"', '"Anek Bangla"', 'ui-sans-serif', 'system-ui', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', '"Anek Bangla"', 'ui-monospace', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                ink: {
                    50:  '#f8fafc',
                    100: '#f1f5f9',
                    200: '#e2e8f0',
                    300: '#cbd5e1',
                    400: '#94a3b8',
                    500: '#64748b',
                    600: '#475569',
                    700: '#334155',
                    800: '#1e293b',
                    900: '#0a0e27',
                    950: '#050816',
                },
                brand: {
                    cyan:   '#06b6d4',
                    blue:   '#3b82f6',
                    indigo: '#6366f1',
                    violet: '#8b5cf6',
                },
            },
            backgroundImage: {
                'gradient-brand': 'linear-gradient(135deg, #06b6d4 0%, #3b82f6 35%, #6366f1 70%, #8b5cf6 100%)',
                'gradient-text':  'linear-gradient(90deg, #0ea5e9 0%, #6366f1 50%, #8b5cf6 100%)',
                'grid-dark':      'linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px)',
            },
            boxShadow: {
                'glass':       '0 8px 32px rgba(31, 38, 135, 0.06)',
                'glass-lg':    '0 16px 48px rgba(31, 38, 135, 0.08)',
                'card':        '0 4px 24px rgba(31, 38, 135, 0.05)',
                'cta':         '0 12px 32px -8px rgba(99, 102, 241, 0.55)',
                'pricing-pop': '0 0 0 1.5px #6366f1, 0 20px 50px -10px rgba(99,102,241,.35)',
            },
            keyframes: {
                blob: {
                    '0%,100%': { transform: 'translate(0,0) scale(1)' },
                    '33%':     { transform: 'translate(30px,-20px) scale(1.05)' },
                    '66%':     { transform: 'translate(-20px,30px) scale(0.95)' },
                },
            },
            animation: {
                blob: 'blob 18s ease-in-out infinite',
            },
        },
    },

    plugins: [forms],
};
