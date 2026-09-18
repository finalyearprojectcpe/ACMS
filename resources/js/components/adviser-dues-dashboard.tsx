import { Link } from '@inertiajs/react';
import {
    RiAddLine,
    RiBankCardLine,
    RiErrorWarningLine,
} from '@remixicon/react';

import { ActionLink } from '@/components/action-link';
import { DueStatusBadge } from '@/components/adviser-due-ui';
import { DashboardMetric } from '@/components/dashboard-metric';
import { TableEmptyRow } from '@/components/table-empty-row';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { DashboardAdviserDues } from '@/types';

export function AdviserDuesDashboard({ dues }: { dues: DashboardAdviserDues }) {
    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <h1 className="text-xl font-semibold tracking-normal">
                        Dues
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Create dues for your student sets and track payments.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" asChild>
                        <Link href={dues.paymentSettingsUrl}>
                            <RiBankCardLine />
                            Paystack Settings
                        </Link>
                    </Button>
                    <Button asChild>
                        <Link href={dues.createUrl}>
                            <RiAddLine />
                            Create Due
                        </Link>
                    </Button>
                </div>
            </div>

            {!dues.hasActivePaymentAccount ? (
                <Alert className="border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
                    <RiErrorWarningLine className="size-4" />
                    <AlertTitle>Connect a payout account</AlertTitle>
                    <AlertDescription>
                        You can save drafts, but Paystack must confirm a payout
                        account before you publish a due.
                    </AlertDescription>
                </Alert>
            ) : null}

            <div className="grid gap-3 md:grid-cols-4">
                <DashboardMetric
                    label="Expected"
                    value={dues.metrics.expectedAmount}
                    valueClassName="text-2xl"
                />
                <DashboardMetric
                    label="Collected"
                    value={dues.metrics.collectedAmount}
                    valueClassName="text-2xl"
                />
                <DashboardMetric
                    label="Outstanding"
                    value={dues.metrics.outstandingAmount}
                    valueClassName="text-2xl"
                />
                <DashboardMetric
                    label="Students Owing"
                    value={dues.metrics.studentsOwing}
                    valueClassName="text-2xl"
                />
            </div>

            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardHeader className="border-b border-border">
                    <CardTitle>Created Dues</CardTitle>
                </CardHeader>
                <CardContent className="p-0">
                    <ScrollArea className="max-h-[34rem]">
                        <Table className="text-sm">
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-[28%] px-4">
                                        Due
                                    </TableHead>
                                    <TableHead>Set</TableHead>
                                    <TableHead className="w-[12%] text-center">
                                        Amount
                                    </TableHead>
                                    <TableHead className="w-[14%] text-center">
                                        Paid
                                    </TableHead>
                                    <TableHead className="w-[14%] text-center">
                                        Outstanding
                                    </TableHead>
                                    <TableHead className="w-[12%] pr-4 text-right">
                                        Action
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {dues.dues.length > 0 ? (
                                    dues.dues.map((due) => (
                                        <TableRow
                                            key={due.id}
                                            className="odd:bg-background even:bg-muted/25"
                                        >
                                            <TableCell className="px-4">
                                                <div className="grid gap-1">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className="font-medium">
                                                            {due.title}
                                                        </span>
                                                        <DueStatusBadge
                                                            status={due.status}
                                                        />
                                                    </div>
                                                    {due.description ? (
                                                        <span className="hidden truncate text-xs text-muted-foreground md:block">
                                                            {due.description}
                                                        </span>
                                                    ) : null}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                <div className="grid gap-1">
                                                    <span>{due.set}</span>
                                                    {due.paymentAccount ? (
                                                        <span className="text-xs">
                                                            {
                                                                due
                                                                    .paymentAccount
                                                                    .label
                                                            }
                                                        </span>
                                                    ) : null}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-center font-semibold tabular-nums">
                                                {due.amount}
                                            </TableCell>
                                            <TableCell className="text-center tabular-nums">
                                                {due.paidCount}
                                            </TableCell>
                                            <TableCell className="text-center font-medium tabular-nums">
                                                {due.outstandingAmount}
                                            </TableCell>
                                            <TableCell className="pr-4 text-right">
                                                <ActionLink
                                                    href={due.detailUrl}
                                                >
                                                    View
                                                </ActionLink>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableEmptyRow colSpan={6} className="py-8">
                                        No dues created yet.
                                    </TableEmptyRow>
                                )}
                            </TableBody>
                        </Table>
                    </ScrollArea>
                </CardContent>
            </Card>
        </div>
    );
}
