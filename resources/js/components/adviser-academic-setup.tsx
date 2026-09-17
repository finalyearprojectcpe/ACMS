import { Link, useForm } from '@inertiajs/react';
import { RiAddLine, RiArrowLeftLine, RiSave3Line } from '@remixicon/react';
import type { FormEvent } from 'react';

import {
    storeSession,
    storeStudentSet,
    updateSession,
    updateStudentSet,
} from '@/actions/App/Http/Controllers/AcademicSetupController';
import { section as dashboardSection } from '@/actions/App/Http/Controllers/DashboardController';
import { ActionLink } from '@/components/action-link';
import { StudentSetChoiceLabel } from '@/components/student-set-label';
import { Badge } from '@/components/ui/badge';
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
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    DashboardAcademicSetupForm,
    DashboardAdviserSettings,
} from '@/types';

export function AdviserAcademicSetup({
    settings,
}: {
    settings: DashboardAdviserSettings;
}) {
    if (settings.setupForm?.kind === 'academic-session') {
        return <AcademicSessionForm form={settings.setupForm} />;
    }

    if (settings.setupForm?.kind === 'student-set') {
        return <StudentSetForm form={settings.setupForm} settings={settings} />;
    }

    return <AcademicSetupSummary settings={settings} />;
}

function AcademicSetupSummary({
    settings,
}: {
    settings: DashboardAdviserSettings;
}) {
    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardHeader className="gap-3 border-b border-border sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <CardTitle>Academic Setup</CardTitle>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Add sessions first, then create the student sets you
                        advise.
                    </p>
                </div>
                <div className="flex flex-wrap gap-3">
                    <ActionLink
                        href={dashboardSection(
                            { section: 'profile-settings' },
                            { query: { setup: 'academic-session' } },
                        )}
                        icon={<RiAddLine className="size-4" />}
                    >
                        Add Session
                    </ActionLink>
                    {settings.academicSessions.length > 0 ? (
                        <ActionLink
                            href={dashboardSection(
                                { section: 'profile-settings' },
                                { query: { setup: 'student-set' } },
                            )}
                            icon={<RiAddLine className="size-4" />}
                        >
                            Add Student Set
                        </ActionLink>
                    ) : null}
                </div>
            </CardHeader>
            <CardContent className="grid gap-6 p-0">
                <div>
                    <div className="border-b border-border px-4 py-3 text-sm font-semibold">
                        Academic Sessions
                    </div>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="px-4">Session</TableHead>
                                <TableHead>Dates</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="pr-4 text-right">
                                    Action
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {settings.academicSessions.length > 0 ? (
                                settings.academicSessions.map((session) => (
                                    <TableRow
                                        key={session.id}
                                        className="odd:bg-muted/25"
                                    >
                                        <TableCell className="px-4 font-medium">
                                            {session.name} Session
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {dateRange(
                                                session.startsOn,
                                                session.endsOn,
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {session.status}
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="pr-4 text-right">
                                            <ActionLink
                                                href={dashboardSection(
                                                    {
                                                        section:
                                                            'profile-settings',
                                                    },
                                                    {
                                                        query: {
                                                            setup: 'academic-session',
                                                            edit: session.id,
                                                        },
                                                    },
                                                )}
                                            >
                                                Edit
                                            </ActionLink>
                                        </TableCell>
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="px-4 py-6 text-muted-foreground"
                                    >
                                        Add your first academic session to
                                        create a student set.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>

                <div>
                    <div className="border-y border-border px-4 py-3 text-sm font-semibold">
                        Student Sets
                    </div>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="px-4">
                                    Student Set
                                </TableHead>
                                <TableHead className="text-center">
                                    Years
                                </TableHead>
                                <TableHead className="text-center">
                                    Maximum Units
                                </TableHead>
                                <TableHead className="pr-4 text-right">
                                    Action
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {settings.assignedSets.length > 0 ? (
                                settings.assignedSets.map((set) => (
                                    <TableRow
                                        key={set.id}
                                        className="odd:bg-muted/25"
                                    >
                                        <TableCell className="px-4">
                                            <StudentSetChoiceLabel
                                                entrySession={set.entrySession}
                                                currentLevel={set.currentLevel}
                                                minimumYearsToDegree={
                                                    set.minimumYearsToDegree
                                                }
                                            />
                                        </TableCell>
                                        <TableCell className="text-center tabular-nums">
                                            {set.minimumYearsToDegree}
                                        </TableCell>
                                        <TableCell className="text-center tabular-nums">
                                            {set.maximumCourseUnits}
                                        </TableCell>
                                        <TableCell className="pr-4 text-right">
                                            <ActionLink
                                                href={dashboardSection(
                                                    {
                                                        section:
                                                            'profile-settings',
                                                    },
                                                    {
                                                        query: {
                                                            setup: 'student-set',
                                                            edit: set.id,
                                                        },
                                                    },
                                                )}
                                            >
                                                Edit
                                            </ActionLink>
                                        </TableCell>
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell
                                        colSpan={4}
                                        className="px-4 py-6 text-muted-foreground"
                                    >
                                        No student sets have been added.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </CardContent>
        </Card>
    );
}

function AcademicSessionForm({
    form: setup,
}: {
    form: Extract<DashboardAcademicSetupForm, { kind: 'academic-session' }>;
}) {
    const form = useForm({
        name: setup.name,
        starts_on: setup.startsOn,
        ends_on: setup.endsOn,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.submit(
            setup.mode === 'edit' && setup.id
                ? updateSession(setup.id)
                : storeSession(),
        );
    }

    return (
        <SetupFormShell
            title={
                setup.mode === 'edit'
                    ? `Edit ${setup.name} Session`
                    : 'Add Academic Session'
            }
            onSubmit={submit}
            processing={form.processing}
        >
            <FieldGroup>
                <Field data-invalid={Boolean(form.errors.name)}>
                    <FieldLabel htmlFor="session-name">Session</FieldLabel>
                    <Input
                        id="session-name"
                        value={form.data.name}
                        placeholder="2025/2026"
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                    />
                    <FieldError>{form.errors.name}</FieldError>
                </Field>
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field data-invalid={Boolean(form.errors.starts_on)}>
                        <FieldLabel htmlFor="session-start">
                            Start Date
                        </FieldLabel>
                        <Input
                            id="session-start"
                            type="date"
                            value={form.data.starts_on}
                            onChange={(event) =>
                                form.setData('starts_on', event.target.value)
                            }
                        />
                        <FieldError>{form.errors.starts_on}</FieldError>
                    </Field>
                    <Field data-invalid={Boolean(form.errors.ends_on)}>
                        <FieldLabel htmlFor="session-end">End Date</FieldLabel>
                        <Input
                            id="session-end"
                            type="date"
                            value={form.data.ends_on}
                            onChange={(event) =>
                                form.setData('ends_on', event.target.value)
                            }
                        />
                        <FieldError>{form.errors.ends_on}</FieldError>
                    </Field>
                </div>
            </FieldGroup>
        </SetupFormShell>
    );
}

function StudentSetForm({
    form: setup,
    settings,
}: {
    form: Extract<DashboardAcademicSetupForm, { kind: 'student-set' }>;
    settings: DashboardAdviserSettings;
}) {
    const form = useForm({
        entry_academic_session_id:
            setup.entryAcademicSessionId?.toString() ?? '',
        minimum_years_to_degree: setup.minimumYearsToDegree,
        maximum_course_units: setup.maximumCourseUnits,
    });
    const selectedSession = settings.academicSessions.find(
        (session) => session.id === setup.entryAcademicSessionId,
    );

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.submit(
            setup.mode === 'edit' && setup.id
                ? updateStudentSet(setup.id)
                : storeStudentSet(),
        );
    }

    return (
        <SetupFormShell
            title={
                setup.mode === 'edit'
                    ? `Edit ${selectedSession?.name ?? ''} Set`
                    : 'Add Student Set'
            }
            onSubmit={submit}
            processing={form.processing}
        >
            <FieldGroup>
                {setup.mode === 'create' ? (
                    <Field
                        data-invalid={Boolean(
                            form.errors.entry_academic_session_id,
                        )}
                    >
                        <FieldLabel>Entry Session</FieldLabel>
                        <Select
                            value={form.data.entry_academic_session_id}
                            onValueChange={(value) =>
                                form.setData('entry_academic_session_id', value)
                            }
                        >
                            <SelectTrigger
                                aria-invalid={Boolean(
                                    form.errors.entry_academic_session_id,
                                )}
                            >
                                <SelectValue placeholder="Select the entry session" />
                            </SelectTrigger>
                            <SelectContent>
                                {settings.academicSessions.map((session) => (
                                    <SelectItem
                                        key={session.id}
                                        value={session.id.toString()}
                                    >
                                        {session.name} Session
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <FieldError>
                            {form.errors.entry_academic_session_id}
                        </FieldError>
                    </Field>
                ) : (
                    <div className="rounded-md border border-border bg-muted/30 px-3 py-2 text-sm">
                        <span className="text-muted-foreground">
                            Entry Session
                        </span>
                        <span className="ml-2 font-medium">
                            {selectedSession?.name} Session
                        </span>
                    </div>
                )}
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        data-invalid={Boolean(
                            form.errors.minimum_years_to_degree,
                        )}
                    >
                        <FieldLabel htmlFor="set-years">
                            Years to Complete Programme
                        </FieldLabel>
                        <Input
                            id="set-years"
                            type="number"
                            min={1}
                            max={10}
                            value={form.data.minimum_years_to_degree}
                            onChange={(event) =>
                                form.setData(
                                    'minimum_years_to_degree',
                                    Number(event.target.value),
                                )
                            }
                        />
                        <FieldError>
                            {form.errors.minimum_years_to_degree}
                        </FieldError>
                    </Field>
                    <Field
                        data-invalid={Boolean(form.errors.maximum_course_units)}
                    >
                        <FieldLabel htmlFor="set-units">
                            Maximum Course Units
                        </FieldLabel>
                        <Input
                            id="set-units"
                            type="number"
                            min={1}
                            max={40}
                            value={form.data.maximum_course_units}
                            onChange={(event) =>
                                form.setData(
                                    'maximum_course_units',
                                    Number(event.target.value),
                                )
                            }
                        />
                        <FieldError>
                            {form.errors.maximum_course_units}
                        </FieldError>
                    </Field>
                </div>
            </FieldGroup>
        </SetupFormShell>
    );
}

function SetupFormShell({
    children,
    onSubmit,
    processing,
    title,
}: {
    children: React.ReactNode;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    processing: boolean;
    title: string;
}) {
    const backRoute = dashboardSection({ section: 'profile-settings' });

    return (
        <div className="mx-auto flex w-full max-w-2xl flex-col gap-4">
            <ActionLink
                href={backRoute}
                icon={<RiArrowLeftLine className="size-4" />}
                className="w-fit"
            >
                Back to Profile Settings
            </ActionLink>
            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardForm onSubmit={onSubmit}>
                    <CardHeader className="border-b border-border">
                        <CardTitle>{title}</CardTitle>
                    </CardHeader>
                    <CardContent className="p-5">{children}</CardContent>
                    <CardFooter className="justify-end gap-2 border-t border-border">
                        <Button asChild type="button" variant="outline">
                            <Link href={backRoute}>Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            <RiSave3Line />
                            {processing ? 'Saving...' : 'Save'}
                        </Button>
                    </CardFooter>
                </CardForm>
            </Card>
        </div>
    );
}

function dateRange(startsOn: string, endsOn: string): string {
    const formatter = new Intl.DateTimeFormat('en-NG', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
        timeZone: 'UTC',
    });

    return `${formatter.format(new Date(startsOn))} - ${formatter.format(new Date(endsOn))}`;
}
