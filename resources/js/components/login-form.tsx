import { Link, useForm } from '@inertiajs/react';
import type { ComponentProps, FormEvent } from 'react';

import { store as loginUser } from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
import { createRequest as requestPasswordReset } from '@/actions/App/Http/Controllers/Auth/PasswordResetController';
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
import { schoolEmailPlaceholder, useCurrentSchool } from '@/lib/school';
import {
    staff as staffSignup,
    student as studentSignup,
} from '@/routes/signup';

export function LoginForm({ className, ...props }: ComponentProps<'div'>) {
    const school = useCurrentSchool();
    const emailPlaceholder = schoolEmailPlaceholder(school);
    const form = useForm({
        email: '',
        password: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.submit(loginUser(), {
            onSuccess: () => form.reset('password'),
        });
    }

    return (
        <AuthCard className={className} {...props}>
            <form onSubmit={submit}>
                <FieldGroup className="gap-7">
                    <AuthFormHeader
                        title="Welcome back"
                        description="Log in with your email and password."
                    />

                    <Field data-invalid={Boolean(form.errors.email)}>
                        <FieldLabel htmlFor="email">Email</FieldLabel>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            value={form.data.email}
                            placeholder={emailPlaceholder}
                            autoComplete="email"
                            aria-invalid={Boolean(form.errors.email)}
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                            required
                        />
                        <FieldError>{form.errors.email}</FieldError>
                    </Field>

                    <Field data-invalid={Boolean(form.errors.password)}>
                        <div className="flex items-center justify-between gap-4">
                            <FieldLabel htmlFor="password">Password</FieldLabel>
                            <Link
                                href={requestPasswordReset()}
                                className="text-xs font-medium text-link hover:underline"
                            >
                                Forgot password?
                            </Link>
                        </div>
                        <PasswordInput
                            id="password"
                            name="password"
                            value={form.data.password}
                            autoComplete="current-password"
                            aria-invalid={Boolean(form.errors.password)}
                            onChange={(event) =>
                                form.setData('password', event.target.value)
                            }
                            required
                        />
                        <FieldError>{form.errors.password}</FieldError>
                    </Field>

                    <Field>
                        <AuthFormActions>
                            <Button
                                type="submit"
                                size="lg"
                                disabled={form.processing}
                            >
                                {form.processing ? 'Logging in...' : 'Log in'}
                            </Button>
                            <FieldDescription className="flex flex-wrap items-center justify-center gap-x-2 gap-y-1 text-center">
                                <span>New to GradeManager?</span>
                                <Button
                                    asChild
                                    variant="link"
                                    className="h-auto p-0 text-xs"
                                >
                                    <Link href={studentSignup()}>
                                        Create student account
                                    </Link>
                                </Button>
                                <span className="text-muted-foreground">/</span>
                                <Button
                                    asChild
                                    variant="link"
                                    className="h-auto p-0 text-xs"
                                >
                                    <Link href={staffSignup()}>
                                        Create staff account
                                    </Link>
                                </Button>
                            </FieldDescription>
                        </AuthFormActions>
                    </Field>
                </FieldGroup>
            </form>
        </AuthCard>
    );
}
