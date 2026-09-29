import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { store } from '@/actions/App/Http/Controllers/Auth/StudentInvitationController';
import {
    AuthCard,
    AuthFormActions,
    AuthFormHeader,
} from '@/components/auth-card';
import { FlashToasts } from '@/components/flash-toasts';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
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
                    {invitation.available ? (
                        <StudentInvitationForm invitation={invitation} />
                    ) : (
                        <ClosedInvitation invitation={invitation} />
                    )}
                </div>
            </main>
        </>
    );
}

function StudentInvitationForm({
    invitation,
}: {
    invitation: StudentInvitation;
}) {
    const form = useForm({
        surname: '',
        other_names: '',
        registration_number: '',
        email: '',
        password: '',
        password_confirmation: '',
        invitation: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.submit(store({ studentSet: invitation.inviteToken }), {
            onSuccess: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <AuthCard>
            <form onSubmit={submit}>
                <FieldGroup className="gap-7">
                    <AuthFormHeader
                        title={invitation.studentSet}
                        description={`Create an account to request access to ${invitation.courseAdviser}'s set.`}
                    />

                    {form.errors.invitation ? (
                        <Alert variant="destructive">
                            <AlertDescription>
                                {form.errors.invitation}
                            </AlertDescription>
                        </Alert>
                    ) : null}

                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field data-invalid={Boolean(form.errors.surname)}>
                            <FieldLabel htmlFor="surname">Surname</FieldLabel>
                            <Input
                                id="surname"
                                value={form.data.surname}
                                autoComplete="family-name"
                                aria-invalid={Boolean(form.errors.surname)}
                                onChange={(event) =>
                                    form.setData('surname', event.target.value)
                                }
                                required
                            />
                            <FieldError>{form.errors.surname}</FieldError>
                        </Field>
                        <Field data-invalid={Boolean(form.errors.other_names)}>
                            <FieldLabel htmlFor="other-names">
                                Other Names
                            </FieldLabel>
                            <Input
                                id="other-names"
                                value={form.data.other_names}
                                autoComplete="given-name"
                                aria-invalid={Boolean(form.errors.other_names)}
                                onChange={(event) =>
                                    form.setData(
                                        'other_names',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                            <FieldError>{form.errors.other_names}</FieldError>
                        </Field>
                    </div>

                    <Field
                        data-invalid={Boolean(form.errors.registration_number)}
                    >
                        <FieldLabel htmlFor="registration-number">
                            Registration Number
                        </FieldLabel>
                        <Input
                            id="registration-number"
                            value={form.data.registration_number}
                            maxLength={255}
                            autoComplete="off"
                            aria-invalid={Boolean(
                                form.errors.registration_number,
                            )}
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

                    <Field data-invalid={Boolean(form.errors.email)}>
                        <FieldLabel htmlFor="student-email">Email</FieldLabel>
                        <Input
                            id="student-email"
                            type="email"
                            value={form.data.email}
                            placeholder="student@example.com"
                            autoComplete="email"
                            aria-invalid={Boolean(form.errors.email)}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                            required
                        />
                        <FieldError>{form.errors.email}</FieldError>
                    </Field>

                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field data-invalid={Boolean(form.errors.password)}>
                            <FieldLabel htmlFor="student-password">
                                Password
                            </FieldLabel>
                            <PasswordInput
                                id="student-password"
                                value={form.data.password}
                                autoComplete="new-password"
                                aria-invalid={Boolean(form.errors.password)}
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
                                Confirm Password
                            </FieldLabel>
                            <PasswordInput
                                id="student-confirm-password"
                                value={form.data.password_confirmation}
                                autoComplete="new-password"
                                aria-invalid={Boolean(
                                    form.errors.password_confirmation,
                                )}
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
                    </div>

                    <Field>
                        <AuthFormActions>
                            <Button
                                type="submit"
                                size="lg"
                                disabled={form.processing}
                            >
                                {form.processing
                                    ? 'Creating account...'
                                    : 'Send join request'}
                            </Button>
                            <LoginPrompt />
                        </AuthFormActions>
                    </Field>
                </FieldGroup>
            </form>
        </AuthCard>
    );
}

function ClosedInvitation({ invitation }: { invitation: StudentInvitation }) {
    return (
        <AuthCard>
            <div className="flex flex-col gap-7">
                <AuthFormHeader
                    title="Student signup closed"
                    description={`${invitation.studentSet} is not accepting new signups.`}
                />
                <p className="text-sm text-muted-foreground">
                    Ask {invitation.courseAdviser} to reopen the link if you
                    still need to join this set.
                </p>
                <LoginPrompt />
            </div>
        </AuthCard>
    );
}

function LoginPrompt() {
    return (
        <FieldDescription className="text-center">
            Already have an account?{' '}
            <Button
                asChild
                variant="link"
                className="h-auto p-0 text-xs text-link"
            >
                <Link href={home()}>Log in</Link>
            </Button>
        </FieldDescription>
    );
}
