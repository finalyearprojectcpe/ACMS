import { Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { ComponentProps, FormEvent } from 'react';

import {
    storeAdvisor,
    storeStudent,
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
import { home } from '@/routes';
import type { AccountType, SignupActivation } from '@/types';

type SignupFormProps = ComponentProps<'div'> & {
    accountType: AccountType;
    activation: SignupActivation | null;
};

export function SignupForm({ accountType, activation, ...props }: SignupFormProps) {
    if (accountType === 'adviser') {
        return <AdvisorSignupForm {...props} />;
    }

    return <StudentSignupForm activation={activation ?? { step: 'verify', identifier: null }} {...props} />;
}

function AdvisorSignupForm(props: ComponentProps<'div'>) {
    const form = useForm({
        name: '',
        email: '',
        institution_name: '',
        programme_name: '',
        programme_abbreviation: '',
        minimum_years_to_degree: '4',
        maximum_course_units: '24',
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.submit(storeAdvisor(), {
            onSuccess: () => form.reset('password', 'password_confirmation'),
        });
    }

    return (
        <AuthCard {...props}>
            <form onSubmit={submit}>
                <FieldGroup className="gap-7">
                    <AuthFormHeader
                        title="Course adviser account"
                        description="Create your workspace and start managing your student sets."
                    />

                    <Field data-invalid={Boolean(form.errors.name)}>
                        <FieldLabel htmlFor="name">Full name</FieldLabel>
                        <Input
                            id="name"
                            value={form.data.name}
                            autoComplete="name"
                            onChange={(event) => form.setData('name', event.target.value)}
                            required
                        />
                        <FieldError>{form.errors.name}</FieldError>
                    </Field>

                    <Field data-invalid={Boolean(form.errors.email)}>
                        <FieldLabel htmlFor="email">Email</FieldLabel>
                        <Input
                            id="email"
                            type="email"
                            value={form.data.email}
                            placeholder="name@university.edu"
                            autoComplete="email"
                            onChange={(event) => form.setData('email', event.target.value)}
                            required
                        />
                        <FieldError>{form.errors.email}</FieldError>
                    </Field>

                    <Field data-invalid={Boolean(form.errors.institution_name)}>
                        <FieldLabel htmlFor="institution-name">Institution name <span className="font-normal text-muted-foreground">(optional)</span></FieldLabel>
                        <Input
                            id="institution-name"
                            value={form.data.institution_name}
                            placeholder="Federal University of Technology, Owerri"
                            onChange={(event) => form.setData('institution_name', event.target.value)}
                        />
                        <FieldError>{form.errors.institution_name}</FieldError>
                    </Field>

                    <div className="grid gap-5 sm:grid-cols-[minmax(0,1fr)_10rem]">
                        <Field data-invalid={Boolean(form.errors.programme_name)}>
                            <FieldLabel htmlFor="programme-name">Programme</FieldLabel>
                            <Input
                                id="programme-name"
                                value={form.data.programme_name}
                                placeholder="Computer Engineering"
                                onChange={(event) => form.setData('programme_name', event.target.value)}
                                required
                            />
                            <FieldError>{form.errors.programme_name}</FieldError>
                        </Field>
                        <Field data-invalid={Boolean(form.errors.programme_abbreviation)}>
                            <FieldLabel htmlFor="programme-abbreviation">Abbreviation</FieldLabel>
                            <Input
                                id="programme-abbreviation"
                                value={form.data.programme_abbreviation}
                                placeholder="CPE"
                                onChange={(event) => form.setData('programme_abbreviation', event.target.value.toUpperCase())}
                                required
                            />
                            <FieldError>{form.errors.programme_abbreviation}</FieldError>
                        </Field>
                    </div>

                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field data-invalid={Boolean(form.errors.minimum_years_to_degree)}>
                            <FieldLabel htmlFor="minimum-years">Years to degree</FieldLabel>
                            <Input
                                id="minimum-years"
                                type="number"
                                min={1}
                                max={7}
                                value={form.data.minimum_years_to_degree}
                                onChange={(event) => form.setData('minimum_years_to_degree', event.target.value)}
                                required
                            />
                            <FieldError>{form.errors.minimum_years_to_degree}</FieldError>
                        </Field>
                        <Field data-invalid={Boolean(form.errors.maximum_course_units)}>
                            <FieldLabel htmlFor="maximum-units">Maximum units per semester</FieldLabel>
                            <Input
                                id="maximum-units"
                                type="number"
                                min={1}
                                max={60}
                                value={form.data.maximum_course_units}
                                onChange={(event) => form.setData('maximum_course_units', event.target.value)}
                                required
                            />
                            <FieldError>{form.errors.maximum_course_units}</FieldError>
                        </Field>
                    </div>

                    <PasswordFields
                        password={form.data.password}
                        passwordConfirmation={form.data.password_confirmation}
                        passwordError={form.errors.password}
                        passwordConfirmationError={form.errors.password_confirmation}
                        onPasswordChange={(value) => form.setData('password', value)}
                        onPasswordConfirmationChange={(value) => form.setData('password_confirmation', value)}
                    />

                    <Field>
                        <AuthFormActions>
                            <Button type="submit" size="lg" disabled={form.processing}>
                                {form.processing ? 'Creating account...' : 'Create adviser account'}
                            </Button>
                            <LoginPrompt />
                        </AuthFormActions>
                    </Field>
                </FieldGroup>
            </form>
        </AuthCard>
    );
}

function StudentSignupForm({ activation, ...props }: ComponentProps<'div'> & { activation: SignupActivation }) {
    const [stepOverride, setStepOverride] = useState<SignupActivation['step'] | null>(null);
    const step = stepOverride ?? activation.step;
    const form = useForm({
        email: '',
        registration_number: '',
        access_code: '',
        password: '',
        password_confirmation: '',
    });
    const isVerifyStep = step === 'verify';

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.submit(isVerifyStep ? verifyStudent() : storeStudent(), {
            onSuccess: () => {
                if (isVerifyStep) {
                    setStepOverride(null);
                    form.reset('access_code', 'password', 'password_confirmation');
                    return;
                }
                form.reset('password', 'password_confirmation');
            },
        });
    }

    return (
        <AuthCard {...props}>
            <form onSubmit={submit}>
                <FieldGroup className="gap-7">
                    <AuthFormHeader
                        title="Student account"
                        description={isVerifyStep ? 'Enter the details your course adviser issued to you.' : 'Set the email and password you will use to log in.'}
                    />
                    <SignupStepper step={step} />

                    {isVerifyStep ? (
                        <>
                            <Field data-invalid={Boolean(form.errors.registration_number)}>
                                <FieldLabel htmlFor="registration-number">Registration number</FieldLabel>
                                <Input
                                    id="registration-number"
                                    value={form.data.registration_number}
                                    placeholder="2021001234"
                                    inputMode="numeric"
                                    autoComplete="off"
                                    onChange={(event) => form.setData('registration_number', event.target.value)}
                                    required
                                />
                                <FieldError>{form.errors.registration_number}</FieldError>
                            </Field>
                            <Field data-invalid={Boolean(form.errors.access_code)}>
                                <FieldLabel htmlFor="access-code">Access code</FieldLabel>
                                <Input
                                    id="access-code"
                                    value={form.data.access_code}
                                    placeholder="Enter access code"
                                    autoComplete="one-time-code"
                                    onChange={(event) => form.setData('access_code', event.target.value)}
                                    required
                                />
                                <FieldError>{form.errors.access_code}</FieldError>
                            </Field>
                        </>
                    ) : (
                        <>
                            <div className="grid gap-1 border border-border bg-muted/40 p-3 text-sm">
                                <span className="text-xs font-medium text-muted-foreground">Verified registration number</span>
                                <span className="font-semibold text-foreground">{activation.identifier ?? form.data.registration_number}</span>
                            </div>
                            <Field data-invalid={Boolean(form.errors.email)}>
                                <FieldLabel htmlFor="student-email">Email</FieldLabel>
                                <Input
                                    id="student-email"
                                    type="email"
                                    value={form.data.email}
                                    placeholder="student@university.edu"
                                    autoComplete="email"
                                    onChange={(event) => form.setData('email', event.target.value)}
                                    required
                                />
                                <FieldError>{form.errors.email}</FieldError>
                            </Field>
                            <PasswordFields
                                password={form.data.password}
                                passwordConfirmation={form.data.password_confirmation}
                                passwordError={form.errors.password}
                                passwordConfirmationError={form.errors.password_confirmation}
                                onPasswordChange={(value) => form.setData('password', value)}
                                onPasswordConfirmationChange={(value) => form.setData('password_confirmation', value)}
                            />
                        </>
                    )}

                    <Field>
                        <AuthFormActions>
                            <Button type="submit" size="lg" disabled={form.processing}>
                                {form.processing ? (isVerifyStep ? 'Verifying...' : 'Activating...') : isVerifyStep ? 'Continue' : 'Activate account'}
                            </Button>
                            {!isVerifyStep ? (
                                <Button type="button" variant="link" className="h-auto self-center p-0 text-xs text-link" onClick={() => setStepOverride('verify')}>
                                    Verify a different account
                                </Button>
                            ) : null}
                            <LoginPrompt />
                        </AuthFormActions>
                    </Field>
                </FieldGroup>
            </form>
        </AuthCard>
    );
}

function PasswordFields({
    password,
    passwordConfirmation,
    passwordError,
    passwordConfirmationError,
    onPasswordChange,
    onPasswordConfirmationChange,
}: {
    password: string;
    passwordConfirmation: string;
    passwordError?: string;
    passwordConfirmationError?: string;
    onPasswordChange: (value: string) => void;
    onPasswordConfirmationChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-5 sm:grid-cols-2">
            <Field data-invalid={Boolean(passwordError)}>
                <FieldLabel htmlFor="password">Password</FieldLabel>
                <PasswordInput
                    id="password"
                    value={password}
                    autoComplete="new-password"
                    onChange={(event) => onPasswordChange(event.target.value)}
                    required
                />
                <FieldError>{passwordError}</FieldError>
            </Field>
            <Field data-invalid={Boolean(passwordConfirmationError)}>
                <FieldLabel htmlFor="password-confirmation">Confirm password</FieldLabel>
                <PasswordInput
                    id="password-confirmation"
                    value={passwordConfirmation}
                    autoComplete="new-password"
                    onChange={(event) => onPasswordConfirmationChange(event.target.value)}
                    required
                />
                <FieldError>{passwordConfirmationError}</FieldError>
            </Field>
        </div>
    );
}

function LoginPrompt() {
    return (
        <FieldDescription className="text-center">
            Already have an account?{' '}
            <Button asChild variant="link" className="h-auto p-0 text-xs text-link">
                <Link href={home()}>Log in</Link>
            </Button>
        </FieldDescription>
    );
}

function SignupStepper({ step }: { step: SignupActivation['step'] }) {
    const currentIndex = step === 'verify' ? 0 : 1;
    const steps = ['Verify access', 'Set login details'];

    return (
        <ol className="flex items-center gap-2 text-sm" aria-label="Account activation progress">
            {steps.map((label, index) => (
                <li key={label} className="flex min-w-0 items-center gap-2">
                    <span className={index === currentIndex ? 'font-semibold text-foreground' : 'font-medium text-muted-foreground'}>{label}</span>
                    {index === 0 ? <span className="text-muted-foreground/50" aria-hidden="true">&gt;</span> : null}
                </li>
            ))}
        </ol>
    );
}
