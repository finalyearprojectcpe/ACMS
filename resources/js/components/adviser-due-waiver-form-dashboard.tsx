import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { waive as waiveStudentDue } from '@/actions/App/Http/Controllers/DueController';
import { CopyableValue } from '@/components/copyable-value';
import { PersonCell } from '@/components/person-cell';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardForm,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';
import type { DashboardAdviserDueWaiverForm } from '@/types';

export function AdviserDueWaiverFormDashboard({
    form,
}: {
    form: DashboardAdviserDueWaiverForm;
}) {
    const waiverForm = useForm({ reason: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        waiverForm.submit(
            waiveStudentDue({
                due: form.due.id,
                studentDueAssignment: form.assignment.id,
            }),
        );
    }

    return (
        <div className="mx-auto flex w-full max-w-2xl flex-col gap-4">
            <div>
                <h1 className="text-xl font-semibold tracking-normal">
                    Waive Payment Requirement
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Record that this student does not need to pay this due.
                </p>
            </div>

            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardForm onSubmit={submit}>
                    <CardHeader className="border-b border-border">
                        <CardTitle>{form.due.title}</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-5 p-5">
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <PersonCell
                                name={form.assignment.studentName}
                                avatar={form.assignment.studentAvatar}
                            />
                            <CopyableValue
                                value={form.assignment.registrationNumber}
                                className="h-9 w-fit"
                                valueClassName="text-xs"
                            />
                        </div>
                        <div className="flex items-center justify-between gap-4 border-y border-border py-3 text-sm">
                            <span className="text-muted-foreground">
                                Payment being waived
                            </span>
                            <span className="font-semibold tabular-nums">
                                {form.due.amount}
                            </span>
                        </div>
                        <Field data-invalid={Boolean(waiverForm.errors.reason)}>
                            <FieldLabel htmlFor="waiver-reason">
                                Reason (Optional)
                            </FieldLabel>
                            <Textarea
                                id="waiver-reason"
                                value={waiverForm.data.reason}
                                onChange={(event) =>
                                    waiverForm.setData(
                                        'reason',
                                        event.target.value,
                                    )
                                }
                                placeholder="Scholarship, duplicate charge, or another clear reason"
                                rows={3}
                            />
                            <FieldError>{waiverForm.errors.reason}</FieldError>
                        </Field>
                    </CardContent>
                    <CardFooter className="justify-end bg-muted/20">
                        <Button
                            type="submit"
                            variant="destructive"
                            disabled={waiverForm.processing}
                        >
                            Waive Payment Requirement
                        </Button>
                    </CardFooter>
                </CardForm>
            </Card>
        </div>
    );
}
