import React, { useEffect, useMemo, useRef, useState } from 'react';
import tw from 'twin.macro';
import { useStoreState } from 'easy-peasy';
import { FontAwesomeIcon } from '@fortawesome/react-fontawesome';
import {
    faArrowDown,
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
    faPlus,
    faSearchPlus,
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

const maybeImage = (url?: string | null) => !!url && /^https?:\/\/.+\.(png|jpe?g|gif|webp)$/i.test(url);
const maybeVideo = (url?: string | null) => !!url && /^https?:\/\/.+\.(mp4|webm|mov|m4v)$/i.test(url);
const URL_REGEX = /(https?:\/\/[^\s]+)/i;
const MENTION_SPLIT_REGEX = /(@[a-z0-9._-]{2,48}|[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,})/gi;
const MENTION_TOKEN_REGEX = /^@[a-z0-9._-]{2,48}$/i;
const EMAIL_TOKEN_REGEX = /^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$/i;

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

const escapeRegExp = (value: string): string => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const mentionFromEmail = (email?: string | null): string => {
    const clean = String(email || '').trim().toLowerCase();
    if (!clean) return '';
    const local = clean.split('@')[0] || clean;
    const normalized = local.replace(/[^a-z0-9._-]/g, '').slice(0, 32);

    return normalized ? `@${normalized}` : '';
};
const renderTaggedText = (value: string, onTokenClick?: (token: string) => void) =>
    value.split(MENTION_SPLIT_REGEX).map((part, idx) => {
        if (MENTION_TOKEN_REGEX.test(part) || EMAIL_TOKEN_REGEX.test(part)) {
            return (
                <button
                    key={`${part}-${idx}`}
                    type={'button'}
                    onClick={() => onTokenClick?.(part)}
                    css={tw`inline-block rounded px-1 py-0.5 bg-cyan-900/40 text-cyan-200 hover:bg-cyan-800/60`}
                >
                    {part}
                </button>
            );
        }

        return <React.Fragment key={`${part}-${idx}`}>{part}</React.Fragment>;
    });

const when = (value: Date) =>
    value.toLocaleString(undefined, {
        hour: '2-digit',
        minute: '2-digit',
        month: 'short',
        day: 'numeric',
    });

const clamp = (value: number, min: number, max: number) => Math.min(Math.max(value, min), max);
const getPopupSize = (isMinimized: boolean) => ({
    width: Math.min(392, window.innerWidth - 24),
    height: isMinimized ? 46 : Math.min(640, window.innerHeight - 32),
});

const Panel = ({ children }: { children: React.ReactNode }) => (
    <div css={tw`border border-neutral-700 rounded-lg bg-neutral-900/90 overflow-hidden backdrop-blur-sm shadow-xl`}>{children}</div>
);

export default ({ mode, onModeChange, inlineVisible = true }: Props) => {
    const user = useStoreState((state) => state.user.data!);
    const uploadRef = useRef<HTMLInputElement>(null);
    const dragDepthRef = useRef(0);
    const listRef = useRef<HTMLDivElement>(null);
    const longPressRef = useRef<number | null>(null);
    const lastBugSourceRef = useRef<{ text: string; ts: number } | null>(null);

    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [body, setBody] = useState('');
    const [mediaUrl, setMediaUrl] = useState('');
    const [replyToId, setReplyToId] = useState<number | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [isSending, setIsSending] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    const [error, setError] = useState('');
    const [showComposerPreview, setShowComposerPreview] = useState(false);
    const [open, setOpen] = usePersistedState<boolean>(`${user.uuid}:global_chat_popup_open`, false);
    const [minimized, setMinimized] = usePersistedState<boolean>(`${user.uuid}:global_chat_popup_minimized`, true);
    const [popupPos, setPopupPos] = useState<{ x: number; y: number }>({ x: 16, y: 88 });
    const [dragging, setDragging] = useState(false);
    const [isDragOver, setIsDragOver] = useState(false);
    const [pollMs, setPollMs] = usePersistedState<number>(`${user.uuid}:global_chat_poll_ms`, 5000);
    const [showJumpBottom, setShowJumpBottom] = useState(false);
    const [activeImageUrl, setActiveImageUrl] = useState<string | null>(null);
    const [imageZoom, setImageZoom] = useState(1);
    const [imagePan, setImagePan] = useState<{ x: number; y: number }>({ x: 0, y: 0 });
    const [isImagePanning, setIsImagePanning] = useState(false);
    const [stickToBottom, setStickToBottom] = useState(true);
    const panStartRef = useRef<{ x: number; y: number } | null>(null);

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
        if (stickToBottom) {
            list.scrollTop = list.scrollHeight;
            setShowJumpBottom(false);
        }
    }, [messages.length]);

    useEffect(() => {
        if (mode !== 'popup' || !open || !dragging) return;

        const { width: popupWidth, height: popupHeight } = getPopupSize(minimized);

        const onMove = (event: MouseEvent) => {
            setPopupPos((current) => ({
                x: clamp((current?.x ?? 16) + event.movementX, 8, window.innerWidth - popupWidth - 8),
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
            const { width: popupWidth, height: popupHeight } = getPopupSize(minimized);

            setPopupPos((current) => ({
                x: clamp(current?.x ?? 16, 8, window.innerWidth - popupWidth - 8),
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

    const scrollToBottom = () => {
        const list = listRef.current;
        if (!list) return;
        list.scrollTop = list.scrollHeight;
        setStickToBottom(true);
        setShowJumpBottom(false);
    };

    const applyReplyTarget = (message: ChatMessage) => {
        setReplyToId(message.id);
        const mention = mentionFromEmail(message.senderEmail);
        if (!mention) return;
        const mentionMatcher = new RegExp(`(^|\\s)${escapeRegExp(mention)}(?=\\s|$)`, 'i');
        setBody((current) => {
            const currentText = String(current || '');
            if (mentionMatcher.test(currentText)) {
                return currentText;
            }

            return currentText ? `${currentText}\n${mention} ` : `${mention} `;
        });
    };

    const insertMentionToken = (token: string) => {
        const value = token.trim();
        if (!value) return;
        const matcher = new RegExp(`(^|\\s)${escapeRegExp(value)}(?=\\s|$)`, 'i');
        setBody((current) => {
            const currentText = String(current || '');
            if (matcher.test(currentText)) {
                return currentText;
            }

            return currentText ? `${currentText}\n${value} ` : `${value} `;
        });
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
        if (/^\[Bug Source\]/i.test(text)) return;
        if (/Container:\s|URL:\s|Time:\s/i.test(text)) return;

        const normalized = text.replace(/\s+/g, ' ').trim().slice(0, 400);
        const now = Date.now();
        if (lastBugSourceRef.current && lastBugSourceRef.current.text === normalized && now - lastBugSourceRef.current.ts < 4000) {
            return;
        }
        lastBugSourceRef.current = { text: normalized, ts: now };

        const payload = [
            '[Bug Source]',
            `Container: GlobalChatDock (${mode})`,
            `Text: ${normalized}`,
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
        <div css={tw`px-3 py-2 border-b border-neutral-700 flex items-center justify-between gap-2 bg-neutral-800/95`}>
            <div>
                <h3 css={tw`text-sm font-semibold text-neutral-100`}>Global Chat</h3>
                <p css={tw`text-2xs text-neutral-400`}>Realtime ringan, upload media, dan kirim bug lines.</p>
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
                <button
                    type={'button'}
                    css={tw`h-8 w-8 rounded bg-neutral-900 hover:bg-neutral-700 text-neutral-200`}
                    onClick={() => {
                        const nextMode = mode === 'inline' ? 'popup' : 'inline';
                        onModeChange(nextMode);
                        if (nextMode === 'popup') {
                            setPopupPos({ x: 16, y: 88 });
                            setMinimized(false);
                        }
                    }}
                    title={mode === 'inline' ? 'Switch to popup mode' : 'Switch to inline mode'}
                >
                    <FontAwesomeIcon icon={mode === 'inline' ? faExpand : faCompress} />
                </button>
                {mode === 'popup' && (
                    <>
                        <button
                            type={'button'}
                            css={tw`h-8 w-8 rounded bg-neutral-900 hover:bg-neutral-700 text-neutral-200`}
                            onClick={() => setMinimized((state) => !state)}
                            title={minimized ? 'Expand chat' : 'Minimize chat'}
                        >
                            <FontAwesomeIcon icon={faMinus} />
                        </button>
                        <button
                            type={'button'}
                            css={tw`h-8 w-8 rounded bg-neutral-900 hover:bg-neutral-700 text-neutral-200`}
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
            <div css={tw`relative`}>
                <div
                    ref={listRef}
                    onScroll={(event) => {
                        const target = event.currentTarget;
                        const distanceToBottom = target.scrollHeight - target.scrollTop - target.clientHeight;
                        const nearBottom = distanceToBottom <= 80;
                        setStickToBottom(nearBottom);
                        setShowJumpBottom(distanceToBottom > 140);
                    }}
                    css={[tw`overflow-y-auto p-3 space-y-2`, { maxHeight: 'min(54vh, 24rem)' }]}
                >
                    {isLoading ? (
                        <p css={tw`text-xs text-neutral-500 text-center py-6`}>Loading...</p>
                    ) : messages.length === 0 ? (
                        <p css={tw`text-xs text-neutral-500 text-center py-6`}>Belum ada pesan.</p>
                    ) : (
                        messages.map((message) => {
                            const mine = message.senderUuid === user.uuid;

                            return (
                                <div key={message.id} css={[tw`flex`, mine ? tw`justify-end` : tw`justify-start`]}>
                                    <div css={[tw`max-w-[96%] rounded-md px-2.5 py-2 shadow-sm`, mine ? tw`bg-cyan-700/30 border border-cyan-600/40` : tw`bg-neutral-800 border border-neutral-700`]}>
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
                                                {renderTaggedText(message.body, insertMentionToken)}
                                            </div>
                                        )}
                                        {message.mediaUrl && (
                                            <div css={tw`mt-1`}>
                                                {maybeImage(message.mediaUrl) ? (
                                                    <button
                                                        type={'button'}
                                                        onClick={() => {
                                                            setActiveImageUrl(message.mediaUrl);
                                                            setImageZoom(1);
                                                            setImagePan({ x: 0, y: 0 });
                                                        }}
                                                        css={tw`block`}
                                                    >
                                                        <img src={message.mediaUrl} css={tw`max-h-32 rounded border border-neutral-700`} />
                                                    </button>
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
                                                <button type={'button'} css={tw`text-neutral-400 hover:text-neutral-100`} onClick={() => applyReplyTarget(message)}>
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
                {showJumpBottom && (
                    <button
                        type={'button'}
                        onClick={scrollToBottom}
                        css={tw`absolute right-3 bottom-3 h-8 px-2 rounded bg-cyan-700 hover:bg-cyan-600 text-white text-xs shadow-lg`}
                        title={'Scroll to latest'}
                    >
                        <FontAwesomeIcon icon={faArrowDown} /> Latest
                    </button>
                )}
            </div>
            <form
                onSubmit={submit}
                onDragEnter={handleDragEnter}
                onDragOver={handleDragOver}
                onDragLeave={handleDragLeave}
                onDrop={handleDrop}
                css={[tw`border-t border-neutral-700 p-2.5 space-y-2 relative bg-neutral-900/30`, isDragOver ? tw`bg-cyan-900/20` : undefined]}
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
                    css={tw`w-full rounded bg-neutral-800 border border-neutral-700 px-2.5 py-2 text-xs text-neutral-100 focus:outline-none focus:ring-1 focus:ring-cyan-500`}
                />
                <input
                    value={mediaUrl}
                    onChange={(event) => setMediaUrl(event.target.value)}
                    placeholder={'Image URL (auto-filled after upload)'}
                    css={tw`w-full rounded bg-neutral-800 border border-neutral-700 px-2.5 py-2 text-2xs text-neutral-100 focus:outline-none focus:ring-1 focus:ring-cyan-500`}
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
                        <button type={'button'} onClick={() => uploadRef.current?.click()} css={tw`inline-flex h-8 items-center gap-1 rounded bg-neutral-800 hover:bg-neutral-700 px-2.5 py-1 text-2xs text-neutral-100`}>
                            <FontAwesomeIcon icon={faUpload} /> {isUploading ? 'Uploading' : 'Media'}
                        </button>
                        <button type={'button'} onClick={sendBugContext} css={tw`inline-flex h-8 items-center gap-1 rounded bg-neutral-800 hover:bg-neutral-700 px-2.5 py-1 text-2xs text-neutral-100`}>
                            <FontAwesomeIcon icon={faBug} /> Bug Lines
                        </button>
                        <button
                            type={'button'}
                            onClick={() => setShowComposerPreview((value) => !value)}
                            css={tw`inline-flex h-8 items-center gap-1 rounded bg-neutral-800 hover:bg-neutral-700 px-2.5 py-1 text-2xs text-neutral-100`}
                        >
                            {showComposerPreview ? 'Hide Preview' : 'Show Preview'}
                        </button>
                    </div>
                    <button type={'submit'} disabled={isSending || isUploading} css={tw`inline-flex h-8 items-center gap-1 rounded bg-cyan-700 hover:bg-cyan-600 px-2.5 py-1 text-xs text-white disabled:opacity-50`}>
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

    const imageModal = activeImageUrl && (
        <div
            css={tw`fixed inset-0 z-[60] bg-black/90 p-4 flex flex-col`}
            onClick={() => setActiveImageUrl(null)}
        >
            <div css={tw`ml-auto flex items-center gap-2`}>
                <button
                    type={'button'}
                    css={tw`h-9 w-9 rounded bg-neutral-800 hover:bg-neutral-700 text-neutral-100`}
                    onClick={(event) => {
                        event.stopPropagation();
                        setImageZoom((z) => clamp(z - 0.2, 0.6, 3.6));
                    }}
                >
                    <FontAwesomeIcon icon={faMinus} />
                </button>
                <button
                    type={'button'}
                    css={tw`h-9 w-9 rounded bg-neutral-800 hover:bg-neutral-700 text-neutral-100`}
                    onClick={(event) => {
                        event.stopPropagation();
                        setImageZoom(1);
                    }}
                >
                    <FontAwesomeIcon icon={faSearchPlus} />
                </button>
                <button
                    type={'button'}
                    css={tw`h-9 w-9 rounded bg-neutral-800 hover:bg-neutral-700 text-neutral-100`}
                    onClick={(event) => {
                        event.stopPropagation();
                        setImageZoom((z) => clamp(z + 0.2, 0.6, 3.6));
                    }}
                >
                    <FontAwesomeIcon icon={faPlus} />
                </button>
                <button
                    type={'button'}
                    css={tw`h-9 w-9 rounded bg-neutral-800 hover:bg-neutral-700 text-neutral-100`}
                    onClick={(event) => {
                        event.stopPropagation();
                        setActiveImageUrl(null);
                    }}
                >
                    <FontAwesomeIcon icon={faTimes} />
                </button>
            </div>
            <div
                css={tw`flex-1 mt-3 flex items-center justify-center overflow-auto`}
                onClick={(event) => event.stopPropagation()}
                onWheel={(event) => {
                    event.preventDefault();
                    setImageZoom((z) => clamp(z + (event.deltaY < 0 ? 0.1 : -0.1), 0.6, 3.6));
                }}
                onMouseMove={(event) => {
                    if (!isImagePanning || !panStartRef.current) return;
                    const dx = event.clientX - panStartRef.current.x;
                    const dy = event.clientY - panStartRef.current.y;
                    panStartRef.current = { x: event.clientX, y: event.clientY };
                    setImagePan((prev) => ({ x: prev.x + dx, y: prev.y + dy }));
                }}
                onMouseUp={() => {
                    setIsImagePanning(false);
                    panStartRef.current = null;
                }}
                onMouseLeave={() => {
                    setIsImagePanning(false);
                    panStartRef.current = null;
                }}
            >
                <img
                    src={activeImageUrl}
                    css={tw`rounded border border-neutral-700 select-none`}
                    onMouseDown={(event) => {
                        event.preventDefault();
                        setIsImagePanning(true);
                        panStartRef.current = { x: event.clientX, y: event.clientY };
                    }}
                    style={{
                        transform: `translate(${imagePan.x}px, ${imagePan.y}px) scale(${imageZoom})`,
                        transformOrigin: 'center center',
                        maxHeight: '80vh',
                        maxWidth: '90vw',
                        cursor: isImagePanning ? 'grabbing' : 'grab',
                    }}
                />
            </div>
        </div>
    );

    if (mode === 'inline') {
        if (!inlineVisible) return null;
        return (
            <>
                {panel}
                {imageModal}
            </>
        );
    }

    const showBubble = !open || minimized;

    return (
        <>
            {showBubble ? (
                <button
                    type={'button'}
                    css={tw`fixed z-50 left-4 bottom-4 rounded-full h-14 px-4 bg-cyan-700 hover:bg-cyan-600 text-white shadow-xl border border-cyan-400/50 flex items-center justify-center gap-2`}
                    onClick={() => {
                        setOpen(true);
                        setMinimized(false);
                        setPopupPos({ x: 16, y: 88 });
                    }}
                    title={minimized ? 'Restore global chat' : 'Open global chat'}
                    aria-label={'Open global chat popup'}
                >
                    <FontAwesomeIcon icon={faCommentDots} />
                    <span css={tw`text-xs font-semibold`}>Chat</span>
                </button>
            ) : (
                <div
                    css={[
                        tw`fixed z-50 w-[392px] max-w-[95vw]`,
                        { left: popupPos?.x ?? 16, top: popupPos?.y ?? 88 },
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
            {imageModal}
        </>
    );
};
