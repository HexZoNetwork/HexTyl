import * as React from 'react';
import { useState } from 'react';
import { Link, NavLink } from 'react-router-dom';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCogs, faLayerGroup, faSignOutAlt } from '@fortawesome/free-solid-svg-icons';
import { useStoreState } from 'easy-peasy';
import { ApplicationStore } from '@/state';
import SearchContainer from '@/components/dashboard/search/SearchContainer';
import tw from 'twin.macro';
import styled from 'styled-components/macro';
import http from '@/api/http';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import Tooltip from '@/components/elements/tooltip/Tooltip';
import Avatar from '@/components/Avatar';
import isPanelAdmin from '@/helpers/isPanelAdmin';

const RightNavigation = styled.div`
    & > a,
    & > button,
    & > .navigation-link {
        ${tw`flex items-center justify-center h-[2.55rem] w-[2.55rem] no-underline text-neutral-300 cursor-pointer transition-all duration-150 rounded-xl my-2`};
        border: 1px solid transparent;
        position: relative;

        &:active,
        &:hover {
            ${tw`text-neutral-100`};
            background: var(--accent-soft);
            border-color: var(--accent-strong);
            box-shadow: none;
        }

        &:active,
        &:hover,
        &.active {
            box-shadow: inset 0 -2px var(--accent);
        }

        &.active {
            background: var(--accent-soft);
            border-color: var(--accent-strong);
        }
    }
`;

export default () => {
    const name = useStoreState((state: ApplicationStore) => state.settings.data!.name);
    const panelAdmin = useStoreState((state: ApplicationStore) => isPanelAdmin(state.user.data));
    const [isLoggingOut, setIsLoggingOut] = useState(false);

    const onTriggerLogout = () => {
        setIsLoggingOut(true);
        http.post('/auth/logout').finally(() => {
            // @ts-expect-error this is valid
            window.location = '/';
        });
    };

    return (
        <div className={'w-full overflow-x-auto border-b border-neutral-700/40 sticky top-0 z-40'}>
            <SpinnerOverlay visible={isLoggingOut} />
            <div
                className={'mx-auto w-full flex items-center h-[3.75rem] sm:h-[4.1rem] max-w-[1360px] px-2 sm:px-3'}
                style={{
                    background: 'linear-gradient(100deg, var(--nav-start) 0%, var(--nav-mid) 52%, var(--nav-end) 100%)',
                    backdropFilter: 'blur(12px)',
                    boxShadow: '0 10px 24px rgba(2, 8, 18, 0.34), inset 0 -1px 0 rgba(255, 255, 255, 0.04)',
                }}
            >
                <div id={'logo'} className={'flex-1'}>
                    <Link
                        to={'/'}
                        className={
                            'inline-flex items-center gap-2.5 text-2xl font-header font-medium px-2 sm:px-3 no-underline text-neutral-200 hover:text-neutral-100 transition-colors duration-150'
                        }
                    >
                        <img src='/favicons/logo.png' alt={name} className={'h-9 w-9 rounded-md object-cover'} />
                        <span
                            className={'hidden md:inline text-[15px] font-semibold tracking-wide text-neutral-200/90'}
                        >
                            HexTyl Panel
                        </span>
                    </Link>
                </div>
                <RightNavigation className={'flex h-full items-center justify-center'}>
                    <SearchContainer />
                    <Tooltip placement={'bottom'} content={'Dashboard'}>
                        <NavLink to={'/'} exact>
                            <FontAwesomeIcon icon={faLayerGroup} />
                        </NavLink>
                    </Tooltip>
                    {panelAdmin && (
                        <Tooltip placement={'bottom'} content={'Admin'}>
                            <a href={'/admin'} rel={'noreferrer'}>
                                <FontAwesomeIcon icon={faCogs} />
                            </a>
                        </Tooltip>
                    )}
                    <Tooltip placement={'bottom'} content={'Account Settings'}>
                        <NavLink to={'/account'}>
                            <span className={'flex items-center w-5 h-5'}>
                                <Avatar.User />
                            </span>
                        </NavLink>
                    </Tooltip>
                    <Tooltip placement={'bottom'} content={'Sign Out'}>
                        <button onClick={onTriggerLogout}>
                            <FontAwesomeIcon icon={faSignOutAlt} />
                        </button>
                    </Tooltip>
                </RightNavigation>
            </div>
        </div>
    );
};
