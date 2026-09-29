import { Link, router } from '@inertiajs/react';
import { Fragment } from 'react';

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
    overviewUrl,
}: {
    results: DashboardStudentResults;
    overviewUrl?: string;
}) {
    if (results.periods.length === 0) {
        return (
            <Empty className="border-y py-14">
                <EmptyHeader>
                    <EmptyTitle>No published results yet</EmptyTitle>
                    <EmptyDescription>
                        Results appear as your course adviser publishes each
                        subject.
                    </EmptyDescription>
                </EmptyHeader>
            </Empty>
        );
    }

    const selected = results.selected;
    const DetailHeading = overviewUrl ? 'h1' : 'h2';
    const currentKey = selected
        ? `${selected.sessionYears}:${selected.semester}`
        : 'overview';
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
                            if (value === 'overview' && overviewUrl) {
                                router.visit(overviewUrl);

                                return;
                            }

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
                            {overviewUrl ? (
                                <SelectItem value="overview">
                                    Overview
                                </SelectItem>
                            ) : null}
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
                        {overviewUrl ? (
                            <Link
                                href={overviewUrl}
                                aria-current={
                                    currentKey === 'overview'
                                        ? 'page'
                                        : undefined
                                }
                                className={`${levelSemesterRailItemClassName(currentKey === 'overview')} before:hidden`}
                            >
                                Overview
                            </Link>
                        ) : null}
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
                                <DetailHeading className="text-lg font-semibold">
                                    {selected.label}
                                </DetailHeading>
                            </div>
                            <div className="text-right">
                                <div className="text-xs text-muted-foreground">
                                    Semester GPA
                                </div>
                                <strong className="text-xl tabular-nums">
                                    {selected.gpa?.toFixed(2) ?? 'Pending'}
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
                                                {course.pending ? (
                                                    <span className="text-xs text-muted-foreground">
                                                        Awaiting result
                                                    </span>
                                                ) : course.missingScript ? (
                                                    <span className="text-xs font-medium text-amber-700 dark:text-amber-300">
                                                        Missing script
                                                    </span>
                                                ) : (
                                                    <GradeLetter
                                                        grade={course.grade}
                                                    />
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                            <ScrollBar orientation="horizontal" />
                        </ScrollArea>
                    </div>
                ) : overviewUrl ? (
                    <StudentAcademicHistory history={results.history} />
                ) : null}
            </section>
        </div>
    );
}

function StudentAcademicHistory({
    history,
}: {
    history: DashboardStudentResults['history'];
}) {
    const sessions = Object.groupBy(history, (period) => period.sessionYears);

    return (
        <div className="mx-auto max-w-2xl min-w-0">
            <h1 className="mb-4 text-lg font-semibold">Academic History</h1>
            <ScrollArea className="w-full border-y">
                <Table className="min-w-[320px] text-sm">
                    <TableHeader>
                        <TableRow>
                            <TableHead className="pl-3">Semester</TableHead>
                            <TableHead className="w-20 text-right">
                                GPA
                            </TableHead>
                            <TableHead className="w-28 pr-3 text-right">
                                Official CGPA
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {Object.entries(sessions).map(
                            ([sessionYears, periods]) => (
                                <Fragment key={sessionYears}>
                                    <TableRow>
                                        <TableCell
                                            colSpan={3}
                                            className="bg-muted/30 px-3 py-2 font-semibold"
                                        >
                                            {periods?.[0].level} ·{' '}
                                            {periods?.[0].session} Session
                                        </TableCell>
                                    </TableRow>
                                    {periods?.map((period) => (
                                        <TableRow key={period.url}>
                                            <TableCell className="pl-3">
                                                <Link
                                                    href={period.url}
                                                    aria-label={`View ${period.level} ${period.semester} result for ${period.session} Session`}
                                                    className="font-medium text-link hover:underline"
                                                >
                                                    {period.semester}
                                                </Link>
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {period.gpa?.toFixed(2) ??
                                                    'Pending'}
                                            </TableCell>
                                            <TableCell className="pr-3 text-right font-medium tabular-nums">
                                                {period.cgpa?.toFixed(2) ?? '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </Fragment>
                            ),
                        )}
                    </TableBody>
                </Table>
                <ScrollBar orientation="horizontal" />
            </ScrollArea>
        </div>
    );
}
