import React, { lazy } from 'react';
import { hot } from 'react-hot-loader/root';
import { Route, Router, Switch, useLocation } from 'react-router-dom';
import { StoreProvider, useStoreState } from 'easy-peasy';
import { store } from '@/state';
import { SiteSettings } from '@/state/settings';
import ProgressBar from '@/components/elements/ProgressBar';
import { NotFound } from '@/components/elements/ScreenBlock';
import tw from 'twin.macro';
import GlobalStylesheet from '@/assets/css/GlobalStylesheet';
import { history } from '@/components/history';
import { setupInterceptors } from '@/api/interceptors';
import AuthenticatedRoute from '@/components/elements/AuthenticatedRoute';
import { ServerContext } from '@/state/server';
import { usePersistedState } from '@/plugins/usePersistedState';
import GlobalChatDock from '@/components/dashboard/chat/GlobalChatDock';
import '@/assets/tailwind.css';
import Spinner from '@/components/elements/Spinner';

const DashboardRouter = lazy(() => import(/* webpackChunkName: "dashboard" */ '@/routers/DashboardRouter'));
const ServerRouter = lazy(() => import(/* webpackChunkName: "server" */ '@/routers/ServerRouter'));
const AuthenticationRouter = lazy(() => import(/* webpackChunkName: "auth" */ '@/routers/AuthenticationRouter'));

interface ExtendedWindow extends Window {
    SiteConfiguration?: SiteSettings;
    PterodactylUser?: {
        uuid: string;
        username: string;
        email: string;
        /* eslint-disable camelcase */
        root_admin: boolean;
        use_totp: boolean;
        language: string;
        updated_at: string;
        created_at: string;
        /* eslint-enable camelcase */
    };
}

setupInterceptors(history);

const AppRoutes = () => {
    const location = useLocation();
    const user = useStoreState((state) => state.user.data);
    const [chatMode, setChatMode] = usePersistedState<'inline' | 'popup'>(`${user?.uuid}:global_chat_mode`, 'inline');
    const currentChatMode = chatMode || 'inline';
    const handleChatModeChange = (mode: 'inline' | 'popup') => setChatMode(mode);
    const showGlobalPopup = !!user && !location.pathname.startsWith('/auth');

    return (
        <>
            <Switch>
                <Route path={'/auth'}>
                    <Spinner.Suspense>
                        <AuthenticationRouter />
                    </Spinner.Suspense>
                </Route>
                <AuthenticatedRoute path={'/server/:id'}>
                    <Spinner.Suspense>
                        <ServerContext.Provider>
                            <ServerRouter />
                        </ServerContext.Provider>
                    </Spinner.Suspense>
                </AuthenticatedRoute>
                <AuthenticatedRoute path={'/'}>
                    <Spinner.Suspense>
                        <DashboardRouter chatMode={currentChatMode} onChatModeChange={handleChatModeChange} />
                    </Spinner.Suspense>
                </AuthenticatedRoute>
                <Route path={'*'}>
                    <NotFound />
                </Route>
            </Switch>
            {showGlobalPopup && (
                <GlobalChatDock mode={currentChatMode} onModeChange={handleChatModeChange} inlineVisible={false} />
            )}
        </>
    );
};

const App = () => {
    const { PterodactylUser, SiteConfiguration } = window as ExtendedWindow;
    if (PterodactylUser && !store.getState().user.data) {
        store.getActions().user.setUserData({
            uuid: PterodactylUser.uuid,
            username: PterodactylUser.username,
            email: PterodactylUser.email,
            language: PterodactylUser.language,
            rootAdmin: PterodactylUser.root_admin,
            useTotp: PterodactylUser.use_totp,
            createdAt: new Date(PterodactylUser.created_at),
            updatedAt: new Date(PterodactylUser.updated_at),
        });
    }

    if (!store.getState().settings.data) {
        store.getActions().settings.setSettings(SiteConfiguration!);
    }

    return (
        <>
            <GlobalStylesheet />
            <StoreProvider store={store}>
                <ProgressBar />
                <div css={tw`mx-auto w-auto`}>
                    <Router history={history}>
                        <AppRoutes />
                    </Router>
                </div>
            </StoreProvider>
        </>
    );
};

export default hot(App);
