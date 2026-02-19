import React, { memo } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { IconProp } from '@fortawesome/fontawesome-svg-core';
import tw from 'twin.macro';
import isEqual from 'react-fast-compare';

interface Props {
    icon?: IconProp;
    title: string | React.ReactNode;
    className?: string;
    children: React.ReactNode;
}

const TitledGreyBox = ({ icon, title, children, className }: Props) => (
    <div
        css={tw`rounded-lg shadow-md bg-neutral-700 border border-neutral-500/20 overflow-hidden`}
        style={{ background: 'linear-gradient(180deg, rgba(44, 55, 76, 0.82) 0%, rgba(37, 47, 65, 0.82) 100%)' }}
        className={className}
    >
        <div css={tw`bg-neutral-900/70 p-3 border-b border-neutral-500/25 backdrop-blur-sm`}>
            {typeof title === 'string' ? (
                <p css={tw`text-sm uppercase tracking-wide text-cyan-100/95`}>
                    {icon && <FontAwesomeIcon icon={icon} css={tw`mr-2 text-cyan-200/80`} />}
                    {title}
                </p>
            ) : (
                title
            )}
        </div>
        <div css={tw`p-4`}>{children}</div>
    </div>
);

export default memo(TitledGreyBox, isEqual);
