import { cn } from '@/lib/utils';

type GradeDisplayProps = {
    grade: string | null;
};

const gradeClass: Record<string, string> = {
    A: 'text-emerald-700 dark:text-emerald-300',
    B: 'text-lime-700 dark:text-lime-300',
    C: 'text-yellow-700 dark:text-yellow-300',
    D: 'text-amber-700 dark:text-amber-300',
    E: 'text-orange-700 dark:text-orange-300',
    F: 'text-red-700 dark:text-red-300',
};

export function GradeDisplay({ grade }: GradeDisplayProps) {
    if (grade === null) {
        return <span className="text-muted-foreground">N/A</span>;
    }

    return (
        <span
            className={cn(
                'inline-flex items-center justify-center gap-1 font-semibold tabular-nums',
                gradeClass[grade] ?? 'text-foreground',
            )}
        >
            <span>{grade}</span>
        </span>
    );
}

export function gradeTextClass(grade: string | null): string {
    return grade === null
        ? 'text-muted-foreground'
        : (gradeClass[grade] ?? 'text-foreground');
}
