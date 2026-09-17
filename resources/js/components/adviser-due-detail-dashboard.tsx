import { Link, router, usePoll } from '@inertiajs/react';
import {
    RiArchiveLine,
    RiDownload2Line,
    RiEditLine,
    RiHistoryLine,
} from '@remixicon/react';
import { useEffect } from 'react';

import {
    createStudentDueWaiver,
    editAdviserDue,
} from '@/actions/App/Http/Controllers/DashboardController';
import {
    archive as archiveAdviserDue,
    exportMethod as exportAdviserDue,
    restore as restoreAdviserDue,
    restoreAssignment as restoreStudentDue,
} from '@/actions/App/Http/Controllers/DueController';
import { ActionLink } from '@/components/action-link';
import {
    DueEmptyRow,
    DueMetric,
    DueStatusBadge,
    PaymentStatusBadge,
} from '@/components/adviser-due-ui';
import { CopyableValue } from '@/components/copyable-value';
import { PersonCell } from '@/components/person-cell';
import { ReceiptAction } from '@/components/receipt-action';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
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
import type { DashboardAdviserDueDetail } from '@/types';

export function AdviserDueDetailDashboard({
    detail,
}: {
    detail: DashboardAdviserDueDetail;
}) {
    const isArchived = detail.due.status === 'Archived';
    const hasReceiptWork = detail.students.some(
        (student) =>
            ['pending', 'processing'].includes(student.receiptStatus ?? '') ||
            ['queued', 'processing', 'delayed'].includes(
                student.receiptEmailStatus ?? '',
            ),
    );
    const { start: startReceiptPoll, stop: stopReceiptPoll } = usePoll(
        3000,
        {},
        { autoStart: false },
    );

    useEffect(() => {
        if (hasReceiptWork) {
            startReceiptPoll();
        } else {
            stopReceiptPoll();
        }

        return stopReceiptPoll;
    }, [hasReceiptWork, startReceiptPoll, stopReceiptPoll]);

    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="text-xl font-semibold tracking-normal">
                            {detail.due.title}
                        </h1>
                        <DueStatusBadge status={detail.due.status} />
                    </div>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {detail.due.set} · {detail.due.amount}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" asChild>
                        <a href={exportAdviserDue(detail.due.id).url} download>
                            <RiDownload2Line />
                            Export Payments
                        </a>
                    </Button>
                    {!isArchived ? (
                        <Button variant="outline" asChild>
                            <Link href={editAdviserDue(detail.due.id)}>
                                <RiEditLine />
                                Edit Due
                            </Link>
                        </Button>
                    ) : null}
                    <DueLifecycleAction due={detail.due} />
                </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <DueMetric label="Paid" value={detail.due.paidCount} />
                <DueMetric label="Pending" value={detail.due.pendingCount} />
                <DueMetric label="Waived" value={detail.due.waivedCount} />
                <DueMetric
                    label="Collected"
                    value={detail.due.collectedAmount}
                />
                <DueMetric
                    label="Outstanding"
                    value={detail.due.outstandingAmount}
                />
            </div>

            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardHeader className="border-b border-border">
                    <CardTitle>Students</CardTitle>
                </CardHeader>
                <CardContent className="p-0">
                    <ScrollArea className="max-h-[36rem]">
                        <Table className="text-sm">
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-[34%] px-4">
                                        Student
                                    </TableHead>
                                    <TableHead className="w-[22%]">
                                        Reg No.
                                    </TableHead>
                                    <TableHead className="w-[14%] text-center">
                                        Amount
                                    </TableHead>
                                    <TableHead className="w-[14%] text-center">
                                        Status
                                    </TableHead>
                                    <TableHead className="w-[16%] pr-4 text-right">
                                        Action
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {detail.students.length > 0 ? (
                                    detail.students.map((student) => (
                                        <TableRow
                                            key={student.id}
                                            className="odd:bg-background even:bg-muted/25"
                                        >
                                            <TableCell className="px-4">
                                                <PersonCell
                                                    name={student.studentName}
                                                    avatar={
                                                        student.studentAvatar
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <CopyableValue
                                                    value={
                                                        student.registrationNumber
                                                    }
                                                    className="h-8 w-fit"
                                                    valueClassName="text-xs"
                                                />
                                            </TableCell>
                                            <TableCell className="text-center font-semibold tabular-nums">
                                                {student.amount}
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <div className="grid justify-items-center gap-1">
                                                    <PaymentStatusBadge
                                                        status={student.status}
                                                    />
                                                    {student.waiverReason ? (
                                                        <span className="max-w-40 truncate text-xs text-muted-foreground">
                                                            {
                                                                student.waiverReason
                                                            }
                                                        </span>
                                                    ) : null}
                                                </div>
                                            </TableCell>
                                            <TableCell className="pr-4 text-right">
                                                <StudentDueAction
                                                    dueId={detail.due.id}
                                                    student={student}
                                                    isArchived={isArchived}
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <DueEmptyRow colSpan={5}>
                                        No students assigned yet.
                                    </DueEmptyRow>
                                )}
                            </TableBody>
                        </Table>
                    </ScrollArea>
                </CardContent>
            </Card>
        </div>
    );
}

function StudentDueAction({
    dueId,
    student,
    isArchived,
}: {
    dueId: number;
    student: DashboardAdviserDueDetail['students'][number];
    isArchived: boolean;
}) {
    if (student.receiptStatus) {
        return <ReceiptAction receipt={student} />;
    }

    if (student.status === 'Pending' && !isArchived) {
        return (
            <ActionLink
                href={createStudentDueWaiver({
                    due: dueId,
                    studentDueAssignment: student.id,
                })}
            >
                Waive
            </ActionLink>
        );
    }

    if (student.status === 'Waived' && !isArchived) {
        return (
            <RestoreStudentDueAction
                dueId={dueId}
                assignmentId={student.id}
                studentName={student.studentName}
            />
        );
    }

    return <span className="text-xs text-muted-foreground">No action</span>;
}

function DueLifecycleAction({
    due,
}: {
    due: DashboardAdviserDueDetail['due'];
}) {
    const isArchived = due.status === 'Archived';

    return (
        <AlertDialog>
            <AlertDialogTrigger asChild>
                <Button variant={isArchived ? 'outline' : 'destructive'}>
                    {isArchived ? <RiHistoryLine /> : <RiArchiveLine />}
                    {isArchived ? 'Restore Due' : 'Archive Due'}
                </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        {isArchived
                            ? `Restore ${due.title}?`
                            : `Archive ${due.title}?`}
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        {isArchived ? (
                            <>
                                Students who still owe{' '}
                                <strong className="text-foreground">
                                    {due.title}
                                </strong>{' '}
                                can pay it again. Completed payments and
                                receipts stay unchanged.
                            </>
                        ) : (
                            <>
                                Students can no longer start a new payment for{' '}
                                <strong className="text-foreground">
                                    {due.title}
                                </strong>
                                . Completed payments and receipts stay
                                available.
                            </>
                        )}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        variant={isArchived ? 'default' : 'destructive'}
                        onClick={() =>
                            router.patch(
                                (isArchived
                                    ? restoreAdviserDue(due.id)
                                    : archiveAdviserDue(due.id)
                                ).url,
                            )
                        }
                    >
                        {isArchived ? 'Restore Due' : 'Archive Due'}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

function RestoreStudentDueAction({
    dueId,
    assignmentId,
    studentName,
}: {
    dueId: number;
    assignmentId: number;
    studentName: string;
}) {
    return (
        <AlertDialog>
            <AlertDialogTrigger asChild>
                <button type="button" className="text-xs font-medium text-link">
                    Restore
                </button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        Restore this payment requirement?
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        <strong className="text-foreground">
                            {studentName}
                        </strong>{' '}
                        will owe this due again and can pay from the student
                        dashboard.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Keep Waived</AlertDialogCancel>
                    <AlertDialogAction
                        onClick={() =>
                            router.patch(
                                restoreStudentDue({
                                    due: dueId,
                                    studentDueAssignment: assignmentId,
                                }).url,
                            )
                        }
                    >
                        Restore Requirement
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
