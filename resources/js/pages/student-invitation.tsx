import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import {
    clearLookup,
    lookup,
    store,
} from '@/actions/App/Http/Controllers/Auth/StudentInvitationController';
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
import { PasswordInput } from '@/components/ui/password-input';
import { home } from '@/routes';

type StudentInvitation = {
    available: boolean;
    inviteToken: string;
    studentSet: string;
    courseAdviser: string;
    student: { name: string; registrationNumber: string } | null;
};

export default function StudentInvitationPage({
    invitation,
}: {
    invitation: StudentInvitation;
}) {
    return (
        <>
            <Head title={`Join ${invitation.studentSet}`} />
            <FlashToasts />
            <main className="flex min-h-svh flex-col items-center justify-center p-4 sm:p-6 lg:p-10">
                <div className="w-full max-w-5xl">
                    <AuthCard>
                        <div className="flex flex-col gap-7">
                            <AuthFormHeader
                                title={
                                    invitation.available
                                        ? 'Join your student set'
                                        : 'Student signup closed'
                                }
                                description={invitation.studentSet}
                            />
                            {invitation.available ? (
                                invitation.student ? (
                                    <CreateAccount invitation={invitation} />
                                ) : (
                                    <FindStudent invitation={invitation} />
                                )
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    Ask your course adviser to reopen this
                                    invite.
                                </p>
                            )}
                            <p className="text-center text-xs text-muted-foreground">
                                Already have an account?{' '}
                                <Link
                                    className="text-link hover:underline"
                                    href={home()}
                                >
                                    Log in
                                </Link>
                            </p>
                        </div>
                    </AuthCard>
                </div>
            </main>
        </>
    );
}

function FindStudent({ invitation }: { invitation: StudentInvitation }) {
    const form = useForm({ registration_number: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.submit(lookup({ studentSet: invitation.inviteToken }));
    }

    return (
        <form onSubmit={submit} className="flex flex-col gap-5">
            <p className="text-sm text-muted-foreground">
                Enter the registration number on your class list.
            </p>
            <Field data-invalid={Boolean(form.errors.registration_number)}>
                <FieldLabel htmlFor="registration-number">
                    Registration number
                </FieldLabel>
                <Input
                    id="registration-number"
                    autoFocus
                    autoComplete="off"
                    value={form.data.registration_number}
                    onChange={(event) =>
                        form.setData('registration_number', event.target.value)
                    }
                    required
                />
                <FieldError>{form.errors.registration_number}</FieldError>
            </Field>
            <Button type="submit" disabled={form.processing}>
                Find my record
            </Button>
        </form>
    );
}

function CreateAccount({ invitation }: { invitation: StudentInvitation }) {
    const student = invitation.student!;
    const form = useForm({
        email: '',
        password: '',
        password_confirmation: '',
        invitation: '',
    });
    const correction = useForm({});

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.submit(store({ studentSet: invitation.inviteToken }), {
            onSuccess: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <div className="flex flex-col gap-6">
            <MatchedStudentIdentity
                name={student.name}
                registrationNumber={student.registrationNumber}
            />
            <form onSubmit={submit} className="flex flex-col gap-5">
                <FieldGroup>
                    <Field data-invalid={Boolean(form.errors.email)}>
                        <FieldLabel htmlFor="student-email">
                            Email address
                        </FieldLabel>
                        <Input
                            id="student-email"
                            type="email"
                            autoComplete="email"
                            value={form.data.email}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                            required
                        />
                        <FieldError>{form.errors.email}</FieldError>
                    </Field>
                    <Field data-invalid={Boolean(form.errors.password)}>
                        <FieldLabel htmlFor="student-password">
                            Password
                        </FieldLabel>
                        <PasswordInput
                            id="student-password"
                            autoComplete="new-password"
                            value={form.data.password}
                            onChange={(event) =>
                                form.setData('password', event.target.value)
                            }
                            required
                        />
                        <FieldError>{form.errors.password}</FieldError>
                    </Field>
                    <Field
                        data-invalid={Boolean(
                            form.errors.password_confirmation,
                        )}
                    >
                        <FieldLabel htmlFor="student-confirm-password">
                            Confirm password
                        </FieldLabel>
                        <PasswordInput
                            id="student-confirm-password"
                            autoComplete="new-password"
                            value={form.data.password_confirmation}
                            onChange={(event) =>
                                form.setData(
                                    'password_confirmation',
                                    event.target.value,
                                )
                            }
                            required
                        />
                        <FieldError>
                            {form.errors.password_confirmation}
                        </FieldError>
                    </Field>
                </FieldGroup>
                {form.errors.invitation && (
                    <p className="text-sm text-destructive">
                        {form.errors.invitation}
                    </p>
                )}
                <div className="flex flex-wrap items-center gap-4">
                    <Button type="submit" disabled={form.processing}>
                        Request to join
                    </Button>
                    <Button
                        type="button"
                        variant="link"
                        className="px-0 text-link"
                        disabled={correction.processing}
                        onClick={() =>
                            correction.submit(
                                clearLookup({
                                    studentSet: invitation.inviteToken,
                                }),
                            )
                        }
                    >
                        Wrong student?
                    </Button>
                </div>
            </form>
        </div>
    );
}
