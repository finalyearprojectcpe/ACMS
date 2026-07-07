import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { DashboardAcademicSession, DashboardLevel } from '@/types';

type StudentSetLabelProps = {
    entrySession: DashboardAcademicSession | null;
    currentLevel: DashboardLevel | null;
    minimumYearsToDegree?: number | null;
    className?: string;
};

export function StudentSetLabel({
    entrySession,
    currentLevel,
    minimumYearsToDegree,
    className,
}: StudentSetLabelProps) {
    return (
        <div className={className}>
            <div className="font-medium text-foreground">
                {entrySession === null ? 'No entry set' : `${entrySession.name} Set`}
            </div>
            <Badge
                variant="outline"
                className="mt-1 border-border bg-muted/60 text-muted-foreground"
            >
                {studentSetLevelText(currentLevel, minimumYearsToDegree)}
            </Badge>
        </div>
    );
}

export function StudentSetChoiceLabel({
    entrySession,
    currentLevel,
    minimumYearsToDegree,
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
                    {studentSetLevelText(currentLevel, minimumYearsToDegree)}
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
}: {
    entrySession: DashboardAcademicSession | null;
    currentLevel: DashboardLevel | null;
    minimumYearsToDegree?: number | null;
}): string {
    return `${studentSetName(entrySession)} · ${studentSetLevelText(currentLevel, minimumYearsToDegree)}`;
}

export function studentSetLevelText(
    currentLevel: DashboardLevel | null,
    minimumYearsToDegree?: number | null,
): string {
    if (currentLevel === null) {
        return 'Level not set';
    }

    if (
        typeof minimumYearsToDegree === 'number' &&
        currentLevel.id > minimumYearsToDegree
    ) {
        return 'Graduated';
    }

    return shortLevelTitle(currentLevel.title);
}

function shortLevelTitle(level: string): string {
    const match = level.match(/^(\d+)00 Level$/);

    if (match) {
        return `${match[1]}00L`;
    }

    return level.replace(' Level', 'L');
}
