import { memo } from 'react';

import { GradeLetter } from '@/components/grade-letter';
import { PersonCell } from '@/components/person-cell';
import { Badge } from '@/components/ui/badge';
import { TableCell, TableRow } from '@/components/ui/table';
import { courseToneClasses } from '@/lib/course-presentation';
import type { DashboardAdviserSetResults } from '@/types';

type Columns = DashboardAdviserSetResults['columns'];
type Student = DashboardAdviserSetResults['students'][number];

export function columnBoundary(columns: Columns, index: number): string {
    const current = columns[index];
    const next = columns[index + 1];

    if (!next || current.session !== next.session) {
        return 'border-r-[3px] border-r-link/35 dark:border-r-link/45';
    }

    return current.group !== next.group
        ? 'border-r-2 border-r-border'
        : 'border-r border-r-border/60';
}

function Standing({ label }: { label: string | null }) {
    if (!label) {
        return <span className="text-muted-foreground">—</span>;
    }

    const shortLabel =
        {
            'First Class': '1st',
            'Second Class Upper': '2:1',
            'Second Class Lower': '2:2',
            'Third Class': '3rd',
            Pass: 'Pass',
            'Below Pass Band': 'Below',
        }[label] ?? label;
    const tone =
        label === 'First Class'
            ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
            : label === 'Second Class Upper'
              ? 'bg-green-700/10 text-green-800 dark:text-green-300'
              : label === 'Second Class Lower'
                ? 'bg-amber-500/10 text-amber-800 dark:text-amber-300'
                : label === 'Third Class' || label === 'Pass'
                  ? 'bg-orange-500/10 text-orange-700 dark:text-orange-300'
                  : 'bg-red-500/10 text-red-700 dark:text-red-300';

    return (
        <Badge className={`${tone} cursor-help`} title={label}>
            <span aria-hidden="true">{shortLabel}</span>
            <span className="sr-only">{label}</span>
        </Badge>
    );
}

function Remarks({ label }: { label: string | null }) {
    if (!label) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <Badge
            className={
                label === 'Pass'
                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                    : 'bg-red-500/10 text-red-700 dark:text-red-300'
            }
            title={
                label === 'Pass'
                    ? 'All results published with no failed courses'
                    : `${label.slice(0, -1)} failed courses`
            }
        >
            {label}
        </Badge>
    );
}

export const SetResultRow = memo(function SetResultRow({
    student,
    columns,
    index,
    measureRow,
    number,
}: {
    student: Student;
    columns: Columns;
    index: number;
    measureRow: (element: HTMLTableRowElement | null) => void;
    number: number;
}) {
    const isShaded = index % 2 === 1;
    const identityBackground = isShaded
        ? 'bg-[color-mix(in_oklch,var(--muted)_20%,var(--background))]'
        : 'bg-background';

    return (
        <TableRow
            data-result-row
            data-index={index}
            ref={measureRow}
            className={`group ${isShaded ? 'bg-muted/20' : 'bg-background'}`}
        >
            <TableCell
                className={`sticky left-0 z-10 w-12 min-w-12 border-r px-1 text-center text-xs text-muted-foreground tabular-nums group-hover:bg-[color-mix(in_oklch,var(--muted)_50%,var(--background))] ${identityBackground}`}
            >
                {number}
            </TableCell>
            <TableCell
                className={`sticky left-12 z-10 w-40 min-w-40 border-r-[3px] border-r-foreground/30 px-2 group-hover:bg-[color-mix(in_oklch,var(--muted)_50%,var(--background))] md:w-56 md:min-w-56 md:px-3 ${identityBackground}`}
            >
                <PersonCell
                    name={student.name}
                    avatar={student.avatar}
                    secondary={student.registrationNumber}
                    href={student.profileUrl ?? undefined}
                />
            </TableCell>
            {columns.map((column, columnIndex) => {
                const cell = student.cells[column.id];
                const stateTitle =
                    cell?.state === 'missing'
                        ? 'Missing script'
                        : cell?.state === 'unpublished'
                          ? 'Grade not published'
                          : cell?.state === 'awaiting'
                            ? 'Awaiting grade'
                            : undefined;
                const isCarryover = cell?.assignmentOrigin === 'carryover';
                const tone =
                    isCarryover || column.carryoverOnly
                        ? 'carryover'
                        : column.isElective
                          ? 'elective'
                          : 'normal';
                const title = [
                    isCarryover ? 'Carryover' : undefined,
                    stateTitle,
                ]
                    .filter(Boolean)
                    .join(' · ');

                return (
                    <TableCell
                        key={column.id}
                        className={`text-center ${columnBoundary(columns, columnIndex)} ${courseToneClasses.cell[tone]}`}
                        title={title || undefined}
                    >
                        {isCarryover ? (
                            <span className="sr-only">Carryover: </span>
                        ) : null}
                        {!cell ? (
                            <span className="text-muted-foreground/60">—</span>
                        ) : cell.state === 'missing' ? (
                            <span className="text-xs font-medium text-destructive">
                                MS
                            </span>
                        ) : cell.state === 'published' ? (
                            <GradeLetter grade={cell.grade} />
                        ) : cell.state === 'unpublished' ? (
                            <span
                                className="font-semibold text-muted-foreground"
                                aria-label="Result entered but not published"
                            >
                                *
                            </span>
                        ) : (
                            <span
                                className="text-muted-foreground"
                                aria-label="Awaiting result"
                            >
                                ·
                            </span>
                        )}
                    </TableCell>
                );
            })}
            <TableCell className="text-center tabular-nums">
                {student.totalGradePoints}
            </TableCell>
            <TableCell className="text-center tabular-nums">
                {student.totalNumberOfUnits}
            </TableCell>
            <TableCell className="w-20 min-w-20 text-center tabular-nums">
                {student.gpa?.toFixed(2) ?? '—'}
            </TableCell>
            <TableCell className="w-20 min-w-20 text-center font-semibold tabular-nums">
                {student.cgpa?.toFixed(2) ?? '—'}
            </TableCell>
            <TableCell
                className={`w-[4.75rem] min-w-[4.75rem] px-1 text-center group-hover:bg-[color-mix(in_oklch,var(--muted)_50%,var(--background))] lg:sticky lg:right-[4.75rem] lg:z-10 lg:border-l-[3px] lg:border-l-foreground/30 ${identityBackground}`}
            >
                <Remarks label={student.remarks} />
            </TableCell>
            <TableCell
                className={`w-[4.75rem] min-w-[4.75rem] px-1 text-center group-hover:bg-[color-mix(in_oklch,var(--muted)_50%,var(--background))] lg:sticky lg:right-0 lg:z-10 ${identityBackground}`}
            >
                <Standing label={student.standing} />
            </TableCell>
        </TableRow>
    );
});
