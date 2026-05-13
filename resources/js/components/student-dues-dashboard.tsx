import { RiCheckboxCircleFill } from '@remixicon/react';
import type { ReactNode } from 'react';

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
    DashboardStudentDue,
    DashboardStudentDues,
    DashboardStudentPaidDue,
} from '@/types';

type StudentDuesDashboardProps = {
    dues: DashboardStudentDues;
};

export function StudentDuesDashboard({ dues }: StudentDuesDashboardProps) {
    return (
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-4">
            <div className="grid gap-3 md:grid-cols-2">
                <SummaryBlock
                    label="Paid Amount"
                    value={dues.paidAmount}
                    detail={`${dues.paid.length} completed payments`}
                />
                <SummaryBlock
                    label="Total Dues"
                    value={dues.totalDues}
                    detail="Pending and paid records"
                />
            </div>

            <DuesCard title="Pending Dues">
                <PendingDuesTable dues={dues.pending} />
            </DuesCard>

            <DuesCard title="Paid Dues">
                <PaidDuesTable dues={dues.paid} />
            </DuesCard>
        </div>
    );
}

function SummaryBlock({
    label,
    value,
    detail,
}: {
    label: string;
    value: string | number;
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
                <div className="mt-1 text-xs text-muted-foreground">
                    {detail}
                </div>
            </CardContent>
        </Card>
    );
}

function DuesCard({ title, children }: { title: string; children: ReactNode }) {
    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardHeader className="border-b border-border">
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent className="p-0">{children}</CardContent>
        </Card>
    );
}

function PendingDuesTable({ dues }: { dues: DashboardStudentDue[] }) {
    return (
        <Table className="text-sm">
            <TableHeader>
                <TableRow>
                    <TableHead className="w-[30%] px-4">Name</TableHead>
                    <TableHead className="hidden md:table-cell">
                        Description
                    </TableHead>
                    <TableHead className="w-[14%] text-center">
                        Amount
                    </TableHead>
                    <TableHead className="w-[14%] pr-4 text-right">
                        Action
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {dues.length > 0 ? (
                    dues.map((due) => (
                        <TableRow key={due.name}>
                            <TableCell className="px-4 font-medium">
                                {due.name}
                            </TableCell>
                            <TableCell className="hidden max-w-[28rem] truncate text-muted-foreground md:table-cell">
                                {due.description}
                            </TableCell>
                            <TableCell className="text-center font-semibold tabular-nums">
                                {due.amount}
                            </TableCell>
                            <TableCell className="pr-4 text-right">
                                <a
                                    href={due.paymentUrl}
                                    className="text-xs font-medium text-link"
                                >
                                    Pay now
                                </a>
                            </TableCell>
                        </TableRow>
                    ))
                ) : (
                    <EmptyTableRow colSpan={4}>No pending dues.</EmptyTableRow>
                )}
            </TableBody>
        </Table>
    );
}

function PaidDuesTable({ dues }: { dues: DashboardStudentPaidDue[] }) {
    return (
        <Table className="text-sm">
            <TableHeader>
                <TableRow>
                    <TableHead className="w-[26%] px-4">Name</TableHead>
                    <TableHead className="hidden md:table-cell">
                        Description
                    </TableHead>
                    <TableHead className="w-[14%] text-center">
                        Amount
                    </TableHead>
                    <TableHead className="w-[14%] text-center">
                        Paid At
                    </TableHead>
                    <TableHead className="w-[16%] pr-4 text-right">
                        Receipt
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {dues.length > 0 ? (
                    dues.map((due) => (
                        <TableRow key={`${due.name}-${due.paidAt}`}>
                            <TableCell className="px-4 font-medium">
                                <span className="flex min-w-0 items-center gap-2">
                                    <RiCheckboxCircleFill className="size-4 shrink-0 text-link" />
                                    <span className="truncate">{due.name}</span>
                                </span>
                            </TableCell>
                            <TableCell className="hidden max-w-[28rem] truncate text-muted-foreground md:table-cell">
                                {due.description}
                            </TableCell>
                            <TableCell className="text-center font-semibold tabular-nums">
                                {due.amount}
                            </TableCell>
                            <TableCell className="text-center tabular-nums">
                                {due.paidAt}
                            </TableCell>
                            <TableCell className="pr-4 text-right">
                                <a
                                    href={due.receiptUrl}
                                    className="text-xs font-medium text-link"
                                >
                                    Download receipt
                                </a>
                            </TableCell>
                        </TableRow>
                    ))
                ) : (
                    <EmptyTableRow colSpan={5}>No paid dues.</EmptyTableRow>
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
