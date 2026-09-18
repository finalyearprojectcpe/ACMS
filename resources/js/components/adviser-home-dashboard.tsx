import type { ReactNode } from 'react';
import { DashboardMetric } from '@/components/dashboard-metric';
import { PersonCell } from '@/components/person-cell';
import { studentSetListText } from '@/components/student-set-label';
import { TableEmptyRow } from '@/components/table-empty-row';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    DashboardAdviserHome,
    DashboardAdviserHomePayment,
} from '@/types';

type AdviserHomeDashboardProps = {
    home: DashboardAdviserHome;
};

export function AdviserHomeDashboard({ home }: AdviserHomeDashboardProps) {
    const assignedSet = studentSetListText(home.assignedSets);

    return (
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-4">
            <div className="grid gap-3 sm:grid-cols-2">
                <DashboardMetric
                    label="Total Students"
                    value={home.totalStudents}
                    detail={assignedSet}
                    truncateDetail
                />
                <DashboardMetric
                    label="Maximum Course Units"
                    value={home.maximumCourseUnits}
                    detail="Current semester limit"
                />
            </div>

            <div className="grid gap-4">
                <DashboardCard title="Recent Dues Payments">
                    <RecentPaymentsTable payments={home.recentPayments} />
                </DashboardCard>
            </div>
        </div>
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
    payments: DashboardAdviserHomePayment[];
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
                    <TableEmptyRow colSpan={4}>
                        No recent payments.
                    </TableEmptyRow>
                )}
            </TableBody>
        </Table>
    );
}
