import { Link } from '@inertiajs/react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { initialsFor } from '@/lib/person';
import { cn } from '@/lib/utils';

type PersonCellProps = {
    name: string;
    secondary?: string | null;
    avatar?: string | null;
    className?: string;
    avatarClassName?: string;
    size?: 'sm' | 'md';
    href?: string;
};

export function PersonCell({
    name,
    secondary,
    avatar,
    className,
    avatarClassName,
    size = 'sm',
    href,
}: PersonCellProps) {
    return (
        <div className={cn('flex min-w-0 items-center gap-3', className)}>
            <Avatar
                className={cn(
                    size === 'md' ? 'size-10' : 'size-8',
                    avatarClassName,
                )}
            >
                <AvatarImage src={avatar ?? undefined} alt={`${name} avatar`} />
                <AvatarFallback className="text-xs font-medium">
                    {initialsFor(name)}
                </AvatarFallback>
            </Avatar>
            <div className="min-w-0">
                {href ? (
                    <Link
                        href={href}
                        className="block truncate font-medium text-link hover:underline"
                    >
                        {name}
                    </Link>
                ) : (
                    <div className="truncate font-medium">{name}</div>
                )}
                {secondary ? (
                    <div className="truncate text-xs text-muted-foreground">
                        {secondary}
                    </div>
                ) : null}
            </div>
        </div>
    );
}
