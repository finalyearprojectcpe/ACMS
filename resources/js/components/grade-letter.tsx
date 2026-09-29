import { cn } from '@/lib/utils';

const colors: Record<string, string> = {
    A: 'bg-green-500/15 text-green-800 dark:text-green-300',
    B: 'bg-green-700/15 text-green-900 dark:text-green-200',
    C: 'bg-yellow-500/15 text-yellow-900 dark:text-yellow-200',
    D: 'bg-red-800/15 text-red-900 dark:text-red-200',
    E: 'bg-red-700/15 text-red-800 dark:text-red-200',
    F: 'bg-red-500/15 text-red-800 dark:text-red-200',
};

export function GradeLetter({
    grade,
    className,
}: {
    grade: string | null;
    className?: string;
}) {
    return (
        <span
            className={cn(
                grade
                    ? 'inline-flex size-6 items-center justify-center rounded-sm text-xs font-bold tabular-nums'
                    : 'text-muted-foreground',
                grade ? colors[grade] : null,
                className,
            )}
        >
            {grade ?? '—'}
        </span>
    );
}
