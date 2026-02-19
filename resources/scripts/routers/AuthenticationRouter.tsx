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
            css={tw`relative min-h-screen pt-8 xl:pt-24 overflow-hidden`}
            style={{
                background:
                    'radial-gradient(circle at 14% 12%, rgba(6, 176, 209, 0.18) 0%, transparent 30%), radial-gradient(circle at 86% 86%, rgba(6, 176, 209, 0.12) 0%, transparent 34%), linear-gradient(160deg, #0b1220 0%, #111827 52%, #090f1a 100%)',
            }}
        >
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
