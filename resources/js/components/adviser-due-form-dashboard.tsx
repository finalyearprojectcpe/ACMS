import { Link, useForm } from '@inertiajs/react';
import {
    RiBankCardLine,
    RiErrorWarningLine,
    RiSave3Line,
    RiSendPlaneLine,
} from '@remixicon/react';
import type { FormEvent } from 'react';

import {
    store as storeAdviserDue,
    update as updateAdviserDue,
} from '@/actions/App/Http/Controllers/DueController';
import { StudentSetPicker } from '@/components/student-set-picker';
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
import { Textarea } from '@/components/ui/textarea';
import type { DashboardAdviserDueForm } from '@/types';

type DueFormData = {
    student_set_id: string;
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
        title: form.defaults.title,
        description: form.defaults.description,
        amount_naira: form.defaults.amountNaira,
        due_on: form.defaults.dueOn,
    });
    const payoutAccount = form.paymentAccounts[0] ?? null;
    const publishedAmountChanged =
        form.mode === 'edit' &&
        form.due?.status === 'Published' &&
        Number(dueForm.data.amount_naira) !== Number(form.defaults.amountNaira);
    const updatedAmount = new Intl.NumberFormat('en-NG', {
        style: 'currency',
        currency: 'NGN',
        minimumFractionDigits: 0,
        maximumFractionDigits: 2,
    }).format(Number(dueForm.data.amount_naira) || 0);

    function submit(action: 'draft' | 'publish') {
        dueForm.transform((data) => ({ ...data, action }));
        dueForm.submit(
            form.mode === 'edit' && form.due
                ? updateAdviserDue(form.due.id)
                : storeAdviserDue(),
            { onFinish: () => dueForm.transform((data) => data) },
        );
    }

    function preventSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
    }

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-4">
            <div>
                <h1 className="text-xl font-semibold tracking-normal">
                    {form.mode === 'edit' ? 'Edit Due' : 'Create Due'}
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    {form.mode === 'edit'
                        ? 'Update the details students see for this due.'
                        : 'Save a draft or publish the due to students.'}
                </p>
            </div>

            <Alert
                className={
                    payoutAccount
                        ? 'border-green-200 bg-green-50 text-green-900 dark:border-green-400/30 dark:bg-green-400/10 dark:text-green-100'
                        : 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100'
                }
            >
                {payoutAccount ? (
                    <RiBankCardLine className="size-4" />
                ) : (
                    <RiErrorWarningLine className="size-4" />
                )}
                <AlertTitle>
                    {payoutAccount
                        ? 'Payout account ready'
                        : 'Payout account needed'}
                </AlertTitle>
                <AlertDescription>
                    {payoutAccount ? (
                        <>
                            Payments will settle into{' '}
                            <strong>{payoutAccount.label}</strong>.
                        </>
                    ) : (
                        <>
                            You can save a draft. Connect an account in{' '}
                            <Link
                                href={form.paymentSettingsUrl}
                                className="font-medium text-link underline-offset-4 hover:underline"
                            >
                                Paystack Settings
                            </Link>{' '}
                            before publishing.
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
                                dueForm.errors.student_set_id,
                            )}
                        >
                            <FieldLabel>Student Set</FieldLabel>
                            <StudentSetPicker
                                value={dueForm.data.student_set_id}
                                onValueChange={(value) =>
                                    dueForm.setData('student_set_id', value)
                                }
                                options={form.assignedSets.map((set) => ({
                                    value: set.id.toString(),
                                    entrySession: set.entrySession,
                                    currentLevel: set.currentLevel,
                                    minimumYearsToDegree:
                                        set.minimumYearsToDegree,
                                    meta: set.advisorName ?? undefined,
                                }))}
                                ariaLabel="Student set"
                                disabled={
                                    form.mode === 'edit' &&
                                    !form.due?.canChangeSet
                                }
                            />
                            <FieldError>
                                {dueForm.errors.student_set_id}
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
