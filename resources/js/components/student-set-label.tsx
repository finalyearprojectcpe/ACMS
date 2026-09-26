import { Badge } from '@/components/ui/badge';
import { shortLevelLabel } from '@/lib/academic';
import { cn } from '@/lib/utils';
import type { DashboardAcademicSession, DashboardLevel } from '@/types';

export type StudentSetTextContext = {
    name?: string;
    entrySession: DashboardAcademicSession | null;
    currentLevel: DashboardLevel | null;
};

type StudentSetLabelProps = StudentSetTextContext & {
    className?: string;
};

export function StudentSetLabel({
    name,
    entrySession,
    currentLevel,
    className,
}: StudentSetLabelProps) {
    return (
        <div className={className}>
            <div className="font-medium text-foreground">
                {entrySession === null
                    ? 'No entry set'
                    : studentSetName(entrySession, name)}
            </div>
            <Badge
                variant="outline"
                className="mt-1 border-border bg-muted/60 text-muted-foreground"
            >
                {studentSetLevelText(currentLevel)}
            </Badge>
        </div>
    );
}

export function StudentSetChoiceLabel({
    name,
    entrySession,
    currentLevel,
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
                {studentSetName(entrySession, name)}
            </span>
            <span className="ml-auto flex shrink-0 items-center gap-2 text-xs">
                {meta ? (
                    <span className="hidden max-w-28 truncate text-muted-foreground sm:inline">
                        {meta}
                    </span>
                ) : null}
                <span className="font-semibold text-muted-foreground">
                    {studentSetLevelText(currentLevel)}
                </span>
            </span>
        </span>
    );
}

export function studentSetName(
    entrySession: DashboardAcademicSession | null,
    name?: string,
): string {
    if (name) {
        return name;
    }

    return entrySession === null ? 'No entry set' : entrySession.name;
}

export function studentSetText({
    name,
    entrySession,
    currentLevel,
}: StudentSetTextContext): string {
    return `${studentSetName(entrySession, name)} · ${studentSetLevelText(currentLevel)}`;
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
): string {
    if (currentLevel === null) {
        return 'Level not set';
    }

    return shortLevelLabel(currentLevel.title);
}
