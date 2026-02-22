import styled from 'styled-components/macro';
import tw from 'twin.macro';

export default styled.div<{ $hoverable?: boolean }>`
    ${tw`flex rounded-lg no-underline text-neutral-200 items-center p-4 border overflow-hidden`};
    background: linear-gradient(180deg, rgba(47, 58, 80, 0.78) 0%, rgba(39, 48, 67, 0.78) 100%);
    border-color: var(--ui-border);
    box-shadow: 0 10px 26px rgba(6, 12, 23, 0.26);
    transition: transform 180ms ease, border-color 180ms ease, box-shadow 180ms ease, background 180ms ease;

    ${(props) =>
        props.$hoverable !== false &&
        `
        &:hover {
            transform: translateY(-2px);
            border-color: var(--ui-border-strong);
            box-shadow: 0 16px 35px rgba(4, 10, 20, 0.38), 0 0 0 1px rgba(81, 206, 236, 0.08);
            background: linear-gradient(180deg, rgba(52, 64, 88, 0.85) 0%, rgba(43, 52, 74, 0.85) 100%);
        }
    `};

    & .icon {
        ${tw`rounded-full w-16 flex items-center justify-center p-3`};
        background: linear-gradient(160deg, rgba(77, 173, 212, 0.26) 0%, rgba(58, 80, 115, 0.55) 100%);
        border: 1px solid rgba(120, 174, 201, 0.22);
    }
`;
