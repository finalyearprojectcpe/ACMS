import { Link, useForm } from '@inertiajs/react';
import {
    RiAddLine,
    RiBankCardLine,
    RiErrorWarningLine,
    RiSave3Line,
    RiSendPlaneLine,
} from '@remixicon/react';
import type { FormEvent } from 'react';

import { storeStaffDue } from '@/actions/App/Http/Controllers/DashboardController';
import { ActionLink } from '@/components/action-link';
import { CopyableValue } from '@/components/copyable-value';
import { PersonCell } from '@/components/person-cell';
import { StudentSetPicker } from '@/components/student-set-picker';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardForm,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import type {
    DashboardPaymentAccount,
    DashboardStaffDueDetail,
    DashboardStaffDueForm,
    DashboardStaffDues,
} from '@/types';

type StaffDueFormData = {
    entry_academic_session_id: string;
    title: string;
    description: string;
    amount_naira: string;
    due_on: string;
};

export function StaffDuesDashboard({ dues }: { dues: DashboardStaffDues }) {
    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <h1 className="text-xl font-semibold tracking-normal">
                        Dues
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Create dues for your assigned student sets and track
                        payment status.
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
                        Dues can be saved as drafts, but they cannot be
                        published until Paystack confirms where the money should
                        settle.
                    </AlertDescription>
                </Alert>
            ) : null}

            <div className="grid gap-3 md:grid-cols-4">
                <MetricCard
                    label="Expected"
                    value={dues.metrics.expectedAmount}
                />
                <MetricCard
                    label="Collected"
                    value={dues.metrics.collectedAmount}
                />
                <MetricCard
                    label="Outstanding"
                    value={dues.metrics.outstandingAmount}
                />
                <MetricCard
                    label="Students Owing"
                    value={dues.metrics.studentsOwing}
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
                                    <EmptyRow colSpan={6}>
                                        No dues created yet.
                                    </EmptyRow>
                                )}
                            </TableBody>
                        </Table>
                    </ScrollArea>
                </CardContent>
            </Card>
        </div>
    );
}

export function StaffDueFormDashboard({
    form,
}: {
    form: DashboardStaffDueForm;
}) {
    const dueForm = useForm<StaffDueFormData>({
        entry_academic_session_id:
            form.defaults.entryAcademicSessionId?.toString() ?? '',
        title: '',
        description: '',
        amount_naira: '',
        due_on: form.defaults.dueOn,
    });

    function submit(action: 'draft' | 'publish') {
        dueForm.transform((data) => ({
            ...data,
            action,
        }));
        dueForm.submit(storeStaffDue(), {
            onFinish: () => dueForm.transform((data) => data),
        });
    }

    function preventSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
    }

    const selectedPaymentAccount = paymentAccountForSet(
        form.paymentAccounts,
        dueForm.data.entry_academic_session_id,
    );
    const canPublish = Boolean(selectedPaymentAccount);

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-4">
            <div className="min-w-0">
                <h1 className="text-xl font-semibold tracking-normal">
                    Create Due
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Publish when students should see the due on their dashboard.
                </p>
            </div>

            <Alert
                className={
                    selectedPaymentAccount
                        ? 'border-green-200 bg-green-50 text-green-900 dark:border-green-400/30 dark:bg-green-400/10 dark:text-green-100'
                        : 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100'
                }
            >
                {selectedPaymentAccount ? (
                    <RiBankCardLine className="size-4" />
                ) : (
                    <RiErrorWarningLine className="size-4" />
                )}
                <AlertTitle>
                    {selectedPaymentAccount
                        ? 'Payout account ready'
                        : 'Payout account needed'}
                </AlertTitle>
                <AlertDescription>
                    {selectedPaymentAccount ? (
                        <>
                            Published payments for this set will settle into{' '}
                            <span className="font-medium">
                                {selectedPaymentAccount.label}
                            </span>
                            .
                        </>
                    ) : (
                        <>
                            You can save this due as a draft. To publish it,
                            first connect a Paystack payout account in{' '}
                            <Link
                                href={form.paymentSettingsUrl}
                                className="font-medium text-link underline-offset-4 hover:underline"
                            >
                                Paystack Settings
                            </Link>
                            .
                        </>
                    )}
                </AlertDescription>
            </Alert>

            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardForm onSubmit={preventSubmit}>
                    <CardHeader className="border-b border-border">
                        <CardTitle>Due Details</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-5 p-5">
                        <Field
                            data-invalid={Boolean(
                                dueForm.errors.entry_academic_session_id,
                            )}
                        >
                            <FieldLabel>Student Set</FieldLabel>
                            <StudentSetPicker
                                value={dueForm.data.entry_academic_session_id}
                                onValueChange={(value) =>
                                    dueForm.setData(
                                        'entry_academic_session_id',
                                        value,
                                    )
                                }
                                options={form.assignedSets.map((set) => ({
                                    value: set.entrySession.id.toString(),
                                    entrySession: set.entrySession,
                                    currentLevel: set.currentLevel,
                                    minimumYearsToDegree:
                                        set.minimumYearsToDegree,
                                    meta: set.advisorName ?? undefined,
                                }))}
                                ariaLabel="Student set"
                            />
                            <FieldError>
                                {dueForm.errors.entry_academic_session_id}
                            </FieldError>
                        </Field>
                        <Field data-invalid={Boolean(dueForm.errors.title)}>
                            <FieldLabel htmlFor="due-title">
                                Due Name
                            </FieldLabel>
                            <Input
                                id="due-title"
                                value={dueForm.data.title}
                                onChange={(event) =>
                                    dueForm.setData('title', event.target.value)
                                }
                                placeholder="Departmental Dues 300 Level"
                            />
                            <FieldError>{dueForm.errors.title}</FieldError>
                        </Field>
                        <Field
                            data-invalid={Boolean(dueForm.errors.description)}
                        >
                            <FieldLabel htmlFor="due-description">
                                Short Description
                            </FieldLabel>
                            <Textarea
                                id="due-description"
                                value={dueForm.data.description}
                                onChange={(event) =>
                                    dueForm.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                placeholder="Department levy."
                                rows={3}
                            />
                            <FieldError>
                                {dueForm.errors.description}
                            </FieldError>
                        </Field>
                        <FieldGroup className="grid gap-5 sm:grid-cols-2">
                            <Field
                                data-invalid={Boolean(
                                    dueForm.errors.amount_naira,
                                )}
                            >
                                <FieldLabel htmlFor="due-amount">
                                    Amount (NGN)
                                </FieldLabel>
                                <Input
                                    id="due-amount"
                                    type="number"
                                    min="100"
                                    step="100"
                                    value={dueForm.data.amount_naira}
                                    onChange={(event) =>
                                        dueForm.setData(
                                            'amount_naira',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="15000"
                                />
                                <FieldError>
                                    {dueForm.errors.amount_naira}
                                </FieldError>
                            </Field>
                            <Field
                                data-invalid={Boolean(dueForm.errors.due_on)}
                            >
                                <FieldLabel htmlFor="due-date">
                                    Due Date
                                </FieldLabel>
                                <Input
                                    id="due-date"
                                    type="date"
                                    value={dueForm.data.due_on}
                                    onChange={(event) =>
                                        dueForm.setData(
                                            'due_on',
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldError>{dueForm.errors.due_on}</FieldError>
                            </Field>
                        </FieldGroup>
                    </CardContent>
                    <CardFooter className="justify-end gap-2 bg-muted/20">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={dueForm.processing}
                            onClick={() => submit('draft')}
                        >
                            <RiSave3Line />
                            Save Draft
                        </Button>
                        <Button
                            type="button"
                            disabled={dueForm.processing || !canPublish}
                            aria-disabled={!canPublish}
                            onClick={() => submit('publish')}
                        >
                            <RiSendPlaneLine />
                            Publish
                        </Button>
                    </CardFooter>
                </CardForm>
            </Card>
        </div>
    );
}

export function StaffDueDetailDashboard({
    detail,
}: {
    detail: DashboardStaffDueDetail;
}) {
    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <div className="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
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
            </div>

            <div className="grid gap-3 md:grid-cols-4">
                <MetricCard label="Paid" value={detail.due.paidCount} />
                <MetricCard label="Pending" value={detail.due.pendingCount} />
                <MetricCard
                    label="Collected"
                    value={detail.due.collectedAmount}
                />
                <MetricCard
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
                                        Receipt
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
                                                <PaymentStatusBadge
                                                    status={student.status}
                                                />
                                            </TableCell>
                                            <TableCell className="pr-4 text-right">
                                                {student.receiptUrl ? (
                                                    <ActionLink
                                                        href={
                                                            student.receiptUrl
                                                        }
                                                    >
                                                        Download
                                                    </ActionLink>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        Not paid
                                                    </span>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <EmptyRow colSpan={5}>
                                        No students assigned yet.
                                    </EmptyRow>
                                )}
                            </TableBody>
                        </Table>
                    </ScrollArea>
                </CardContent>
            </Card>
        </div>
    );
}

function MetricCard({
    label,
    value,
}: {
    label: string;
    value: string | number;
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
                <div className="text-2xl font-semibold tracking-normal tabular-nums">
                    {value}
                </div>
            </CardContent>
        </Card>
    );
}

function DueStatusBadge({ status }: { status: string }) {
    const className =
        status === 'Published'
            ? 'border-green-200 bg-green-50 text-green-700 dark:border-green-400/30 dark:bg-green-400/10 dark:text-green-300'
            : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-300';

    return (
        <Badge variant="outline" className={className}>
            {status}
        </Badge>
    );
}

function PaymentStatusBadge({ status }: { status: string }) {
    return (
        <Badge
            variant="outline"
            className={
                status === 'Paid'
                    ? 'border-green-200 bg-green-50 text-green-700 dark:border-green-400/30 dark:bg-green-400/10 dark:text-green-300'
                    : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-300'
            }
        >
            {status}
        </Badge>
    );
}

function paymentAccountForSet(
    accounts: DashboardPaymentAccount[],
    entryAcademicSessionId: string,
): DashboardPaymentAccount | null {
    const setId = Number(entryAcademicSessionId);
    const setAccount = accounts.find(
        (account) =>
            account.status === 'Active' &&
            account.entryAcademicSessionId === setId,
    );

    if (setAccount) {
        return setAccount;
    }

    return (
        accounts.find(
            (account) =>
                account.status === 'Active' &&
                account.entryAcademicSessionId === null,
        ) ?? null
    );
}

function EmptyRow({
    colSpan,
    children,
}: {
    colSpan: number;
    children: string;
}) {
    return (
        <TableRow>
            <TableCell
                colSpan={colSpan}
                className="px-4 py-8 text-muted-foreground"
            >
                {children}
            </TableCell>
        </TableRow>
    );
}
