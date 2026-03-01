import React from 'react';
import FlashMessageRender from '@/components/FlashMessageRender';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import tw from 'twin.macro';

type Props = Readonly<
    React.DetailedHTMLProps<React.HTMLAttributes<HTMLDivElement>, HTMLDivElement> & {
        title?: string;
        borderColor?: string;
        showFlashes?: string | boolean;
        showLoadingOverlay?: boolean;
    }
>;

const ContentBox = ({ title, borderColor, showFlashes, showLoadingOverlay, children, ...props }: Props) => (
    <div {...props}>
        {title && (
            <h2
                css={tw`mb-3 px-1 text-sm sm:text-base font-semibold uppercase tracking-[0.08em]`}
                style={{ color: 'var(--text-muted)' }}
            >
                {title}
            </h2>
        )}
        {showFlashes && (
            <FlashMessageRender byKey={typeof showFlashes === 'string' ? showFlashes : undefined} css={tw`mb-4`} />
        )}
        <div
            css={[tw`p-4 sm:p-5 rounded-2xl border relative`, !!borderColor && tw`border-t-4`]}
            style={{
                borderColor: borderColor || 'var(--ui-border)',
                background: 'var(--glass-elevated)',
                boxShadow: 'var(--panel-shadow)',
            }}
        >
            <SpinnerOverlay visible={showLoadingOverlay || false} />
            {children}
        </div>
    </div>
);

export default ContentBox;
