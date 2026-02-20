import tw from 'twin.macro';
import { createGlobalStyle } from 'styled-components/macro';
// @ts-expect-error untyped font file
import font from '@fontsource-variable/ibm-plex-sans/files/ibm-plex-sans-latin-wght-normal.woff2';

export default createGlobalStyle`
    :root {
        --bg-base: #171c2a;
        --bg-elevated: #20283a;
        --ui-border: rgba(131, 173, 196, 0.22);
        --ui-border-strong: rgba(131, 173, 196, 0.42);
        --ui-glow: rgba(49, 196, 223, 0.25);
        --text-muted: #98afbf;
    }

    body[data-dashboard-template='ocean'] {
        --bg-base: #0f2233;
        --bg-elevated: #143247;
        --ui-border: rgba(91, 198, 255, 0.26);
        --ui-border-strong: rgba(91, 198, 255, 0.52);
        --ui-glow: rgba(75, 201, 255, 0.32);
        --text-muted: #9ec7db;
    }

    body[data-dashboard-template='ember'] {
        --bg-base: #251910;
        --bg-elevated: #322318;
        --ui-border: rgba(255, 160, 99, 0.26);
        --ui-border-strong: rgba(255, 160, 99, 0.5);
        --ui-glow: rgba(255, 133, 61, 0.28);
        --text-muted: #d6b19a;
    }

    @font-face {
        font-family: 'IBM Plex Sans';
        font-style: normal;
        font-display: swap;
        font-weight: 100 700;
        src: url(${font}) format('woff2-variations');
        unicode-range: U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD;
    }

    body {
        ${tw`font-sans bg-neutral-800 text-neutral-200`};
        letter-spacing: 0.015em;
        min-height: 100vh;
        background:
            radial-gradient(1250px 460px at 8% -10%, rgba(31, 140, 178, 0.16), transparent 60%),
            radial-gradient(900px 420px at 95% 0%, rgba(17, 76, 122, 0.2), transparent 62%),
            linear-gradient(180deg, #0f1420 0%, var(--bg-base) 100%);
        text-rendering: optimizeLegibility;
        -webkit-font-smoothing: antialiased;
    }

    body[data-dashboard-template='ocean'] {
        background:
            radial-gradient(1200px 460px at 12% -12%, rgba(27, 194, 255, 0.2), transparent 60%),
            radial-gradient(960px 420px at 96% 2%, rgba(24, 103, 178, 0.22), transparent 62%),
            linear-gradient(180deg, #09111b 0%, var(--bg-base) 100%);
    }

    body[data-dashboard-template='ember'] {
        background:
            radial-gradient(1200px 420px at -4% -14%, rgba(255, 129, 64, 0.2), transparent 60%),
            radial-gradient(900px 400px at 98% 4%, rgba(200, 89, 34, 0.2), transparent 60%),
            linear-gradient(180deg, #170f0a 0%, var(--bg-base) 100%);
    }

    h1, h2, h3, h4, h5, h6 {
        ${tw`font-medium tracking-normal font-header`};
    }

    p {
        ${tw`text-neutral-200 leading-snug font-sans`};
    }

    a {
        transition: color 160ms ease, opacity 160ms ease, border-color 160ms ease, box-shadow 180ms ease;
    }

    *::selection {
        background: rgba(49, 196, 223, 0.32);
        color: #ebf8ff;
    }

    *:focus-visible {
        outline: 2px solid rgba(88, 221, 255, 0.8);
        outline-offset: 2px;
        border-radius: 0.25rem;
    }

    form {
        ${tw`m-0`};
    }

    textarea, select, input, button, button:focus, button:focus-visible {
        ${tw`outline-none`};
    }

    input[type=number]::-webkit-outer-spin-button,
    input[type=number]::-webkit-inner-spin-button {
        -webkit-appearance: none !important;
        margin: 0;
    }

    input[type=number] {
        -moz-appearance: textfield !important;
    }

    /* Scroll Bar Style */
    ::-webkit-scrollbar {
        background: none;
        width: 16px;
        height: 16px;
    }

    ::-webkit-scrollbar-thumb {
        border: solid 0 rgb(0 0 0 / 0%);
        border-right-width: 4px;
        border-left-width: 4px;
        -webkit-border-radius: 9px 4px;
        -webkit-box-shadow:
            inset 0 0 0 1px rgba(122, 168, 196, 0.5),
            inset 0 0 0 4px rgba(42, 57, 82, 0.9);
    }

    ::-webkit-scrollbar-track-piece {
        margin: 4px 0;
    }

    ::-webkit-scrollbar-thumb:horizontal {
        border-right-width: 0;
        border-left-width: 0;
        border-top-width: 4px;
        border-bottom-width: 4px;
        -webkit-border-radius: 4px 9px;
    }

    ::-webkit-scrollbar-corner {
        background: transparent;
    }

    @media (prefers-reduced-motion: reduce) {
        *,
        *::before,
        *::after {
            animation-duration: 1ms !important;
            animation-iteration-count: 1 !important;
            transition-duration: 1ms !important;
            scroll-behavior: auto !important;
        }
    }
`;
