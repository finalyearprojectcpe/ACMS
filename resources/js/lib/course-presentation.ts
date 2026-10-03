export type CourseOrigin = 'scheduled' | 'carryover' | 'deferred';
export type CourseTone = 'normal' | 'elective' | 'carryover' | 'deferred';

export function courseTone(
    origin: CourseOrigin,
    isElective: boolean,
): CourseTone {
    return origin === 'scheduled'
        ? isElective
            ? 'elective'
            : 'normal'
        : origin;
}

export const courseToneClasses = {
    row: {
        elective:
            'bg-amber-400/10 hover:bg-amber-400/15 dark:bg-amber-300/10 dark:hover:bg-amber-300/15',
        carryover: '',
        deferred: '',
        normal: '',
    },
    header: {
        elective: 'bg-amber-400/15 dark:bg-amber-300/15',
        carryover: 'bg-red-500/15 dark:bg-red-400/15',
        deferred: '',
        normal: '',
    },
    cell: {
        elective:
            'bg-amber-400/10 group-hover:bg-amber-400/15 dark:bg-amber-300/10 dark:group-hover:bg-amber-300/15',
        carryover:
            'bg-red-500/10 group-hover:bg-red-500/15 dark:bg-red-400/10 dark:group-hover:bg-red-400/15',
        deferred: '',
        normal: '',
    },
    marker: {
        elective: '',
        carryover: 'border-l-2 border-l-red-500/60 dark:border-l-red-400/60',
        deferred: '',
        normal: '',
    },
} as const;
