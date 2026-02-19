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
        <div css={tw`text-center mb-5`}>
            <p css={tw`inline-flex items-center px-3 py-1 rounded-full text-xs uppercase tracking-wide border border-primary-400/30 bg-primary-500/10 text-primary-200`}>
                Secure Control Panel
            </p>
            {title && <h2 css={tw`text-3xl text-center text-neutral-100 font-semibold pt-4`}>{title}</h2>}
            <p css={tw`mt-2 text-sm text-neutral-400`}>Fast, secure access to your infrastructure.</p>
        </div>

        <FlashMessageRender css={tw`mb-3 px-1`} />
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
                css={tw`w-full rounded-xl p-8 mx-1 border border-primary-500/30 backdrop-blur-sm`}
                style={{
                    background: 'linear-gradient(180deg, rgba(9, 16, 29, 0.92) 0%, rgba(8, 14, 25, 0.94) 100%)',
                    boxShadow: '0 22px 60px rgba(2, 6, 23, 0.65)',
                }}
            >
                <div css={tw`flex-1`}>{props.children}</div>
                <div css={tw`mt-6 pt-4 border-t border-neutral-700/60`}>
                    <p css={tw`text-xs text-neutral-500 text-center uppercase tracking-wide`}>
                        Protected by HexZo Security Layer
                    </p>
                </div>
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
