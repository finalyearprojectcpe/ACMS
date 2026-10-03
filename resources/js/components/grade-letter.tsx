import { cn } from '@/lib/utils';

const colors: Record<string, string> = {
    A: 'bg-green-500/20 text-green-700 dark:text-green-300',
    B: 'bg-green-700/15 text-green-900 dark:text-green-400',
    C: 'bg-yellow-400/25 text-yellow-800 dark:text-yellow-200',
    D: 'bg-amber-600/15 text-amber-900 dark:text-amber-300',
    E: 'bg-red-500/20 text-red-800 dark:text-red-300',
    F: 'bg-red-800/15 text-red-900 dark:text-red-400',
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
