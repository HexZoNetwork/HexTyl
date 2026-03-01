import styled from 'styled-components/macro';
import tw from 'twin.macro';

const SubNavigation = styled.div`
    ${tw`w-full overflow-x-auto`};
    border-top: 1px solid var(--ui-border);
    border-bottom: 1px solid var(--ui-border);
    background: rgba(17, 25, 40, 0.86);
    backdrop-filter: blur(8px);
    box-shadow: inset 0 1px 0 rgba(152, 164, 206, 0.07), 0 8px 18px rgba(4, 13, 24, 0.24);

    & > div {
        ${tw`flex items-center text-sm mx-auto px-2 py-1.5`};
        max-width: 1320px;

        & > a,
        & > div {
            ${tw`inline-block py-2.5 px-4 text-neutral-300 no-underline whitespace-nowrap transition-all duration-150 rounded-lg`};
            border: 1px solid var(--ui-border);
            background: rgba(24, 33, 50, 0.5);

            &:not(:first-of-type) {
                ${tw`ml-2`};
            }

            &:hover {
                ${tw`text-neutral-100`};
                background: var(--accent-soft);
                border-color: var(--accent-strong);
            }

            &:active,
            &.active {
                ${tw`text-neutral-100`};
                border-color: var(--accent-strong);
                background: var(--accent-soft);
                box-shadow: inset 0 -2px var(--accent);
            }
        }
    }
`;

export default SubNavigation;
