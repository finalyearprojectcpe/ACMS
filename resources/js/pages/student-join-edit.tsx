import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { AuthCard, AuthFormHeader } from '@/components/auth-card';
import { FlashToasts } from '@/components/flash-toasts';
import { MatchedStudentIdentity } from '@/components/matched-student-identity';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { status, update } from '@/routes/student-joins';

type JoinEdit = {
    set: string;
    officialName: string;
    email: string;
    registrationNumber: string;
    status: 'pending' | 'rejected';
    reason: string | null;
};

export default function StudentJoinEditPage({
    request,
}: {
    request: JoinEdit;
}) {
    const form = useForm<{ email: string; registration_number?: string }>({
        email: request.email,
        ...(request.status === 'rejected'
            ? { registration_number: request.registrationNumber }
            : {}),
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.submit(update());
    }

    return (
        <>
            <Head title="Correct student request" />
            <FlashToasts />
            <main className="flex min-h-svh flex-col items-center justify-center p-4 sm:p-6 lg:p-10">
                <div className="w-full max-w-5xl">
                    <AuthCard>
                        <form onSubmit={submit} className="flex flex-col gap-6">
                            <AuthFormHeader
                                title={
                                    request.status === 'rejected'
                                        ? 'Correct your request'
                                        : 'Update your details'
                                }
                                description={request.set}
                            />
                            {request.reason ? (
                                <div className="border-l-2 border-destructive bg-destructive/5 px-4 py-3 text-sm">
                                    <p className="font-medium text-destructive">
                                        Adviser feedback
                                    </p>
                                    <p className="mt-1">{request.reason}</p>
                                </div>
                            ) : null}
                            <MatchedStudentIdentity
                                name={request.officialName}
                                registrationNumber={
                                    request.status === 'pending'
                                        ? request.registrationNumber
                                        : undefined
                                }
                            />
                            <FieldGroup>
                                <Field
                                    data-invalid={Boolean(form.errors.email)}
                                >
                                    <FieldLabel htmlFor="join-email">
                                        Email address
                                    </FieldLabel>
                                    <Input
                                        id="join-email"
                                        type="email"
                                        value={form.data.email}
                                        onChange={(event) =>
                                            form.setData(
                                                'email',
                                                event.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <FieldError>{form.errors.email}</FieldError>
                                </Field>
                                {request.status === 'rejected' && (
                                    <Field
                                        data-invalid={Boolean(
                                            form.errors.registration_number,
                                        )}
                                    >
                                        <FieldLabel htmlFor="join-registration-number">
                                            Registration number
                                        </FieldLabel>
                                        <Input
                                            id="join-registration-number"
                                            value={
                                                form.data.registration_number ??
                                                ''
                                            }
                                            maxLength={255}
                                            onChange={(event) =>
                                                form.setData(
                                                    'registration_number',
                                                    event.target.value,
                                                )
                                            }
                                            required
                                        />
                                        <FieldError>
                                            {form.errors.registration_number}
                                        </FieldError>
                                    </Field>
                                )}
                            </FieldGroup>
                            <div className="flex flex-wrap gap-3">
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                >
                                    {request.status === 'rejected'
                                        ? 'Send for review'
                                        : 'Save and verify email'}
                                </Button>
                                <Button asChild variant="outline">
                                    <Link href={status()}>Back to request</Link>
                                </Button>
                            </div>
                        </form>
                    </AuthCard>
                </div>
            </main>
        </>
    );
}
