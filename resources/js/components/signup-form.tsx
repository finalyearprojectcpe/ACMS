import { Link, useForm } from '@inertiajs/react';
import type { ComponentProps, FormEvent } from 'react';

import { store as registerUser } from '@/actions/App/Http/Controllers/Auth/RegisteredUserController';
import { AcademicSelect } from '@/components/academic-select';
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
import { RadioCard } from '@/components/ui/radio-card';
import { home } from '@/routes';
import type { AccountType, AccountTypeOption, DepartmentGroup } from '@/types';

type SignupFormData = {
    account_type: AccountType;
    surname: string;
    other_names: string;
    email: string;
    password: string;
    faculty_id: string;
    department_id: string;
    registration_number: string;
};

export function SignupForm({
    className,
    accountTypes,
    departments,
    ...props
}: ComponentProps<'div'> & {
    accountTypes: AccountTypeOption[];
    departments: DepartmentGroup[];
}) {
    const form = useForm<SignupFormData>({
        account_type: 'student',
        surname: '',
        other_names: '',
        email: '',
        password: '',
        faculty_id: '',
        department_id: '',
        registration_number: '',
    });
    const selectedFaculty = departments.find(
        (faculty) => faculty.id.toString() === form.data.faculty_id,
    );
    const filteredDepartments = selectedFaculty?.departments ?? [];

    function updateAccountType(accountType: AccountType) {
        form.setData((data) => ({
            ...data,
            account_type: accountType,
            registration_number:
                accountType === 'staff' ? '' : data.registration_number,
        }));
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.submit(registerUser(), {
            onSuccess: () => form.reset('password', 'registration_number'),
        });
    }

    return (
        <AuthCard className={className} {...props}>
            <form onSubmit={submit}>
                <FieldGroup className="gap-6">
                    <AuthFormHeader
                        title="Create your account"
                        description="Set up your GradeManager profile."
                    />

                    <Field data-invalid={Boolean(form.errors.account_type)}>
                        <FieldLabel>Account type</FieldLabel>
                        <div className="grid grid-cols-2 gap-2">
                            {accountTypes.map((type) => (
                                <RadioCard
                                    key={type.value}
                                    id={`account-type-${type.value}`}
                                    name="account_type"
                                    value={type.value}
                                    label={type.label}
                                    checked={
                                        form.data.account_type === type.value
                                    }
                                    onChange={() =>
                                        updateAccountType(type.value)
                                    }
                                    required
                                />
                            ))}
                        </div>
                        <FieldError>{form.errors.account_type}</FieldError>
                    </Field>

                    <Field
                        data-invalid={Boolean(
                            form.errors.surname || form.errors.other_names,
                        )}
                    >
                        <FieldLabel>Full Name</FieldLabel>
                        <div className="grid gap-2 sm:grid-cols-2">
                            <div className="flex flex-col gap-2">
                                <Input
                                    id="surname"
                                    name="surname"
                                    type="text"
                                    value={form.data.surname}
                                    placeholder="Surname"
                                    autoComplete="family-name"
                                    aria-label="Surname"
                                    aria-invalid={Boolean(form.errors.surname)}
                                    onChange={(event) =>
                                        form.setData(
                                            'surname',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                                <FieldError>{form.errors.surname}</FieldError>
                            </div>

                            <div className="flex flex-col gap-2">
                                <Input
                                    id="other_names"
                                    name="other_names"
                                    type="text"
                                    value={form.data.other_names}
                                    placeholder="Other names"
                                    autoComplete="given-name"
                                    aria-label="Other names"
                                    aria-invalid={Boolean(
                                        form.errors.other_names,
                                    )}
                                    onChange={(event) =>
                                        form.setData(
                                            'other_names',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                                <FieldError>
                                    {form.errors.other_names}
                                </FieldError>
                            </div>
                        </div>
                    </Field>

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
                            autoComplete="new-password"
                            aria-invalid={Boolean(form.errors.password)}
                            onChange={(event) =>
                                form.setData('password', event.target.value)
                            }
                            required
                        />
                        <FieldError>{form.errors.password}</FieldError>
                    </Field>

                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field data-invalid={Boolean(form.errors.faculty_id)}>
                            <FieldLabel htmlFor="faculty_id">
                                Faculty
                            </FieldLabel>
                            <AcademicSelect
                                id="faculty_id"
                                name="faculty_id"
                                value={form.data.faculty_id}
                                options={departments}
                                placeholder="Select faculty"
                                invalid={Boolean(form.errors.faculty_id)}
                                onValueChange={(value) =>
                                    form.setData((data) => ({
                                        ...data,
                                        faculty_id: value,
                                        department_id: '',
                                    }))
                                }
                            />
                            <FieldError>{form.errors.faculty_id}</FieldError>
                        </Field>

                        <Field
                            data-invalid={Boolean(form.errors.department_id)}
                        >
                            <FieldLabel htmlFor="department_id">
                                Department
                            </FieldLabel>
                            <AcademicSelect
                                id="department_id"
                                name="department_id"
                                value={form.data.department_id}
                                options={filteredDepartments}
                                placeholder={
                                    form.data.faculty_id
                                        ? 'Select department'
                                        : 'Select faculty first'
                                }
                                invalid={Boolean(form.errors.department_id)}
                                disabled={!form.data.faculty_id}
                                onValueChange={(value) =>
                                    form.setData('department_id', value)
                                }
                            />
                            <FieldError>{form.errors.department_id}</FieldError>
                        </Field>
                    </div>

                    {form.data.account_type === 'student' && (
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
                            <FieldError>
                                {form.errors.registration_number}
                            </FieldError>
                        </Field>
                    )}

                    <Field>
                        <AuthFormActions>
                            <Button
                                type="submit"
                                size="lg"
                                disabled={form.processing}
                            >
                                {form.processing
                                    ? 'Creating...'
                                    : 'Create account'}
                            </Button>
                            <FieldDescription className="text-center">
                                Already have an account?{' '}
                                <Button
                                    asChild
                                    variant="link"
                                    className="h-auto p-0 text-xs"
                                >
                                    <Link href={home()}>Login</Link>
                                </Button>
                            </FieldDescription>
                        </AuthFormActions>
                    </Field>
                </FieldGroup>
            </form>
        </AuthCard>
    );
}
