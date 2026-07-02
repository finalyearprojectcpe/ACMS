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
};

export function PersonCell({
    name,
    secondary,
    avatar,
    className,
    avatarClassName,
    size = 'sm',
}: PersonCellProps) {
    return (
        <div className={cn('flex min-w-0 items-center gap-3', className)}>
            <Avatar
                className={cn(
                    size === 'md' ? 'size-10' : 'size-8',
                    'rounded-none after:rounded-none',
                    avatarClassName,
                )}
            >
                <AvatarImage
                    src={avatar ?? undefined}
                    alt={`${name} avatar`}
                    className="rounded-none"
                />
                <AvatarFallback className="rounded-none text-xs font-medium">
                    {initialsFor(name)}
                </AvatarFallback>
            </Avatar>
            <div className="min-w-0">
                <div className="truncate font-medium">{name}</div>
                {secondary ? (
                    <div className="truncate text-xs text-muted-foreground">
                        {secondary}
                    </div>
                ) : null}
            </div>
        </div>
    );
}
