import { Link, useForm } from '@inertiajs/react';
import {
    RiAddLine,
    RiArrowLeftLine,
    RiGroupLine,
    RiSave3Line,
} from '@remixicon/react';
import type { FormEvent } from 'react';

import {
    createStudentSet,
    section as dashboardSection,
} from '@/actions/App/Http/Controllers/DashboardController';
import { store as storeStudentSet } from '@/actions/App/Http/Controllers/StudentSetController';
import { ActionLink } from '@/components/action-link';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardForm,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
    InputGroupText,
} from '@/components/ui/input-group';
import type { DashboardAdviserStudentSets } from '@/types';

export function AdviserStudentSetsDashboard({
    settings,
}: {
    settings: DashboardAdviserStudentSets;
}) {
    return settings.isCreating ? (
        <StudentSetForm />
    ) : (
        <EmptyStudentSets />
    );
}

function EmptyStudentSets() {
    return (
        <div className="mx-auto w-full max-w-2xl">
            <Card className="border border-border bg-background shadow-sm">
                <Empty className="min-h-72">
                    <EmptyHeader>
                        <EmptyMedia variant="icon">
                            <RiGroupLine />
                        </EmptyMedia>
                        <EmptyTitle>No student sets yet</EmptyTitle>
                        <EmptyDescription>
                            Add the first set you advise.
                        </EmptyDescription>
                    </EmptyHeader>
                    <EmptyContent>
                        <Button asChild>
                            <Link href={createStudentSet()}>
                                <RiAddLine />
                                Add Student Set
                            </Link>
                        </Button>
                    </EmptyContent>
                </Empty>
            </Card>
        </div>
    );
}

function StudentSetForm() {
    const form = useForm({
        department_name: '',
        start_year: '',
    });
    const entrySessionEndYear = /^\d{4}$/.test(form.data.start_year)
        ? String(Number(form.data.start_year) + 1)
        : 'Next year';

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.submit(storeStudentSet());
    }

    return (
        <div className="mx-auto flex w-full max-w-2xl flex-col gap-5">
            <ActionLink
                href={dashboardSection({ section: 'student-sets' })}
                icon={<RiArrowLeftLine className="size-4" />}
                className="w-fit"
            >
                Back to Student Sets
            </ActionLink>

            <div>
                <h1 className="text-xl font-semibold tracking-normal">
                    Add New Set
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Enter the department and entry session for this set.
                </p>
            </div>

            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardForm onSubmit={submit}>
                    <CardHeader className="border-b border-border">
                        <CardTitle>Set Details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <FieldGroup>
                            <Field
                                data-invalid={Boolean(
                                    form.errors.department_name,
                                )}
                            >
                                <FieldLabel htmlFor="department-name">
                                    Department
                                </FieldLabel>
                                <Input
                                    id="department-name"
                                    autoFocus
                                    autoComplete="organization-title"
                                    placeholder="Computer Engineering"
                                    value={form.data.department_name}
                                    onChange={(event) =>
                                        form.setData(
                                            'department_name',
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldDescription>
                                    Enter the full department name.
                                </FieldDescription>
                                <FieldError>
                                    {form.errors.department_name}
                                </FieldError>
                            </Field>

                            <Field
                                data-invalid={Boolean(form.errors.start_year)}
                            >
                                <FieldLabel htmlFor="entry-session">
                                    Entry Session
                                </FieldLabel>
                                <InputGroup className="h-10 w-40 max-w-full bg-background">
                                    <InputGroupInput
                                        id="entry-session"
                                        aria-invalid={Boolean(
                                            form.errors.start_year,
                                        )}
                                        inputMode="numeric"
                                        maxLength={4}
                                        placeholder="2025"
                                        value={form.data.start_year}
                                        onChange={(event) =>
                                            form.setData(
                                                'start_year',
                                                event.target.value
                                                    .replace(/\D/g, '')
                                                    .slice(0, 4),
                                            )
                                        }
                                    />
                                    <InputGroupAddon align="inline-end">
                                        <InputGroupText
                                            className="gap-1 text-sm font-normal tabular-nums"
                                            aria-live="polite"
                                        >
                                            <span aria-hidden="true">/</span>
                                            <span>{entrySessionEndYear}</span>
                                        </InputGroupText>
                                    </InputGroupAddon>
                                </InputGroup>
                                <FieldDescription>
                                    Enter the year the students joined.
                                </FieldDescription>
                                <FieldError>
                                    {form.errors.start_year}
                                </FieldError>
                            </Field>
                        </FieldGroup>
                    </CardContent>
                    <CardFooter className="justify-end bg-muted/20">
                        <Button type="submit" disabled={form.processing}>
                            <RiSave3Line />
                            {form.processing ? 'Adding Set...' : 'Add Set'}
                        </Button>
                    </CardFooter>
                </CardForm>
            </Card>
        </div>
    );
}
