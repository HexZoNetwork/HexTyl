import React from 'react';
import tw from 'twin.macro';
import { usePersistedState } from '@/plugins/usePersistedState';
import { useStoreState } from 'easy-peasy';
import GlobalChatDock from '@/components/dashboard/chat/GlobalChatDock';

export default () => {
    const uuid = useStoreState((state) => state.user.data!.uuid);
    const [chatMode, setChatMode] = usePersistedState<'inline' | 'popup'>(`${uuid}:global_chat_mode`, 'inline');

    return (
        <div css={tw`w-full max-w-5xl mx-auto mt-10 px-4`}>
            <h1 css={tw`text-2xl font-bold text-neutral-100`}>Global Chat</h1>
            <p css={tw`text-sm text-neutral-400 mt-1`}>Setting mode dipakai bersama dashboard: `inline` tampil panel, `popup` jadi window melayang.</p>

            <div css={tw`mt-5`}>
                <GlobalChatDock mode={chatMode} onModeChange={setChatMode} />
            </div>
        </div>
    );
};
