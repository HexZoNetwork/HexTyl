import React from 'react';
import { Route, Switch, useRouteMatch } from 'react-router-dom';
import LoginContainer from '@/components/auth/LoginContainer';
import ForgotPasswordContainer from '@/components/auth/ForgotPasswordContainer';
import ResetPasswordContainer from '@/components/auth/ResetPasswordContainer';
import LoginCheckpointContainer from '@/components/auth/LoginCheckpointContainer';
import { NotFound } from '@/components/elements/ScreenBlock';
import { useHistory, useLocation } from 'react-router';
import tw from 'twin.macro';

export default () => {
    const history = useHistory();
    const location = useLocation();
    const { path } = useRouteMatch();

    return (
        <div
            css={tw`relative min-h-screen pt-8 xl:pt-20 overflow-hidden`}
            style={{
                background:
                    'radial-gradient(circle at 14% 12%, rgba(6, 176, 209, 0.18) 0%, transparent 30%), radial-gradient(circle at 86% 86%, rgba(6, 176, 209, 0.12) 0%, transparent 34%), linear-gradient(160deg, #0b1220 0%, #111827 52%, #090f1a 100%)',
            }}
        >
            <div
                css={tw`absolute inset-0 pointer-events-none opacity-40`}
                style={{
                    backgroundImage:
                        'linear-gradient(rgba(148, 163, 184, 0.08) 1px, transparent 1px), linear-gradient(90deg, rgba(148, 163, 184, 0.08) 1px, transparent 1px)',
                    backgroundSize: '34px 34px',
                    maskImage: 'radial-gradient(circle at center, black 30%, transparent 85%)',
                }}
            />
            <div
                css={tw`pointer-events-none absolute rounded-full border border-primary-500/20`}
                style={{
                    width: '460px',
                    height: '460px',
                    left: '-160px',
                    top: '-170px',
                }}
            />
            <div
                css={tw`pointer-events-none absolute rounded-full border border-primary-500/20`}
                style={{
                    width: '560px',
                    height: '560px',
                    right: '-240px',
                    bottom: '-220px',
                }}
            />
            <div css={tw`relative z-10 text-center mb-6 px-4`}>
                <h1 css={tw`text-2xl sm:text-3xl font-semibold text-neutral-100 tracking-tight`}>HexTyl Panel</h1>
                <p css={tw`mt-2 text-sm text-neutral-400`}>Free-Opensource Pterodactyl Protect And Theme</p>
            </div>
            <Switch location={location}>
                <Route path={`${path}/login`} component={LoginContainer} exact />
                <Route path={`${path}/login/checkpoint`} component={LoginCheckpointContainer} />
                <Route path={`${path}/password`} component={ForgotPasswordContainer} exact />
                <Route path={`${path}/password/reset/:token`} component={ResetPasswordContainer} />
                <Route path={`${path}/checkpoint`} />
                <Route path={'*'}>
                    <NotFound onBack={() => history.push('/auth/login')} />
                </Route>
            </Switch>
        </div>
    );
};
