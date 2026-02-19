import styled from 'styled-components/macro';
import tw, { theme } from 'twin.macro';

const SubNavigation = styled.div`
    ${tw`w-full shadow overflow-x-auto`};
    border-top: 1px solid rgba(116, 173, 203, 0.18);
    border-bottom: 1px solid rgba(116, 173, 203, 0.2);
    background: linear-gradient(180deg, rgba(30, 41, 59, 0.92) 0%, rgba(23, 31, 45, 0.95) 100%);
    backdrop-filter: blur(6px);

    & > div {
        ${tw`flex items-center text-sm mx-auto px-2 py-1`};
        max-width: 1320px;

        & > a,
        & > div {
            ${tw`inline-block py-2.5 px-4 text-neutral-300 no-underline whitespace-nowrap transition-all duration-150 rounded-md`};
            border: 1px solid transparent;

            &:not(:first-of-type) {
                ${tw`ml-2`};
            }

            &:hover {
                ${tw`text-cyan-100`};
                background: rgba(56, 189, 248, 0.1);
                border-color: rgba(125, 211, 252, 0.24);
            }

            &:active,
            &.active {
                ${tw`text-cyan-50`};
                border-color: rgba(125, 211, 252, 0.32);
                background: linear-gradient(180deg, rgba(8, 101, 133, 0.34) 0%, rgba(7, 77, 104, 0.22) 100%);
                box-shadow:
                    inset 0 -2px ${theme`colors.cyan.500`.toString()},
                    0 6px 16px rgba(4, 24, 35, 0.35);
            }
        }
    }
`;

export default SubNavigation;
