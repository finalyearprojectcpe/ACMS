import { RiImageEditLine } from '@remixicon/react';
import type { ReactNode, RefObject } from 'react';

import { CopyableValue } from '@/components/copyable-value';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { CardContent } from '@/components/ui/card';
import { initialsFor } from '@/lib/person';
import { cn } from '@/lib/utils';

type ProfilePhotoIdentifier = {
    label: string;
    value: string;
};

type ProfilePhotoIdentityContentProps = {
    avatar: string | null;
    name: string;
    email?: string | null;
    inputId: string;
    inputRef?: RefObject<HTMLInputElement | null>;
    accept?: string;
    identifier?: ProfilePhotoIdentifier;
    meta?: ReactNode;
    actions?: ReactNode;
    className?: string;
    onPhotoChange: (file: File | undefined) => void;
};

export function ProfilePhotoIdentityContent({
    avatar,
    name,
    email,
    inputId,
    inputRef,
    accept = 'image/*',
    identifier,
    meta,
    actions,
    className,
    onPhotoChange,
}: ProfilePhotoIdentityContentProps) {
    return (
        <CardContent
            className={cn(
                'flex flex-col gap-5 p-4 sm:flex-row sm:items-center',
                className,
            )}
        >
            <Avatar className="size-20">
                <AvatarImage src={avatar ?? undefined} alt={`${name} avatar`} />
                <AvatarFallback className="text-xl font-semibold">
                    {initialsFor(name)}
                </AvatarFallback>
            </Avatar>

            <div className="min-w-0 flex-1">
                <div className="truncate text-xl font-semibold tracking-normal">
                    {name}
                </div>
                {email ? (
                    <div className="mt-1 truncate text-sm text-muted-foreground">
                        {email}
                    </div>
                ) : null}
                {identifier ? (
                    <div className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                        <span className="font-medium">{identifier.label}</span>
                        <CopyableValue
                            value={identifier.value}
                            className="h-7"
                            valueClassName="text-xs"
                        />
                    </div>
                ) : null}
                {meta ? <div className="mt-2">{meta}</div> : null}
            </div>

            <div className="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end">
                <input
                    ref={inputRef}
                    id={inputId}
                    type="file"
                    accept={accept}
                    className="sr-only"
                    onChange={(event) => onPhotoChange(event.target.files?.[0])}
                />
                <Button type="button" variant="outline" asChild>
                    <label htmlFor={inputId}>
                        <RiImageEditLine />
                        Change picture
                    </label>
                </Button>
                {actions}
            </div>
        </CardContent>
    );
}
