import { qualifiedSemesterLabel } from '@/lib/academic';
import { cn } from '@/lib/utils';

export { qualifiedSemesterLabel } from '@/lib/academic';

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
