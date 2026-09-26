import { RiImageEditLine } from '@remixicon/react';
import type { RefObject } from 'react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { initialsFor } from '@/lib/person';
import { cn } from '@/lib/utils';

type EditableProfilePhotoProps = {
    avatar: string | null;
    name: string;
    inputId: string;
    inputRef: RefObject<HTMLInputElement | null>;
    accept?: string;
    className?: string;
    avatarClassName?: string;
    onPhotoChange: (file: File | undefined) => void;
};

export function EditableProfilePhoto({
    avatar,
    name,
    inputId,
    inputRef,
    accept = 'image/jpeg,image/png,image/webp',
    className,
    avatarClassName,
    onPhotoChange,
}: EditableProfilePhotoProps) {
    return (
        <div className={cn('relative w-fit', className)}>
            <input
                ref={inputRef}
                id={inputId}
                type="file"
                accept={accept}
                className="sr-only"
                onChange={(event) => onPhotoChange(event.target.files?.[0])}
            />
            <button
                type="button"
                aria-label="Change profile photo"
                className="group/photo relative block rounded-md outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                onClick={() => inputRef.current?.click()}
            >
                <Avatar className={cn('size-20', avatarClassName)}>
                    <AvatarImage
                        src={avatar ?? undefined}
                        alt={`${name} profile photo`}
                    />
                    <AvatarFallback className="text-xl font-semibold">
                        {initialsFor(name)}
                    </AvatarFallback>
                </Avatar>
                <span className="absolute inset-0 grid place-items-center rounded-md bg-black/50 text-white opacity-0 transition-opacity group-hover/photo:opacity-100 group-focus-visible/photo:opacity-100">
                    <RiImageEditLine className="size-5" aria-hidden="true" />
                </span>
                <span className="absolute right-1 bottom-1 grid size-7 place-items-center rounded-md bg-black/70 text-white shadow-sm [@media(hover:hover)]:hidden">
                    <RiImageEditLine className="size-4" aria-hidden="true" />
                </span>
            </button>
        </div>
    );
}
