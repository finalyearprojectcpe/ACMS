import { Badge } from '@/components/ui/badge';
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

export function studentSetText({
    entrySession,
    currentLevel,
    minimumYearsToDegree,
}: {
    entrySession: DashboardAcademicSession | null;
    currentLevel: DashboardLevel | null;
    minimumYearsToDegree?: number | null;
}): string {
    const setName =
        entrySession === null ? 'No entry set' : `${entrySession.name} Set`;

    return `${setName} · ${studentSetLevelText(currentLevel, minimumYearsToDegree)}`;
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
