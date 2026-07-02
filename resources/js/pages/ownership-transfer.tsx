import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { store as acceptFacultyHeadTransfer } from '@/actions/App/Http/Controllers/FacultyHeadTransferController';
import { store as acceptOwnershipTransfer } from '@/actions/App/Http/Controllers/SchoolAdminTransferController';
import {
    AuthCard,
    AuthFormActions,
    AuthFormHeader,
} from '@/components/auth-card';
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

type OwnershipTransfer = {
    token: string;
    kind?: 'school_admin' | 'faculty_head';
    schoolName: string;
    schoolAbbreviation: string;
    facultyName?: string;
    facultyAbbreviation?: string;
    recipientEmail: string;
    recipientName: string | null;
    expiresAt: string;
};

type OwnershipTransferFormData = {
    name: string;
    access_code: string;
    password: string;
    password_confirmation: string;
};

export default function OwnershipTransferPage({
    transfer,
    statusMessage,
}: {
    transfer: OwnershipTransfer | null;
    statusMessage: string | null;
}) {
    if (transfer === null) {
        return (
            <>
                <Head title="Ownership invite" />
                <main className="flex min-h-svh flex-col items-center justify-center p-4 sm:p-6 lg:p-10">
                    <div className="w-full max-w-5xl">
                        <AuthCard>
                            <FieldGroup className="gap-7">
                                <AuthFormHeader
                                    title="Ownership invite"
                                    description={
                                        statusMessage ??
                                        'This ownership invite cannot be used.'
                                    }
                                />
                                <AuthFormActions>
                                    <Button asChild size="lg">
                                        <a href={home().url}>Return to login</a>
                                    </Button>
                                </AuthFormActions>
                            </FieldGroup>
                        </AuthCard>
                    </div>
                </main>
            </>
        );
    }

    const pageTitle =
        transfer.kind === 'faculty_head'
            ? `${transfer.facultyAbbreviation ?? transfer.schoolAbbreviation} faculty head invite`
            : `${transfer.schoolAbbreviation} ownership`;

    return (
        <>
            <Head title={pageTitle} />
            <main className="flex min-h-svh flex-col items-center justify-center p-4 sm:p-6 lg:p-10">
                <div className="w-full max-w-5xl">
                    <OwnershipTransferForm transfer={transfer} />
                </div>
            </main>
        </>
    );
}

function OwnershipTransferForm({ transfer }: { transfer: OwnershipTransfer }) {
    const isFacultyHeadTransfer = transfer.kind === 'faculty_head';
    const transferTitle = isFacultyHeadTransfer
        ? 'Take over faculty head'
        : 'Take over school admin';
    const transferDescription = isFacultyHeadTransfer
        ? `Complete the ${transfer.facultyAbbreviation ?? transfer.schoolAbbreviation} faculty head transfer.`
        : `Complete the ${transfer.schoolAbbreviation} school admin transfer.`;
    const transferAction = isFacultyHeadTransfer
        ? acceptFacultyHeadTransfer(transfer.token)
        : acceptOwnershipTransfer(transfer.token);
    const form = useForm<OwnershipTransferFormData>({
        name: transfer.recipientName ?? '',
        access_code: '',
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.submit(transferAction, {
            onSuccess: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <AuthCard>
            <form onSubmit={submit}>
                <FieldGroup className="gap-7">
                    <AuthFormHeader
                        title={transferTitle}
                        description={transferDescription}
                    />

                    <div className="grid gap-1 border border-border bg-muted/40 p-3 text-sm">
                        <span className="text-xs font-medium tracking-normal text-muted-foreground">
                            Invite details
                        </span>
                        <span className="font-medium text-foreground">
                            {isFacultyHeadTransfer
                                ? (transfer.facultyName ?? transfer.schoolName)
                                : transfer.schoolName}
                        </span>
                        {isFacultyHeadTransfer ? (
                            <span className="text-muted-foreground">
                                {transfer.schoolName}
                            </span>
                        ) : null}
                        <span className="text-muted-foreground">
                            {transfer.recipientEmail}
                        </span>
                        <span className="text-xs text-muted-foreground">
                            Expires {transfer.expiresAt}
                        </span>
                    </div>

                    <Field data-invalid={Boolean(form.errors.name)}>
                        <FieldLabel htmlFor="name">Full name</FieldLabel>
                        <Input
                            id="name"
                            name="name"
                            type="text"
                            value={form.data.name}
                            autoComplete="name"
                            aria-invalid={Boolean(form.errors.name)}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            required
                        />
                        <FieldError>{form.errors.name}</FieldError>
                    </Field>

                    <Field data-invalid={Boolean(form.errors.access_code)}>
                        <FieldLabel htmlFor="access-code">
                            Access code
                        </FieldLabel>
                        <Input
                            id="access-code"
                            name="access_code"
                            type="text"
                            value={form.data.access_code}
                            placeholder="Enter access code"
                            autoComplete="one-time-code"
                            aria-invalid={Boolean(form.errors.access_code)}
                            onChange={(event) =>
                                form.setData('access_code', event.target.value)
                            }
                            required
                        />
                        <FieldDescription>
                            Enter the access code issued with this ownership
                            invite.
                        </FieldDescription>
                        <FieldError>{form.errors.access_code}</FieldError>
                    </Field>

                    <Field data-invalid={Boolean(form.errors.password)}>
                        <FieldLabel htmlFor="password">Password</FieldLabel>
                        <PasswordInput
                            id="password"
                            name="password"
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
                        <FieldLabel htmlFor="password-confirmation">
                            Confirm password
                        </FieldLabel>
                        <PasswordInput
                            id="password-confirmation"
                            name="password_confirmation"
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

                    <Field>
                        <AuthFormActions>
                            <Button
                                type="submit"
                                size="lg"
                                disabled={form.processing}
                            >
                                {form.processing
                                    ? 'Completing transfer...'
                                    : 'Complete transfer'}
                            </Button>
                        </AuthFormActions>
                    </Field>
                </FieldGroup>
            </form>
        </AuthCard>
    );
}
