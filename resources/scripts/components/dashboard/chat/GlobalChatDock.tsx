import React, { useEffect, useMemo, useRef, useState } from 'react';
import tw from 'twin.macro';
import { useStoreState } from 'easy-peasy';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faCheck,
    faCheckDouble,
    faCommentDots,
    faExpand,
    faPaperPlane,
    faReply,
    faTimes,
    faUpload,
    faCompress,
    faBug,
    faMinus,
    faSyncAlt,
} from '@fortawesome/free-solid-svg-icons';
import { ChatMessage } from '@/api/chat/types';
import getGlobalChatMessages from '@/api/account/chat/getGlobalChatMessages';
import createGlobalChatMessage from '@/api/account/chat/createGlobalChatMessage';
import uploadGlobalChatImage from '@/api/account/chat/uploadGlobalChatImage';
import { httpErrorToHuman } from '@/api/http';
import { usePersistedState } from '@/plugins/usePersistedState';

interface Props {
    mode: 'inline' | 'popup';
    onModeChange: (mode: 'inline' | 'popup') => void;
    inlineVisible?: boolean;
}

const maybeImage = (url?: string | null) => !!url && /^https?:\/\/.+\.(png|jpe?g|gif|webp|svg)$/i.test(url);
const maybeVideo = (url?: string | null) => !!url && /^https?:\/\/.+\.(mp4|webm|mov|m4v)$/i.test(url);
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

const when = (value: Date) =>
    value.toLocaleString(undefined, {
        hour: '2-digit',
        minute: '2-digit',
        month: 'short',
        day: 'numeric',
    });

const clamp = (value: number, min: number, max: number) => Math.min(Math.max(value, min), max);

const Panel = ({ children }: { children: React.ReactNode }) => (
    <div css={tw`border border-neutral-700 rounded-lg bg-neutral-900/80 overflow-hidden backdrop-blur-sm shadow-xl`}>{children}</div>
);

export default ({ mode, onModeChange, inlineVisible = true }: Props) => {
    const user = useStoreState((state) => state.user.data!);
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
    const [showComposerPreview, setShowComposerPreview] = useState(false);
    const [open, setOpen] = usePersistedState<boolean>(`${user.uuid}:global_chat_popup_open`, true);
    const [minimized, setMinimized] = usePersistedState<boolean>(`${user.uuid}:global_chat_popup_minimized`, false);
    const [popupPos, setPopupPos] = usePersistedState<{ x: number; y: number }>(`${user.uuid}:global_chat_popup_pos`, { x: 28, y: 88 });
    const [dragging, setDragging] = useState(false);
    const [isDragOver, setIsDragOver] = useState(false);
    const [pollMs, setPollMs] = usePersistedState<number>(`${user.uuid}:global_chat_poll_ms`, 5000);

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
        if (!pollMs || pollMs <= 0) {
            return;
        }

        const timer = window.setInterval(load, pollMs);
        return () => window.clearInterval(timer);
    }, [pollMs]);

    useEffect(() => {
        const list = listRef.current;
        if (!list) return;
        list.scrollTop = list.scrollHeight;
    }, [messages.length]);

    useEffect(() => {
        if (mode !== 'popup' || !open || !dragging) return;

        const popupWidth = Math.min(380, window.innerWidth - 24);
        const popupHeight = minimized ? 46 : 620;

        const onMove = (event: MouseEvent) => {
            setPopupPos((current) => ({
                x: clamp((current?.x ?? 28) + event.movementX, 8, window.innerWidth - popupWidth - 8),
                y: clamp((current?.y ?? 88) + event.movementY, 8, window.innerHeight - popupHeight - 8),
            }));
        };

        const onUp = () => setDragging(false);

        window.addEventListener('mousemove', onMove);
        window.addEventListener('mouseup', onUp);

        return () => {
            window.removeEventListener('mousemove', onMove);
            window.removeEventListener('mouseup', onUp);
        };
    }, [mode, open, minimized, dragging]);

    useEffect(() => {
        if (mode !== 'popup' || !open) return;

        const onResize = () => {
            const popupWidth = Math.min(380, window.innerWidth - 24);
            const popupHeight = minimized ? 46 : 620;

            setPopupPos((current) => ({
                x: clamp(current?.x ?? 28, 8, window.innerWidth - popupWidth - 8),
                y: clamp(current?.y ?? 88, 8, window.innerHeight - popupHeight - 8),
            }));
        };

        const onKeyDown = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        window.addEventListener('resize', onResize);
        window.addEventListener('keydown', onKeyDown);

        return () => {
            window.removeEventListener('resize', onResize);
            window.removeEventListener('keydown', onKeyDown);
        };
    }, [mode, open, minimized]);

    const handleUpload = (file?: File | null) => {
        if (!file) return;

        setIsUploading(true);
        uploadGlobalChatImage(file)
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
            `5) Container: GlobalChatDock (${mode})`,
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

    const sendBugSourceToChat = (sourceText: string) => {
        const text = sourceText.trim();
        if (!text) return;

        const payload = [
            '[Bug Source]',
            `Container: GlobalChatDock (${mode})`,
            `Text: ${text}`,
            `URL: ${window.location.href}`,
            `Time: ${new Date().toISOString()}`,
        ].join('\n');

        createGlobalChatMessage({ body: payload })
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

    const header = (
        <div css={tw`px-3 py-2 border-b border-neutral-700 flex items-center justify-between gap-2 bg-neutral-800`}>
            <div>
                <h3 css={tw`text-sm font-semibold text-neutral-100`}>Global Chat</h3>
                <p css={tw`text-2xs text-neutral-400`}>Reply, image paste/upload, bug source.</p>
            </div>
            <div css={tw`flex items-center gap-1`}>
                <select
                    value={pollMs ?? 5000}
                    onChange={(event) => setPollMs(Number(event.currentTarget.value))}
                    css={tw`h-7 rounded bg-neutral-800 border border-neutral-700 text-2xs text-neutral-200 px-1`}
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
                    css={tw`h-7 w-7 rounded bg-neutral-800 hover:bg-neutral-700 text-neutral-200`}
                    onClick={refreshNow}
                    title={'Refresh now'}
                >
                    <FontAwesomeIcon icon={faSyncAlt} />
                </button>
                <button
                    type={'button'}
                    css={tw`h-7 w-7 rounded bg-neutral-800 hover:bg-neutral-700 text-neutral-200`}
                    onClick={() => onModeChange(mode === 'inline' ? 'popup' : 'inline')}
                    title={mode === 'inline' ? 'Switch to popup mode' : 'Switch to inline mode'}
                >
                    <FontAwesomeIcon icon={mode === 'inline' ? faExpand : faCompress} />
                </button>
                {mode === 'popup' && (
                    <>
                        <button
                            type={'button'}
                            css={tw`h-7 w-7 rounded bg-neutral-800 hover:bg-neutral-700 text-neutral-200`}
                            onClick={() => setMinimized((state) => !state)}
                            title={minimized ? 'Expand chat' : 'Minimize chat'}
                        >
                            <FontAwesomeIcon icon={faMinus} />
                        </button>
                        <button
                            type={'button'}
                            css={tw`h-7 w-7 rounded bg-neutral-800 hover:bg-neutral-700 text-neutral-200`}
                            onClick={() => setOpen(false)}
                            title={'Hide chat'}
                        >
                            <FontAwesomeIcon icon={faTimes} />
                        </button>
                    </>
                )}
            </div>
        </div>
    );

    const bodyUi = minimized ? null : (
        <>
            {error && <div css={tw`px-3 py-2 text-xs text-red-300 border-b border-neutral-700`}>{error}</div>}
            <div ref={listRef} css={tw`max-h-80 overflow-y-auto p-3 space-y-2`}>
                {isLoading ? (
                    <p css={tw`text-xs text-neutral-500 text-center py-6`}>Loading...</p>
                ) : messages.length === 0 ? (
                    <p css={tw`text-xs text-neutral-500 text-center py-6`}>Belum ada pesan.</p>
                ) : (
                    messages.map((message) => {
                        const mine = message.senderUuid === user.uuid;

                        return (
                            <div key={message.id} css={[tw`flex`, mine ? tw`justify-end` : tw`justify-start`]}>
                                <div css={[tw`max-w-[95%] rounded-md px-2.5 py-2`, mine ? tw`bg-cyan-700/30 border border-cyan-600/40` : tw`bg-neutral-800 border border-neutral-700`]}>
                                    <div css={tw`text-2xs text-neutral-400 mb-1`}>{mine ? 'You' : message.senderEmail}</div>
                                    {message.replyToId && <div css={tw`mb-1 text-2xs border-l-2 border-neutral-500 pl-2 text-neutral-400`}>Reply: {message.replyPreview || 'message'}</div>}
                                    {message.body && (
                                        <div
                                            css={tw`text-xs text-neutral-100 break-words whitespace-pre-wrap`}
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
                                        <div css={tw`mt-1`}>
                                            {maybeImage(message.mediaUrl) ? (
                                                <img src={message.mediaUrl} css={tw`max-h-32 rounded border border-neutral-700`} />
                                            ) : maybeVideo(message.mediaUrl) ? (
                                                <video src={message.mediaUrl} controls css={tw`max-h-44 rounded border border-neutral-700 w-full`} />
                                            ) : (
                                                <a href={message.mediaUrl} target={'_blank'} rel={'noreferrer'} css={tw`text-cyan-300 text-2xs break-all`}>
                                                    {message.mediaUrl}
                                                </a>
                                            )}
                                        </div>
                                    )}
                                    <div css={tw`mt-1 text-2xs text-neutral-400 flex items-center justify-between gap-2`}>
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
            <form
                onSubmit={submit}
                onDragEnter={handleDragEnter}
                onDragOver={handleDragOver}
                onDragLeave={handleDragLeave}
                onDrop={handleDrop}
                css={[tw`border-t border-neutral-700 p-2 space-y-2 relative`, isDragOver ? tw`bg-cyan-900/20` : undefined]}
            >
                {isDragOver && (
                    <div css={tw`absolute inset-0 border-2 border-dashed border-cyan-400 rounded bg-cyan-900/30 flex items-center justify-center text-cyan-200 text-xs z-10`}>
                        Drop media to upload
                    </div>
                )}
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
                    onPaste={handlePasteImage}
                    placeholder={'Type message... (paste image supported)'}
                    css={tw`w-full rounded bg-neutral-800 border border-neutral-700 px-2 py-2 text-xs text-neutral-100 focus:outline-none focus:ring-1 focus:ring-cyan-500`}
                />
                <input
                    value={mediaUrl}
                    onChange={(event) => setMediaUrl(event.target.value)}
                    placeholder={'Image URL (auto-filled after upload)'}
                    css={tw`w-full rounded bg-neutral-800 border border-neutral-700 px-2 py-2 text-2xs text-neutral-100 focus:outline-none focus:ring-1 focus:ring-cyan-500`}
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
                {composePreviewUrl && !maybeImage(composePreviewUrl) && (
                    <a
                        href={composePreviewUrl}
                        target={'_blank'}
                        rel={'noreferrer'}
                        css={tw`block rounded border border-neutral-700 bg-neutral-900/70 px-2 py-2 hover:border-cyan-500/50`}
                    >
                        <div css={tw`text-2xs text-neutral-400`}>Link Preview</div>
                        <div css={tw`text-2xs text-cyan-300 break-all`}>{getUrlLabel(composePreviewUrl)}</div>
                    </a>
                )}
                <div css={tw`flex flex-wrap gap-1 justify-between`}>
                    <div css={tw`flex flex-wrap gap-1`}>
                        <button type={'button'} onClick={() => uploadRef.current?.click()} css={tw`inline-flex items-center gap-1 rounded bg-neutral-800 hover:bg-neutral-700 px-2 py-1 text-2xs text-neutral-100`}>
                            <FontAwesomeIcon icon={faUpload} /> {isUploading ? 'Uploading' : 'Media'}
                        </button>
                        <button type={'button'} onClick={sendBugContext} css={tw`inline-flex items-center gap-1 rounded bg-neutral-800 hover:bg-neutral-700 px-2 py-1 text-2xs text-neutral-100`}>
                            <FontAwesomeIcon icon={faBug} /> Bug Lines
                        </button>
                        <button
                            type={'button'}
                            onClick={() => setShowComposerPreview((value) => !value)}
                            css={tw`inline-flex items-center gap-1 rounded bg-neutral-800 hover:bg-neutral-700 px-2 py-1 text-2xs text-neutral-100`}
                        >
                            {showComposerPreview ? 'Hide Preview' : 'Show Preview'}
                        </button>
                    </div>
                    <button type={'submit'} disabled={isSending || isUploading} css={tw`inline-flex items-center gap-1 rounded bg-cyan-700 hover:bg-cyan-600 px-2 py-1 text-xs text-white disabled:opacity-50`}>
                        <FontAwesomeIcon icon={faPaperPlane} /> Send
                    </button>
                </div>
            </form>
        </>
    );

    const panel = (
        <Panel>
            {header}
            {bodyUi}
        </Panel>
    );

    if (mode === 'inline') {
        if (!inlineVisible) return null;
        return panel;
    }

    const showBubble = !open || minimized;

    return (
        <>
            {showBubble ? (
                <button
                    type={'button'}
                    css={[
                        tw`fixed z-50 rounded-full h-12 w-12 bg-cyan-700 hover:bg-cyan-600 text-white shadow-lg border border-cyan-500/40 flex items-center justify-center`,
                        { left: popupPos?.x ?? 28, top: popupPos?.y ?? 88 },
                    ]}
                    onClick={() => {
                        setOpen(true);
                        setMinimized(false);
                    }}
                    title={minimized ? 'Restore global chat' : 'Open global chat'}
                >
                    <FontAwesomeIcon icon={faCommentDots} />
                </button>
            ) : (
                <div
                    css={[
                        tw`fixed z-50 w-[380px] max-w-[95vw]`,
                        { left: popupPos?.x ?? 28, top: popupPos?.y ?? 88 },
                    ]}
                >
                    <div
                        css={tw`cursor-move bg-neutral-800 px-3 py-1.5 text-2xs text-neutral-300 border border-neutral-700 border-b-0 rounded-t-lg select-none`}
                        onMouseDown={() => setDragging(true)}
                    >
                        Drag Global Chat Window
                    </div>
                    {panel}
                </div>
            )}
        </>
    );
};
