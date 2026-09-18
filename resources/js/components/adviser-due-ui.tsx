import { Badge } from '@/components/ui/badge';

export function DueStatusBadge({ status }: { status: string }) {
    const className =
        status === 'Published'
            ? 'border-green-200 bg-green-50 text-green-700 dark:border-green-400/30 dark:bg-green-400/10 dark:text-green-300'
            : status === 'Archived'
              ? 'border-border bg-muted text-muted-foreground'
              : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-300';

    return (
        <Badge variant="outline" className={className}>
            {status}
        </Badge>
    );
}

export function PaymentStatusBadge({ status }: { status: string }) {
    const className =
        status === 'Paid'
            ? 'border-green-200 bg-green-50 text-green-700 dark:border-green-400/30 dark:bg-green-400/10 dark:text-green-300'
            : status === 'Waived'
              ? 'border-border bg-muted text-muted-foreground'
              : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-300';

    return (
        <Badge variant="outline" className={className}>
            {status}
        </Badge>
    );
}
