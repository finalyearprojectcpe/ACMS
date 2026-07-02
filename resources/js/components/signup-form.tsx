import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { ComponentProps, FormEvent } from 'react';

import {
    storeStaff,
    storeStudent,
    verifyStaff,
    verifyStudent,
} from '@/actions/App/Http/Controllers/Auth/RegisteredUserController';
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
import { home } from '@/routes';
import type { AccountType, SignupActivation } from '@/types';

type SignupFormData = {
    email: string;
    registration_number: string;
    access_code: string;
    password: string;
    password_confirmation: string;
};

export function SignupForm({
    className,
    accountType,
    activation,
    ...props
}: ComponentProps<'div'> & {
    accountType: AccountType;
    activation: SignupActivation;
}) {
    const school = useCurrentSchool();
    const emailPlaceholder = schoolEmailPlaceholder(school);
    const isStudent = accountType === 'student';
    const [stepOverride, setStepOverride] = useState<
        SignupActivation['step'] | null
    >(null);
    const step = stepOverride ?? activation.step;
    const form = useForm<SignupFormData>({
        email: '',
        registration_number: '',
        access_code: '',
        password: '',
        password_confirmation: '',
    });
    const isVerifyStep = step === 'verify';
    const title = isStudent ? 'Student account' : 'Staff account';
    const description = isVerifyStep
        ? 'Enter the details issued to you to confirm this account.'
        : isStudent
          ? 'Set your login email and password.'
          : 'Set your login password.';
    const identifierLabel = isStudent ? 'Registration number' : 'Email';
    const identifierValue =
        activation.identifier ??
        (isStudent ? form.data.registration_number : form.data.email);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.submit(
            isVerifyStep
                ? isStudent
                    ? verifyStudent()
                    : verifyStaff()
                : isStudent
                  ? storeStudent()
                  : storeStaff(),
            {
                onSuccess: () => {
                    if (isVerifyStep) {
                        setStepOverride(null);
                        form.reset(
                            'access_code',
                            'password',
                            'password_confirmation',
                        );

                        return;
                    }

                    form.reset('password', 'password_confirmation');
                },
            },
        );
    }

    function verifyAnotherAccount() {
        setStepOverride('verify');
        form.reset('access_code', 'password', 'password_confirmation');
    }

    return (
        <AuthCard className={className} {...props}>
            <form onSubmit={submit}>
                <FieldGroup className="gap-7">
                    <AuthFormHeader title={title} description={description} />
                    <SignupStepper step={step} />

                    {isVerifyStep ? (
                        <>
                            {isStudent ? (
                                <Field
                                    data-invalid={Boolean(
                                        form.errors.registration_number,
                                    )}
                                >
                                    <FieldLabel htmlFor="registration-number">
                                        Registration number
                                    </FieldLabel>
                                    <Input
                                        id="registration-number"
                                        name="registration_number"
                                        type="text"
                                        value={form.data.registration_number}
                                        placeholder="2021001234"
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
                                    <FieldDescription>
                                        Enter the registration number issued by
                                        the registrar.
                                    </FieldDescription>
                                    <FieldError>
                                        {form.errors.registration_number}
                                    </FieldError>
                                </Field>
                            ) : (
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
                                        placeholder={emailPlaceholder}
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
                                    <FieldDescription>
                                        Enter the staff email address registered
                                        for your account.
                                    </FieldDescription>
                                    <FieldError>{form.errors.email}</FieldError>
                                </Field>
                            )}

                            <Field
                                data-invalid={Boolean(form.errors.access_code)}
                            >
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
                                    aria-invalid={Boolean(
                                        form.errors.access_code,
                                    )}
                                    onChange={(event) =>
                                        form.setData(
                                            'access_code',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                                <FieldDescription>
                                    Enter the access code issued to you.
                                </FieldDescription>
                                <FieldError>
                                    {form.errors.access_code}
                                </FieldError>
                            </Field>
                        </>
                    ) : (
                        <>
                            <div className="grid gap-1 border border-border bg-muted/40 p-3 text-sm">
                                <span className="text-xs font-medium tracking-normal text-muted-foreground">
                                    Verified access
                                </span>
                                <span className="font-medium text-foreground">
                                    {identifierLabel}:{' '}
                                    <span className="font-semibold">
                                        {identifierValue}
                                    </span>
                                </span>
                            </div>

                            {isStudent && (
                                <Field
                                    data-invalid={Boolean(form.errors.email)}
                                >
                                    <FieldLabel htmlFor="login-email">
                                        Email
                                    </FieldLabel>
                                    <Input
                                        id="login-email"
                                        name="email"
                                        type="email"
                                        value={form.data.email}
                                        placeholder={emailPlaceholder}
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
                                    <FieldDescription>
                                        Enter the email address you want to use
                                        for login.
                                    </FieldDescription>
                                    <FieldError>{form.errors.email}</FieldError>
                                </Field>
                            )}

                            <Field data-invalid={Boolean(form.errors.password)}>
                                <FieldLabel htmlFor="password">
                                    Password
                                </FieldLabel>
                                <PasswordInput
                                    id="password"
                                    name="password"
                                    value={form.data.password}
                                    autoComplete="new-password"
                                    aria-invalid={Boolean(form.errors.password)}
                                    onChange={(event) =>
                                        form.setData(
                                            'password',
                                            event.target.value,
                                        )
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
                        </>
                    )}

                    <Field>
                        <AuthFormActions>
                            <Button
                                type="submit"
                                size="lg"
                                disabled={form.processing}
                            >
                                {form.processing
                                    ? isVerifyStep
                                        ? 'Verifying...'
                                        : 'Activating...'
                                    : isVerifyStep
                                      ? 'Continue'
                                      : 'Activate account'}
                            </Button>
                            {!isVerifyStep && (
                                <Button
                                    type="button"
                                    variant="link"
                                    className="h-auto self-center p-0 text-xs text-link"
                                    onClick={verifyAnotherAccount}
                                >
                                    Verify a different account
                                </Button>
                            )}
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
                        </AuthFormActions>
                    </Field>
                </FieldGroup>
            </form>
        </AuthCard>
    );
}

function SignupStepper({ step }: { step: SignupActivation['step'] }) {
    const currentIndex = step === 'verify' ? 0 : 1;
    const steps = [
        { label: 'Verify access', value: 'verify' },
        { label: 'Set password', value: 'complete' },
    ] as const;

    return (
        <ol
            className="flex items-center gap-2 text-sm"
            aria-label="Account activation progress"
        >
            {steps.map((item, index) => {
                const isCurrent = index === currentIndex;

                return (
                    <li
                        key={item.value}
                        className="flex min-w-0 items-center gap-2"
                    >
                        <span
                            className={
                                isCurrent
                                    ? 'font-semibold text-foreground'
                                    : 'font-medium text-muted-foreground'
                            }
                        >
                            {item.label}
                        </span>
                        {index < steps.length - 1 && (
                            <span
                                className="text-muted-foreground/50"
                                aria-hidden="true"
                            >
                                &gt;
                            </span>
                        )}
                    </li>
                );
            })}
        </ol>
    );
}
