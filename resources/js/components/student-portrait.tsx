import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { initialsFor } from '@/lib/person';
import { cn } from '@/lib/utils';

type StudentPortraitProps = {
    name: string;
    image?: string | null;
    className?: string;
};

export function StudentPortrait({
    name,
    image,
    className,
}: StudentPortraitProps) {
    return (
        <Avatar
            className={cn('size-24 shrink-0 rounded-md sm:size-28', className)}
        >
            <AvatarImage src={image ?? undefined} alt={name} />
            <AvatarFallback className="text-xl font-semibold">
                {initialsFor(name)}
            </AvatarFallback>
        </Avatar>
    );
}
