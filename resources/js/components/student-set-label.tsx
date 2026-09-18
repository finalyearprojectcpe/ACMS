import { Badge } from '@/components/ui/badge';
import { shortLevelLabel } from '@/lib/academic';
import { cn } from '@/lib/utils';
import type { DashboardAcademicSession, DashboardLevel } from '@/types';

export type StudentSetTextContext = {
    entrySession: DashboardAcademicSession | null;
    currentLevel: DashboardLevel | null;
    minimumYearsToDegree?: number | null;
    isGraduated?: boolean;
};

type StudentSetLabelProps = StudentSetTextContext & {
    className?: string;
};

export function StudentSetLabel({
    entrySession,
    currentLevel,
    minimumYearsToDegree,
    isGraduated,
    className,
}: StudentSetLabelProps) {
    return (
        <div className={className}>
            <div className="font-medium text-foreground">
                {entrySession === null
                    ? 'No entry set'
                    : `${entrySession.name} Set`}
            </div>
            <Badge
                variant="outline"
                className="mt-1 border-border bg-muted/60 text-muted-foreground"
            >
                {studentSetLevelText(
                    currentLevel,
                    minimumYearsToDegree,
                    isGraduated,
                )}
            </Badge>
        </div>
    );
}

export function StudentSetChoiceLabel({
    entrySession,
    currentLevel,
    minimumYearsToDegree,
    isGraduated,
    meta,
    className,
}: StudentSetLabelProps & {
    meta?: string;
}) {
    return (
        <span
            className={cn(
                'flex min-w-0 flex-1 items-center justify-between gap-3 text-left',
                className,
            )}
        >
            <span className="min-w-0 truncate font-medium text-foreground">
                {studentSetName(entrySession)}
            </span>
            <span className="ml-auto flex shrink-0 items-center gap-2 text-xs">
                {meta ? (
                    <span className="hidden max-w-28 truncate text-muted-foreground sm:inline">
                        {meta}
                    </span>
                ) : null}
                <span className="font-semibold text-muted-foreground">
                    {studentSetLevelText(
                        currentLevel,
                        minimumYearsToDegree,
                        isGraduated,
                    )}
                </span>
            </span>
        </span>
    );
}

export function studentSetName(
    entrySession: DashboardAcademicSession | null,
): string {
    return entrySession === null ? 'No entry set' : `${entrySession.name} Set`;
}

export function studentSetText({
    entrySession,
    currentLevel,
    minimumYearsToDegree,
    isGraduated,
}: StudentSetTextContext): string {
    return `${studentSetName(entrySession)} · ${studentSetLevelText(currentLevel, minimumYearsToDegree, isGraduated)}`;
}

export function studentSetListText(
    sets: StudentSetTextContext[],
    emptyLabel = 'No assigned set',
): string {
    return sets.length === 0
        ? emptyLabel
        : sets.map((set) => studentSetText(set)).join(', ');
}

export function studentSetLevelText(
    currentLevel: DashboardLevel | null,
    minimumYearsToDegree?: number | null,
    isGraduated = false,
): string {
    if (isGraduated) {
        return 'Graduated';
    }

    if (currentLevel === null) {
        return 'Level not set';
    }

    if (
        typeof minimumYearsToDegree === 'number' &&
        currentLevel.yearNumber > minimumYearsToDegree
    ) {
        return 'Graduated';
    }

    return shortLevelLabel(currentLevel.title);
}
