import { Link } from '@inertiajs/react';
import { RiArrowLeftLine } from '@remixicon/react';

import { ActionLink } from '@/components/action-link';
import { CopyableValue } from '@/components/copyable-value';
import { StudentPortrait } from '@/components/student-portrait';
import { StudentResultsView } from '@/components/student-results-view';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type {
    DashboardStudentRecord,
    DashboardStudentRecordDue,
    DashboardStudentRecordResults,
} from '@/types';

export function StudentRecordDashboard({
    record,
}: {
    record: DashboardStudentRecord;
}) {
    const resultWidth = 136 + record.results.columns.length * 150;
    const hasDues =
        record.dues.unpaid.length +
            record.dues.paid.length +
            record.dues.waived.length >
        0;
    const initialDuesTab = record.dues.unpaid.length
        ? 'unpaid'
        : record.dues.paid.length
          ? 'paid'
          : 'waived';

    return (
        <div className="mx-auto flex w-full max-w-5xl min-w-0 flex-col gap-8">
            <ActionLink
                href={record.student.studentSet.url}
                icon={<RiArrowLeftLine className="size-4" />}
            >
                Back to Students
            </ActionLink>
            <header className="min-w-0 border-b pb-5">
                <div className="grid min-w-0 grid-cols-[auto_minmax(0,1fr)] items-center gap-x-4 gap-y-4 sm:grid-cols-[auto_minmax(0,1fr)_auto] sm:gap-x-6">
                    <StudentPortrait
                        name={record.student.fullName}
                        image={record.student.avatar}
                    />
                    <div className="min-w-0">
                        <h1 className="text-lg font-semibold break-words sm:text-xl">
                            {record.student.fullName}
                        </h1>
                        <p className="mt-1 text-sm break-words text-muted-foreground">
                            {record.student.studentSet.name}
                        </p>
                        <div className="mt-3">
                            <span className="mb-1 block text-xs text-muted-foreground">
                                Registration number
                            </span>
                            <CopyableValue
                                value={record.student.registrationNumber}
                            />
                        </div>
                    </div>
                    <div className="col-span-2 flex items-baseline justify-between border-t pt-3 sm:col-span-1 sm:min-w-32 sm:flex-col sm:items-start sm:gap-1 sm:border-t-0 sm:border-l sm:py-2 sm:pl-6">
                        <span className="text-xs text-muted-foreground">
                            CGPA
                        </span>
                        <span className="text-xl font-semibold tabular-nums sm:text-2xl">
                            {record.results.cgpa?.toFixed(2) ?? '—'}
                        </span>
                    </div>
                </div>
                {record.pendingRegistrations.length > 0 ? (
                    <div className="mt-5 border-t pt-4 text-sm">
                        <p className="font-medium">Registrations to review</p>
                        <div className="mt-2 flex flex-wrap gap-x-6 gap-y-2">
                            {record.pendingRegistrations.map((registration) => (
                                <ActionLink
                                    key={registration.url}
                                    href={registration.url}
                                >
                                    {registration.label}
                                </ActionLink>
                            ))}
                        </div>
                    </div>
                ) : null}
            </header>
            <section className="min-w-0">
                <h2 className="mb-3 text-base font-semibold">Results</h2>
                {record.results.columns.length === 0 ? (
                    <p className="border-y py-8 text-sm text-muted-foreground">
                        No academic sessions have been added to this set.
                    </p>
                ) : (
                    <div
                        className="mx-auto min-w-0"
                        style={{ maxWidth: resultWidth }}
                    >
                        <ScrollArea className="w-full border-y">
                            <div style={{ width: resultWidth }}>
                                <Table className="table-fixed">
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="sticky left-0 z-10 w-[136px] bg-background">
                                                Result
                                            </TableHead>
                                            {record.results.columns.map(
                                                (column) => (
                                                    <TableHead
                                                        key={column.session}
                                                        className="w-[150px] text-center"
                                                    >
                                                        <span className="block font-semibold text-foreground">
                                                            {column.level}
                                                        </span>
                                                        <span className="block text-xs leading-4 font-normal">
                                                            {column.session}{' '}
                                                            Session
                                                        </span>
                                                    </TableHead>
                                                ),
                                            )}
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {[0, 1].map((semesterIndex) => (
                                            <TableRow key={semesterIndex}>
                                                <TableCell className="sticky left-0 z-10 bg-background font-medium">
                                                    {semesterIndex === 0
                                                        ? '1st Semester'
                                                        : '2nd Semester'}
                                                </TableCell>
                                                {record.results.columns.map(
                                                    (column) => {
                                                        const result =
                                                            column.semesters[
                                                                semesterIndex
                                                            ];

                                                        return (
                                                            <TableCell
                                                                key={
                                                                    column.session
                                                                }
                                                                className="p-0 text-center"
                                                            >
                                                                {result?.url ? (
                                                                    <Link
                                                                        href={
                                                                            result.url
                                                                        }
                                                                        aria-label={`View ${column.level} ${result.name} result`}
                                                                        className="flex min-h-12 items-center justify-center p-2 font-semibold text-link tabular-nums hover:bg-muted/50 hover:underline focus-visible:bg-muted/50 focus-visible:underline"
                                                                    >
                                                                        {result.gpa?.toFixed(
                                                                            2,
                                                                        ) ??
                                                                            '—'}
                                                                    </Link>
                                                                ) : (
                                                                    <span className="text-muted-foreground">
                                                                        —
                                                                    </span>
                                                                )}
                                                            </TableCell>
                                                        );
                                                    },
                                                )}
                                            </TableRow>
                                        ))}
                                        <TableRow className="bg-muted/30 font-semibold">
                                            <TableCell className="sticky left-0 z-10 bg-muted">
                                                CGPA
                                            </TableCell>
                                            {record.results.columns.map(
                                                (column) => (
                                                    <TableCell
                                                        key={column.session}
                                                        className="text-center tabular-nums"
                                                    >
                                                        {column.cgpa?.toFixed(
                                                            2,
                                                        ) ?? '—'}
                                                    </TableCell>
                                                ),
                                            )}
                                        </TableRow>
                                    </TableBody>
                                </Table>
                            </div>
                            <ScrollBar orientation="horizontal" />
                        </ScrollArea>
                    </div>
                )}
            </section>
            <section className="min-w-0">
                <h2 className="mb-3 text-base font-semibold">Student Dues</h2>
                {hasDues ? (
                    <Tabs
                        defaultValue={initialDuesTab}
                        className="min-w-0 gap-4"
                    >
                        <TabsList
                            variant="line"
                            className="h-10 gap-3 border-b px-0"
                        >
                            {(['unpaid', 'paid', 'waived'] as const).map(
                                (kind) => (
                                    <TabsTrigger
                                        key={kind}
                                        value={kind}
                                        className="h-full flex-none px-2 text-sm"
                                    >
                                        {kind === 'unpaid'
                                            ? 'Unpaid'
                                            : kind === 'paid'
                                              ? 'Paid'
                                              : 'Waived'}
                                        <span className="text-muted-foreground tabular-nums">
                                            {record.dues[kind].length}
                                        </span>
                                    </TabsTrigger>
                                ),
                            )}
                        </TabsList>
                        {(['unpaid', 'paid', 'waived'] as const).map((kind) => (
                            <TabsContent key={kind} value={kind}>
                                <DueTable
                                    rows={record.dues[kind]}
                                    kind={kind}
                                />
                            </TabsContent>
                        ))}
                    </Tabs>
                ) : (
                    <p className="border-y py-6 text-sm text-muted-foreground">
                        No dues assigned.
                    </p>
                )}
            </section>
        </div>
    );
}

function DueTable({
    rows,
    kind,
}: {
    rows: DashboardStudentRecordDue[];
    kind: 'unpaid' | 'paid' | 'waived';
}) {
    return (
        <div className="min-w-0">
            {rows.length === 0 ? (
                <p className="border-y py-5 text-sm text-muted-foreground">
                    No {kind} dues.
                </p>
            ) : (
                <ScrollArea className="w-full border-y">
                    <div className="min-w-[620px]">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Due</TableHead>
                                    <TableHead>Session</TableHead>
                                    <TableHead className="text-right">
                                        Amount
                                    </TableHead>
                                    <TableHead>
                                        {kind === 'unpaid'
                                            ? 'Assigned'
                                            : kind === 'paid'
                                              ? 'Paid'
                                              : 'Waived'}
                                    </TableHead>
                                    {kind === 'paid' ? (
                                        <TableHead className="text-right">
                                            Receipt
                                        </TableHead>
                                    ) : null}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((row, index) => (
                                    <TableRow key={`${row.name}-${index}`}>
                                        <TableCell className="font-medium">
                                            {row.name}
                                            {kind === 'waived' &&
                                            row.waiverReason ? (
                                                <span className="block text-xs text-muted-foreground">
                                                    {row.waiverReason}
                                                </span>
                                            ) : null}
                                        </TableCell>
                                        <TableCell>{row.period}</TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {row.amount}
                                        </TableCell>
                                        <TableCell>
                                            {kind === 'unpaid'
                                                ? row.assignedAt
                                                : kind === 'paid'
                                                  ? row.paidAt
                                                  : row.waivedAt}
                                        </TableCell>
                                        {kind === 'paid' ? (
                                            <TableCell className="text-right">
                                                {row.receiptUrl ? (
                                                    <a
                                                        href={row.receiptUrl}
                                                        className="font-medium text-link hover:underline"
                                                    >
                                                        Download PDF
                                                    </a>
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        {row.receiptStatus ===
                                                        'failed'
                                                            ? 'Unavailable'
                                                            : 'Preparing'}
                                                    </span>
                                                )}
                                            </TableCell>
                                        ) : null}
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                    <ScrollBar orientation="horizontal" />
                </ScrollArea>
            )}
        </div>
    );
}

export function StudentRecordResultsDashboard({
    record,
}: {
    record: DashboardStudentRecordResults;
}) {
    return (
        <div className="flex min-h-0 w-full flex-1 flex-col">
            <div className="mb-5">
                <ActionLink
                    href={record.backUrl}
                    icon={<RiArrowLeftLine className="size-4" />}
                >
                    Back to {record.student.fullName}
                </ActionLink>
            </div>
            <div className="mb-5 flex min-w-0 items-center gap-4 border-b pb-5 sm:gap-6">
                <StudentPortrait
                    name={record.student.fullName}
                    image={record.student.avatar}
                />
                <div className="min-w-0">
                    <h1 className="text-lg font-semibold break-words sm:text-xl">
                        {record.student.fullName}
                    </h1>
                    <div className="mt-2">
                        <CopyableValue
                            value={record.student.registrationNumber}
                        />
                    </div>
                </div>
            </div>
            <StudentResultsView results={record.results} />
        </div>
    );
}
