import { Link, useForm } from '@inertiajs/react';
import {
    RiErrorWarningLine,
    RiSave3Line,
    RiSendPlaneLine,
} from '@remixicon/react';
import type { FormEvent } from 'react';

import {
    store as storeAdviserDue,
    update as updateAdviserDue,
} from '@/actions/App/Http/Controllers/DueController';
import { ActionLink } from '@/components/action-link';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatNaira } from '@/lib/currency';
import type { DashboardAdviserDueForm } from '@/types';

type DueFormData = {
    student_set_id: string;
    academic_session_id: string;
    academic_session_semester_id: string;
    title: string;
    description: string;
    amount_naira: string;
    due_on: string;
};

export function AdviserDueFormDashboard({
    form,
}: {
    form: DashboardAdviserDueForm;
}) {
    const dueForm = useForm<DueFormData>({
        student_set_id: form.defaults.studentSetId?.toString() ?? '',
        academic_session_id: form.defaults.academicSessionId?.toString() ?? '',
        academic_session_semester_id:
            form.defaults.academicSessionSemesterId?.toString() ?? 'whole',
        title: form.defaults.title,
        description: form.defaults.description,
        amount_naira: form.defaults.amountNaira,
        due_on: form.defaults.dueOn,
    });
    const payoutAccount = form.paymentAccounts[0] ?? null;
    const selectedSession = form.sessions.find(
        (session) => session.id.toString() === dueForm.data.academic_session_id,
    );
    const publishedAmountChanged =
        form.mode === 'edit' &&
        form.due?.status === 'Published' &&
        Number(dueForm.data.amount_naira) !== Number(form.defaults.amountNaira);
    const updatedAmount = formatNaira(Number(dueForm.data.amount_naira) || 0);

    function submit(action: 'draft' | 'publish') {
        dueForm.transform((data) => ({
            ...data,
            academic_session_semester_id:
                data.academic_session_semester_id === 'whole'
                    ? null
                    : data.academic_session_semester_id,
            action,
        }));
        dueForm.submit(
            form.mode === 'edit' && form.due
                ? updateAdviserDue(form.due.publicId)
                : storeAdviserDue(),
            { onFinish: () => dueForm.transform((data) => data) },
        );
    }

    function preventSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
    }

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-4">
            <Card className="gap-0 border border-border bg-background py-0 shadow-sm ring-1 ring-border/70">
                <CardForm onSubmit={preventSubmit} className="gap-0">
                    <CardHeader className="gap-1 border-b border-border p-5">
                        <CardTitle className="text-xl">
                            {form.mode === 'edit' ? 'Edit Due' : 'Create Due'}
                        </CardTitle>
                        <p className="text-sm text-muted-foreground">
                            {form.selectedSetName}
                        </p>
                    </CardHeader>
                    <CardContent className="grid gap-5 p-5">
                        {!payoutAccount ? (
                            <Alert className="border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
                                <RiErrorWarningLine className="size-4" />
                                <AlertTitle>Payout account needed</AlertTitle>
                                <AlertDescription>
                                    Save a draft now, or{' '}
                                    <ActionLink href={form.paymentSettingsUrl}>
                                        connect a payout account
                                    </ActionLink>{' '}
                                    to publish.
                                </AlertDescription>
                            </Alert>
                        ) : null}
                        <FieldError>{dueForm.errors.student_set_id}</FieldError>
                        <FieldGroup className="grid gap-5 sm:grid-cols-2">
                            <Field
                                data-invalid={Boolean(
                                    dueForm.errors.academic_session_id,
                                )}
                            >
                                <FieldLabel>Academic Session</FieldLabel>
                                <Select
                                    value={dueForm.data.academic_session_id}
                                    disabled={
                                        !form.due?.canChangePeriod &&
                                        form.mode === 'edit'
                                    }
                                    onValueChange={(value) =>
                                        dueForm.setData({
                                            ...dueForm.data,
                                            academic_session_id: value,
                                            academic_session_semester_id:
                                                'whole',
                                        })
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Select a session" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {form.sessions.map((session) => (
                                            <SelectItem
                                                key={session.id}
                                                value={session.id.toString()}
                                            >
                                                {session.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <FieldError>
                                    {dueForm.errors.academic_session_id}
                                </FieldError>
                            </Field>
                            <Field
                                data-invalid={Boolean(
                                    dueForm.errors.academic_session_semester_id,
                                )}
                            >
                                <FieldLabel>Applies To</FieldLabel>
                                <Select
                                    value={
                                        dueForm.data
                                            .academic_session_semester_id
                                    }
                                    disabled={
                                        !selectedSession ||
                                        (!form.due?.canChangePeriod &&
                                            form.mode === 'edit')
                                    }
                                    onValueChange={(value) =>
                                        dueForm.setData(
                                            'academic_session_semester_id',
                                            value,
                                        )
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Select a period" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="whole">
                                            Whole Session
                                        </SelectItem>
                                        {selectedSession?.semesters.map(
                                            (semester) => (
                                                <SelectItem
                                                    key={semester.id}
                                                    value={semester.id.toString()}
                                                >
                                                    {semester.name}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <FieldError>
                                    {
                                        dueForm.errors
                                            .academic_session_semester_id
                                    }
                                </FieldError>
                            </Field>
                        </FieldGroup>
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
                                placeholder="Student Association Dues"
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
                                placeholder="Annual student association levy."
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
                                    Amount (₦)
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
                                    disabled={
                                        form.mode === 'edit' &&
                                        !form.due?.canChangeAmount
                                    }
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
                        <Button type="button" variant="ghost" asChild>
                            <Link href={form.returnUrl}>Cancel</Link>
                        </Button>
                        {form.mode === 'edit' &&
                        form.due?.status === 'Published' ? (
                            publishedAmountChanged ? (
                                <AmountChangeConfirmation
                                    amount={updatedAmount}
                                    processing={dueForm.processing}
                                    onConfirm={() => submit('draft')}
                                />
                            ) : (
                                <Button
                                    type="button"
                                    disabled={dueForm.processing}
                                    onClick={() => submit('draft')}
                                >
                                    <RiSave3Line />
                                    Save Changes
                                </Button>
                            )
                        ) : (
                            <>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={dueForm.processing}
                                    onClick={() => submit('draft')}
                                >
                                    <RiSave3Line />
                                    {form.mode === 'edit'
                                        ? 'Save Changes'
                                        : 'Save Draft'}
                                </Button>
                                <Button
                                    type="button"
                                    disabled={
                                        dueForm.processing || !payoutAccount
                                    }
                                    onClick={() => submit('publish')}
                                >
                                    <RiSendPlaneLine />
                                    Publish
                                </Button>
                            </>
                        )}
                    </CardFooter>
                </CardForm>
            </Card>
        </div>
    );
}

function AmountChangeConfirmation({
    amount,
    processing,
    onConfirm,
}: {
    amount: string;
    processing: boolean;
    onConfirm: () => void;
}) {
    return (
        <AlertDialog>
            <AlertDialogTrigger asChild>
                <Button type="button" disabled={processing}>
                    <RiSave3Line />
                    Save Changes
                </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        Change this due to {amount}?
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        Students who still owe this due will see the new amount.
                        Completed payments and receipts keep the amount already
                        paid.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Keep Current Amount</AlertDialogCancel>
                    <AlertDialogAction onClick={onConfirm}>
                        Change Due Amount
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}
