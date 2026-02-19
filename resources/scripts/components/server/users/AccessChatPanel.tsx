import React, { useEffect, useMemo, useRef, useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCheck, faCheckDouble, faPaperPlane, faReply, faTimes, faUpload, faBug } from '@fortawesome/free-solid-svg-icons';
import tw from 'twin.macro';
import getServerChatMessages from '@/api/server/chat/getServerChatMessages';
import createServerChatMessage from '@/api/server/chat/createServerChatMessage';
import uploadServerChatImage from '@/api/server/chat/uploadServerChatImage';
import { ChatMessage } from '@/api/chat/types';
import { httpErrorToHuman } from '@/api/http';

interface Props {
    serverUuid: string;
    currentUserUuid: string;
}

const isLikelyImage = (url?: string | null) => !!url && /^https?:\/\/.+\.(png|jpe?g|gif|webp|svg)$/i.test(url);

const formatTime = (value: Date) =>
    value.toLocaleString(undefined, {
        hour: '2-digit',
        minute: '2-digit',
        month: 'short',
        day: 'numeric',
    });

export default ({ serverUuid, currentUserUuid }: Props) => {
    const uploadRef = useRef<HTMLInputElement>(null);

    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [body, setBody] = useState('');
    const [mediaUrl, setMediaUrl] = useState('');
    const [replyToId, setReplyToId] = useState<number | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [isSending, setIsSending] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    const [error, setError] = useState('');

    const replyToMessage = useMemo(() => messages.find((message) => message.id === replyToId) || null, [messages, replyToId]);

    const load = () => {
        getServerChatMessages(serverUuid, 100)
            .then((response) => {
                setMessages(response);
                setError('');
            })
            .catch((err) => {
                setError(httpErrorToHuman(err));
            })
            .finally(() => setIsLoading(false));
    };

    useEffect(() => {
        setIsLoading(true);
        load();
        const timer = window.setInterval(load, 5000);

        return () => window.clearInterval(timer);
    }, [serverUuid]);

    const handleUpload = (file?: File | null) => {
        if (!file) return;

        setIsUploading(true);
        uploadServerChatImage(serverUuid, file)
            .then((url) => {
                setMediaUrl(url);
                setError('');
            })
            .catch((err) => setError(httpErrorToHuman(err)))
            .finally(() => setIsUploading(false));
    };

    const handlePasteImage = (event: React.ClipboardEvent<HTMLTextAreaElement>) => {
        const items = event.clipboardData?.items;
        if (!items) return;

        for (const item of Array.from(items)) {
            if (item.type.startsWith('image/')) {
                event.preventDefault();
                handleUpload(item.getAsFile());
                break;
            }
        }
    };

    const sendBugContext = () => {
        const context = [
            '[Bug Context]',
            `URL: ${window.location.href}`,
            `UA: ${navigator.userAgent}`,
            `Time: ${new Date().toISOString()}`,
        ].join('\n');

        setBody((current) => (current ? `${current}\n\n${context}` : context));
    };

    const sendMessage = (event: React.FormEvent) => {
        event.preventDefault();

        const cleanBody = body.trim();
        const cleanMedia = mediaUrl.trim();
        if (!cleanBody && !cleanMedia) {
            return;
        }

        setIsSending(true);
        createServerChatMessage(serverUuid, {
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
            .catch((err) => {
                setError(httpErrorToHuman(err));
            })
            .finally(() => setIsSending(false));
    };

    return (
        <div css={tw`mt-6 border border-neutral-700 rounded bg-neutral-900/60`}>
            <div css={tw`px-4 py-3 border-b border-neutral-700 flex items-center justify-between gap-2`}>
                <div>
                    <h3 css={tw`text-sm font-semibold text-neutral-100`}>Shared Access Chat</h3>
                    <p css={tw`text-xs text-neutral-400`}>MariaDB storage + Redis cache active. Paste image, upload image, atau kirim bug context.</p>
                </div>
            </div>

            {error && <div css={tw`px-4 py-2 text-xs text-red-300 border-b border-neutral-700`}>{error}</div>}

            <div css={tw`max-h-80 overflow-y-auto p-3 space-y-2`}>
                {isLoading ? (
                    <p css={tw`text-xs text-neutral-400 text-center py-6`}>Loading chat...</p>
                ) : !messages.length ? (
                    <p css={tw`text-xs text-neutral-400 text-center py-6`}>Belum ada chat. Kirim pesan pertama.</p>
                ) : (
                    messages.map((message) => {
                        const mine = message.senderUuid === currentUserUuid;

                        return (
                            <div key={message.id} css={[tw`flex`, mine ? tw`justify-end` : tw`justify-start`]}>
                                <div css={[tw`max-w-[90%] sm:max-w-[75%] rounded px-3 py-2`, mine ? tw`bg-cyan-700/30 border border-cyan-600/40` : tw`bg-neutral-800 border border-neutral-700`]}>
                                    <div css={tw`text-2xs text-neutral-400 mb-1`}>{mine ? 'You' : message.senderEmail}</div>
                                    {message.replyToId && (
                                        <div css={tw`mb-2 text-2xs border-l-2 border-neutral-500 pl-2 text-neutral-400`}>
                                            Reply ke: {message.replyPreview || 'message'}
                                        </div>
                                    )}
                                    {message.body && <div css={tw`text-sm text-neutral-100 break-words whitespace-pre-wrap`}>{message.body}</div>}
                                    {message.mediaUrl && (
                                        <div css={tw`mt-2`}>
                                            {isLikelyImage(message.mediaUrl) ? (
                                                <img src={message.mediaUrl} css={tw`max-h-40 rounded border border-neutral-700`} />
                                            ) : (
                                                <a href={message.mediaUrl} target={'_blank'} rel={'noreferrer'} css={tw`text-cyan-300 text-xs break-all`}>
                                                    {message.mediaUrl}
                                                </a>
                                            )}
                                        </div>
                                    )}

                                    <div css={tw`mt-2 text-2xs text-neutral-400 flex items-center justify-between gap-2`}>
                                        <span>{formatTime(message.createdAt)}</span>
                                        <div css={tw`flex items-center gap-2`}>
                                            <button type={'button'} css={tw`text-neutral-400 hover:text-neutral-100`} onClick={() => setReplyToId(message.id)} title={'Reply'}>
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

            <form onSubmit={sendMessage} css={tw`border-t border-neutral-700 p-3 space-y-2`}>
                {replyToMessage && (
                    <div css={tw`flex items-center justify-between gap-2 rounded border border-neutral-700 bg-neutral-800 px-2 py-1`}>
                        <div css={tw`text-2xs text-neutral-300 truncate`}>Replying: {replyToMessage.body || replyToMessage.mediaUrl || 'media'}</div>
                        <button type={'button'} css={tw`text-neutral-400 hover:text-neutral-100`} onClick={() => setReplyToId(null)}>
                            <FontAwesomeIcon icon={faTimes} />
                        </button>
                    </div>
                )}
                <textarea
                    value={body}
                    onChange={(event) => setBody(event.target.value)}
                    onPaste={handlePasteImage}
                    rows={2}
                    placeholder={'Type message... (paste image langsung juga bisa)'}
                    css={tw`w-full rounded bg-neutral-800 border border-neutral-700 px-3 py-2 text-sm text-neutral-100 focus:outline-none focus:ring-1 focus:ring-cyan-500`}
                />
                <input
                    value={mediaUrl}
                    onChange={(event) => setMediaUrl(event.target.value)}
                    placeholder={'Optional media URL (auto-filled after upload/paste)'}
                    css={tw`w-full rounded bg-neutral-800 border border-neutral-700 px-3 py-2 text-xs text-neutral-100 focus:outline-none focus:ring-1 focus:ring-cyan-500`}
                />
                <input
                    ref={uploadRef}
                    type={'file'}
                    accept={'image/*'}
                    css={tw`hidden`}
                    onChange={(event) => {
                        handleUpload(event.currentTarget.files?.[0] || null);
                        event.currentTarget.value = '';
                    }}
                />
                <div css={tw`flex flex-wrap gap-2 justify-between`}> 
                    <div css={tw`flex gap-2`}>
                        <button type={'button'} onClick={() => uploadRef.current?.click()} css={tw`inline-flex items-center gap-2 rounded bg-neutral-800 hover:bg-neutral-700 px-3 py-1.5 text-xs text-neutral-100`}>
                            <FontAwesomeIcon icon={faUpload} /> {isUploading ? 'Uploading...' : 'Upload Image'}
                        </button>
                        <button type={'button'} onClick={sendBugContext} css={tw`inline-flex items-center gap-2 rounded bg-neutral-800 hover:bg-neutral-700 px-3 py-1.5 text-xs text-neutral-100`}>
                            <FontAwesomeIcon icon={faBug} /> Send Bug Context
                        </button>
                    </div>
                    <button type={'submit'} disabled={isSending || isUploading} css={tw`inline-flex items-center gap-2 rounded bg-cyan-700 hover:bg-cyan-600 px-3 py-1.5 text-sm text-white disabled:opacity-50`}>
                        <FontAwesomeIcon icon={faPaperPlane} />
                        Send
                    </button>
                </div>
            </form>
        </div>
    );
};
