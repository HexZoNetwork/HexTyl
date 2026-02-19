import React, { useEffect, useState } from 'react';
import { Server } from '@/api/server/getServer';
import getServers from '@/api/getServers';
import ServerRow from '@/components/dashboard/ServerRow';
import Spinner from '@/components/elements/Spinner';
import PageContentBlock from '@/components/elements/PageContentBlock';
import useFlash from '@/plugins/useFlash';
import { useStoreState } from 'easy-peasy';
import { usePersistedState } from '@/plugins/usePersistedState';
import tw from 'twin.macro';
import styled from 'styled-components/macro';
import useSWR from 'swr';
import { PaginatedResult } from '@/api/http';
import Pagination from '@/components/elements/Pagination';
import { useLocation } from 'react-router-dom';
import GlobalChatDock from '@/components/dashboard/chat/GlobalChatDock';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faServer, faUsers, faGlobe, faCog, faComments } from '@fortawesome/free-solid-svg-icons';

// ── Tab types ───────────────────────────────────────────────────────────────
type TabId = 'mine' | 'subuser' | 'public' | 'admin-all' | 'global-chat';

interface Tab {
    id: TabId;
    label: string;
    icon: React.ReactNode;
    apiType: string;
    emptyText: string;
}

const TABS_USER: Tab[] = [
    { id: 'mine', label: 'My Servers', icon: <FontAwesomeIcon icon={faServer} />, apiType: 'owner', emptyText: 'You have no servers.' },
    { id: 'subuser', label: 'Shared Servers', icon: <FontAwesomeIcon icon={faUsers} />, apiType: 'subuser', emptyText: 'No servers are shared with you.' },
    { id: 'public', label: 'Public Servers', icon: <FontAwesomeIcon icon={faGlobe} />, apiType: 'public', emptyText: 'There are no public servers.' },
];

const TAB_ADMIN: Tab = {
    id: 'admin-all', label: 'All Servers', icon: <FontAwesomeIcon icon={faCog} />, apiType: 'admin-all', emptyText: 'No servers on this system.',
};

const TAB_CHAT: Tab = {
    id: 'global-chat', label: 'Global Chat', icon: <FontAwesomeIcon icon={faComments} />, apiType: 'chat', emptyText: '',
};

// ── Styled tab bar ───────────────────────────────────────────────────────────
const TabBar = styled.div`
    ${tw`flex mb-5 border-b border-neutral-700/80 overflow-x-auto overflow-y-hidden whitespace-nowrap gap-1 pb-1`};
    scrollbar-width: thin;
`;

const TabButton = styled.button<{ $active: boolean }>`
    ${tw`px-4 py-2.5 text-sm font-medium transition-all duration-150 focus:outline-none inline-flex items-center gap-2 rounded-t-md`};
    border-bottom: 2px solid ${({ $active }) => $active ? '#06b0d1' : 'transparent'};
    color: ${({ $active }) => $active ? '#06b0d1' : '#8ab0be'};
    background: ${({ $active }) => ($active ? 'rgba(6,176,209,0.12)' : 'transparent')};
    border-top: none;
    border-left: none;
    border-right: none;
    cursor: pointer;
    &:hover {
        color: #4ce0f2;
        border-bottom-color: #4ce0f2;
        background: rgba(76, 224, 242, 0.1);
    }
`;

// ── Component ────────────────────────────────────────────────────────────────
interface Props {
    chatMode: 'inline' | 'popup';
    onChatModeChange: (mode: 'inline' | 'popup') => void;
}

export default ({ chatMode, onChatModeChange }: Props) => {
    const { search } = useLocation();
    const defaultPage = Number(new URLSearchParams(search).get('page') || '1');

    const [page, setPage] = useState(!isNaN(defaultPage) && defaultPage > 0 ? defaultPage : 1);
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const uuid = useStoreState((state) => state.user.data!.uuid);
    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);

    const allTabs: Tab[] = rootAdmin ? [...TABS_USER, TAB_CHAT, TAB_ADMIN] : [...TABS_USER, TAB_CHAT];

    const [activeTab, setActiveTab] = usePersistedState<TabId>(`${uuid}:dashboard_tab`, 'mine');
    const currentTab = allTabs.find((t) => t.id === activeTab) ?? allTabs[0];

    const isChatTab = currentTab.id === 'global-chat';

    const { data: servers, error } = useSWR<PaginatedResult<Server>>(
        isChatTab ? null : ['/api/client/servers', currentTab.apiType, page],
        () => getServers({ page, type: currentTab.apiType })
    );

    // Reset page when tab changes
    useEffect(() => setPage(1), [activeTab]);

    useEffect(() => {
        if (!servers) return;
        if (servers.pagination.currentPage > 1 && !servers.items.length) {
            setPage(1);
        }
    }, [servers?.pagination.currentPage]);

    useEffect(() => {
        window.history.replaceState(null, document.title, `/${page <= 1 ? '' : `?page=${page}`}`);
    }, [page]);

    useEffect(() => {
        if (error) clearAndAddHttpError({ key: 'dashboard', error });
        if (!error) clearFlashes('dashboard');
    }, [error]);

    return (
        <PageContentBlock title={'Dashboard'} showFlashKey={'dashboard'}>
            {/* ── Tab bar ── */}
            <TabBar>
                {allTabs.map((tab) => (
                    <TabButton
                        key={tab.id}
                        $active={activeTab === tab.id}
                        onClick={() => setActiveTab(tab.id)}
                    >
                        {tab.icon}
                        <span>{tab.label}</span>
                    </TabButton>
                ))}
            </TabBar>

            {isChatTab ? (
                chatMode === 'inline' ? (
                    <GlobalChatDock mode={chatMode} onModeChange={onChatModeChange} />
                ) : (
                    <div css={tw`mx-auto max-w-xl rounded-lg border border-neutral-700 bg-neutral-900/50 px-4 py-5 text-center`}>
                        <p css={tw`text-sm text-neutral-300`}>Global Chat sedang di mode popup.</p>
                        <p css={tw`mt-1 text-xs text-neutral-500`}>Klik bubble chat di kiri bawah untuk membuka.</p>
                    </div>
                )
            ) : (
                <>
                    {!servers ? (
                        <Spinner centered size={'large'} />
                    ) : (
                        <Pagination data={servers} onPageSelect={setPage}>
                            {({ items }) =>
                                items.length > 0 ? (
                                    items.map((server, index) => (
                                        <ServerRow key={server.uuid} server={server} css={index > 0 ? tw`mt-2` : undefined} />
                                    ))
                                ) : (
                                    <p css={tw`text-center text-sm text-neutral-400`}>
                                        {currentTab.emptyText}
                                    </p>
                                )
                            }
                        </Pagination>
                    )}
                </>
            )}
        </PageContentBlock>
    );
};
