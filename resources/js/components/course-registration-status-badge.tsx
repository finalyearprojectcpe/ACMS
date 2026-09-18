import { Badge } from '@/components/ui/badge';

const statusClasses: Record<string, string> = {
    'In review':
        'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-500/30 dark:bg-blue-500/15 dark:text-blue-300',
    Approved:
        'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-300',
    Returned:
        'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/15 dark:text-amber-300',
    'Changes requested':
        'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/15 dark:text-amber-300',
    Draft: 'border-border bg-secondary text-secondary-foreground',
};

export function CourseRegistrationStatusBadge({ status }: { status: string }) {
    return (
        <Badge
            variant="outline"
            className={
                statusClasses[status] ??
                'border-border bg-secondary text-secondary-foreground'
            }
        >
            {status}
        </Badge>
    );
}
