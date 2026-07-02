import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const courseTypeClasses: Record<string, string> = {
    'Faculty Course':
        'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-400/30 dark:bg-indigo-400/10 dark:text-indigo-300',
    'Departmental Course':
        'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-300',
    'Elective Course':
        'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-300',
};

export function CourseTypeBadge({
    type,
    className,
}: {
    type: string;
    className?: string;
}) {
    return (
        <Badge
            variant="outline"
            className={cn(
                courseTypeClasses[type] ?? courseTypeClasses['Departmental Course'],
                className,
            )}
        >
            {type}
        </Badge>
    );
}
