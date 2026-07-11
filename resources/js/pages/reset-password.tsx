import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { storeReset } from '@/actions/App/Http/Controllers/Auth/PasswordResetController';
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
import { PasswordInput } from '@/components/ui/password-input';
import { home } from '@/routes';

type ResetPasswordFormData = {
    token: string;
    email: string;
    password: string;
    password_confirmation: string;
};

export default function ResetPasswordPage({
    token,
    email,
}: {
    token: string;
    email: string;
}) {
    const form = useForm<ResetPasswordFormData>({
        token,
        email,
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.submit(storeReset(), {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <>
            <Head title="Choose a new password" />
            <FlashToasts />
            <main className="flex min-h-svh flex-col items-center justify-center p-4 sm:p-6 lg:p-10">
                <div className="w-full max-w-5xl">
                    <AuthCard>
                        <form onSubmit={submit}>
                            <FieldGroup className="gap-7">
                                <AuthFormHeader
                                    title="Choose a new password"
                                    description="Use a strong password you do not use elsewhere."
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
                                <Field
                                    data-invalid={Boolean(form.errors.password)}
                                >
                                    <FieldLabel htmlFor="password">
                                        New password
                                    </FieldLabel>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        value={form.data.password}
                                        autoComplete="new-password"
                                        aria-invalid={Boolean(
                                            form.errors.password,
                                        )}
                                        onChange={(event) =>
                                            form.setData(
                                                'password',
                                                event.target.value,
                                            )
                                        }
                                        required
                                    />
                                    <FieldError>
                                        {form.errors.password}
                                    </FieldError>
                                </Field>
                                <Field
                                    data-invalid={Boolean(
                                        form.errors.password_confirmation,
                                    )}
                                >
                                    <FieldLabel htmlFor="password-confirmation">
                                        Confirm new password
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
                                                ? 'Saving password...'
                                                : 'Save New Password'}
                                        </Button>
                                        <FieldDescription className="text-center">
                                            <Button
                                                asChild
                                                variant="link"
                                                className="h-auto p-0 text-xs text-link"
                                            >
                                                <Link href={home()}>
                                                    Back to login
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
