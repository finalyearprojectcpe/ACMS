import { cn } from '@/lib/utils';

type QualifiedSemesterProps = {
    level: string;
    semester: string;
    className?: string;
};

export function QualifiedSemester({
    level,
    semester,
    className,
}: QualifiedSemesterProps) {
    return (
        <span className={cn(className)}>
            {qualifiedSemesterLabel(level, semester)}
        </span>
    );
}

export function qualifiedSemesterLabel(level: string, semester: string): string {
    return `${shortLevel(level)} ${semesterName(semester)}`;
}

function shortLevel(level: string): string {
    return level.replace(/\s*Level$/i, 'L');
}

function semesterName(semester: string): string {
    if (/semester$/i.test(semester)) {
        return semester;
    }

    return `${semester} Semester`;
}
