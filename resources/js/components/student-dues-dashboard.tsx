import { Link } from '@inertiajs/react';
import { RiInformationLine } from '@remixicon/react';
import { useState } from 'react';

import { DashboardMetric } from '@/components/dashboard-metric';
import { ReceiptAction } from '@/components/receipt-action';
import { TableEmptyRow } from '@/components/table-empty-row';
import { Badge } from '@/components/ui/badge';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useReceiptWorkPolling } from '@/hooks/use-receipt-work-polling';
import type { DashboardStudentDueRow, DashboardStudentDues } from '@/types';

type DueFilter = 'unpaid' | 'paid' | 'waived';

export function StudentDuesDashboard({ dues }: { dues: DashboardStudentDues }) {
    const [filter, setFilter] = useState<DueFilter>('unpaid');
    const hasReceiptWork = dues.rows.some(
        (due) =>
            due.status === 'Paid' &&
            (['pending', 'processing'].includes(due.receiptStatus ?? '') ||
                ['queued', 'processing', 'delayed'].includes(
                    due.receiptEmailStatus ?? '',
                )),
    );
    useReceiptWorkPolling(hasReceiptWork);

    const rows = dues.rows.filter((due) => due.status.toLowerCase() === filter);

    return (
        <div className="mx-auto flex w-full max-w-5xl flex-col gap-6">
            <div className="grid gap-3 sm:grid-cols-2">
                <DashboardMetric
                    label="Paid"
                    value={dues.paidAmount}
                    detail={`out of ${dues.totalAmount} payable`}
                />
                <DashboardMetric label="Unpaid Dues" value={dues.unpaidCount} />
            </div>
            <section className="min-w-0">
                <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border pb-3">
                    <h1 className="text-base font-semibold">Dues</h1>
                    <Tabs
                        value={filter}
                        onValueChange={(value) => setFilter(value as DueFilter)}
                    >
                        <TabsList aria-label="Filter dues by status">
                            <TabsTrigger value="unpaid">Unpaid</TabsTrigger>
                            <TabsTrigger value="paid">Paid</TabsTrigger>
                            <TabsTrigger value="waived">Waived</TabsTrigger>
                        </TabsList>
                    </Tabs>
                </div>
                <div className="mt-4 border border-border">
                    <Table className="min-w-[620px] text-sm">
                        <TableHeader>
                            <TableRow>
                                <TableHead className="px-4">Due</TableHead>
                                <TableHead className="w-32 text-right">
                                    Amount
                                </TableHead>
                                <TableHead className="w-28 text-center">
                                    Status
                                </TableHead>
                                <TableHead className="w-36 pr-4 text-right">
                                    Action
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.length ? (
                                rows.map((due) => (
                                    <DueRow key={due.id} due={due} />
                                ))
                            ) : (
                                <TableEmptyRow colSpan={4}>
                                    No {filter} dues.
                                </TableEmptyRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </section>
        </div>
    );
}

function DueRow({ due }: { due: DashboardStudentDueRow }) {
    return (
        <TableRow>
            <TableCell className="px-4 whitespace-normal">
                <span className="font-medium">{due.name}</span>
                {due.paidAt ? (
                    <span className="mt-0.5 block text-xs text-muted-foreground">
                        Paid {due.paidAt}
                    </span>
                ) : null}
            </TableCell>
            <TableCell className="text-right font-medium tabular-nums">
                {due.amount}
            </TableCell>
            <TableCell className="text-center">
                <div className="inline-flex items-center gap-1">
                    <Badge
                        variant={
                            due.status === 'Unpaid'
                                ? 'destructive'
                                : 'secondary'
                        }
                        className={
                            due.status === 'Paid'
                                ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300'
                                : undefined
                        }
                    >
                        {due.status}
                    </Badge>
                    {due.status === 'Waived' && due.waiverReason ? (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <button
                                    type="button"
                                    aria-label={`Reason ${due.name} was waived`}
                                    className="text-muted-foreground"
                                >
                                    <RiInformationLine className="size-4" />
                                </button>
                            </TooltipTrigger>
                            <TooltipContent>{due.waiverReason}</TooltipContent>
                        </Tooltip>
                    ) : null}
                </div>
            </TableCell>
            <TableCell className="pr-4 text-right">
                {due.status === 'Unpaid' ? (
                    due.paymentUrl ? (
                        <Link
                            href={due.paymentUrl}
                            method="post"
                            as="button"
                            className="font-medium text-link hover:underline"
                        >
                            {due.paymentActionLabel ?? 'Pay now'}
                        </Link>
                    ) : (
                        <span
                            className="text-muted-foreground"
                            title={due.paymentUnavailableReason ?? undefined}
                        >
                            Unavailable
                        </span>
                    )
                ) : due.status === 'Paid' && due.receiptStatus !== undefined ? (
                    <ReceiptAction
                        receipt={{
                            receiptStatus: due.receiptStatus ?? null,
                            receiptUrl: due.receiptUrl ?? null,
                            receiptRetryUrl: due.receiptRetryUrl ?? null,
                            receiptEmailStatus: due.receiptEmailStatus ?? null,
                            receiptEmailRetryUrl:
                                due.receiptEmailRetryUrl ?? null,
                        }}
                    />
                ) : null}
            </TableCell>
        </TableRow>
    );
}
