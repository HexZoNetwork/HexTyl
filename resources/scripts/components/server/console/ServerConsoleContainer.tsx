import React, { memo } from 'react';
import { ServerContext } from '@/state/server';
import Can from '@/components/elements/Can';
import ServerContentBlock from '@/components/elements/ServerContentBlock';
import isEqual from 'react-fast-compare';
import Spinner from '@/components/elements/Spinner';
import Features from '@feature/Features';
import Console from '@/components/server/console/Console';
import StatGraphs from '@/components/server/console/StatGraphs';
import PowerButtons from '@/components/server/console/PowerButtons';
import ServerDetailsBlock from '@/components/server/console/ServerDetailsBlock';
import { Alert } from '@/components/elements/alert';
import tw from 'twin.macro';
import styled from 'styled-components/macro';

export type PowerAction = 'start' | 'stop' | 'restart' | 'kill';

const Hero = styled.div`
    ${tw`mb-5 rounded-2xl border p-4 sm:p-5`};
    border-color: var(--ui-border);
    background: linear-gradient(115deg, var(--glass-elevated) 0%, var(--glass-elevated) 60%, var(--surface-tint) 100%);
    box-shadow: var(--panel-shadow);
`;

const StatPills = styled.div`
    ${tw`mt-3 flex flex-wrap items-center gap-2`};
`;

const StatPill = styled.span`
    ${tw`inline-flex items-center rounded-full border px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide`};
    border-color: var(--ui-border-strong);
    background: var(--chip-bg);
    color: #dbe6ff;
`;

const ServerConsoleContainer = () => {
    const name = ServerContext.useStoreState((state) => state.server.data!.name);
    const description = ServerContext.useStoreState((state) => state.server.data!.description);
    const isInstalling = ServerContext.useStoreState((state) => state.server.isInstalling);
    const isTransferring = ServerContext.useStoreState((state) => state.server.data!.isTransferring);
    const status = ServerContext.useStoreState((state) => state.status.value);
    const node = ServerContext.useStoreState((state) => state.server.data!.node);
    const eggFeatures = ServerContext.useStoreState((state) => state.server.data!.eggFeatures, isEqual);
    const isNodeUnderMaintenance = ServerContext.useStoreState((state) => state.server.data!.isNodeUnderMaintenance);

    return (
        <ServerContentBlock title={'Console'}>
            {(isNodeUnderMaintenance || isInstalling || isTransferring) && (
                <Alert type={'warning'} className={'mb-4'}>
                    {isNodeUnderMaintenance
                        ? 'The node of this server is currently under maintenance and all actions are unavailable.'
                        : isInstalling
                        ? 'This server is currently running its installation process and most actions are unavailable.'
                        : 'This server is currently being transferred to another node and all actions are unavailable.'}
                </Alert>
            )}
            <Hero>
                <div className={'grid grid-cols-4 gap-4'}>
                    <div className={'hidden sm:block sm:col-span-2 lg:col-span-3 pr-4'}>
                        <p css={tw`text-[11px] uppercase tracking-[0.14em]`} style={{ color: 'var(--text-muted)' }}>
                            Server Console
                        </p>
                        <h1
                            className={
                                'font-header font-medium text-2xl sm:text-[2rem] text-gray-50 leading-relaxed line-clamp-1 tracking-tight'
                            }
                        >
                            {name}
                        </h1>
                        <p className={'text-sm text-neutral-300 line-clamp-2'}>
                            {description || 'No description set.'}
                        </p>
                        <StatPills>
                            <StatPill>Status: {status || 'offline'}</StatPill>
                            <StatPill>Node: {node || 'unknown'}</StatPill>
                            <StatPill>{isNodeUnderMaintenance ? 'Maintenance' : 'Operational'}</StatPill>
                        </StatPills>
                    </div>
                    <div className={'col-span-4 sm:col-span-2 lg:col-span-1 self-end'}>
                        <Can action={['control.start', 'control.stop', 'control.restart']} matchAny>
                            <PowerButtons className={'flex sm:justify-end space-x-2'} />
                        </Can>
                    </div>
                </div>
            </Hero>
            <div className={'grid grid-cols-4 gap-2 sm:gap-4 mb-5'}>
                <div className={'flex col-span-4 lg:col-span-3'}>
                    <Spinner.Suspense>
                        <Console />
                    </Spinner.Suspense>
                </div>
                <ServerDetailsBlock className={'col-span-4 lg:col-span-1 order-last lg:order-none'} />
            </div>
            <div className={'grid grid-cols-1 md:grid-cols-3 gap-2 sm:gap-4'}>
                <Spinner.Suspense>
                    <StatGraphs />
                </Spinner.Suspense>
            </div>
            <Features enabled={eggFeatures} />
        </ServerContentBlock>
    );
};

export default memo(ServerConsoleContainer, isEqual);
