import { Link, router } from '@inertiajs/react';

import { GradeLetter } from '@/components/grade-letter';
import {
    LevelSemesterRail,
    LevelSemesterRailGroup,
    levelSemesterRailItemClassName,
} from '@/components/level-semester-rail';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyTitle,
} from '@/components/ui/empty';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { DashboardStudentResults } from '@/types';

export function StudentResultsView({
    results,
}: {
    results: DashboardStudentResults;
}) {
    if (results.periods.length === 0) {
        return (
            <Empty className="border-y py-14">
                <EmptyHeader>
                    <EmptyTitle>No published results yet</EmptyTitle>
                    <EmptyDescription>
                        Results appear after the course adviser publishes a
                        semester.
                    </EmptyDescription>
                </EmptyHeader>
            </Empty>
        );
    }

    const selected = results.selected;
    const currentKey = selected
        ? `${selected.sessionYears}:${selected.semester}`
        : '';
    const grouped = Object.groupBy(results.periods, (period) =>
        period.shortLabel.slice(0, 4),
    );

    return (
        <div className="grid min-h-0 min-w-0 flex-1 grid-cols-1 content-start md:grid-cols-[14rem_minmax(0,1fr)] md:content-normal">
            <div className="border-b md:border-r md:border-b-0">
                <div className="p-4 md:hidden">
                    <Select
                        value={currentKey}
                        onValueChange={(value) => {
                            const period = results.periods.find(
                                (item) =>
                                    `${item.sessionYears}:${item.semester}` ===
                                    value,
                            );

                            if (period) {
                                router.visit(period.url);
                            }
                        }}
                    >
                        <SelectTrigger aria-label="Semester result">
                            <SelectValue placeholder="Choose a semester" />
                        </SelectTrigger>
                        <SelectContent>
                            {results.periods.map((period) => (
                                <SelectItem
                                    key={period.url}
                                    value={`${period.sessionYears}:${period.semester}`}
                                >
                                    {period.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <ScrollArea className="hidden max-h-[min(65dvh,42rem)] md:block">
                    <LevelSemesterRail>
                        {Object.entries(grouped).map(([level, periods]) => (
                            <LevelSemesterRailGroup key={level} label={level}>
                                {periods?.map((period) => (
                                    <Link
                                        key={period.url}
                                        href={period.url}
                                        aria-current={
                                            `${period.sessionYears}:${period.semester}` ===
                                            currentKey
                                                ? 'page'
                                                : undefined
                                        }
                                        className={levelSemesterRailItemClassName(
                                            `${period.sessionYears}:${period.semester}` ===
                                                currentKey,
                                        )}
                                    >
                                        {period.semester === 'first'
                                            ? '1st Semester'
                                            : '2nd Semester'}
                                    </Link>
                                ))}
                            </LevelSemesterRailGroup>
                        ))}
                    </LevelSemesterRail>
                </ScrollArea>
            </div>
            <section className="min-w-0 py-5 md:pl-6">
                {selected ? (
                    <div className="mx-auto max-w-xl min-w-0">
                        <header className="mb-5 grid grid-cols-[minmax(0,1fr)_auto] items-start gap-4">
                            <div className="min-w-0">
                                <h2 className="text-lg font-semibold">
                                    {selected.label}
                                </h2>
                            </div>
                            <div className="text-right">
                                <div className="text-xs text-muted-foreground">
                                    GPA
                                </div>
                                <strong className="text-xl tabular-nums">
                                    {selected.gpa?.toFixed(2) ?? '—'}
                                </strong>
                            </div>
                        </header>
                        <ScrollArea className="w-full border-y">
                            <Table className="min-w-[460px]">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="pl-3">
                                            Course
                                        </TableHead>
                                        <TableHead>Title</TableHead>
                                        <TableHead className="text-right">
                                            Units
                                        </TableHead>
                                        <TableHead className="pr-3 text-right">
                                            Grade
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {selected.courses.map((course) => (
                                        <TableRow key={course.code}>
                                            <TableCell className="pl-3 font-medium whitespace-nowrap">
                                                {course.code}
                                            </TableCell>
                                            <TableCell>
                                                {course.title}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {course.units}
                                            </TableCell>
                                            <TableCell className="pr-3 text-right">
                                                <GradeLetter
                                                    grade={course.grade}
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                            <ScrollBar orientation="horizontal" />
                        </ScrollArea>
                    </div>
                ) : null}
            </section>
        </div>
    );
}
