import { Link, useForm } from '@inertiajs/react';
import type { ComponentProps, FormEvent } from 'react';

import { storeAdvisor } from '@/actions/App/Http/Controllers/Auth/RegisteredUserController';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { home } from '@/routes';
import type { HonorificOption } from '@/types';

type SignupFormProps = ComponentProps<'div'> & {
    honorifics: HonorificOption[];
};

export function SignupForm({ honorifics, ...props }: SignupFormProps) {
    const form = useForm({
        title: '',
        name: '',
        email: '',
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
                        description="Manage your student sets."
                    />

                    <Field
                        data-invalid={Boolean(
                            form.errors.title || form.errors.name,
                        )}
                    >
                        <FieldLabel htmlFor="name">Full name</FieldLabel>
                        <div className="grid grid-cols-[7rem_minmax(0,1fr)] gap-3">
                            <Select
                                value={form.data.title}
                                onValueChange={(value) =>
                                    form.setData('title', value)
                                }
                                required
                            >
                                <SelectTrigger
                                    id="title"
                                    aria-label="Title"
                                    aria-invalid={Boolean(form.errors.title)}
                                >
                                    <SelectValue placeholder="Title" />
                                </SelectTrigger>
                                <SelectContent>
                                    {honorifics.map((honorific) => (
                                        <SelectItem
                                            key={honorific.value}
                                            value={honorific.value}
                                        >
                                            {honorific.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <Input
                                id="name"
                                value={form.data.name}
                                placeholder="Okoro Chinedu"
                                autoComplete="name"
                                aria-invalid={Boolean(form.errors.name)}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                                required
                            />
                        </div>
                        <FieldError>
                            {form.errors.title ?? form.errors.name}
                        </FieldError>
                    </Field>

                    <Field data-invalid={Boolean(form.errors.email)}>
                        <FieldLabel htmlFor="email">Email</FieldLabel>
                        <Input
                            id="email"
                            type="email"
                            value={form.data.email}
                            placeholder="name@example.com"
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
                            <FieldLabel htmlFor="password">Password</FieldLabel>
                            <PasswordInput
                                id="password"
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
                                    : 'Create adviser account'}
                            </Button>
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
