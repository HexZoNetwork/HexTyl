import React, { useEffect, useMemo, useRef, useState } from 'react';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import { faCheck, faCheckDouble, faPaperPlane, faReply, faTimes, faUpload, faBug, faSyncAlt } from '@fortawesome/free-solid-svg-icons';
import tw from 'twin.macro';
import getServerChatMessages from '@/api/server/chat/getServerChatMessages';
import createServerChatMessage from '@/api/server/chat/createServerChatMessage';
import uploadServerChatImage from '@/api/server/chat/uploadServerChatImage';
import { ChatMessage } from '@/api/chat/types';
import { httpErrorToHuman } from '@/api/http';
import { usePersistedState } from '@/plugins/usePersistedState';

interface Props {
    serverUuid: string;
    currentUserUuid: string;
}

const isLikelyImage = (url?: string | null) => !!url && /^https?:\/\/.+\.(png|jpe?g|gif|webp|svg)$/i.test(url);
const isLikelyVideo = (url?: string | null) => !!url && /^https?:\/\/.+\.(mp4|webm|mov|m4v)$/i.test(url);
const URL_REGEX = /(https?:\/\/[^\s]+)/i;

const extractFirstUrl = (value?: string | null): string | null => {
    if (!value) return null;
    const match = value.match(URL_REGEX);
    return match?.[1] || null;
};

const getUrlLabel = (url: string): string => {
    try {
        const parsed = new URL(url);
        const path = parsed.pathname === '/' ? '' : parsed.pathname;
        return `${parsed.hostname}${path}`;
    } catch {
        return url;
    }
};

const formatTime = (value: Date) =>
    value.toLocaleString(undefined, {
        hour: '2-digit',
        minute: '2-digit',
        month: 'short',
        day: 'numeric',
    });

export default ({ serverUuid, currentUserUuid }: Props) => {
    const uploadRef = useRef<HTMLInputElement>(null);
    const dragDepthRef = useRef(0);
    const listRef = useRef<HTMLDivElement>(null);
    const longPressRef = useRef<number | null>(null);

    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [body, setBody] = useState('');
    const [mediaUrl, setMediaUrl] = useState('');
    const [replyToId, setReplyToId] = useState<number | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [isSending, setIsSending] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    const [error, setError] = useState('');
    const [isDragOver, setIsDragOver] = useState(false);
    const [showComposerPreview, setShowComposerPreview] = useState(false);
    const [pollMs, setPollMs] = usePersistedState<number>(`server:${serverUuid}:chat_poll_ms`, 5000);

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
        if (!pollMs || pollMs <= 0) {
            return;
        }

        const timer = window.setInterval(load, pollMs);

        return () => window.clearInterval(timer);
    }, [serverUuid, pollMs]);

    useEffect(() => {
        const list = listRef.current;
        if (!list) return;
        list.scrollTop = list.scrollHeight;
    }, [messages.length]);

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
        const bugLines = [
            '1) Issue:',
            '2) Steps to reproduce:',
            '3) Expected result:',
            '4) Actual result:',
            `5) Container: AccessChatPanel (server ${serverUuid})`,
            `6) URL: ${window.location.href}`,
            `7) Viewport: ${window.innerWidth}x${window.innerHeight}`,
            `8) User Agent: ${navigator.userAgent}`,
            `9) Media URL: ${mediaUrl || '-'}`,
            `10) Timestamp: ${new Date().toISOString()}`,
        ].join('\n');

        setBody((current) => (current ? `${current}\n\n${bugLines}` : bugLines));
    };

    const handleDragOver = (event: React.DragEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (!isDragOver) setIsDragOver(true);
    };

    const handleDragLeave = (event: React.DragEvent<HTMLFormElement>) => {
        event.preventDefault();
        dragDepthRef.current = Math.max(0, dragDepthRef.current - 1);
        if (dragDepthRef.current === 0) {
            setIsDragOver(false);
        }
    };

    const handleDragEnter = (event: React.DragEvent<HTMLFormElement>) => {
        event.preventDefault();
        dragDepthRef.current += 1;
        setIsDragOver(true);
    };

    const handleDrop = (event: React.DragEvent<HTMLFormElement>) => {
        event.preventDefault();
        dragDepthRef.current = 0;
        setIsDragOver(false);
        const files = event.dataTransfer?.files;
        const media = files?.length
            ? Array.from(files).find((file) => file.type.startsWith('image/') || file.type.startsWith('video/'))
            : null;
        if (media) {
            handleUpload(media);
            return;
        }

        const droppedUrl = event.dataTransfer?.getData('text/uri-list') || event.dataTransfer?.getData('text/plain');
        if (droppedUrl && /^https?:\/\//i.test(droppedUrl.trim())) {
            setMediaUrl(droppedUrl.trim());
            setError('');
            return;
        }

        setError('Drop media file (image/video) or media URL only.');
    };

    const pollOptions = [
        { value: 0, label: 'Manual' },
        { value: 2000, label: '2s' },
        { value: 5000, label: '5s' },
        { value: 10000, label: '10s' },
        { value: 15000, label: '15s' },
    ];

    const refreshNow = () => {
        setIsLoading(true);
        load();
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

    const sendBugSourceToChat = (sourceText: string) => {
        const text = sourceText.trim();
        if (!text) return;

        const payload = [
            '[Bug Source]',
            `Container: AccessChatPanel (${serverUuid})`,
            `Text: ${text}`,
            `URL: ${window.location.href}`,
            `Time: ${new Date().toISOString()}`,
        ].join('\n');

        createServerChatMessage(serverUuid, { body: payload })
            .then(() => load())
            .catch((err) => setError(httpErrorToHuman(err)));
    };

    const composePreviewUrl = showComposerPreview ? extractFirstUrl(body) : null;

    const clearLongPress = () => {
        if (longPressRef.current) {
            window.clearTimeout(longPressRef.current);
            longPressRef.current = null;
        }
    };

    return (
        <div css={tw`mt-6 border border-neutral-700 rounded-lg bg-neutral-900/70 shadow`}>
            <div css={tw`px-4 py-3 border-b border-neutral-700 flex items-center justify-between gap-2 bg-neutral-800/80 rounded-t-lg`}>
                <div>
                    <h3 css={tw`text-sm font-semibold text-neutral-100`}>Shared Access Chat</h3>
                    <p css={tw`text-xs text-neutral-400`}>MariaDB storage + Redis cache active. Paste image, upload image, atau kirim bug context.</p>
                </div>
                <div css={tw`flex items-center gap-1`}>
                    <select
                        value={pollMs ?? 5000}
                        onChange={(event) => setPollMs(Number(event.currentTarget.value))}
                        css={tw`h-8 rounded bg-neutral-900 border border-neutral-700 text-2xs text-neutral-200 px-1.5`}
                        title={'Polling interval'}
                    >
                        {pollOptions.map((option) => (
                            <option key={option.value} value={option.value}>
                                Poll {option.label}
                            </option>
                        ))}
                    </select>
                    <button
                        type={'button'}
                        css={tw`h-8 w-8 rounded bg-neutral-900 hover:bg-neutral-700 text-neutral-200`}
                        onClick={refreshNow}
                        title={'Refresh now'}
                    >
                        <FontAwesomeIcon icon={faSyncAlt} />
                    </button>
                </div>
            </div>

            {error && <div css={tw`px-4 py-2 text-xs text-red-300 border-b border-neutral-700`}>{error}</div>}

            <div ref={listRef} css={[tw`overflow-y-auto p-3 space-y-2`, { maxHeight: 'min(52vh, 24rem)' }]}>
                {isLoading ? (
                    <p css={tw`text-xs text-neutral-400 text-center py-6`}>Loading chat...</p>
                ) : !messages.length ? (
                    <p css={tw`text-xs text-neutral-400 text-center py-6`}>Belum ada chat. Kirim pesan pertama.</p>
                ) : (
                    messages.map((message) => {
                        const mine = message.senderUuid === currentUserUuid;

                        return (
                            <div key={message.id} css={[tw`flex`, mine ? tw`justify-end` : tw`justify-start`]}>
                                <div css={[tw`max-w-[92%] sm:max-w-[75%] rounded-md px-3 py-2 shadow-sm`, mine ? tw`bg-cyan-700/30 border border-cyan-600/40` : tw`bg-neutral-800 border border-neutral-700`]}>
                                    <div css={tw`text-2xs text-neutral-400 mb-1`}>{mine ? 'You' : message.senderEmail}</div>
                                    {message.replyToId && (
                                        <div css={tw`mb-2 text-2xs border-l-2 border-neutral-500 pl-2 text-neutral-400`}>
                                            Reply ke: {message.replyPreview || 'message'}
                                        </div>
                                    )}
                                    {message.body && (
                                        <div
                                            css={tw`text-sm text-neutral-100 break-words whitespace-pre-wrap`}
                                            onContextMenu={(event) => {
                                                event.preventDefault();
                                                const selected = window.getSelection()?.toString().trim();
                                                sendBugSourceToChat(selected || message.body || '');
                                            }}
                                            onTouchStart={() => {
                                                clearLongPress();
                                                longPressRef.current = window.setTimeout(() => {
                                                    const selected = window.getSelection()?.toString().trim();
                                                    sendBugSourceToChat(selected || message.body || '');
                                                }, 600);
                                            }}
                                            onTouchEnd={clearLongPress}
                                            onTouchCancel={clearLongPress}
                                        >
                                            {message.body}
                                        </div>
                                    )}
                                    {message.mediaUrl && (
                                        <div css={tw`mt-2`}>
                                            {isLikelyImage(message.mediaUrl) ? (
                                                <img src={message.mediaUrl} css={tw`max-h-40 rounded border border-neutral-700`} />
                                            ) : isLikelyVideo(message.mediaUrl) ? (
                                                <video src={message.mediaUrl} controls css={tw`max-h-48 rounded border border-neutral-700 w-full`} />
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

            <form
                onSubmit={sendMessage}
                onDragEnter={handleDragEnter}
                onDragOver={handleDragOver}
                onDragLeave={handleDragLeave}
                onDrop={handleDrop}
                css={[tw`border-t border-neutral-700 p-3 space-y-2 relative bg-neutral-900/30`, isDragOver ? tw`bg-cyan-900/20` : undefined]}
            >
                {isDragOver && (
                    <div css={tw`absolute inset-0 border-2 border-dashed border-cyan-400 rounded bg-cyan-900/30 flex items-center justify-center text-cyan-200 text-xs z-10`}>
                        Drop media to upload
                    </div>
                )}
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
                    accept={'image/*,video/*'}
                    css={tw`hidden`}
                    onChange={(event) => {
                        handleUpload(event.currentTarget.files?.[0] || null);
                        event.currentTarget.value = '';
                    }}
                />
                {composePreviewUrl && !isLikelyImage(composePreviewUrl) && (
                    <a
                        href={composePreviewUrl}
                        target={'_blank'}
                        rel={'noreferrer'}
                        css={tw`block rounded border border-neutral-700 bg-neutral-900/70 px-3 py-2 hover:border-cyan-500/50`}
                    >
                        <div css={tw`text-2xs text-neutral-400`}>Link Preview</div>
                        <div css={tw`text-xs text-cyan-300 break-all`}>{getUrlLabel(composePreviewUrl)}</div>
                    </a>
                )}
                <div css={tw`flex flex-wrap gap-2 justify-between`}>
                    <div css={tw`flex flex-wrap gap-2`}>
                        <button type={'button'} onClick={() => uploadRef.current?.click()} css={tw`inline-flex h-8 items-center gap-2 rounded bg-neutral-800 hover:bg-neutral-700 px-3 py-1.5 text-xs text-neutral-100`}>
                            <FontAwesomeIcon icon={faUpload} /> {isUploading ? 'Uploading...' : 'Upload Media'}
                        </button>
                        <button type={'button'} onClick={sendBugContext} css={tw`inline-flex h-8 items-center gap-2 rounded bg-neutral-800 hover:bg-neutral-700 px-3 py-1.5 text-xs text-neutral-100`}>
                            <FontAwesomeIcon icon={faBug} /> Send Bug Context
                        </button>
                        <button
                            type={'button'}
                            onClick={() => setShowComposerPreview((value) => !value)}
                            css={tw`inline-flex h-8 items-center gap-2 rounded bg-neutral-800 hover:bg-neutral-700 px-3 py-1.5 text-xs text-neutral-100`}
                        >
                            {showComposerPreview ? 'Hide Link Preview' : 'Show Link Preview'}
                        </button>
                    </div>
                    <button type={'submit'} disabled={isSending || isUploading} css={tw`inline-flex h-8 items-center gap-2 rounded bg-cyan-700 hover:bg-cyan-600 px-3 py-1.5 text-sm text-white disabled:opacity-50`}>
                        <FontAwesomeIcon icon={faPaperPlane} />
                        Send
                    </button>
                </div>
            </form>
        </div>
    );
};
