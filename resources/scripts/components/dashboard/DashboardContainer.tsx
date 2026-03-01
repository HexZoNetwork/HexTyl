import React, { useEffect, useMemo, useState } from 'react';
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
import isPanelAdmin from '@/helpers/isPanelAdmin';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faServer,
    faUsers,
    faGlobe,
    faCog,
    faComments,
    faSearch,
    faTimes,
    faSortAlphaDownAlt,
    faSortAlphaUpAlt,
    faStar,
    faThLarge,
    faList,
    faSyncAlt,
    faEraser,
    faShieldAlt,
} from '@fortawesome/free-solid-svg-icons';

// ── Tab types ───────────────────────────────────────────────────────────────
type TabId = 'mine' | 'subuser' | 'public' | 'admin-all' | 'global-chat';

interface Tab {
    id: TabId;
    label: string;
    icon: React.ReactNode;
    apiType: string;
    emptyText: string;
}

type SortMode = 'recent' | 'name-asc' | 'name-desc' | 'favorites';
type DensityMode = 'comfortable' | 'compact';

const TABS_USER: Tab[] = [
    {
        id: 'mine',
        label: 'My Servers',
        icon: <FontAwesomeIcon icon={faServer} />,
        apiType: 'owner',
        emptyText: 'You have no servers.',
    },
    {
        id: 'subuser',
        label: 'Shared Servers',
        icon: <FontAwesomeIcon icon={faUsers} />,
        apiType: 'subuser',
        emptyText: 'No servers are shared with you.',
    },
    {
        id: 'public',
        label: 'Public Servers',
        icon: <FontAwesomeIcon icon={faGlobe} />,
        apiType: 'public',
        emptyText: 'There are no public servers.',
    },
];

const TAB_ADMIN: Tab = {
    id: 'admin-all',
    label: 'All Servers',
    icon: <FontAwesomeIcon icon={faCog} />,
    apiType: 'admin-all',
    emptyText: 'No servers on this system.',
};

const TAB_CHAT: Tab = {
    id: 'global-chat',
    label: 'Global Chat',
    icon: <FontAwesomeIcon icon={faComments} />,
    apiType: 'chat',
    emptyText: '',
};

// ── Styled tab bar ───────────────────────────────────────────────────────────
const TabBar = styled.div`
    ${tw`flex mb-6 overflow-x-auto overflow-y-hidden whitespace-nowrap gap-2 pb-2 pt-1 px-1 rounded-xl`};
    background: var(--glass-bg);
    border: 1px solid var(--ui-border);
    box-shadow: var(--panel-shadow);
    scrollbar-width: thin;
    scroll-snap-type: x proximity;
`;

const TabButton = styled.button<{ $active: boolean }>`
    ${tw`px-4 py-2.5 text-[13px] sm:text-sm font-medium transition-all duration-150 focus:outline-none inline-flex items-center gap-2 rounded-xl`};
    border: 1px solid ${({ $active }) => ($active ? 'var(--accent-strong)' : 'transparent')};
    color: ${({ $active }) => ($active ? '#f8fafc' : 'var(--text-muted)')};
    background: ${({ $active }) => ($active ? 'var(--accent-soft)' : 'transparent')};
    cursor: pointer;
    transform: translateY(${({ $active }) => ($active ? '-1px' : '0')});
    box-shadow: ${({ $active }) => ($active ? 'inset 0 -1px 0 var(--accent-strong)' : 'none')};
    scroll-snap-align: start;

    &:hover {
        color: #e4ebff;
        border-color: var(--accent-strong);
        background: var(--accent-soft);
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
    ${tw`mb-5 rounded-xl border px-4 py-3 flex items-center gap-3`};
    border-color: var(--ui-border);
    background: var(--glass-elevated);
    box-shadow: 0 10px 22px rgba(6, 14, 28, 0.26);
`;

const SearchInput = styled.input`
    ${tw`w-full bg-transparent border-0 outline-none text-sm text-neutral-100`};

    &::placeholder {
        color: #8e9cb7;
    }
`;

const SearchClear = styled.button`
    ${tw`border-0 bg-transparent p-1 text-neutral-400 hover:text-cyan-200 transition-colors duration-150 cursor-pointer`};
`;

const ControlWrap = styled.div`
    ${tw`mb-4 grid gap-3`};
    grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr);

    @media (max-width: 1024px) {
        grid-template-columns: minmax(0, 1fr);
    }

    @media (max-width: 640px) {
        ${tw`gap-2 mb-3`};
    }
`;

const StickyControls = styled.div`
    ${tw`sticky z-30`};
    top: 5.25rem;
    padding-top: 0.25rem;
    margin-bottom: 0.25rem;
    backdrop-filter: blur(8px);

    @media (max-width: 768px) {
        top: 4.9rem;
    }
`;

const FloatingTools = styled.div`
    ${tw`fixed z-40 flex items-center gap-2 rounded-2xl border px-2 py-2`};
    right: 1.1rem;
    bottom: 1.1rem;
    border-color: var(--ui-border-strong);
    background: color-mix(in srgb, var(--glass-elevated) 88%, transparent);
    box-shadow: 0 16px 36px rgba(4, 11, 23, 0.46);
    backdrop-filter: blur(10px);

    @media (max-width: 768px) {
        right: 50%;
        bottom: 0.72rem;
        transform: translateX(50%);
        max-width: calc(100vw - 1rem);
        overflow-x: auto;
        scrollbar-width: none;
    }
`;

const FloatingToolButton = styled.button<{ $tone: 'neutral' | 'accent' }>`
    ${tw`inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-xs font-semibold whitespace-nowrap`};
    border-color: ${({ $tone }) => ($tone === 'accent' ? 'var(--accent-strong)' : 'var(--ui-border)')};
    color: ${({ $tone }) => ($tone === 'accent' ? '#f7fbff' : 'var(--text-muted)')};
    background: ${({ $tone }) => ($tone === 'accent' ? 'var(--accent-soft)' : 'var(--chip-bg)')};

    &:hover {
        border-color: var(--accent-strong);
        color: #edf5ff;
    }

    @media (max-width: 640px) {
        ${tw`px-2.5 py-1.5 text-[11px]`};
    }
`;

const StatPanel = styled.div`
    ${tw`rounded-xl border p-4`};
    border-color: var(--ui-border);
    background: var(--glass-elevated);
    box-shadow: 0 10px 22px rgba(6, 14, 28, 0.26);
`;

const QuickActions = styled.div`
    ${tw`rounded-xl border p-4 flex flex-wrap gap-2 justify-start content-start`};
    border-color: var(--ui-border);
    background: var(--glass-elevated);
    box-shadow: 0 10px 22px rgba(6, 14, 28, 0.26);

    @media (max-width: 640px) {
        ${tw`flex-nowrap overflow-x-auto`};
        -webkit-overflow-scrolling: touch;
        scrollbar-width: thin;
    }
`;

const ChipButton = styled.button<{ $active: boolean }>`
    ${tw`inline-flex items-center gap-2 px-3 py-1.5 rounded-xl border text-xs font-semibold transition-colors duration-150`};
    border-color: ${({ $active }) => ($active ? 'var(--accent-strong)' : 'var(--ui-border)')};
    color: ${({ $active }) => ($active ? '#eff4ff' : 'var(--text-muted)')};
    background: ${({ $active }) => ($active ? 'var(--accent-soft)' : 'var(--chip-bg)')};

    &:hover {
        border-color: var(--accent-strong);
        color: #f1f6ff;
        transform: translateY(-1px);
    }

    @media (max-width: 640px) {
        ${tw`px-2.5 py-1 text-[11px]`};
        white-space: nowrap;
    }
`;

const ModeDot = styled.span<{ $popup: boolean }>`
    ${tw`inline-block h-2 w-2 rounded-full`};
    background: ${({ $popup }) => ($popup ? '#22d3ee' : '#34d399')};
    box-shadow: 0 0 0 4px ${({ $popup }) => ($popup ? 'rgba(34, 211, 238, 0.15)' : 'rgba(52, 211, 153, 0.15)')};
`;

const EmptyStateCard = styled.div`
    ${tw`mx-auto max-w-3xl rounded-2xl border px-8 py-8 text-center`};
    border-color: var(--ui-border-strong);
    background: var(--glass-elevated);
    box-shadow: 0 14px 28px rgba(4, 10, 20, 0.4);
`;

const EmptyStateActions = styled.div`
    ${tw`mt-5 flex flex-wrap items-center justify-center gap-2`};
`;

const EmptyStateLink = styled.a`
    ${tw`inline-flex items-center gap-2 rounded-lg border px-3 py-1.5 text-xs font-semibold`};
    border-color: var(--accent-strong);
    color: #edf3ff;
    background: var(--accent-soft);

    &:hover {
        border-color: var(--accent-strong);
        background: var(--accent-soft);
    }
`;

const HeroStats = styled.div`
    ${tw`mt-4 grid grid-cols-2 sm:grid-cols-4 gap-2`};
`;

const HeroStatCard = styled.div`
    ${tw`rounded-xl border px-3 py-2`};
    border-color: var(--ui-border);
    background: var(--glass-bg);
    box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.03);
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
    const currentUser = useStoreState((state) => state.user.data!);
    const panelAdmin = isPanelAdmin(currentUser);
    const [sortMode, setSortMode] = usePersistedState<SortMode>(`${uuid}:dashboard_sort`, 'recent');
    const [densityMode, setDensityMode] = usePersistedState<DensityMode>(`${uuid}:dashboard_density`, 'comfortable');
    const [favorites, setFavorites] = usePersistedState<string[]>(`${uuid}:dashboard_favorites`, []);

    const allTabs: Tab[] = panelAdmin ? [...TABS_USER, TAB_CHAT, TAB_ADMIN] : [...TABS_USER, TAB_CHAT];

    const [activeTab, setActiveTab] = usePersistedState<TabId>(`${uuid}:dashboard_tab`, 'mine');
    const currentTab = allTabs.find((t) => t.id === activeTab) ?? allTabs[0];

    const isChatTab = currentTab.id === 'global-chat';

    const {
        data: servers,
        error,
        mutate,
        isValidating,
    } = useSWR<PaginatedResult<Server>>(
        isChatTab ? null : ['/api/client/servers', currentTab.apiType, page, debouncedQuery],
        () => getServers({ page, type: currentTab.apiType, query: debouncedQuery || undefined })
    );

    const sortedServers = useMemo(() => {
        if (!servers) return [];

        const items = [...servers.items];
        if (sortMode === 'name-asc') {
            items.sort((a, b) => a.name.localeCompare(b.name));
            return items;
        }
        if (sortMode === 'name-desc') {
            items.sort((a, b) => b.name.localeCompare(a.name));
            return items;
        }
        if (sortMode === 'favorites') {
            items.sort((a, b) => Number(favorites.includes(b.uuid)) - Number(favorites.includes(a.uuid)));
            return items;
        }

        return items;
    }, [servers, sortMode, favorites]);

    const toggleFavorite = (serverUuid: string) => {
        setFavorites((prev) =>
            prev.includes(serverUuid) ? prev.filter((uuidValue) => uuidValue !== serverUuid) : [...prev, serverUuid]
        );
    };

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

    const resetDashboardView = () => {
        setQuery('');
        setDebouncedQuery('');
        setSortMode('recent');
        setDensityMode('comfortable');
        setPage(1);
    };

    const openGlobalChat = () => {
        localStorage.setItem(`${uuid}:global_chat_popup_open`, JSON.stringify(true));
        localStorage.setItem(`${uuid}:global_chat_popup_minimized`, JSON.stringify(false));
        onChatModeChange('popup');
        window.dispatchEvent(new Event('hextyl:open-global-chat'));
    };

    const totalOnPage = servers?.items.length ?? 0;
    const favoriteOnPage = servers?.items.filter((item) => favorites.includes(item.uuid)).length ?? 0;

    return (
        <PageContentBlock title={'Dashboard'} showFlashKey={'dashboard'}>
            <div
                css={tw`mb-6 rounded-2xl border px-5 py-6 sm:px-7 sm:py-7`}
                style={{
                    borderColor: 'var(--ui-border)',
                    background:
                        'linear-gradient(115deg, var(--glass-elevated) 0%, var(--glass-elevated) 58%, var(--surface-tint) 100%)',
                    boxShadow: '0 16px 34px rgba(4, 12, 24, 0.4)',
                }}
            >
                <p css={tw`text-[11px] uppercase tracking-[0.14em]`} style={{ color: 'var(--text-muted)' }}>
                    Dashboard
                </p>
                <h2 css={tw`mt-2 text-2xl sm:text-[2rem] font-semibold text-neutral-100`}>
                    Welcome back, {currentUser.username}
                </h2>
                <p css={tw`mt-2 text-sm sm:text-[15px] text-neutral-300 max-w-3xl hidden sm:block`}>
                    Monitor status, manage instances, and jump into console operations in one place.
                </p>
                <HeroStats>
                    <HeroStatCard>
                        <p css={tw`text-[11px] uppercase tracking-wide text-neutral-400`}>Visible</p>
                        <p css={tw`mt-1 text-lg font-semibold text-neutral-100`}>{totalOnPage}</p>
                    </HeroStatCard>
                    <HeroStatCard>
                        <p css={tw`text-[11px] uppercase tracking-wide text-neutral-400`}>Favorites</p>
                        <p css={tw`mt-1 text-lg font-semibold text-neutral-100`}>{favoriteOnPage}</p>
                    </HeroStatCard>
                    <HeroStatCard>
                        <p css={tw`text-[11px] uppercase tracking-wide text-neutral-400`}>Layout</p>
                        <p css={tw`mt-1 text-lg font-semibold text-neutral-100`}>
                            {densityMode === 'compact' ? 'Compact' : 'Comfort'}
                        </p>
                    </HeroStatCard>
                    <HeroStatCard>
                        <p css={tw`text-[11px] uppercase tracking-wide text-neutral-400`}>Access</p>
                        <p css={tw`mt-1 text-lg font-semibold text-neutral-100`}>{panelAdmin ? 'Admin' : 'User'}</p>
                    </HeroStatCard>
                </HeroStats>
            </div>
            {/* ── Tab bar ── */}
            <TabBar>
                {allTabs.map((tab) => (
                    <TabButton key={tab.id} $active={activeTab === tab.id} onClick={() => setActiveTab(tab.id)}>
                        {tab.icon}
                        <span>{tab.label}</span>
                    </TabButton>
                ))}
            </TabBar>

            {isChatTab ? (
                chatMode === 'inline' ? (
                    <GlobalChatDock mode={chatMode} onModeChange={onChatModeChange} />
                ) : (
                    <div
                        css={tw`mx-auto max-w-xl rounded-lg border border-neutral-700 bg-neutral-900/50 px-4 py-5 text-center`}
                    >
                        <p css={tw`text-sm text-neutral-300`}>Global Chat sedang di mode popup.</p>
                        <p css={tw`mt-1 text-xs text-neutral-500`}>Klik bubble chat di kiri bawah untuk membuka.</p>
                    </div>
                )
            ) : (
                <>
                    <StickyControls>
                        <ControlWrap>
                            <StatPanel>
                                <div css={tw`flex flex-wrap items-center gap-3`}>
                                    <span css={tw`text-xs uppercase tracking-wide text-neutral-300`}>
                                        HexWings Overview
                                    </span>
                                    <span css={tw`text-xs text-neutral-400`}>
                                        Showing {totalOnPage} of {servers?.pagination.total ?? 0} servers
                                    </span>
                                    <span css={tw`text-xs text-neutral-300 hidden sm:inline`}>
                                        Favorites on page: {favoriteOnPage}
                                    </span>
                                    <span css={tw`text-xs text-neutral-300 hidden md:inline-flex items-center gap-1`}>
                                        <FontAwesomeIcon icon={faShieldAlt} />
                                        Guarded by Wings Security
                                    </span>
                                </div>
                                <div css={tw`mt-3 flex flex-wrap gap-2`}>
                                    <ChipButton $active={sortMode === 'recent'} onClick={() => setSortMode('recent')}>
                                        <FontAwesomeIcon icon={faList} />
                                        Recent
                                    </ChipButton>
                                    <ChipButton
                                        $active={sortMode === 'name-asc'}
                                        onClick={() => setSortMode('name-asc')}
                                    >
                                        <FontAwesomeIcon icon={faSortAlphaDownAlt} />
                                        Name A-Z
                                    </ChipButton>
                                    <ChipButton
                                        $active={sortMode === 'name-desc'}
                                        onClick={() => setSortMode('name-desc')}
                                    >
                                        <FontAwesomeIcon icon={faSortAlphaUpAlt} />
                                        Name Z-A
                                    </ChipButton>
                                    <ChipButton
                                        $active={sortMode === 'favorites'}
                                        onClick={() => setSortMode('favorites')}
                                    >
                                        <FontAwesomeIcon icon={faStar} />
                                        Favorites First
                                    </ChipButton>
                                </div>
                            </StatPanel>
                            <QuickActions>
                                <ChipButton
                                    $active={densityMode === 'comfortable'}
                                    onClick={() => setDensityMode('comfortable')}
                                    title={'Comfortable spacing'}
                                >
                                    <FontAwesomeIcon icon={faThLarge} />
                                    Comfortable
                                </ChipButton>
                                <ChipButton
                                    $active={densityMode === 'compact'}
                                    onClick={() => setDensityMode('compact')}
                                    title={'Compact spacing'}
                                >
                                    <FontAwesomeIcon icon={faList} />
                                    Compact
                                </ChipButton>
                                <ChipButton
                                    $active={chatMode === 'popup'}
                                    onClick={() => onChatModeChange(chatMode === 'inline' ? 'popup' : 'inline')}
                                    type={'button'}
                                    title={'Toggle chat mode (inline/popup)'}
                                >
                                    <ModeDot $popup={chatMode === 'popup'} />
                                    <FontAwesomeIcon icon={faComments} />
                                    Chat {chatMode === 'inline' ? 'Inline' : 'Popup'}
                                </ChipButton>
                                <ChipButton $active={false} onClick={() => void mutate()} type={'button'}>
                                    <FontAwesomeIcon icon={faSyncAlt} spin={isValidating} />
                                    {isValidating ? 'Refreshing...' : 'Refresh Data'}
                                </ChipButton>
                                <ChipButton $active={false} onClick={resetDashboardView} type={'button'}>
                                    <FontAwesomeIcon icon={faEraser} />
                                    Reset View
                                </ChipButton>
                            </QuickActions>
                        </ControlWrap>
                        <SearchBar>
                            <FontAwesomeIcon icon={faSearch} color={'#8f9bb3'} />
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
                    </StickyControls>
                    <FloatingTools>
                        <FloatingToolButton
                            $tone={'accent'}
                            onClick={openGlobalChat}
                            type={'button'}
                            title={'Open chat popup'}
                        >
                            <FontAwesomeIcon icon={faComments} />
                            Open Chat
                        </FloatingToolButton>
                        <FloatingToolButton $tone={'neutral'} onClick={() => void mutate()} type={'button'}>
                            <FontAwesomeIcon icon={faSyncAlt} spin={isValidating} />
                            {isValidating ? 'Refreshing...' : 'Refresh'}
                        </FloatingToolButton>
                        <FloatingToolButton $tone={'neutral'} onClick={resetDashboardView} type={'button'}>
                            <FontAwesomeIcon icon={faEraser} />
                            Reset
                        </FloatingToolButton>
                    </FloatingTools>
                    {!servers ? (
                        <Spinner centered size={'large'} />
                    ) : (
                        <Pagination data={servers} onPageSelect={setPage}>
                            {() =>
                                sortedServers.length > 0 ? (
                                    sortedServers.map((server, index) => (
                                        <AnimatedList key={server.uuid} $delay={Math.min(index * 45, 240)}>
                                            <ServerRow
                                                server={server}
                                                isFavorite={favorites.includes(server.uuid)}
                                                onToggleFavorite={toggleFavorite}
                                                css={
                                                    index > 0
                                                        ? densityMode === 'compact'
                                                            ? tw`mt-1.5`
                                                            : tw`mt-2.5`
                                                        : undefined
                                                }
                                            />
                                        </AnimatedList>
                                    ))
                                ) : (
                                    <EmptyStateCard>
                                        <p css={tw`text-base font-semibold text-neutral-100`}>
                                            {debouncedQuery
                                                ? `No servers found for "${debouncedQuery}"`
                                                : currentTab.emptyText}
                                        </p>
                                        <p css={tw`mt-2 text-sm text-neutral-300`}>
                                            {debouncedQuery
                                                ? 'Try another keyword or reset filters.'
                                                : panelAdmin
                                                ? 'Provision infrastructure from Admin panel, then refresh this dashboard.'
                                                : 'Server provisioning is handled by admin. Contact admin to create a server for your account.'}
                                        </p>
                                        <EmptyStateActions>
                                            {panelAdmin && (
                                                <EmptyStateLink href={'/root/servers'}>
                                                    <FontAwesomeIcon icon={faServer} />
                                                    Manage Servers
                                                </EmptyStateLink>
                                            )}
                                            {panelAdmin && (
                                                <EmptyStateLink href={'/root/nodes'}>
                                                    <FontAwesomeIcon icon={faServer} />
                                                    Manage Nodes
                                                </EmptyStateLink>
                                            )}
                                            <ChipButton $active={false} type={'button'} onClick={() => void mutate()}>
                                                <FontAwesomeIcon icon={faSyncAlt} spin={isValidating} />
                                                {isValidating ? 'Refreshing...' : 'Refresh Data'}
                                            </ChipButton>
                                            {debouncedQuery && (
                                                <ChipButton
                                                    $active={false}
                                                    type={'button'}
                                                    onClick={() => setQuery('')}
                                                >
                                                    <FontAwesomeIcon icon={faEraser} />
                                                    Clear Search
                                                </ChipButton>
                                            )}
                                        </EmptyStateActions>
                                    </EmptyStateCard>
                                )
                            }
                        </Pagination>
                    )}
                </>
            )}
        </PageContentBlock>
    );
};
