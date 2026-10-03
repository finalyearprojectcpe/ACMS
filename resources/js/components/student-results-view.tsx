import { Link } from '@inertiajs/react';
import { Fragment } from 'react';

import { section as dashboardSection } from '@/actions/App/Http/Controllers/DashboardController';
import { AcademicPeriodLayout } from '@/components/academic-period-layout';
import { AcademicPeriodNavigation } from '@/components/academic-period-navigation';
import { DownloadButton } from '@/components/download-button';
import { GradeLetter } from '@/components/grade-letter';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyTitle,
} from '@/components/ui/empty';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { courseTone, courseToneClasses } from '@/lib/course-presentation';
import { cn } from '@/lib/utils';
import { pdf as semesterResultsPdf } from '@/routes/dashboard/student-results';
import type { DashboardStudentResults } from '@/types';

export function StudentResultsView({
    results,
    overviewUrl,
    allowDownload = false,
}: {
    results: DashboardStudentResults;
    overviewUrl?: string;
    allowDownload?: boolean;
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
    const canDownload =
        allowDownload &&
        selected &&
        !selected.locked &&
        selected.courses.some((course) => !course.pending);
    const DetailHeading = overviewUrl ? 'h1' : 'h2';
    const currentKey = selected
        ? `${selected.sessionYears}:${selected.semester}`
        : 'overview';
    const grouped = Object.groupBy(
        results.periods,
        (period) => period.sessionYears,
    );

    return (
        <AcademicPeriodLayout
            className="md:min-h-[min(65dvh,42rem)]"
            navigation={(collapsed) => (
                <AcademicPeriodNavigation
                    collapsed={collapsed}
                    ariaLabel="Semester results"
                    overview={
                        overviewUrl
                            ? {
                                  key: 'overview',
                                  href: overviewUrl,
                                  label: 'Overview',
                                  compactLabel: 'All',
                                  selected: currentKey === 'overview',
                              }
                            : undefined
                    }
                    groups={Object.entries(grouped).map(
                        ([sessionYears, periods]) => ({
                            key: sessionYears,
                            label: periods?.[0].level ?? '',
                            detail: `${periods?.[0].session ?? ''} Session`,
                            compactLabel: (periods?.[0].level ?? '').replace(
                                /\s*Level$/i,
                                'L',
                            ),
                            links: (periods ?? []).map((period) => ({
                                key: `${period.sessionYears}:${period.semester}`,
                                href: period.url,
                                label:
                                    period.semester === 'first'
                                        ? '1st Semester'
                                        : '2nd Semester',
                                compactLabel:
                                    period.semester === 'first' ? '1st' : '2nd',
                                selected:
                                    `${period.sessionYears}:${period.semester}` ===
                                    currentKey,
                                locked: period.locked,
                            })),
                        }),
                    )}
                />
            )}
        >
            <section className="min-w-0 flex-1 py-5 md:pl-6">
                {selected ? (
                    <div className="mx-auto max-w-2xl min-w-0">
                        <header className="mb-5 flex flex-wrap items-start justify-between gap-4">
                            <div className="min-w-0">
                                <DetailHeading className="text-lg font-semibold">
                                    {selected.label}
                                </DetailHeading>
                            </div>
                            {!selected.locked ? (
                                <div className="flex flex-wrap items-center justify-end gap-4">
                                    <div className="text-right">
                                        <div className="text-xs text-muted-foreground">
                                            Semester GPA
                                        </div>
                                        <strong className="text-xl tabular-nums">
                                            {selected.gpa?.toFixed(2) ??
                                                'Pending'}
                                        </strong>
                                    </div>
                                </div>
                            ) : null}
                        </header>
                        {selected.locked ? (
                            <Empty className="border-y py-12">
                                <EmptyHeader>
                                    <EmptyTitle>Results locked</EmptyTitle>
                                    <EmptyDescription>
                                        {selected.unpaidDueCount}{' '}
                                        {selected.unpaidDueCount === 1
                                            ? 'due'
                                            : 'dues'}{' '}
                                        for this period must be paid or waived
                                        before you can view these results.
                                    </EmptyDescription>
                                </EmptyHeader>
                                <Button asChild>
                                    <Link href={dashboardSection('dues').url}>
                                        View Dues
                                    </Link>
                                </Button>
                            </Empty>
                        ) : (
                            <>
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
                                                <TableRow
                                                    key={course.code}
                                                    className={
                                                        courseToneClasses.row[
                                                            courseTone(
                                                                course.assignmentOrigin,
                                                                course.isElective,
                                                            )
                                                        ]
                                                    }
                                                >
                                                    <TableCell
                                                        className={cn(
                                                            'pl-3 font-medium whitespace-nowrap',
                                                            courseToneClasses
                                                                .marker[
                                                                courseTone(
                                                                    course.assignmentOrigin,
                                                                    course.isElective,
                                                                )
                                                            ],
                                                        )}
                                                        title={
                                                            course.assignmentOrigin ===
                                                            'carryover'
                                                                ? 'Carryover'
                                                                : undefined
                                                        }
                                                    >
                                                        {course.assignmentOrigin ===
                                                        'carryover' ? (
                                                            <span className="sr-only">
                                                                Carryover:{' '}
                                                            </span>
                                                        ) : null}
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
                                                                grade={
                                                                    course.grade
                                                                }
                                                            />
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                    <ScrollBar orientation="horizontal" />
                                </ScrollArea>
                                {canDownload && (
                                    <div className="flex justify-end pt-4">
                                        <DownloadButton
                                            href={semesterResultsPdf.url({
                                                sessionYears:
                                                    selected.sessionYears,
                                                semester: selected.semester,
                                            })}
                                        >
                                            Download as PDF
                                        </DownloadButton>
                                    </div>
                                )}
                            </>
                        )}
                    </div>
                ) : overviewUrl ? (
                    <StudentAcademicHistory history={results.history} />
                ) : null}
            </section>
        </AcademicPeriodLayout>
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
                                                {period.locked
                                                    ? 'Locked'
                                                    : (period.gpa?.toFixed(2) ??
                                                      'Pending')}
                                            </TableCell>
                                            <TableCell className="pr-3 text-right font-medium tabular-nums">
                                                {period.locked
                                                    ? 'Locked'
                                                    : (period.cgpa?.toFixed(
                                                          2,
                                                      ) ?? '—')}
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
