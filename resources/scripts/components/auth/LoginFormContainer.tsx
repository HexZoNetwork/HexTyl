import React, { forwardRef } from 'react';
import { Form } from 'formik';
import styled from 'styled-components/macro';
import { breakpoint } from '@/theme';
import FlashMessageRender from '@/components/FlashMessageRender';
import tw from 'twin.macro';

type Props = React.DetailedHTMLProps<React.FormHTMLAttributes<HTMLFormElement>, HTMLFormElement> & {
    title?: string;
};

const Container = styled.div`
    ${tw`mx-auto w-full px-4`};

    ${breakpoint('sm')`
        ${tw`w-4/5`}
    `};

    ${breakpoint('md')`
        ${tw`p-10`}
    `};

    ${breakpoint('lg')`
        ${tw`w-3/5`}
    `};

    ${breakpoint('xl')`
        ${tw`w-full`}
        max-width: 700px;
    `};
`;

export default forwardRef<HTMLFormElement, Props>(({ title, ...props }, ref) => (
    <Container>
        {title && <h2 css={tw`text-3xl text-center text-neutral-100 font-semibold py-4`}>{title}</h2>}
        <FlashMessageRender css={tw`mb-2 px-1`} />
        <Form {...props} ref={ref} css={tw`flex flex-col`}>
            <div css={tw`mb-6 flex justify-center`}>
                <div
                    css={tw`bg-neutral-900 rounded-full shadow-2xl border-2 border-primary-500 overflow-hidden flex items-center justify-center ring-4 ring-primary-500/20`}
                    style={{ width: '120px', height: '120px' }}
                >
                    <img src={'/favicons/logo.png'} css={tw`w-full h-full object-cover`} alt={'HexTyl Logo'} />
                </div>
            </div>
            <div
                css={tw`w-full bg-neutral-900 shadow-2xl rounded-xl p-8 mx-1 border border-primary-500/40 backdrop-blur-sm`}
                style={{ boxShadow: '0 22px 60px rgba(2, 6, 23, 0.65)' }}
            >
                <div css={tw`flex-1`}>{props.children}</div>
            </div>
        </Form>
        <p css={tw`text-center text-neutral-400 text-xs mt-5`}>
            &copy; 2015 - {new Date().getFullYear()}&nbsp;
            <a
                rel={'noopener nofollow noreferrer'}
                href={'https://pterodactyl.io'}
                target={'_blank'}
                css={tw`no-underline text-neutral-400 hover:text-primary-300 transition-colors`}
            >
                Pterodactyl Software
            </a>
        </p>
    </Container>
));
