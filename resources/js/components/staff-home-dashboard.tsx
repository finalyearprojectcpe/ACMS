import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';

import { section as dashboardSection } from '@/actions/App/Http/Controllers/DashboardController';
import { CopyableValue } from '@/components/copyable-value';
import {
    AverageGpaByLevelBarChart,
    ChartCard,
    ChartSelect,
    CountBarChart,
    ResultClassificationPieChart,
    averageGpaByLevelRows,
    classificationRowsForStudents,
    setChartOptions,
    studentsForEntrySession,
} from '@/components/dashboard-charts';
import { PersonCell } from '@/components/person-cell';
import { studentSetText } from '@/components/student-set-label';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    HoverCard,
    HoverCardContent,
    HoverCardTrigger,
} from '@/components/ui/hover-card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    DashboardStaffHome,
    DashboardStaffHomeCarryoverSummary,
    DashboardStaffHomePayment,
    DashboardStaffHomePendingApproval,
    DashboardStaffHomeStudentDueSummary,
} from '@/types';

type StaffHomeDashboardProps = {
    home: DashboardStaffHome;
};

export function StaffHomeDashboard({ home }: StaffHomeDashboardProps) {
    const assignedSet = assignedSetLabel(home.assignedSets);
    const setOptions = useMemo(
        () => setChartOptions(home.assignedSets),
        [home.assignedSets],
    );
    const [gpaScope, setGpaScope] = useState('assigned');
    const [classificationScope, setClassificationScope] = useState('assigned');
    const gpaOptions = useMemo(
        () => [
            { value: 'assigned', label: 'Assigned Students' },
            ...setOptions,
        ],
        [setOptions],
    );
    const gpaStudents = useMemo(
        () =>
            gpaScope === 'assigned'
                ? home.students
                : studentsForEntrySession(home.students, gpaScope),
        [gpaScope, home.students],
    );
    const classificationOptions = useMemo(
        () => [
            { value: 'assigned', label: 'Assigned Students' },
            ...setOptions,
        ],
        [setOptions],
    );
    const classificationStudents = useMemo(
        () =>
            classificationScope === 'assigned'
                ? home.students
                : studentsForEntrySession(home.students, classificationScope),
        [classificationScope, home.students],
    );
    const carryoverRows = useMemo(
        () =>
            home.carryoverSummaries.map((summary) => ({
                label: summary.code,
                value: summary.students.length,
            })),
        [home.carryoverSummaries],
    );

    return (
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-4">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <MetricBlock
                    label="Total Students"
                    value={home.totalStudents}
                    detail={assignedSet}
                />
                <MetricBlock
                    label="Maximum Course Units"
                    value={home.maximumCourseUnits}
                    detail="Current semester limit"
                />
                <MetricBlock
                    label="With Carryovers"
                    value={home.studentsWithCarryovers}
                    detail="Students needing resits"
                />
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <ChartCard
                    title="GPA by Level"
                    detail="Performance for the selected student scope."
                    control={
                        <ChartSelect
                            value={gpaScope}
                            options={gpaOptions}
                            label="Select GPA scope"
                            onChange={setGpaScope}
                        />
                    }
                >
                    <AverageGpaByLevelBarChart
                        rows={averageGpaByLevelRows(gpaStudents, home.levels)}
                    />
                </ChartCard>

                <ChartCard
                    title="Result Class Distribution"
                    detail="CGPA classification for the selected students."
                    control={
                        <ChartSelect
                            value={classificationScope}
                            options={classificationOptions}
                            label="Select result scope"
                            onChange={setClassificationScope}
                        />
                    }
                >
                    <ResultClassificationPieChart
                        rows={classificationRowsForStudents(
                            classificationStudents,
                        )}
                    />
                </ChartCard>

                <ChartCard
                    title="Carryovers by Course"
                    detail="Courses creating the most resit pressure."
                >
                    <CountBarChart rows={carryoverRows} dataLabel="Students" />
                </ChartCard>
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <DashboardCard
                    title="Students Owing Dues"
                    action={
                        <Link
                            href={dashboardSection({ section: 'dues' })}
                            className="text-xs font-medium text-link"
                        >
                            View dues
                        </Link>
                    }
                >
                    <PendingDuesByStudentTable
                        summaries={home.pendingDuesByStudent}
                    />
                </DashboardCard>

                <DashboardCard
                    title="Pending Approvals"
                    action={
                        <Link
                            href={dashboardSection({ section: 'approvals' })}
                            className="text-xs font-medium text-link"
                        >
                            View all
                        </Link>
                    }
                >
                    <PendingApprovalsTable approvals={home.pendingApprovals} />
                </DashboardCard>

                <DashboardCard title="Recent Dues Payments">
                    <RecentPaymentsTable payments={home.recentPayments} />
                </DashboardCard>

                <DashboardCard title="Carryover Summary">
                    <CarryoverSummaryTable
                        summaries={home.carryoverSummaries}
                    />
                </DashboardCard>
            </div>
        </div>
    );
}

function MetricBlock({
    label,
    value,
    detail,
}: {
    label: string;
    value: ReactNode;
    detail: string;
}) {
    return (
        <Card
            size="sm"
            className="border border-border bg-background shadow-sm ring-1 ring-border/70"
        >
            <CardHeader>
                <CardTitle className="text-xs text-muted-foreground">
                    {label}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="text-3xl font-semibold tracking-normal tabular-nums">
                    {value}
                </div>
                <div className="mt-1 truncate text-xs text-muted-foreground">
                    {detail}
                </div>
            </CardContent>
        </Card>
    );
}

function DashboardCard({
    title,
    action,
    children,
}: {
    title: string;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardHeader className="flex flex-row items-center justify-between gap-3 border-b border-border">
                <CardTitle>{title}</CardTitle>
                {action}
            </CardHeader>
            <CardContent className="p-0">{children}</CardContent>
        </Card>
    );
}

function PendingDuesByStudentTable({
    summaries,
}: {
    summaries: DashboardStaffHomeStudentDueSummary[];
}) {
    return (
        <Table className="text-sm">
            <TableHeader>
                <TableRow>
                    <TableHead className="w-[36%] px-4">Student</TableHead>
                    <TableHead className="w-[42%]">Pending Dues</TableHead>
                    <TableHead className="w-[22%] pr-4 text-right">
                        Outstanding
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {summaries.length > 0 ? (
                    summaries.map((summary) => (
                        <TableRow
                            key={summary.registrationNumber}
                            className="odd:bg-background even:bg-muted/25"
                        >
                            <TableCell className="px-4">
                                <div className="flex min-w-0 flex-col gap-1.5">
                                    <PersonCell
                                        name={summary.studentName}
                                        avatar={summary.studentAvatar}
                                    />
                                    <CopyableValue
                                        value={summary.registrationNumber}
                                        className="h-7"
                                        valueClassName="text-xs"
                                    />
                                </div>
                            </TableCell>
                            <TableCell className="whitespace-normal">
                                <DueBadgeList dues={summary.dues} />
                            </TableCell>
                            <TableCell className="pr-4 text-right font-semibold tabular-nums">
                                {summary.outstandingAmount}
                            </TableCell>
                        </TableRow>
                    ))
                ) : (
                    <EmptyTableRow colSpan={3}>
                        No students owing dues.
                    </EmptyTableRow>
                )}
            </TableBody>
        </Table>
    );
}

function DueBadgeList({ dues }: { dues: string[] }) {
    const visibleDues = dues.slice(0, 3);
    const hiddenDues = dues.slice(3);

    return (
        <div className="flex max-w-full flex-wrap items-center gap-1.5">
            {visibleDues.map((due) => (
                <Badge
                    key={due}
                    variant="outline"
                    className="max-w-44 border-border bg-muted/60 text-foreground"
                >
                    <span className="truncate">{due}</span>
                </Badge>
            ))}

            {hiddenDues.length > 0 ? (
                <HoverCard>
                    <HoverCardTrigger asChild>
                        <button
                            type="button"
                            className="text-xs font-medium text-link underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-hidden"
                        >
                            +{hiddenDues.length} more
                        </button>
                    </HoverCardTrigger>
                    <HoverCardContent align="start" className="w-64">
                        <div className="font-medium text-foreground">
                            Other pending dues
                        </div>
                        <div className="mt-2 flex flex-col gap-1.5">
                            {hiddenDues.map((due) => (
                                <div
                                    key={due}
                                    className="rounded-none bg-muted px-2 py-1 text-muted-foreground"
                                >
                                    {due}
                                </div>
                            ))}
                        </div>
                    </HoverCardContent>
                </HoverCard>
            ) : null}
        </div>
    );
}

function PendingApprovalsTable({
    approvals,
}: {
    approvals: DashboardStaffHomePendingApproval[];
}) {
    return (
        <Table className="text-sm">
            <TableHeader>
                <TableRow>
                    <TableHead className="w-[38%] px-4">Student</TableHead>
                    <TableHead className="w-[36%]">Request</TableHead>
                    <TableHead className="w-[26%] pr-4 text-right">
                        Submitted
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {approvals.length > 0 ? (
                    approvals.map((approval) => (
                        <TableRow
                            key={`${approval.registrationNumber}-${approval.requestType}`}
                            className="odd:bg-background even:bg-muted/25"
                        >
                            <TableCell className="px-4">
                                <div className="flex min-w-0 flex-col gap-1.5">
                                    <PersonCell
                                        name={approval.studentName}
                                        avatar={approval.studentAvatar}
                                    />
                                    <CopyableValue
                                        value={approval.registrationNumber}
                                        className="h-7"
                                        valueClassName="text-xs"
                                    />
                                </div>
                            </TableCell>
                            <TableCell className="text-muted-foreground">
                                {approval.requestType}
                            </TableCell>
                            <TableCell className="pr-4 text-right tabular-nums">
                                {approval.submittedAt}
                            </TableCell>
                        </TableRow>
                    ))
                ) : (
                    <EmptyTableRow colSpan={3}>
                        No pending approvals.
                    </EmptyTableRow>
                )}
            </TableBody>
        </Table>
    );
}

function RecentPaymentsTable({
    payments,
}: {
    payments: DashboardStaffHomePayment[];
}) {
    return (
        <Table className="text-sm">
            <TableHeader>
                <TableRow>
                    <TableHead className="w-[32%] px-4">Student</TableHead>
                    <TableHead>Due</TableHead>
                    <TableHead className="w-[16%] text-center">
                        Amount
                    </TableHead>
                    <TableHead className="hidden w-[18%] pr-4 text-right md:table-cell">
                        Paid At
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {payments.length > 0 ? (
                    payments.map((payment) => (
                        <TableRow
                            key={`${payment.studentName}-${payment.dueName}`}
                            className="odd:bg-background even:bg-muted/25"
                        >
                            <TableCell className="px-4">
                                <PersonCell
                                    name={payment.studentName}
                                    avatar={payment.studentAvatar}
                                />
                            </TableCell>
                            <TableCell className="text-muted-foreground">
                                {payment.dueName}
                            </TableCell>
                            <TableCell className="text-center font-semibold tabular-nums">
                                {payment.amount}
                            </TableCell>
                            <TableCell className="hidden pr-4 text-right tabular-nums md:table-cell">
                                {payment.paidAt}
                            </TableCell>
                        </TableRow>
                    ))
                ) : (
                    <EmptyTableRow colSpan={4}>
                        No recent payments.
                    </EmptyTableRow>
                )}
            </TableBody>
        </Table>
    );
}

function CarryoverSummaryTable({
    summaries,
}: {
    summaries: DashboardStaffHomeCarryoverSummary[];
}) {
    return (
        <Table className="text-sm">
            <TableHeader>
                <TableRow>
                    <TableHead className="w-[56%] px-4">Course</TableHead>
                    <TableHead className="w-[16%] text-center">Units</TableHead>
                    <TableHead className="w-[28%] pr-4 text-right">
                        Students
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {summaries.length > 0 ? (
                    summaries.map((summary) => (
                        <TableRow
                            key={summary.code}
                            className="odd:bg-background even:bg-muted/25"
                        >
                            <TableCell className="px-4">
                                <div className="min-w-0">
                                    <div className="truncate font-semibold">
                                        {summary.code}
                                    </div>
                                    <div className="truncate text-xs text-muted-foreground">
                                        {summary.title}
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell className="text-center font-medium tabular-nums">
                                {summary.units}
                            </TableCell>
                            <TableCell className="pr-4 text-right">
                                <div className="font-semibold tabular-nums">
                                    {summary.students.length}
                                </div>
                            </TableCell>
                        </TableRow>
                    ))
                ) : (
                    <EmptyTableRow colSpan={3}>
                        No carryover records.
                    </EmptyTableRow>
                )}
            </TableBody>
        </Table>
    );
}

function EmptyTableRow({
    children,
    colSpan,
}: {
    children: string;
    colSpan: number;
}) {
    return (
        <TableRow>
            <TableCell
                colSpan={colSpan}
                className="px-4 py-6 text-muted-foreground"
            >
                {children}
            </TableCell>
        </TableRow>
    );
}

function assignedSetLabel(sets: DashboardStaffHome['assignedSets']): string {
    if (sets.length === 0) {
        return 'No assigned set';
    }

    return sets.map((set) => studentSetText(set)).join(', ');
}
