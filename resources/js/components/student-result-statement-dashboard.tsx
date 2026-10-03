import { ActionLink } from '@/components/action-link';
import { CopyableValue } from '@/components/copyable-value';
import { DownloadButton } from '@/components/download-button';
import { GradeLetter } from '@/components/grade-letter';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { show as showStudent } from '@/routes/dashboard/students';
import { download } from '@/routes/dashboard/students/statement';
import type { DashboardResultStatement } from '@/types';

export function StudentResultStatementDashboard({
    statement,
}: {
    statement: DashboardResultStatement;
}) {
    return (
        <div className="mx-auto flex w-full max-w-4xl flex-col gap-7">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <ActionLink
                    href={showStudent(statement.student.registrationRouteValue)}
                >
                    Go to Student Profile
                </ActionLink>
                {statement.periods.length > 0 ? (
                    <DownloadButton
                        variant="default"
                        size="default"
                        href={
                            download(statement.student.registrationRouteValue)
                                .url
                        }
                    >
                        Download as DOCX
                    </DownloadButton>
                ) : null}
            </div>
            <header className="border-b border-border pb-6">
                <div className="flex flex-wrap items-baseline justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-semibold sm:text-2xl">
                            Statement of Results for {statement.student.name}
                        </h1>
                    </div>
                </div>
                <div className="mt-6 grid gap-x-8 gap-y-4 text-sm sm:grid-cols-2">
                    <div>
                        <p className="text-xs text-muted-foreground">
                            Registration number
                        </p>
                        <CopyableValue
                            value={statement.student.registrationNumber}
                            className="mt-1"
                        />
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">
                            Student set
                        </p>
                        <p className="mt-1">{statement.student.set}</p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">
                            Course adviser
                        </p>
                        <p className="mt-1">{statement.student.adviser}</p>
                    </div>
                    <div>
                        <p className="text-xs text-muted-foreground">As of</p>
                        <p className="mt-1">{statement.asOf}</p>
                    </div>
                </div>
            </header>
            {statement.periods.length === 0 ? (
                <div className="border-y border-border py-12 text-center text-sm text-muted-foreground">
                    No results have been published for this student yet.
                </div>
            ) : (
                statement.periods.map((period) => (
                    <section
                        key={`${period.session}-${period.semester}`}
                        className="min-w-0"
                    >
                        <div className="mb-3 flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                            <h2 className="text-base font-semibold">
                                {period.level} · {period.semester}
                            </h2>
                            <span className="text-xs text-muted-foreground">
                                {period.session}
                            </span>
                        </div>
                        <ScrollArea className="w-full border-y border-border">
                            <Table className="min-w-[570px] text-sm">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-32">
                                            Course
                                        </TableHead>
                                        <TableHead>Course title</TableHead>
                                        <TableHead className="w-20 text-right">
                                            Units
                                        </TableHead>
                                        <TableHead className="w-28 text-center">
                                            Grade
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {period.courses.map((course) => (
                                        <TableRow key={course.code}>
                                            <TableCell className="font-medium">
                                                {course.code}
                                            </TableCell>
                                            <TableCell>
                                                {course.title}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
                                                {course.units}
                                            </TableCell>
                                            <TableCell className="text-center font-medium">
                                                {course.grade.length === 1 ? (
                                                    <GradeLetter
                                                        grade={course.grade}
                                                    />
                                                ) : (
                                                    course.grade
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                    <TableRow className="bg-muted/30 font-medium">
                                        <TableCell colSpan={2}>
                                            Total Units
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {period.totalUnits}
                                        </TableCell>
                                        <TableCell />
                                    </TableRow>
                                </TableBody>
                            </Table>
                            <ScrollBar orientation="horizontal" />
                        </ScrollArea>
                        <div className="mt-2 flex flex-wrap items-center justify-between gap-2 text-sm">
                            <p className="text-muted-foreground">
                                {period.awaitingPublication > 0
                                    ? `${period.awaitingPublication} course ${period.awaitingPublication === 1 ? 'result' : 'results'} awaiting publication`
                                    : ''}
                            </p>
                            <p className="font-medium tabular-nums">
                                Semester GPA:{' '}
                                {period.gpa?.toFixed(2) ?? 'Not available'}
                            </p>
                        </div>
                    </section>
                ))
            )}
            {statement.periods.length > 0 ? (
                <div className="border-t border-border pt-5 text-right text-sm font-semibold tabular-nums">
                    Cumulative GPA:{' '}
                    {statement.cgpa?.toFixed(2) ?? 'Not available'}
                </div>
            ) : null}
        </div>
    );
}
