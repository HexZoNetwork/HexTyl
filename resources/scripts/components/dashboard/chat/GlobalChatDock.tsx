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
} from '@fortawesome/free-solid-svg-icons';
import { ChatMessage } from '@/api/chat/types';
import getGlobalChatMessages from '@/api/account/chat/getGlobalChatMessages';
import createGlobalChatMessage from '@/api/account/chat/createGlobalChatMessage';
import uploadGlobalChatImage from '@/api/account/chat/uploadGlobalChatImage';
import { httpErrorToHuman } from '@/api/http';

interface Props {
    mode: 'inline' | 'popup';
    onModeChange: (mode: 'inline' | 'popup') => void;
}

const maybeImage = (url?: string | null) => !!url && /^https?:\/\/.+\.(png|jpe?g|gif|webp|svg)$/i.test(url);

const when = (value: Date) =>
    value.toLocaleString(undefined, {
        hour: '2-digit',
        minute: '2-digit',
        month: 'short',
        day: 'numeric',
    });

const Panel = ({ children }: { children: React.ReactNode }) => (
    <div css={tw`border border-neutral-700 rounded bg-neutral-900/70 overflow-hidden`}>{children}</div>
);

export default ({ mode, onModeChange }: Props) => {
    const user = useStoreState((state) => state.user.data!);
    const uploadRef = useRef<HTMLInputElement>(null);

    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [body, setBody] = useState('');
    const [mediaUrl, setMediaUrl] = useState('');
    const [replyToId, setReplyToId] = useState<number | null>(null);
    const [isLoading, setIsLoading] = useState(true);
    const [isSending, setIsSending] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    const [error, setError] = useState('');
    const [open, setOpen] = useState(true);
    const [popupPos, setPopupPos] = useState({ x: 24, y: 96 });
    const [dragging, setDragging] = useState(false);

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

    useEffect(() => {
        if (!dragging) return;

        const onMove = (event: MouseEvent) => {
            setPopupPos((current) => ({
                x: Math.max(8, current.x + event.movementX),
                y: Math.max(8, current.y + event.movementY),
            }));
        };

        const onUp = () => setDragging(false);

        window.addEventListener('mousemove', onMove);
        window.addEventListener('mouseup', onUp);

        return () => {
            window.removeEventListener('mousemove', onMove);
            window.removeEventListener('mouseup', onUp);
        };
    }, [dragging]);

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
        const context = [
            '[Bug Context]',
            `URL: ${window.location.href}`,
            `UA: ${navigator.userAgent}`,
            `Time: ${new Date().toISOString()}`,
        ].join('\n');

        setBody((current) => (current ? `${current}\n\n${context}` : context));
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

    const content = (
        <>
            <div css={tw`px-3 py-2 border-b border-neutral-700 flex items-center justify-between gap-2`}>
                <div>
                    <h3 css={tw`text-sm font-semibold text-neutral-100`}>Global Chat</h3>
                    <p css={tw`text-2xs text-neutral-400`}>Paste/upload image + reply + bug context.</p>
                </div>
                <div css={tw`flex items-center gap-2`}>
                    <button
                        type={'button'}
                        css={tw`text-2xs px-2 py-1 rounded bg-neutral-800 hover:bg-neutral-700 text-neutral-200`}
                        onClick={() => onModeChange(mode === 'inline' ? 'popup' : 'inline')}
                        title={mode === 'inline' ? 'Switch to popup' : 'Switch to inline'}
                    >
                        <FontAwesomeIcon icon={mode === 'inline' ? faExpand : faCompress} />
                    </button>
                    {mode === 'popup' && (
                        <button type={'button'} css={tw`text-2xs px-2 py-1 rounded bg-neutral-800 hover:bg-neutral-700 text-neutral-200`} onClick={() => setOpen(false)}>
                            <FontAwesomeIcon icon={faTimes} />
                        </button>
                    )}
                </div>
            </div>
            {error && <div css={tw`px-3 py-2 text-xs text-red-300 border-b border-neutral-700`}>{error}</div>}
            <div css={tw`max-h-72 overflow-y-auto p-3 space-y-2`}>
                {isLoading ? (
                    <p css={tw`text-xs text-neutral-500 text-center py-6`}>Loading...</p>
                ) : messages.length === 0 ? (
                    <p css={tw`text-xs text-neutral-500 text-center py-6`}>Belum ada pesan.</p>
                ) : (
                    messages.map((message) => {
                        const mine = message.senderUuid === user.uuid;

                        return (
                            <div key={message.id} css={[tw`flex`, mine ? tw`justify-end` : tw`justify-start`]}>
                                <div css={[tw`max-w-[95%] rounded px-2 py-2`, mine ? tw`bg-cyan-700/30 border border-cyan-600/40` : tw`bg-neutral-800 border border-neutral-700`]}>
                                    <div css={tw`text-2xs text-neutral-400 mb-1`}>{mine ? 'You' : message.senderEmail}</div>
                                    {message.replyToId && <div css={tw`mb-1 text-2xs border-l-2 border-neutral-500 pl-2 text-neutral-400`}>Reply: {message.replyPreview || 'message'}</div>}
                                    {message.body && <div css={tw`text-xs text-neutral-100 break-words whitespace-pre-wrap`}>{message.body}</div>}
                                    {message.mediaUrl && (
                                        <div css={tw`mt-1`}>
                                            {maybeImage(message.mediaUrl) ? (
                                                <img src={message.mediaUrl} css={tw`max-h-32 rounded border border-neutral-700`} />
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
            <form onSubmit={submit} css={tw`border-t border-neutral-700 p-2 space-y-2`}>
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
                    placeholder={'Type / paste image...'}
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
                    accept={'image/*'}
                    css={tw`hidden`}
                    onChange={(event) => {
                        handleUpload(event.currentTarget.files?.[0] || null);
                        event.currentTarget.value = '';
                    }}
                />
                <div css={tw`flex flex-wrap gap-1 justify-between`}>
                    <div css={tw`flex gap-1`}>
                        <button type={'button'} onClick={() => uploadRef.current?.click()} css={tw`inline-flex items-center gap-1 rounded bg-neutral-800 hover:bg-neutral-700 px-2 py-1 text-2xs text-neutral-100`}>
                            <FontAwesomeIcon icon={faUpload} /> {isUploading ? 'Uploading' : 'Image'}
                        </button>
                        <button type={'button'} onClick={sendBugContext} css={tw`inline-flex items-center gap-1 rounded bg-neutral-800 hover:bg-neutral-700 px-2 py-1 text-2xs text-neutral-100`}>
                            <FontAwesomeIcon icon={faBug} /> Bug
                        </button>
                    </div>
                    <button type={'submit'} disabled={isSending || isUploading} css={tw`inline-flex items-center gap-1 rounded bg-cyan-700 hover:bg-cyan-600 px-2 py-1 text-xs text-white disabled:opacity-50`}>
                        <FontAwesomeIcon icon={faPaperPlane} /> Send
                    </button>
                </div>
            </form>
        </>
    );

    if (mode === 'inline') {
        return <Panel>{content}</Panel>;
    }

    return (
        <>
            {!open ? (
                <button
                    type={'button'}
                    css={tw`fixed z-50 bottom-5 right-5 rounded-full h-12 w-12 bg-cyan-700 hover:bg-cyan-600 text-white shadow-lg`}
                    onClick={() => setOpen(true)}
                    title={'Open chat'}
                >
                    <FontAwesomeIcon icon={faCommentDots} />
                </button>
            ) : (
                <div css={[tw`fixed z-50 w-[340px] max-w-[95vw] shadow-2xl`, { left: popupPos.x, top: popupPos.y }]}>
                    <div css={tw`cursor-move bg-neutral-800 px-3 py-1 text-xs text-neutral-300 border border-neutral-700 border-b-0 rounded-t`} onMouseDown={() => setDragging(true)}>
                        Drag Chat Window
                    </div>
                    <Panel>{content}</Panel>
                </div>
            )}
        </>
    );
};
