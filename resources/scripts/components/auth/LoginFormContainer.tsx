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
        <div css={tw`text-center mb-6`}>
            <p
                css={tw`inline-flex items-center px-3 py-1 rounded-full text-xs uppercase tracking-wide border border-cyan-300/30 bg-cyan-500/10 text-cyan-100`}
            >
                HexTyl Secure Access
            </p>
            {title && <h2 css={tw`text-3xl text-center text-neutral-100 font-semibold pt-4`}>{title}</h2>}
            <p css={tw`mt-2 text-sm text-neutral-300`}>Fast, secure access to your infrastructure.</p>
        </div>

        <FlashMessageRender css={tw`mb-3 px-1`} />
        <Form {...props} ref={ref} css={tw`flex flex-col`}>
            <div css={tw`mb-6 flex justify-center`}>
                <div
                    css={tw`bg-neutral-900 rounded-full border-2 border-cyan-400/70 overflow-hidden flex items-center justify-center ring-4 ring-cyan-500/20`}
                    style={{
                        width: '116px',
                        height: '116px',
                        boxShadow: '0 16px 34px rgba(3, 13, 28, 0.54)',
                    }}
                >
                    <img src={'/favicons/logo.png'} css={tw`w-full h-full object-cover`} alt={'HexTyl Logo'} />
                </div>
            </div>
            <div
                css={tw`w-full rounded-2xl p-8 mx-1 border border-cyan-400/20 backdrop-blur-sm relative overflow-hidden`}
                style={{
                    background: 'linear-gradient(180deg, rgba(10, 19, 34, 0.92) 0%, rgba(9, 17, 31, 0.95) 100%)',
                    boxShadow: '0 24px 66px rgba(2, 10, 28, 0.62)',
                }}
            >
                <div
                    css={tw`pointer-events-none absolute inset-0`}
                    style={{
                        background:
                            'radial-gradient(circle at 0% 0%, rgba(79, 220, 255, 0.11), transparent 42%), radial-gradient(circle at 100% 100%, rgba(54, 155, 255, 0.09), transparent 45%)',
                    }}
                />
                <div css={tw`flex-1`}>{props.children}</div>
                <div css={tw`mt-6 pt-4 border-t border-cyan-200/20`}>
                    <p css={tw`text-xs text-cyan-100/50 text-center uppercase tracking-wide`}>
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
                css={tw`no-underline text-neutral-400 hover:text-cyan-200 transition-colors`}
            >
                Pterodactyl Software
            </a>
        </p>
    </Container>
));
