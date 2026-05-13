import { Link, useForm } from '@inertiajs/react';
import type { ComponentProps, FormEvent } from 'react';

import { store as loginUser } from '@/actions/App/Http/Controllers/Auth/AuthenticatedSessionController';
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
import { signup } from '@/routes';

export function LoginForm({ className, ...props }: ComponentProps<'div'>) {
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
                <FieldGroup className="gap-6">
                    <AuthFormHeader
                        title="Welcome back"
                        description="Login with your email and password."
                    />

                    <Field data-invalid={Boolean(form.errors.email)}>
                        <FieldLabel htmlFor="email">Email</FieldLabel>
                        <Input
                            id="email"
                            name="email"
                            type="email"
                            value={form.data.email}
                            placeholder="name@school.edu"
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
                        <FieldLabel htmlFor="password">Password</FieldLabel>
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
                                {form.processing ? 'Logging in...' : 'Login'}
                            </Button>
                            <FieldDescription className="text-center">
                                New to GradeManager?{' '}
                                <Button
                                    asChild
                                    variant="link"
                                    className="h-auto p-0 text-xs"
                                >
                                    <Link href={signup()}>
                                        Create an account
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
