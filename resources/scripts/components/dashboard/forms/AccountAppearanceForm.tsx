import React, { ChangeEvent, useEffect, useMemo, useState } from 'react';
import { Actions, State, useStoreActions, useStoreState } from 'easy-peasy';
import tw from 'twin.macro';
import { Button } from '@/components/elements/button/index';
import SpinnerOverlay from '@/components/elements/SpinnerOverlay';
import { ApplicationStore } from '@/state';
import { httpErrorToHuman } from '@/api/http';
import uploadAccountAvatar from '@/api/account/uploadAccountAvatar';
import deleteAccountAvatar from '@/api/account/deleteAccountAvatar';
import updateAccountProfile, { DashboardTemplate } from '@/api/account/updateAccountProfile';

const templateMeta: Record<DashboardTemplate, { label: string; description: string; preview: string }> = {
    midnight: {
        label: 'Midnight',
        description: 'Classic dark dashboard with cyan highlights.',
        preview: 'linear-gradient(135deg, #0f1420 0%, #17263d 45%, #16395e 100%)',
    },
    ocean: {
        label: 'Ocean',
        description: 'Cool blue palette with brighter glass accents.',
        preview: 'linear-gradient(135deg, #09111b 0%, #0f2a42 45%, #1d5f91 100%)',
    },
    ember: {
        label: 'Ember',
        description: 'Warm amber tones for high contrast focus.',
        preview: 'linear-gradient(135deg, #0f1625 0%, #1d2a44 46%, #d59f5a 100%)',
    },
    slate: {
        label: 'Slate',
        description: 'Neutral graphite style with elegant cool shadows.',
        preview: 'linear-gradient(135deg, #101421 0%, #242c3f 52%, #4f6087 100%)',
    },
};

export default () => {
    const user = useStoreState((state: State<ApplicationStore>) => state.user.data!);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [selectedTemplate, setSelectedTemplate] = useState<DashboardTemplate>(
        (user.dashboardTemplate as DashboardTemplate) || 'midnight'
    );
    const [selectedFile, setSelectedFile] = useState<File | null>(null);

    const { addFlash, clearFlashes } = useStoreActions((actions: Actions<ApplicationStore>) => actions.flashes);
    const updateUserData = useStoreActions((actions: Actions<ApplicationStore>) => actions.user.updateUserData);

    const preview = useMemo(() => {
        if (!selectedFile) return null;
        return URL.createObjectURL(selectedFile);
    }, [selectedFile]);

    useEffect(() => {
        return () => {
            if (preview) {
                URL.revokeObjectURL(preview);
            }
        };
    }, [preview]);

    useEffect(() => {
        const previous = user.dashboardTemplate || 'midnight';
        document.body.dataset.dashboardTemplate = selectedTemplate;

        return () => {
            document.body.dataset.dashboardTemplate = previous;
        };
    }, [selectedTemplate, user.dashboardTemplate]);

    const onFileChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.currentTarget.files?.[0];
        setSelectedFile(file || null);
    };

    const pushSuccess = (message: string) =>
        addFlash({
            key: 'account:appearance',
            type: 'success',
            message,
        });

    const pushError = (error: unknown) =>
        addFlash({
            key: 'account:appearance',
            type: 'error',
            title: 'Error',
            message: httpErrorToHuman(error),
        });

    const onUploadAvatar = async () => {
        if (!selectedFile) return;
        clearFlashes('account:appearance');
        setIsSubmitting(true);

        try {
            const url = await uploadAccountAvatar(selectedFile);
            updateUserData({ avatarUrl: url || undefined });
            setSelectedFile(null);
            pushSuccess('Avatar updated.');
        } catch (error) {
            pushError(error);
        } finally {
            setIsSubmitting(false);
        }
    };

    const onRemoveAvatar = async () => {
        clearFlashes('account:appearance');
        setIsSubmitting(true);

        try {
            const url = await deleteAccountAvatar();
            updateUserData({ avatarUrl: url || undefined });
            setSelectedFile(null);
            pushSuccess('Avatar removed.');
        } catch (error) {
            pushError(error);
        } finally {
            setIsSubmitting(false);
        }
    };

    const onSaveTemplate = async () => {
        clearFlashes('account:appearance');
        setIsSubmitting(true);

        try {
            await updateAccountProfile(selectedTemplate);
            updateUserData({ dashboardTemplate: selectedTemplate });
            pushSuccess('Dashboard template saved.');
        } catch (error) {
            pushError(error);
        } finally {
            setIsSubmitting(false);
        }
    };

    return (
        <div css={tw`relative`}>
            <SpinnerOverlay size={'large'} visible={isSubmitting} />

            <div
                css={tw`flex items-center gap-4 mb-4 rounded-xl border p-3`}
                style={{ borderColor: 'var(--ui-border)' }}
            >
                <img
                    src={preview || user.avatarUrl || '/favicons/logo.png'}
                    alt={'Avatar preview'}
                    css={tw`w-16 h-16 rounded-full object-cover border`}
                    style={{ borderColor: 'var(--ui-border-strong)' }}
                />
                <div css={tw`flex-1`}>
                    <input type={'file'} accept={'image/png,image/jpeg,image/webp'} onChange={onFileChange} />
                    <p css={tw`text-xs text-neutral-400 mt-2`}>PNG/JPG/WEBP. Max 2MB.</p>
                </div>
            </div>

            <div css={tw`flex flex-wrap gap-2 mb-6`}>
                <Button
                    type={'button'}
                    size={Button.Sizes.Small}
                    disabled={!selectedFile || isSubmitting}
                    onClick={onUploadAvatar}
                >
                    Upload Avatar
                </Button>
                <Button
                    type={'button'}
                    size={Button.Sizes.Small}
                    variant={Button.Variants.Secondary}
                    disabled={isSubmitting}
                    onClick={onRemoveAvatar}
                >
                    Remove Avatar
                </Button>
            </div>

            <label css={tw`block text-sm text-neutral-300 mb-2`}>Dashboard Template</label>
            <div css={tw`grid grid-cols-1 sm:grid-cols-2 gap-3 mb-4`}>
                {(Object.keys(templateMeta) as DashboardTemplate[]).map((template) => {
                    const active = selectedTemplate === template;
                    return (
                        <button
                            key={template}
                            type={'button'}
                            onClick={() => setSelectedTemplate(template)}
                            css={tw`text-left rounded-lg border p-3 transition-all duration-150`}
                            style={{
                                borderColor: active ? 'var(--accent-strong)' : 'var(--ui-border)',
                                background: active ? 'var(--accent-soft)' : 'var(--glass-elevated)',
                                boxShadow: active
                                    ? '0 12px 28px rgba(6, 20, 38, 0.45)'
                                    : '0 8px 18px rgba(4, 12, 24, 0.22)',
                            }}
                        >
                            <div
                                css={tw`w-full h-20 rounded-md border mb-2`}
                                style={{
                                    borderColor: 'var(--ui-border)',
                                    background: templateMeta[template].preview,
                                }}
                            />
                            <p css={tw`text-sm font-semibold text-neutral-100`}>{templateMeta[template].label}</p>
                            <p css={tw`mt-1 text-xs text-neutral-400`}>{templateMeta[template].description}</p>
                        </button>
                    );
                })}
            </div>

            <Button type={'button'} size={Button.Sizes.Small} disabled={isSubmitting} onClick={onSaveTemplate}>
                Save Template
            </Button>
        </div>
    );
};
