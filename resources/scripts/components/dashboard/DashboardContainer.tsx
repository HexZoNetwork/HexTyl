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
import { faServer, faUsers, faGlobe, faCog, faComments, faSearch, faTimes } from '@fortawesome/free-solid-svg-icons';

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
    ${tw`flex mb-5 overflow-x-auto overflow-y-hidden whitespace-nowrap gap-2 pb-2 pt-1 px-1 rounded-lg`};
    background: linear-gradient(180deg, rgba(25, 34, 51, 0.86) 0%, rgba(19, 27, 41, 0.86) 100%);
    border: 1px solid var(--ui-border);
    box-shadow: inset 0 0 0 1px rgba(92, 169, 203, 0.05);
    scrollbar-width: thin;
`;

const TabButton = styled.button<{ $active: boolean }>`
    ${tw`px-4 py-2.5 text-sm font-medium transition-all duration-150 focus:outline-none inline-flex items-center gap-2 rounded-md`};
    border: 1px solid ${({ $active }) => ($active ? 'rgba(91, 223, 255, 0.35)' : 'transparent')};
    color: ${({ $active }) => ($active ? '#baf4ff' : '#8ab0be')};
    background: ${({ $active }) =>
        $active
            ? 'linear-gradient(180deg, rgba(22, 103, 134, 0.32) 0%, rgba(18, 77, 112, 0.18) 100%)'
            : 'transparent'};
    cursor: pointer;
    transform: translateY(${({ $active }) => ($active ? '-1px' : '0')});
    box-shadow: ${({ $active }) => ($active ? '0 6px 18px rgba(12, 48, 72, 0.45)' : 'none')};

    &:hover {
        color: #aef1fb;
        border-color: rgba(91, 223, 255, 0.28);
        background: rgba(76, 224, 242, 0.12);
    }
`;

const AnimatedList = styled.div<{ $delay: number }>`
    & > * {
        animation: dashboardListIn 260ms ease both;
        animation-delay: ${({ $delay }) => `${$delay}ms`};
    }

    @keyframes dashboardListIn {
        0% {
            opacity: 0;
            transform: translateY(8px);
        }
        100% {
            opacity: 1;
            transform: translateY(0);
        }
    }
`;

const SearchBar = styled.div`
    ${tw`mb-4 rounded-lg border px-3 py-2 flex items-center gap-3`};
    border-color: rgba(90, 165, 200, 0.24);
    background: linear-gradient(180deg, rgba(22, 31, 46, 0.9) 0%, rgba(17, 24, 37, 0.9) 100%);
`;

const SearchInput = styled.input`
    ${tw`w-full bg-transparent border-0 outline-none text-sm text-neutral-100`};

    &::placeholder {
        color: #90a7b8;
    }
`;

const SearchClear = styled.button`
    ${tw`border-0 bg-transparent p-1 text-neutral-400 hover:text-cyan-200 transition-colors duration-150 cursor-pointer`};
`;

// ── Component ────────────────────────────────────────────────────────────────
interface Props {
    chatMode: 'inline' | 'popup';
    onChatModeChange: (mode: 'inline' | 'popup') => void;
}

export default ({ chatMode, onChatModeChange }: Props) => {
    const { search } = useLocation();
    const searchParams = new URLSearchParams(search);
    const defaultPage = Number(searchParams.get('page') || '1');
    const defaultQuery = searchParams.get('q') || '';

    const [page, setPage] = useState(!isNaN(defaultPage) && defaultPage > 0 ? defaultPage : 1);
    const [query, setQuery] = useState(defaultQuery);
    const [debouncedQuery, setDebouncedQuery] = useState(defaultQuery.trim());
    const { clearFlashes, clearAndAddHttpError } = useFlash();
    const uuid = useStoreState((state) => state.user.data!.uuid);
    const rootAdmin = useStoreState((state) => state.user.data!.rootAdmin);

    const allTabs: Tab[] = rootAdmin ? [...TABS_USER, TAB_CHAT, TAB_ADMIN] : [...TABS_USER, TAB_CHAT];

    const [activeTab, setActiveTab] = usePersistedState<TabId>(`${uuid}:dashboard_tab`, 'mine');
    const currentTab = allTabs.find((t) => t.id === activeTab) ?? allTabs[0];

    const isChatTab = currentTab.id === 'global-chat';

    const { data: servers, error } = useSWR<PaginatedResult<Server>>(
        isChatTab ? null : ['/api/client/servers', currentTab.apiType, page, debouncedQuery],
        () => getServers({ page, type: currentTab.apiType, query: debouncedQuery || undefined })
    );

    useEffect(() => {
        const timeout = setTimeout(() => setDebouncedQuery(query.trim()), 280);
        return () => clearTimeout(timeout);
    }, [query]);

    // Reset page when tab changes
    useEffect(() => setPage(1), [activeTab]);
    useEffect(() => setPage(1), [debouncedQuery]);

    useEffect(() => {
        if (!servers) return;
        if (servers.pagination.currentPage > 1 && !servers.items.length) {
            setPage(1);
        }
    }, [servers?.pagination.currentPage]);

    useEffect(() => {
        const params = new URLSearchParams();
        if (page > 1) params.set('page', String(page));
        if (debouncedQuery.length > 0) params.set('q', debouncedQuery);
        const queryString = params.toString();
        window.history.replaceState(null, document.title, `/${queryString ? `?${queryString}` : ''}`);
    }, [page, debouncedQuery]);

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
                    <SearchBar>
                        <FontAwesomeIcon icon={faSearch} color={'#75d5e9'} />
                        <SearchInput
                            value={query}
                            onChange={(e) => setQuery(e.currentTarget.value)}
                            placeholder={`Search ${currentTab.label.toLowerCase()}...`}
                            aria-label={'Search servers'}
                        />
                        {query.length > 0 && (
                            <SearchClear type={'button'} onClick={() => setQuery('')} aria-label={'Clear search'}>
                                <FontAwesomeIcon icon={faTimes} />
                            </SearchClear>
                        )}
                    </SearchBar>
                    {!servers ? (
                        <Spinner centered size={'large'} />
                    ) : (
                        <Pagination data={servers} onPageSelect={setPage}>
                            {({ items }) =>
                                items.length > 0 ? (
                                    items.map((server, index) => (
                                        <AnimatedList key={server.uuid} $delay={Math.min(index * 45, 240)}>
                                            <ServerRow
                                                server={server}
                                                css={index > 0 ? tw`mt-2` : undefined}
                                            />
                                        </AnimatedList>
                                    ))
                                ) : (
                                    <p css={tw`text-center text-sm text-neutral-400`}>
                                        {debouncedQuery
                                            ? `No servers found for "${debouncedQuery}" in ${currentTab.label}.`
                                            : currentTab.emptyText}
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
