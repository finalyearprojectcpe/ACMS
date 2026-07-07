import { useState } from 'react';

import { GradeDisplay } from '@/components/grade-display';
import { PersonCell } from '@/components/person-cell';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import type { DashboardResultSheet, DashboardResultSheetGrade } from '@/types';

type ResultMatrixTableProps = {
    sheet: DashboardResultSheet;
    className?: string;
};

export function ResultMatrixTable({
    sheet,
    className,
}: ResultMatrixTableProps) {
    const carryoverCourseIds = new Set(
        sheet.courses
            .filter((course) => course.isCarryover)
            .map((course) => course.id),
    );

    return (
        <div
            className={cn(
                'overflow-hidden border border-border bg-background shadow-sm ring-1 ring-border/70',
                className,
            )}
        >
            <ScrollArea className="w-full">
                <Table className="min-w-max text-xs">
                    <TableHeader>
                        <TableRow>
                            <TableHead className="sticky top-0 left-0 z-40 w-72 bg-background px-4 shadow-[1px_0_0_var(--border)]">
                                Student
                            </TableHead>
                            {sheet.courses.map((course) => (
                                <TableHead
                                    key={course.id}
                                    className={cn(
                                        'top-0 z-30 w-24 text-center',
                                        course.isCarryover
                                            ? 'bg-red-50 dark:bg-red-950/30'
                                            : 'bg-background',
                                    )}
                                >
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <div className="inline-flex cursor-help flex-col items-center">
                                                <span
                                                    className={cn(
                                                        'font-semibold',
                                                        course.isCarryover
                                                            ? 'text-red-900 dark:text-red-100'
                                                            : 'text-foreground',
                                                    )}
                                                >
                                                    {course.code}
                                                </span>
                                                <span
                                                    className={cn(
                                                        'text-[10px] font-medium',
                                                        course.isCarryover
                                                            ? 'text-red-700/80 dark:text-red-200/80'
                                                            : 'text-muted-foreground',
                                                    )}
                                                >
                                                    {course.creditUnits}{' '}
                                                    {course.creditUnits === 1
                                                        ? 'unit'
                                                        : 'units'}
                                                </span>
                                            </div>
                                        </TooltipTrigger>
                                        <TooltipContent className="max-w-xs">
                                            <div>{course.title}</div>
                                            {course.isCarryover ? (
                                                <div className="text-xs text-muted-foreground">
                                                    Carryover course
                                                </div>
                                            ) : null}
                                        </TooltipContent>
                                    </Tooltip>
                                </TableHead>
                            ))}
                            <TableHead className="sticky top-0 right-0 z-40 w-20 bg-background pr-4 text-center shadow-[-1px_0_0_var(--border)]">
                                GPA
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {sheet.rows.length > 0 ? (
                            sheet.rows.map((row) => (
                                <TableRow
                                    key={row.id}
                                    className="odd:bg-muted/25"
                                >
                                    <TableCell className="sticky left-0 z-20 w-72 bg-inherit px-4 shadow-[1px_0_0_var(--border)]">
                                        <div className="flex min-w-0 items-center gap-3">
                                            <span className="w-7 shrink-0 text-right text-xs font-medium text-muted-foreground tabular-nums">
                                                {row.serialNumber}
                                            </span>
                                            <PersonCell
                                                name={row.student.name}
                                                avatar={row.student.avatar}
                                                secondary={`Reg No. ${row.student.registrationNumber}`}
                                                className="min-w-0 flex-1"
                                            />
                                        </div>
                                    </TableCell>
                                    {row.grades.map((grade) => {
                                        const isCarryover =
                                            carryoverCourseIds.has(
                                                grade.courseId,
                                            );

                                        return (
                                            <TableCell
                                                key={`${row.id}-${grade.courseId}`}
                                                className={cn(
                                                    'w-24 text-center',
                                                    isCarryover &&
                                                        'bg-red-50/55 dark:bg-red-950/20',
                                                )}
                                            >
                                                <GradeCell grade={grade} />
                                            </TableCell>
                                        );
                                    })}
                                    <TableCell className="sticky right-0 z-20 w-20 bg-inherit pr-4 text-center font-semibold tabular-nums shadow-[-1px_0_0_var(--border)]">
                                        {row.gpa}
                                    </TableCell>
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell
                                    colSpan={sheet.courses.length + 2}
                                    className="px-4 py-8 text-muted-foreground"
                                >
                                    No results have been added to this sheet.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
                <ScrollBar orientation="horizontal" />
            </ScrollArea>
            <CourseKey sheet={sheet} />
        </div>
    );
}

function GradeCell({ grade }: { grade: DashboardResultSheetGrade }) {
    if (grade.grade === null) {
        return (
            <span className="inline-flex h-7 w-9 items-center justify-center text-xs font-medium text-muted-foreground">
                -
            </span>
        );
    }

    return (
        <span className="inline-flex h-7 w-8 items-center justify-center text-sm">
            <GradeDisplay grade={grade.grade} />
        </span>
    );
}

function CourseKey({ sheet }: { sheet: DashboardResultSheet }) {
    const [open, setOpen] = useState(false);

    if (sheet.courses.length === 0) {
        return null;
    }

    return (
        <Collapsible
            open={open}
            onOpenChange={setOpen}
            className="border-t border-border"
        >
            <div className="flex items-center justify-between gap-3 px-4 py-3">
                <div className="text-xs font-medium text-muted-foreground">
                    Course Key
                </div>
                <CollapsibleTrigger className="text-sm font-semibold text-link underline-offset-4 hover:underline">
                    {open ? 'Hide Course Titles' : 'Show Course Titles'}
                </CollapsibleTrigger>
            </div>
            <CollapsibleContent>
                <div className="grid gap-2 px-4 pb-4 text-xs sm:grid-cols-2 xl:grid-cols-3">
                    {sheet.courses.map((course) => (
                        <div key={course.id} className="min-w-0">
                            <span className="font-semibold text-foreground">
                                {course.code}
                            </span>
                            <span className="text-muted-foreground">
                                {' '}
                                · {course.title} · {course.creditUnits}{' '}
                                {course.creditUnits === 1 ? 'unit' : 'units'}
                            </span>
                            {course.isCarryover ? (
                                <span className="font-medium text-red-700 dark:text-red-300">
                                    {' '}
                                    · Carryover
                                </span>
                            ) : null}
                        </div>
                    ))}
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}
