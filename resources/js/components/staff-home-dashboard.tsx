import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';

import {
    AverageGpaByLevelBarChart,
    ChartCard,
    ResultClassificationPieChart,
    averageGpaByLevelRows,
    classificationRowsForStudents,
    studentsForEntrySession,
} from '@/components/dashboard-charts';
import { PersonCell } from '@/components/person-cell';
import {
    StudentSetChoiceLabel,
    studentSetText,
} from '@/components/student-set-label';
import { StudentSetPicker } from '@/components/student-set-picker';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { DashboardStaffHome, DashboardStaffHomePayment } from '@/types';

type StaffHomeDashboardProps = {
    home: DashboardStaffHome;
};

export function StaffHomeDashboard({ home }: StaffHomeDashboardProps) {
    const assignedSet = assignedSetLabel(home.assignedSets);
    const defaultSetScope = home.assignedSets[0]?.entrySession.name ?? '';
    const [gpaScope, setGpaScope] = useState(defaultSetScope);
    const [classificationScope, setClassificationScope] =
        useState(defaultSetScope);
    const effectiveGpaScope = selectedSetScope(gpaScope, home.assignedSets);
    const effectiveClassificationScope = selectedSetScope(
        classificationScope,
        home.assignedSets,
    );
    const gpaStudents = useMemo(
        () => studentsForEntrySession(home.students, effectiveGpaScope),
        [effectiveGpaScope, home.students],
    );
    const classificationStudents = useMemo(
        () =>
            studentsForEntrySession(
                home.students,
                effectiveClassificationScope,
            ),
        [effectiveClassificationScope, home.students],
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
                    detail="Performance for the selected student set."
                    control={
                        <SetScopeControl
                            value={effectiveGpaScope}
                            sets={home.assignedSets}
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
                    detail="CGPA classification for the selected student set."
                    control={
                        <SetScopeControl
                            value={effectiveClassificationScope}
                            sets={home.assignedSets}
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
            </div>

            <div className="grid gap-4">
                <DashboardCard title="Recent Dues Payments">
                    <RecentPaymentsTable payments={home.recentPayments} />
                </DashboardCard>
            </div>
        </div>
    );
}

function SetScopeControl({
    value,
    sets,
    label,
    onChange,
}: {
    value: string;
    sets: DashboardStaffHome['assignedSets'];
    label: string;
    onChange: (value: string) => void;
}) {
    if (sets.length <= 1) {
        const set = sets[0] ?? null;

        return (
            <div className="rounded-md border border-border bg-muted/40 px-3 py-2 text-sm font-medium text-foreground">
                {set ? (
                    <StudentSetChoiceLabel
                        entrySession={set.entrySession}
                        currentLevel={set.currentLevel}
                        minimumYearsToDegree={set.minimumYearsToDegree}
                    />
                ) : (
                    'No assigned set'
                )}
            </div>
        );
    }

    return (
        <StudentSetPicker
            value={value}
            onValueChange={onChange}
            options={sets.map((set) => ({
                value: set.entrySession.name,
                entrySession: set.entrySession,
                currentLevel: set.currentLevel,
                minimumYearsToDegree: set.minimumYearsToDegree,
            }))}
            ariaLabel={label}
        />
    );
}

function selectedSetScope(
    value: string,
    sets: DashboardStaffHome['assignedSets'],
): string {
    if (sets.some((set) => set.entrySession.name === value)) {
        return value;
    }

    return sets[0]?.entrySession.name ?? '';
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
