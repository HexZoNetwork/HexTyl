import tw from 'twin.macro';
import { createGlobalStyle } from 'styled-components/macro';
// @ts-expect-error untyped font file
import font from '@fontsource-variable/ibm-plex-sans/files/ibm-plex-sans-latin-wght-normal.woff2';

export default createGlobalStyle`
    :root {
        --bg-base: #111522;
        --bg-elevated: #1a2234;
        --ui-border: rgba(151, 169, 207, 0.2);
        --ui-border-strong: rgba(151, 169, 207, 0.36);
        --ui-glow: rgba(148, 168, 214, 0.22);
        --text-muted: #9aa8c0;
        --input-bg: rgba(12, 18, 31, 0.8);
        --input-border: rgba(127, 145, 186, 0.34);
        --input-focus: rgba(176, 192, 228, 0.88);
        --glass-bg: rgba(22, 31, 48, 0.66);
        --glass-elevated: rgba(24, 35, 56, 0.8);
        --chip-bg: rgba(33, 41, 60, 0.62);
        --surface-tint: rgba(165, 180, 252, 0.16);
        --nav-start: rgba(12, 16, 27, 0.92);
        --nav-mid: rgba(17, 23, 38, 0.92);
        --nav-end: rgba(19, 27, 44, 0.92);
        --panel-shadow: 0 16px 34px rgba(4, 10, 20, 0.48);
        --accent: #a5b4fc;
        --accent-soft: rgba(165, 180, 252, 0.24);
        --accent-strong: rgba(165, 180, 252, 0.52);
    }

    body[data-dashboard-template='ocean'] {
        --bg-base: #0f1d2c;
        --bg-elevated: #16293f;
        --ui-border: rgba(133, 194, 232, 0.22);
        --ui-border-strong: rgba(133, 194, 232, 0.4);
        --ui-glow: rgba(118, 192, 232, 0.26);
        --text-muted: #9cb8cf;
        --chip-bg: rgba(22, 44, 64, 0.62);
        --surface-tint: rgba(125, 211, 252, 0.14);
        --nav-start: rgba(8, 18, 30, 0.92);
        --nav-mid: rgba(12, 34, 51, 0.92);
        --nav-end: rgba(16, 47, 70, 0.92);
        --accent: #7dd3fc;
        --accent-soft: rgba(125, 211, 252, 0.24);
        --accent-strong: rgba(125, 211, 252, 0.52);
    }

    body[data-dashboard-template='ember'] {
        --bg-base: #121827;
        --bg-elevated: #1a2438;
        --ui-border: rgba(157, 189, 246, 0.22);
        --ui-border-strong: rgba(236, 186, 102, 0.44);
        --ui-glow: rgba(236, 186, 102, 0.2);
        --text-muted: #c3d0e7;
        --chip-bg: rgba(34, 45, 66, 0.64);
        --surface-tint: rgba(236, 186, 102, 0.1);
        --nav-start: rgba(9, 15, 27, 0.94);
        --nav-mid: rgba(15, 27, 44, 0.94);
        --nav-end: rgba(22, 35, 58, 0.94);
        --accent: #ecba66;
        --accent-soft: rgba(236, 186, 102, 0.16);
        --accent-strong: rgba(236, 186, 102, 0.46);
    }

    body[data-dashboard-template='slate'] {
        --bg-base: #1a1f2d;
        --bg-elevated: #252c3e;
        --ui-border: rgba(164, 178, 210, 0.28);
        --ui-border-strong: rgba(164, 178, 210, 0.52);
        --ui-glow: rgba(167, 192, 235, 0.3);
        --text-muted: #aeb8cc;
        --input-bg: rgba(14, 19, 32, 0.72);
        --input-border: rgba(137, 152, 186, 0.4);
        --input-focus: rgba(188, 204, 237, 0.9);
        --glass-bg: rgba(25, 33, 49, 0.6);
        --glass-elevated: rgba(31, 40, 59, 0.76);
        --chip-bg: rgba(38, 47, 70, 0.62);
        --surface-tint: rgba(196, 181, 253, 0.15);
        --nav-start: rgba(16, 20, 33, 0.93);
        --nav-mid: rgba(26, 32, 49, 0.93);
        --nav-end: rgba(39, 48, 74, 0.93);
        --accent: #c4b5fd;
        --accent-soft: rgba(196, 181, 253, 0.24);
        --accent-strong: rgba(196, 181, 253, 0.52);
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
        letter-spacing: 0.005em;
        font-size: 15.5px;
        line-height: 1.5;
        min-height: 100vh;
        position: relative;
        background:
            radial-gradient(1000px 500px at 12% -14%, rgba(126, 145, 198, 0.16), transparent 62%),
            radial-gradient(780px 420px at 96% -6%, rgba(73, 96, 158, 0.14), transparent 66%),
            linear-gradient(180deg, #0c101a 0%, var(--bg-base) 100%);
        text-rendering: optimizeLegibility;
        -webkit-font-smoothing: antialiased;
    }

    body::before {
        content: '';
        position: fixed;
        inset: 0;
        pointer-events: none;
        opacity: 0.22;
        background-image:
            linear-gradient(rgba(255, 255, 255, 0.02) 1px, transparent 1px),
            linear-gradient(90deg, rgba(255, 255, 255, 0.02) 1px, transparent 1px);
        background-size: 44px 44px;
        mask-image: radial-gradient(circle at center, black 34%, transparent 88%);
        z-index: 0;
    }

    body[data-dashboard-template='ocean'] {
        background:
            radial-gradient(920px 420px at 10% -14%, rgba(102, 194, 247, 0.16), transparent 62%),
            radial-gradient(720px 380px at 98% -4%, rgba(66, 116, 206, 0.14), transparent 66%),
            linear-gradient(180deg, #0b1220 0%, var(--bg-base) 100%);
    }

    body[data-dashboard-template='ember'] {
        background:
            radial-gradient(960px 420px at 8% -10%, rgba(236, 186, 102, 0.13), transparent 62%),
            radial-gradient(780px 380px at 98% -2%, rgba(104, 153, 244, 0.14), transparent 66%),
            linear-gradient(180deg, #0f1523 0%, var(--bg-base) 100%);
    }

    body[data-dashboard-template='slate'] {
        background:
            radial-gradient(1200px 440px at 6% -10%, rgba(153, 172, 220, 0.2), transparent 60%),
            radial-gradient(940px 420px at 98% 2%, rgba(112, 134, 194, 0.2), transparent 62%),
            linear-gradient(180deg, #101421 0%, var(--bg-base) 100%);
    }

    h1, h2, h3, h4, h5, h6 {
        ${tw`font-medium tracking-normal font-header`};
        letter-spacing: 0.008em;
    }

    p {
        ${tw`text-neutral-200 leading-snug font-sans`};
    }

    a {
        transition: color 160ms ease, opacity 160ms ease, border-color 160ms ease, box-shadow 180ms ease;
        text-underline-offset: 2px;
    }

    a:hover {
        opacity: .95;
    }

    *::selection {
        background: rgba(148, 163, 184, 0.28);
        color: #f8fafc;
    }

    *:focus-visible {
        outline: 2px solid rgba(165, 180, 220, 0.78);
        outline-offset: 2px;
        border-radius: 0.25rem;
    }

    form {
        ${tw`m-0`};
    }

    textarea, select, input, button, button:focus, button:focus-visible {
        ${tw`outline-none`};
    }

    input,
    select,
    textarea {
        background: var(--input-bg);
        border: 1px solid var(--input-border);
        border-radius: 0.5rem;
        color: #d7e8f3;
        transition: border-color 160ms ease, box-shadow 180ms ease, background-color 160ms ease;
    }

    input::placeholder,
    textarea::placeholder {
        color: #8fb0c4;
        opacity: .82;
    }

    input:focus,
    select:focus,
    textarea:focus {
        border-color: var(--input-focus);
        box-shadow: 0 0 0 3px rgba(66, 196, 236, 0.22);
    }

    input:disabled,
    select:disabled,
    textarea:disabled {
        opacity: .55;
        cursor: not-allowed;
    }

    button {
        transition: box-shadow 160ms ease, opacity 140ms ease, border-color 140ms ease, background-color 140ms ease;
    }

    button:hover {
        transform: none;
    }

    button:disabled {
        opacity: .6;
        cursor: not-allowed;
        transform: none;
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
