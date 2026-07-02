import { useForm } from '@inertiajs/react';
import { RiSave3Line } from '@remixicon/react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';

import { updateProfilePhoto } from '@/actions/App/Http/Controllers/DashboardController';
import { ProfilePhotoIdentityContent } from '@/components/profile-photo-identity-content';
import { Button } from '@/components/ui/button';
import { Card, CardForm } from '@/components/ui/card';
import { FieldError } from '@/components/ui/field';
import { cn } from '@/lib/utils';

type ProfilePhotoFormData = {
    profile_photo: File | null;
};

type ProfilePhotoUser = {
    name: string;
    email: string | null;
    avatar: string | null;
};

type ProfilePhotoSettingsCardProps = {
    user: ProfilePhotoUser;
    inputId: string;
    className?: string;
};

export function ProfilePhotoSettingsCard({
    user,
    inputId,
    className,
}: ProfilePhotoSettingsCardProps) {
    const [preview, setPreview] = useState<string | null>(null);
    const inputRef = useRef<HTMLInputElement | null>(null);
    const objectUrls = useRef<string[]>([]);
    const form = useForm<ProfilePhotoFormData>({
        profile_photo: null,
    });
    const selectedFileName = form.data.profile_photo?.name ?? null;
    const hasSelectedPhoto = form.data.profile_photo !== null;
    const displayPreview = hasSelectedPhoto ? preview : user.avatar;

    useEffect(() => {
        const objectUrlList = objectUrls.current;

        return () => {
            objectUrlList.forEach((url) => URL.revokeObjectURL(url));
        };
    }, []);

    function changePhoto(file: File | undefined) {
        if (!file) {
            return;
        }

        const objectUrl = URL.createObjectURL(file);

        objectUrls.current.push(objectUrl);
        setPreview(objectUrl);
        form.setData('profile_photo', file);
    }

    function resetSelection() {
        setPreview(null);
        form.reset('profile_photo');

        if (inputRef.current) {
            inputRef.current.value = '';
        }
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!hasSelectedPhoto) {
            return;
        }

        form.submit(updateProfilePhoto(), {
            forceFormData: true,
            onSuccess: resetSelection,
        });
    }

    return (
        <Card
            className={cn(
                'border border-border bg-background shadow-sm ring-1 ring-border/70',
                className,
            )}
        >
            <CardForm onSubmit={submit}>
                <ProfilePhotoIdentityContent
                    avatar={displayPreview}
                    name={user.name}
                    email={user.email}
                    inputId={inputId}
                    inputRef={inputRef}
                    accept="image/jpeg,image/png,image/webp"
                    onPhotoChange={changePhoto}
                    meta={
                        selectedFileName || form.errors.profile_photo ? (
                            <div className="grid gap-1">
                                {selectedFileName ? (
                                    <span className="truncate text-sm text-muted-foreground">
                                        {selectedFileName}
                                    </span>
                                ) : null}
                                <FieldError>
                                    {form.errors.profile_photo}
                                </FieldError>
                            </div>
                        ) : null
                    }
                    actions={
                        hasSelectedPhoto ? (
                            <>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    <RiSave3Line />
                                    {form.processing ? 'Saving...' : 'Save'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="link"
                                    className="px-1 text-link"
                                    onClick={resetSelection}
                                >
                                    Cancel
                                </Button>
                            </>
                        ) : null
                    }
                />
            </CardForm>
        </Card>
    );
}
