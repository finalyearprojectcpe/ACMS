import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const roleClasses: Record<string, string> = {
    'Course Adviser':
        'border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-400/30 dark:bg-teal-400/10 dark:text-teal-300',
    Student:
        'border-zinc-200 bg-zinc-50 text-zinc-700 dark:border-zinc-400/30 dark:bg-zinc-400/10 dark:text-zinc-300',
};

export function RoleBadge({
    role,
    context,
    className,
}: {
    role: string;
    context?: string | null;
    className?: string;
}) {
    return (
        <Badge
            variant="outline"
            className={cn(
                'whitespace-nowrap',
                roleClasses[role] ?? roleClasses.Student,
                className,
            )}
        >
            {context ? `${role} · ${context}` : role}
        </Badge>
    );
}
