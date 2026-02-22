import React, { useEffect, useRef, useState } from 'react';
import getFileContents from '@/api/server/files/getFileContents';
import { httpErrorToHuman } from '@/api/http';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import saveFileContents from '@/api/server/files/saveFileContents';
import FileManagerBreadcrumbs from '@/components/server/files/FileManagerBreadcrumbs';
import { useHistory, useLocation, useParams } from 'react-router';
import FileNameModal from '@/components/server/files/FileNameModal';
import Can from '@/components/elements/Can';
import FlashMessageRender from '@/components/FlashMessageRender';
import PageContentBlock from '@/components/elements/PageContentBlock';
import { ServerError } from '@/components/elements/ScreenBlock';
import tw from 'twin.macro';
import Button from '@/components/elements/Button';
import Select from '@/components/elements/Select';
import modes from '@/modes';
import useFlash from '@/plugins/useFlash';
import { ServerContext } from '@/state/server';
import ErrorBoundary from '@/components/elements/ErrorBoundary';
import { encodePathSegments, hashToPath } from '@/helpers';
import { dirname } from 'pathe';
import CodemirrorEditor from '@/components/elements/CodemirrorEditor';
import { usePersistedState } from '@/plugins/usePersistedState';

const DRAFT_SAVE_DELAY_MS = 650;

export default () => {
    const [error, setError] = useState('');
    const { action } = useParams<{ action: 'new' | string }>();
    const [loading, setLoading] = useState(action === 'edit');
    const [content, setContent] = useState('');
    const [modalVisible, setModalVisible] = useState(false);
    const [mode, setMode] = useState('text/plain');
    const [activeContent, setActiveContent] = useState('');
    const [line, setLine] = useState(1);
    const [column, setColumn] = useState(1);
    const [hasDraft, setHasDraft] = useState(false);
    const [draftSavedAt, setDraftSavedAt] = useState<number | null>(null);
    const [wordWrap, setWordWrap] = usePersistedState<boolean>('ide:word_wrap', true);
    const [fontSizePx, setFontSizePx] = usePersistedState<number>('ide:font_size_px', 12);

    const history = useHistory();
    const { hash } = useLocation();

    const id = ServerContext.useStoreState((state) => state.server.data!.id);
    const uuid = ServerContext.useStoreState((state) => state.server.data!.uuid);
    const setDirectory = ServerContext.useStoreActions((actions) => actions.files.setDirectory);
    const { addError, clearFlashes } = useFlash();
    const fetchFileContentRef = useRef<null | (() => Promise<string>)>(null);
    const draftTimerRef = useRef<number | null>(null);

    const path = hashToPath(hash);
    const editorPath = action === 'edit' ? path : `__new__/${path || 'untitled'}`;
    const draftKey = `ide:draft:${uuid}:${editorPath}`;

    useEffect(() => {
        if (action === 'new') return;

        setError('');
        setLoading(true);
        setDirectory(dirname(path));
        getFileContents(uuid, path)
            .then((value) => {
                setContent(value);
                const draft = localStorage.getItem(draftKey);
                setHasDraft(!!draft && draft !== value);
            })
            .catch((error) => {
                console.error(error);
                setError(httpErrorToHuman(error));
            })
            .then(() => setLoading(false));
    }, [action, uuid, path, draftKey]);

    useEffect(() => {
        if (action !== 'new') return;

        const draft = localStorage.getItem(draftKey);
        if (draft) {
            setContent(draft);
            setHasDraft(true);
        }
    }, [action, draftKey]);

    useEffect(
        () => () => {
            if (draftTimerRef.current) {
                window.clearTimeout(draftTimerRef.current);
                draftTimerRef.current = null;
            }
        },
        []
    );

    const save = (name?: string) => {
        if (!fetchFileContentRef.current) {
            return;
        }

        setLoading(true);
        clearFlashes('files:view');
        fetchFileContentRef
            .current()
            .then((content) => saveFileContents(uuid, name || hashToPath(hash), content))
            .then(() => {
                localStorage.removeItem(draftKey);
                setHasDraft(false);
                setDraftSavedAt(null);
                if (name) {
                    history.push(`/server/${id}/files/edit#/${encodePathSegments(name)}`);
                    return;
                }

                return Promise.resolve();
            })
            .catch((error) => {
                console.error(error);
                addError({ message: httpErrorToHuman(error), key: 'files:view' });
            })
            .then(() => setLoading(false));
    };

    const restoreDraft = () => {
        const draft = localStorage.getItem(draftKey);
        if (!draft) {
            setHasDraft(false);
            return;
        }

        setContent(draft);
        setActiveContent(draft);
        setHasDraft(false);
    };

    const discardDraft = () => {
        localStorage.removeItem(draftKey);
        setHasDraft(false);
        setDraftSavedAt(null);
    };

    if (error) {
        return <ServerError message={error} onBack={() => history.goBack()} />;
    }

    return (
        <PageContentBlock>
            <FlashMessageRender byKey={'files:view'} css={tw`mb-4`} />
            <ErrorBoundary>
                <div css={tw`mb-4`}>
                    <FileManagerBreadcrumbs withinFileEditor isNewFile={action !== 'edit'} />
                </div>
            </ErrorBoundary>
            {hash.replace(/^#/, '').endsWith('.pteroignore') && (
                <div css={tw`mb-4 p-4 border-l-4 bg-neutral-900 rounded border-cyan-400`}>
                    <p css={tw`text-neutral-300 text-sm`}>
                        You&apos;re editing a <code css={tw`font-mono bg-black rounded py-px px-1`}>.pteroignore</code>{' '}
                        file. Any files or directories listed in here will be excluded from backups. Wildcards are
                        supported by using an asterisk (<code css={tw`font-mono bg-black rounded py-px px-1`}>*</code>).
                        You can negate a prior rule by prepending an exclamation point (
                        <code css={tw`font-mono bg-black rounded py-px px-1`}>!</code>).
                    </p>
                </div>
            )}
            <FileNameModal
                visible={modalVisible}
                onDismissed={() => setModalVisible(false)}
                onFileNamed={(name) => {
                    setModalVisible(false);
                    save(name);
                }}
            />
            <div css={tw`relative`}>
                <SpinnerOverlay visible={loading} />
                <CodemirrorEditor
                    mode={mode}
                    filename={hash.replace(/^#/, '')}
                    wordWrap={wordWrap}
                    fontSizePx={fontSizePx}
                    onModeChanged={setMode}
                    initialContent={content}
                    fetchContent={(value) => {
                        fetchFileContentRef.current = value;
                    }}
                    onContentChange={(value) => {
                        setActiveContent(value);
                        if (draftTimerRef.current) {
                            window.clearTimeout(draftTimerRef.current);
                        }

                        draftTimerRef.current = window.setTimeout(() => {
                            if (value === content) {
                                localStorage.removeItem(draftKey);
                                setHasDraft(false);
                                setDraftSavedAt(null);
                                return;
                            }

                            localStorage.setItem(draftKey, value);
                            setHasDraft(true);
                            setDraftSavedAt(Date.now());
                        }, DRAFT_SAVE_DELAY_MS);
                    }}
                    onCursorChange={(nextLine, nextColumn) => {
                        setLine(nextLine);
                        setColumn(nextColumn);
                    }}
                    onContentSaved={() => {
                        if (action !== 'edit') {
                            setModalVisible(true);
                        } else {
                            save();
                        }
                    }}
                />
            </div>
            <div css={tw`mt-3 rounded-lg border border-neutral-500/20 bg-neutral-900/30 px-3 py-2`}>
                <div css={tw`flex flex-wrap items-center justify-between gap-2 text-xs text-neutral-300`}>
                    <div css={tw`flex items-center gap-2`}>
                        <span css={tw`px-2 py-1 rounded bg-neutral-800/80 border border-neutral-600/30`}>
                            Ln {line}
                        </span>
                        <span css={tw`px-2 py-1 rounded bg-neutral-800/80 border border-neutral-600/30`}>
                            Col {column}
                        </span>
                        <span css={tw`px-2 py-1 rounded bg-neutral-800/80 border border-neutral-600/30`}>
                            {activeContent.length.toLocaleString()} chars
                        </span>
                        {draftSavedAt && (
                            <span css={tw`px-2 py-1 rounded bg-cyan-700/20 border border-cyan-400/30 text-cyan-200`}>
                                Draft saved {new Date(draftSavedAt).toLocaleTimeString()}
                            </span>
                        )}
                    </div>
                    <div css={tw`flex flex-wrap items-center gap-2`}>
                        {hasDraft && (
                            <>
                                <Button size={'xsmall'} isSecondary onClick={restoreDraft}>
                                    Restore Draft
                                </Button>
                                <Button size={'xsmall'} isSecondary onClick={discardDraft}>
                                    Discard Draft
                                </Button>
                            </>
                        )}
                        <Button
                            size={'xsmall'}
                            isSecondary
                            onClick={() => setWordWrap((prev) => !prev)}
                            css={tw`!text-cyan-200`}
                        >
                            Wrap: {wordWrap ? 'On' : 'Off'}
                        </Button>
                        <Button
                            size={'xsmall'}
                            isSecondary
                            onClick={() => setFontSizePx((prev) => Math.max(11, prev - 1))}
                            css={tw`!text-cyan-200`}
                        >
                            A-
                        </Button>
                        <Button
                            size={'xsmall'}
                            isSecondary
                            onClick={() => setFontSizePx((prev) => Math.min(18, prev + 1))}
                            css={tw`!text-cyan-200`}
                        >
                            A+
                        </Button>
                    </div>
                </div>
            </div>
            <div css={tw`flex justify-end mt-4`}>
                <div css={tw`flex-1 sm:flex-none rounded bg-neutral-900 mr-4`}>
                    <Select value={mode} onChange={(e) => setMode(e.currentTarget.value)}>
                        {modes.map((mode) => (
                            <option key={`${mode.name}_${mode.mime}`} value={mode.mime}>
                                {mode.name}
                            </option>
                        ))}
                    </Select>
                </div>
                {action === 'edit' ? (
                    <Can action={'file.update'}>
                        <Button css={tw`flex-1 sm:flex-none`} onClick={() => save()}>
                            Save Content
                        </Button>
                    </Can>
                ) : (
                    <Can action={'file.create'}>
                        <Button css={tw`flex-1 sm:flex-none`} onClick={() => setModalVisible(true)}>
                            Create File
                        </Button>
                    </Can>
                )}
            </div>
        </PageContentBlock>
    );
};
