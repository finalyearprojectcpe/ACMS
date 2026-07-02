import { useForm } from '@inertiajs/react';
import { RiImageEditLine, RiSave3Line } from '@remixicon/react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';

import { updateProfilePhoto } from '@/actions/App/Http/Controllers/DashboardController';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardForm,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Field, FieldError } from '@/components/ui/field';
import { initialsFor } from '@/lib/person';
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
                <CardHeader className="border-b border-border/80 pb-4">
                    <CardTitle className="text-base">Profile Photo</CardTitle>
                </CardHeader>

                <CardContent className="px-4 pb-4">
                    <div className="flex items-start gap-4">
                        <Avatar className="size-18 rounded-none bg-muted/40 after:rounded-none">
                            <AvatarImage
                                src={displayPreview ?? undefined}
                                alt={`${user.name} avatar`}
                                className="rounded-none"
                            />
                            <AvatarFallback className="rounded-none text-lg font-semibold">
                                {initialsFor(user.name)}
                            </AvatarFallback>
                        </Avatar>

                        <div className="grid min-w-0 flex-1 gap-3">
                            <div className="min-w-0">
                                <div className="truncate font-semibold tracking-normal">
                                    {user.name}
                                </div>
                                {user.email ? (
                                    <div className="mt-0.5 truncate text-sm text-muted-foreground">
                                        {user.email}
                                    </div>
                                ) : null}
                            </div>

                            <Field
                                className="gap-2"
                                data-invalid={Boolean(
                                    form.errors.profile_photo,
                                )}
                            >
                                <input
                                    ref={inputRef}
                                    id={inputId}
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp"
                                    className="sr-only"
                                    onChange={(event) =>
                                        changePhoto(event.target.files?.[0])
                                    }
                                />

                                <div className="flex flex-wrap items-center gap-2">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        asChild
                                    >
                                        <label htmlFor={inputId}>
                                            <RiImageEditLine />
                                            Change Photo
                                        </label>
                                    </Button>

                                    {selectedFileName ? (
                                        <>
                                            <span className="max-w-44 truncate text-sm text-muted-foreground">
                                                {selectedFileName}
                                            </span>
                                            <Button
                                                type="submit"
                                                size="sm"
                                                disabled={form.processing}
                                            >
                                                <RiSave3Line />
                                                {form.processing
                                                    ? 'Saving...'
                                                    : 'Save'}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="link"
                                                size="sm"
                                                className="h-8 px-1 text-link"
                                                onClick={resetSelection}
                                            >
                                                Cancel
                                            </Button>
                                        </>
                                    ) : null}
                                </div>

                                <FieldError>
                                    {form.errors.profile_photo}
                                </FieldError>
                            </Field>
                        </div>
                    </div>
                </CardContent>
            </CardForm>
        </Card>
    );
}
