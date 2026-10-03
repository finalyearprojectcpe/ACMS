import { Link, router } from '@inertiajs/react';
import {
    RiArchiveLine,
    RiEditLine,
    RiHistoryLine,
    RiInformationLine,
} from '@remixicon/react';
import { useMemo, useState } from 'react';

import {
    createStudentDueWaiver,
    editAdviserDue,
} from '@/actions/App/Http/Controllers/DashboardController';
import {
    archive as archiveAdviserDue,
    restore as restoreAdviserDue,
    restoreAssignment as restoreStudentDue,
} from '@/actions/App/Http/Controllers/DueController';
import { download as downloadDueRoster } from '@/actions/App/Http/Controllers/DueRosterExportController';
import { ActionLink } from '@/components/action-link';
import {
    DueStatusBadge,
    PaymentStatusBadge,
} from '@/components/adviser-due-ui';
import { CopyableValue } from '@/components/copyable-value';
import { DownloadButton } from '@/components/download-button';
import { PersonCell } from '@/components/person-cell';
import { ReceiptAction } from '@/components/receipt-action';
import { TableEmptyRow } from '@/components/table-empty-row';
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
import { Input } from '@/components/ui/input';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useReceiptWorkPolling } from '@/hooks/use-receipt-work-polling';
import type { DashboardAdviserDueDetail } from '@/types';

export function AdviserDueDetailDashboard({
    detail,
}: {
    detail: DashboardAdviserDueDetail;
}) {
    const isArchived = detail.due.status === 'Archived';
    const [search, setSearch] = useState('');
    const [status, setStatus] = useState('all');
    const visibleStudents = useMemo(
        () =>
            detail.students.filter(
                (student) =>
                    (status === 'all' ||
                        student.status.toLowerCase() === status) &&
                    `${student.studentName} ${student.registrationNumber}`
                        .toLowerCase()
                        .includes(search.trim().toLowerCase()),
            ),
        [detail.students, search, status],
    );
    const hasReceiptWork = detail.students.some(
        (student) =>
            ['pending', 'processing'].includes(student.receiptStatus ?? '') ||
            ['queued', 'processing', 'delayed'].includes(
                student.receiptEmailStatus ?? '',
            ),
    );
    useReceiptWorkPolling(hasReceiptWork);

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
                        {detail.due.set} · {detail.due.period} ·{' '}
                        {detail.due.amount}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    {!isArchived ? (
                        <Button variant="outline" asChild>
                            <Link href={editAdviserDue(detail.due.publicId)}>
                                <RiEditLine />
                                Edit Due
                            </Link>
                        </Button>
                    ) : null}
                    <DueLifecycleAction due={detail.due} />
                </div>
            </div>

            <Card className="gap-0 border border-border bg-background py-0 shadow-sm ring-1 ring-border/70">
                <CardHeader className="gap-3 border-b border-border p-5">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <CardTitle>Payments</CardTitle>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {detail.due.paidCount} paid ·{' '}
                                {detail.due.pendingCount} unpaid ·{' '}
                                {detail.due.waivedCount} waived · Collected{' '}
                                {detail.due.collectedAmount} · Outstanding{' '}
                                {detail.due.outstandingAmount}
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Input
                            aria-label="Search students"
                            placeholder="Search name or reg no."
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            className="min-w-48 flex-1 bg-background sm:max-w-xs"
                        />
                        <Select value={status} onValueChange={setStatus}>
                            <SelectTrigger
                                aria-label="Payment status"
                                className="w-36"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All</SelectItem>
                                <SelectItem value="paid">Paid</SelectItem>
                                <SelectItem value="unpaid">Unpaid</SelectItem>
                                <SelectItem value="waived">Waived</SelectItem>
                            </SelectContent>
                        </Select>
                        {detail.due.status !== 'Draft' ? (
                            <div className="flex w-full flex-wrap items-center gap-3 sm:ml-auto sm:w-auto">
                                <DownloadButton
                                    size="default"
                                    href={downloadDueRoster.url(
                                        detail.due.publicId,
                                        { query: { status } },
                                    )}
                                >
                                    Export {status} students
                                </DownloadButton>
                            </div>
                        ) : null}
                    </div>
                </CardHeader>
                <CardContent className="p-0">
                    <ScrollArea className="max-h-[36rem]">
                        <Table className="min-w-[850px] text-sm">
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-12 text-center">
                                        S/N
                                    </TableHead>
                                    <TableHead className="w-48 px-4">
                                        Student
                                    </TableHead>
                                    <TableHead className="w-44">
                                        Reg No.
                                    </TableHead>
                                    <TableHead className="w-28 text-center">
                                        Amount
                                    </TableHead>
                                    <TableHead className="w-28 text-center">
                                        Status
                                    </TableHead>
                                    <TableHead className="text-center">
                                        Paid At
                                    </TableHead>
                                    <TableHead className="w-28 pr-4 text-right">
                                        Action
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {visibleStudents.length > 0 ? (
                                    visibleStudents.map((student, index) => (
                                        <TableRow
                                            key={student.id}
                                            className="odd:bg-background even:bg-muted/25"
                                        >
                                            <TableCell className="text-center text-muted-foreground tabular-nums">
                                                {index + 1}
                                            </TableCell>
                                            <TableCell className="px-4">
                                                <PersonCell
                                                    name={student.studentName}
                                                    avatar={
                                                        student.studentAvatar
                                                    }
                                                    href={
                                                        student.profileUrl ??
                                                        undefined
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
                                                <div className="flex items-center justify-center gap-1.5">
                                                    <PaymentStatusBadge
                                                        status={student.status}
                                                    />
                                                    {student.status ===
                                                    'Waived' ? (
                                                        <WaiverReason
                                                            reason={
                                                                student.waiverReason
                                                            }
                                                        />
                                                    ) : null}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-center text-muted-foreground">
                                                {student.paidAt ?? '—'}
                                            </TableCell>
                                            <TableCell className="pr-4 text-right">
                                                <StudentDueAction
                                                    dueId={detail.due.publicId}
                                                    student={student}
                                                    isArchived={isArchived}
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableEmptyRow colSpan={7} className="py-8">
                                        {detail.students.length
                                            ? 'No matching students.'
                                            : 'No students assigned yet.'}
                                    </TableEmptyRow>
                                )}
                            </TableBody>
                        </Table>
                        <ScrollBar orientation="horizontal" />
                    </ScrollArea>
                </CardContent>
            </Card>
        </div>
    );
}

function WaiverReason({ reason }: { reason: string | null }) {
    const [open, setOpen] = useState(false);
    const explanation = reason?.trim() || 'No reason provided.';

    return (
        <Tooltip open={open} onOpenChange={setOpen}>
            <TooltipTrigger asChild>
                <button
                    type="button"
                    aria-label={`Waiver reason: ${explanation}`}
                    onClick={() => setOpen((current) => !current)}
                    className="inline-flex size-7 items-center justify-center text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                >
                    <RiInformationLine className="size-4" />
                </button>
            </TooltipTrigger>
            <TooltipContent className="max-w-64 break-words">
                {explanation}
            </TooltipContent>
        </Tooltip>
    );
}

function StudentDueAction({
    dueId,
    student,
    isArchived,
}: {
    dueId: string;
    student: DashboardAdviserDueDetail['students'][number];
    isArchived: boolean;
}) {
    if (student.receiptStatus) {
        return <ReceiptAction receipt={student} />;
    }

    if (student.status === 'Unpaid' && !isArchived) {
        return (
            <ActionLink
                href={createStudentDueWaiver({
                    due: dueId,
                    studentDueAssignment: student.publicId,
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
                assignmentId={student.publicId}
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
                                can pay it again. Any applicable student results
                                will be locked until they pay or are waived.
                                Completed payments and receipts stay unchanged.
                            </>
                        ) : (
                            <>
                                Students can no longer start a new payment for{' '}
                                <strong className="text-foreground">
                                    {due.title}
                                </strong>
                                . This also removes its result-access
                                requirement. Completed payments and receipts
                                stay available.
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
                                    ? restoreAdviserDue(due.publicId)
                                    : archiveAdviserDue(due.publicId)
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
    dueId: string;
    assignmentId: string;
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
