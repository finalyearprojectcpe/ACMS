import { Badge } from '@/components/ui/badge';

export function SetInviteStatus({ open }: { open: boolean }) {
    return (
        <Badge
            variant="outline"
            className={
                open
                    ? 'border-emerald-600/20 bg-emerald-600/10 text-emerald-700 dark:text-emerald-300'
                    : 'border-destructive/20 bg-destructive/10 text-destructive'
            }
        >
            Invites {open ? 'Open' : 'Closed'}
        </Badge>
    );
}
