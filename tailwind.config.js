const colors = require('tailwindcss/colors');

const gray = {
    50: 'hsl(210, 20%, 98%)',
    100: 'hsl(210, 20%, 94%)',
    200: 'hsl(210, 20%, 86%)',
    300: 'hsl(210, 20%, 72%)',
    400: 'hsl(210, 20%, 60%)',
    500: 'hsl(210, 20%, 48%)',
    600: 'hsl(210, 20%, 38%)',
    700: 'hsl(210, 20%, 28%)',
    800: 'hsl(210, 20%, 18%)',
    900: 'hsl(210, 20%, 10%)',
};

module.exports = {
    content: [
        './resources/scripts/**/*.{js,ts,tsx}',
    ],
    theme: {
        extend: {
            fontFamily: {
                header: ['"IBM Plex Sans"', '"Roboto"', 'system-ui', 'sans-serif'],
            },
            colors: {
                black: '#131a20',
                // "primary" and "neutral" are deprecated, prefer the use of "blue" and "gray"
                // in new code.
                primary: {
                    50: '#e0fbfc',
                    100: '#bdf6fa',
                    200: '#8bedf7',
                    300: '#4ce0f2',
                    400: '#18cbe8',
                    500: '#06b0d1',
                    600: '#068ca6',
                    700: '#087187',
                    800: '#0e5d6d',
                    900: '#114d5c',
                },
                gray: gray,
                neutral: gray,
                cyan: colors.cyan,
            },
            fontSize: {
                '2xs': '0.625rem',
            },
            transitionDuration: {
                250: '250ms',
            },
            borderColor: theme => ({
                default: theme('colors.neutral.400', 'currentColor'),
            }),
        },
    },
    plugins: [
        require('@tailwindcss/line-clamp'),
        require('@tailwindcss/forms')({
            strategy: 'class',
        }),
    ]
};
