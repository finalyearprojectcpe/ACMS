import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const roleClasses: Record<string, string> = {
    'School Admin':
        'border-foreground bg-foreground text-background dark:border-foreground dark:bg-foreground dark:text-background',
    'Faculty Head':
        'border-indigo-200 bg-indigo-50 text-indigo-700 dark:border-indigo-400/30 dark:bg-indigo-400/10 dark:text-indigo-300',
    HOD: 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-400/30 dark:bg-blue-400/10 dark:text-blue-300',
    'Course Adviser':
        'border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-400/30 dark:bg-teal-400/10 dark:text-teal-300',
    Staff: 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-400/30 dark:bg-slate-400/10 dark:text-slate-300',
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
                roleClasses[role] ?? roleClasses.Staff,
                className,
            )}
        >
            {context ? `${role} · ${context}` : role}
        </Badge>
    );
}
