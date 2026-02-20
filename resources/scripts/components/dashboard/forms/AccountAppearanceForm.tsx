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

const templateLabels: Record<DashboardTemplate, string> = {
    midnight: 'Midnight',
    ocean: 'Ocean',
    ember: 'Ember',
};

export default () => {
    const user = useStoreState((state: State<ApplicationStore>) => state.user.data!);
    const [isSubmitting, setIsSubmitting] = useState(false);
    const [selectedTemplate, setSelectedTemplate] = useState<DashboardTemplate>((user.dashboardTemplate as DashboardTemplate) || 'midnight');
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

            <div css={tw`flex items-center gap-4 mb-4`}>
                <img
                    src={preview || user.avatarUrl || '/favicons/logo.png'}
                    alt={'Avatar preview'}
                    css={tw`w-16 h-16 rounded-full object-cover border border-neutral-500`}
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
            <select
                css={tw`w-full rounded border border-neutral-500 bg-neutral-900 text-neutral-100 p-2 mb-4`}
                value={selectedTemplate}
                onChange={(e) => setSelectedTemplate(e.currentTarget.value as DashboardTemplate)}
            >
                {(Object.keys(templateLabels) as DashboardTemplate[]).map((template) => (
                    <option key={template} value={template}>
                        {templateLabels[template]}
                    </option>
                ))}
            </select>

            <Button type={'button'} size={Button.Sizes.Small} disabled={isSubmitting} onClick={onSaveTemplate}>
                Save Template
            </Button>
        </div>
    );
};
