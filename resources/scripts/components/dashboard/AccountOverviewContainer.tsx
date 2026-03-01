import * as React from 'react';
import ContentBox from '@/components/elements/ContentBox';
import UpdatePasswordForm from '@/components/dashboard/forms/UpdatePasswordForm';
import UpdateEmailAddressForm from '@/components/dashboard/forms/UpdateEmailAddressForm';
import ConfigureTwoFactorForm from '@/components/dashboard/forms/ConfigureTwoFactorForm';
import AccountAppearanceForm from '@/components/dashboard/forms/AccountAppearanceForm';
import PageContentBlock from '@/components/elements/PageContentBlock';
import tw from 'twin.macro';
import { breakpoint } from '@/theme';
import styled from 'styled-components/macro';
import MessageBox from '@/components/MessageBox';
import { useLocation } from 'react-router-dom';
import { useStoreState } from 'easy-peasy';

const Container = styled.div`
    ${tw`flex flex-wrap`};

    & > div {
        ${tw`w-full`};

        ${breakpoint('sm')`
      width: calc(50% - 1rem);
    `}

        ${breakpoint('md')`
      ${tw`w-auto flex-1`};
    `}
    }
`;

const Hero = styled.div`
    ${tw`mb-6 rounded-2xl border px-5 py-5 sm:px-6 sm:py-6`};
    border-color: var(--ui-border);
    background: linear-gradient(115deg, var(--glass-elevated) 0%, var(--glass-elevated) 62%, var(--surface-tint) 100%);
    box-shadow: var(--panel-shadow);
`;

const HeroStats = styled.div`
    ${tw`mt-4 grid grid-cols-2 sm:grid-cols-4 gap-2`};
`;

const HeroStatCard = styled.div`
    ${tw`rounded-xl border px-3 py-2`};
    border-color: var(--ui-border);
    background: var(--glass-bg);
`;

export default () => {
    const { state } = useLocation<undefined | { twoFactorRedirect?: boolean }>();
    const user = useStoreState((s) => s.user.data!);

    return (
        <PageContentBlock title={'Account Overview'}>
            {state?.twoFactorRedirect && (
                <MessageBox title={'2-Factor Required'} type={'error'}>
                    Your account must have two-factor authentication enabled in order to continue.
                </MessageBox>
            )}

            <Hero css={state?.twoFactorRedirect ? tw`mt-4` : tw`mt-1`}>
                <p css={tw`text-[11px] uppercase tracking-[0.14em]`} style={{ color: 'var(--text-muted)' }}>
                    Account Center
                </p>
                <h2 css={tw`mt-2 text-2xl sm:text-[2rem] font-semibold text-neutral-100 truncate`}>{user.username}</h2>
                <p css={tw`mt-1 text-sm text-neutral-300 truncate`}>{user.email}</p>
                <HeroStats>
                    <HeroStatCard>
                        <p css={tw`text-[11px] uppercase tracking-wide text-neutral-400`}>Security</p>
                        <p css={tw`mt-1 text-base font-semibold text-neutral-100`}>
                            {user.useTotp ? '2FA On' : '2FA Off'}
                        </p>
                    </HeroStatCard>
                    <HeroStatCard>
                        <p css={tw`text-[11px] uppercase tracking-wide text-neutral-400`}>Role</p>
                        <p css={tw`mt-1 text-base font-semibold text-neutral-100 truncate`}>
                            {user.roleName || 'User'}
                        </p>
                    </HeroStatCard>
                    <HeroStatCard>
                        <p css={tw`text-[11px] uppercase tracking-wide text-neutral-400`}>Template</p>
                        <p css={tw`mt-1 text-base font-semibold text-neutral-100`}>
                            {(user.dashboardTemplate || 'midnight').slice(0, 1).toUpperCase() +
                                (user.dashboardTemplate || 'midnight').slice(1)}
                        </p>
                    </HeroStatCard>
                    <HeroStatCard>
                        <p css={tw`text-[11px] uppercase tracking-wide text-neutral-400`}>Access</p>
                        <p css={tw`mt-1 text-base font-semibold text-neutral-100`}>
                            {user.rootAdmin ? 'Root' : 'Client'}
                        </p>
                    </HeroStatCard>
                </HeroStats>
            </Hero>

            <Container css={tw`lg:grid lg:grid-cols-3 mb-8`}>
                <ContentBox title={'Update Password'} showFlashes={'account:password'}>
                    <UpdatePasswordForm />
                </ContentBox>
                <ContentBox css={tw`mt-8 sm:mt-0 sm:ml-8`} title={'Update Email Address'} showFlashes={'account:email'}>
                    <UpdateEmailAddressForm />
                </ContentBox>
                <ContentBox css={tw`md:ml-8 mt-8 md:mt-0`} title={'Two-Step Verification'}>
                    <ConfigureTwoFactorForm />
                </ContentBox>
            </Container>
            <Container css={tw`lg:grid lg:grid-cols-1 mb-10`}>
                <ContentBox title={'Avatar & Template'} showFlashes={'account:appearance'}>
                    <AccountAppearanceForm />
                </ContentBox>
            </Container>
        </PageContentBlock>
    );
};
