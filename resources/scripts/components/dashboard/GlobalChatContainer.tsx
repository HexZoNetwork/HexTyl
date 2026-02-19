import React, { useEffect, useMemo, useState } from 'react';
import ContentBox from '@/components/elements/ContentBox';
import tw from 'twin.macro';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCheck, faCheckDouble, faPaperPlane, faReply, faTimes } from '@fortawesome/free-solid-svg-icons';
import { useStoreState } from 'easy-peasy';
import getGlobalChatMessages from '@/api/account/chat/getGlobalChatMessages';
import createGlobalChatMessage from '@/api/account/chat/createGlobalChatMessage';
import { ChatMessage } from '@/api/chat/types';
import { httpErrorToHuman } from '@/api/http';

const maybeImage = (url?: string | null) => !!url && /^https?:\/\/.+\.(png|jpe?g|gif|webp|svg)$/i.test(url);

const when = (value: Date) =>
    value.toLocaleString(undefined, {
        hour: '2-digit',
        minute: '2-digit',
        month: 'short',
        day: 'numeric',
    });

export default () => {
    const user = useStoreState((state) => state.user.data!);
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [body, setBody] = useState('');
    const [mediaUrl, setMediaUrl] = useState('');
    const [replyToId, setReplyToId] = useState<number | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [isSending, setIsSending] = useState(false);
    const [error, setError] = useState('');

    const replyTo = useMemo(() => messages.find((message) => message.id === replyToId) || null, [messages, replyToId]);

    const load = () => {
        getGlobalChatMessages(100)
            .then((response) => {
                setMessages(response);
                setError('');
            })
            .catch((err) => setError(httpErrorToHuman(err)))
            .finally(() => setIsLoading(false));
    };

    useEffect(() => {
        setIsLoading(true);
        load();
        const timer = window.setInterval(load, 5000);

        return () => window.clearInterval(timer);
    }, []);

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        const cleanBody = body.trim();
        const cleanMedia = mediaUrl.trim();

        if (!cleanBody && !cleanMedia) return;

        setIsSending(true);
        createGlobalChatMessage({
            body: cleanBody || undefined,
            mediaUrl: cleanMedia || undefined,
            replyToId,
        })
            .then(() => {
                setBody('');
                setMediaUrl('');
                setReplyToId(null);
                load();
            })
            .catch((err) => setError(httpErrorToHuman(err)))
            .finally(() => setIsSending(false));
    };

    return (
        <div css={tw`w-full max-w-5xl mx-auto mt-10 px-4`}>
            <h1 css={tw`text-2xl font-bold text-neutral-100`}>Global Chat</h1>
            <p css={tw`text-sm text-neutral-400 mt-1`}>Ruang chat global via MariaDB + Redis cache, realtime ringan dengan polling.</p>

            <ContentBox css={tw`mt-5 p-0 overflow-hidden`}>
                {error && <div css={tw`px-4 py-2 text-xs text-red-300 border-b border-neutral-700`}>{error}</div>}
                <div css={tw`max-h-[55vh] overflow-y-auto p-4 space-y-2 bg-neutral-900`}>
                    {isLoading ? (
                        <p css={tw`text-xs text-neutral-500 text-center py-8`}>Loading...</p>
                    ) : messages.length === 0 ? (
                        <p css={tw`text-xs text-neutral-500 text-center py-8`}>Belum ada pesan.</p>
                    ) : (
                        messages.map((message) => {
                            const mine = message.senderUuid === user.uuid;

                            return (
                                <div key={message.id} css={[tw`flex`, mine ? tw`justify-end` : tw`justify-start`]}>
                                    <div css={[tw`max-w-[92%] sm:max-w-[72%] rounded px-3 py-2`, mine ? tw`bg-cyan-700/30 border border-cyan-600/40` : tw`bg-neutral-800 border border-neutral-700`]}>
                                        <div css={tw`text-2xs text-neutral-400 mb-1`}>{mine ? 'You' : message.senderEmail}</div>
                                        {message.replyToId && (
                                            <div css={tw`mb-2 text-2xs border-l-2 border-neutral-500 pl-2 text-neutral-400`}>
                                                Reply ke: {message.replyPreview || 'message'}
                                            </div>
                                        )}
                                        {message.body && <div css={tw`text-sm text-neutral-100 break-words`}>{message.body}</div>}
                                        {message.mediaUrl && (
                                            <div css={tw`mt-2`}>
                                                {maybeImage(message.mediaUrl) ? (
                                                    <img src={message.mediaUrl} css={tw`max-h-40 rounded border border-neutral-700`} />
                                                ) : (
                                                    <a href={message.mediaUrl} target={'_blank'} rel={'noreferrer'} css={tw`text-cyan-300 text-xs break-all`}>
                                                        {message.mediaUrl}
                                                    </a>
                                                )}
                                            </div>
                                        )}
                                        <div css={tw`mt-2 text-2xs text-neutral-400 flex items-center justify-between gap-2`}>
                                            <span>{when(message.createdAt)}</span>
                                            <div css={tw`flex items-center gap-2`}>
                                                <button type={'button'} css={tw`text-neutral-400 hover:text-neutral-100`} onClick={() => setReplyToId(message.id)}>
                                                    <FontAwesomeIcon icon={faReply} />
                                                </button>
                                                {mine && (
                                                    <span css={message.readCount > 0 ? tw`text-cyan-300` : tw`text-neutral-400`}>
                                                        <FontAwesomeIcon icon={message.deliveredCount > 0 ? faCheckDouble : faCheck} />
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            );
                        })
                    )}
                </div>
                <form onSubmit={submit} css={tw`p-3 border-t border-neutral-700 bg-neutral-900 space-y-2`}>
                    {replyTo && (
                        <div css={tw`flex items-center justify-between gap-2 rounded border border-neutral-700 bg-neutral-800 px-2 py-1`}>
                            <div css={tw`text-2xs text-neutral-300 truncate`}>Replying: {replyTo.body || replyTo.mediaUrl || 'media'}</div>
                            <button type={'button'} css={tw`text-neutral-400 hover:text-neutral-100`} onClick={() => setReplyToId(null)}>
                                <FontAwesomeIcon icon={faTimes} />
                            </button>
                        </div>
                    )}
                    <textarea
                        rows={2}
                        value={body}
                        onChange={(event) => setBody(event.target.value)}
                        placeholder={'Ketik pesan...'}
                        css={tw`w-full rounded bg-neutral-800 border border-neutral-700 px-3 py-2 text-sm text-neutral-100 focus:outline-none focus:ring-1 focus:ring-cyan-500`}
                    />
                    <input
                        value={mediaUrl}
                        onChange={(event) => setMediaUrl(event.target.value)}
                        placeholder={'Optional media URL (image/video/link)'}
                        css={tw`w-full rounded bg-neutral-800 border border-neutral-700 px-3 py-2 text-xs text-neutral-100 focus:outline-none focus:ring-1 focus:ring-cyan-500`}
                    />
                    <div css={tw`flex justify-end`}>
                        <button type={'submit'} disabled={isSending} css={tw`inline-flex items-center gap-2 rounded bg-cyan-700 hover:bg-cyan-600 px-3 py-1.5 text-sm text-white disabled:opacity-50`}>
                            <FontAwesomeIcon icon={faPaperPlane} /> Send
                        </button>
                    </div>
                </form>
            </ContentBox>
        </div>
    );
};
