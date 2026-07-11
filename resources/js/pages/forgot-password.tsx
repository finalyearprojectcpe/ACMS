import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { storeRequest } from '@/actions/App/Http/Controllers/Auth/PasswordResetController';
import {
    AuthCard,
    AuthFormActions,
    AuthFormHeader,
} from '@/components/auth-card';
import { FlashToasts } from '@/components/flash-toasts';
import { Button } from '@/components/ui/button';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { schoolEmailPlaceholder, useCurrentSchool } from '@/lib/school';
import { home } from '@/routes';

export default function ForgotPasswordPage() {
    const school = useCurrentSchool();
    const form = useForm({ email: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.submit(storeRequest());
    }

    return (
        <>
            <Head title="Reset password" />
            <FlashToasts />
            <main className="flex min-h-svh flex-col items-center justify-center p-4 sm:p-6 lg:p-10">
                <div className="w-full max-w-5xl">
                    <AuthCard>
                        <form onSubmit={submit}>
                            <FieldGroup className="gap-7">
                                <AuthFormHeader
                                    title="Reset password"
                                    description="Enter your login email to receive a secure reset link."
                                />
                                <Field
                                    data-invalid={Boolean(form.errors.email)}
                                >
                                    <FieldLabel htmlFor="email">
                                        Email
                                    </FieldLabel>
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        value={form.data.email}
                                        placeholder={schoolEmailPlaceholder(
                                            school,
                                        )}
                                        autoComplete="email"
                                        aria-invalid={Boolean(
                                            form.errors.email,
                                        )}
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
                                <Field>
                                    <AuthFormActions>
                                        <Button
                                            type="submit"
                                            size="lg"
                                            disabled={form.processing}
                                        >
                                            {form.processing
                                                ? 'Sending link...'
                                                : 'Send Reset Link'}
                                        </Button>
                                        <FieldDescription className="text-center">
                                            Remembered your password?{' '}
                                            <Button
                                                asChild
                                                variant="link"
                                                className="h-auto p-0 text-xs text-link"
                                            >
                                                <Link href={home()}>
                                                    Log in
                                                </Link>
                                            </Button>
                                        </FieldDescription>
                                    </AuthFormActions>
                                </Field>
                            </FieldGroup>
                        </form>
                    </AuthCard>
                </div>
            </main>
        </>
    );
}
