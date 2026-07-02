import { Link, useForm } from '@inertiajs/react';
import {
    RiAddLine,
    RiArrowLeftLine,
    RiLockPasswordLine,
    RiMailSendLine,
    RiPencilLine,
    RiSave3Line,
    RiUserSettingsLine,
} from '@remixicon/react';
import { useMemo } from 'react';
import type { FormEvent, ReactNode } from 'react';

import {
    createAcademicSession,
    createSchoolDepartment,
    createSchoolFaculty,
    createSchoolFacultyHead,
    destroySchoolAdminTransfer,
    editAcademicSession,
    editSchoolDepartment,
    editSchoolFaculty,
    section as dashboardSection,
    storeAcademicSession,
    storeSchoolAdminTransfer,
    storeSchoolDepartment,
    storeSchoolFaculty,
    storeSchoolFacultyHead,
    updateAcademicSession,
    updateSchoolAdminPassword,
    updateSchoolDepartment,
    updateSchoolFaculty,
} from '@/actions/App/Http/Controllers/DashboardController';
import { ActionLink } from '@/components/action-link';
import { CopyableValue } from '@/components/copyable-value';
import { ChartCard, CountBarChart } from '@/components/dashboard-charts';
import { PersonCell } from '@/components/person-cell';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
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
import { PasswordInput } from '@/components/ui/password-input';
import { ScrollArea } from '@/components/ui/scroll-area';
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
    DashboardAcademicSession,
    DashboardSchoolAdmin,
    DashboardSchoolAdminAcademicSessionForm,
    DashboardSchoolAdminDepartmentForm,
    DashboardSchoolAdminFaculty,
    DashboardSchoolAdminFacultyForm,
    DashboardSchoolAdminFacultyHeadForm,
} from '@/types';

type SchoolAdminDashboardProps = {
    schoolAdmin: DashboardSchoolAdmin;
    academicSessionForm: DashboardSchoolAdminAcademicSessionForm | null;
    facultyForm: DashboardSchoolAdminFacultyForm | null;
    departmentForm: DashboardSchoolAdminDepartmentForm | null;
    facultyHeadForm: DashboardSchoolAdminFacultyHeadForm | null;
    section: string;
    sectionTitle: string;
};

type AcademicSessionFormData = {
    name: string;
    starts_on: string;
    ends_on: string;
};

type FacultyFormData = {
    name: string;
    abbreviation: string;
};

type DepartmentFormData = {
    faculty_id: string;
    name: string;
    abbreviation: string;
    minimum_years_to_degree: string;
};

type FacultyHeadFormData = {
    faculty_id: string;
    department_id: string;
    name: string;
    email: string;
    access_code: string;
};

type PasswordFormData = {
    current_password: string;
    password: string;
    password_confirmation: string;
};

type OwnershipTransferFormData = {
    email: string;
    name: string;
};

export function SchoolAdminDashboard({
    schoolAdmin,
    academicSessionForm,
    facultyForm,
    departmentForm,
    facultyHeadForm,
    section,
    sectionTitle,
}: SchoolAdminDashboardProps) {
    if (academicSessionForm !== null) {
        return <SchoolAcademicSessionForm form={academicSessionForm} />;
    }

    if (facultyForm !== null) {
        return <SchoolFacultyForm form={facultyForm} />;
    }

    if (departmentForm !== null) {
        return <SchoolDepartmentForm form={departmentForm} />;
    }

    if (facultyHeadForm !== null) {
        return <SchoolFacultyHeadForm form={facultyHeadForm} />;
    }

    if (section === 'academic-sessions') {
        return <AcademicSessionsPage schoolAdmin={schoolAdmin} />;
    }

    if (section === 'structure') {
        return <SchoolStructurePage schoolAdmin={schoolAdmin} />;
    }

    if (section === 'faculty-heads') {
        return <FacultyHeadsPage schoolAdmin={schoolAdmin} />;
    }

    if (section === 'settings') {
        return <SchoolAdminSettingsPage schoolAdmin={schoolAdmin} />;
    }

    if (section !== 'home') {
        return (
            <h1 className="text-2xl font-semibold tracking-normal">
                {sectionTitle}
            </h1>
        );
    }

    return <SchoolAdminHome schoolAdmin={schoolAdmin} />;
}

function SchoolAdminHome({
    schoolAdmin,
}: {
    schoolAdmin: DashboardSchoolAdmin;
}) {
    const departmentsByFaculty = useMemo(
        () =>
            schoolAdmin.faculties.map((faculty) => ({
                label: faculty.abbreviation,
                value: faculty.departmentsCount,
            })),
        [schoolAdmin.faculties],
    );
    const facultyHeadCoverage = useMemo(
        () => [
            {
                label: 'Assigned',
                value: schoolAdmin.faculties.filter(
                    (faculty) => faculty.head !== null,
                ).length,
            },
            {
                label: 'Needs Head',
                value: schoolAdmin.faculties.filter(
                    (faculty) => faculty.head === null,
                ).length,
            },
        ],
        [schoolAdmin.faculties],
    );

    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <MetricBlock
                    label="Faculties"
                    value={schoolAdmin.metrics.faculties}
                />
                <MetricBlock
                    label="Departments"
                    value={schoolAdmin.metrics.departments}
                />
                <MetricBlock
                    label="Academic Sessions"
                    value={schoolAdmin.metrics.academicSessions}
                />
                <MetricBlock
                    label="Faculty Head Coverage"
                    value={schoolAdmin.metrics.facultyHeadCoverage}
                />
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <ChartCard
                    title="Departments by Faculty"
                    detail="How the school structure is distributed."
                >
                    <CountBarChart
                        rows={departmentsByFaculty}
                        dataLabel="Departments"
                    />
                </ChartCard>

                <ChartCard
                    title="Faculty Head Coverage"
                    detail="Faculties with an assigned head versus those still pending."
                >
                    <CountBarChart
                        rows={facultyHeadCoverage}
                        dataLabel="Faculties"
                    />
                </ChartCard>
            </div>

            <div className="grid gap-4 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                <DashboardSurface
                    title="School Calendar"
                    action={
                        <Link
                            href={dashboardSection({
                                section: 'academic-sessions',
                            })}
                            className="text-xs font-medium text-link"
                        >
                            Manage sessions
                        </Link>
                    }
                >
                    <div className="grid gap-3 sm:grid-cols-2">
                        <SessionSummary
                            label="Current Session"
                            session={schoolAdmin.currentAcademicSession}
                        />
                        <SessionSummary
                            label="Next Session"
                            session={schoolAdmin.nextAcademicSession}
                        />
                    </div>
                </DashboardSurface>

                <DashboardSurface
                    title="Faculty Heads"
                    action={
                        <Link
                            href={dashboardSection({
                                section: 'faculty-heads',
                            })}
                            className="text-xs font-medium text-link"
                        >
                            Manage faculty heads
                        </Link>
                    }
                >
                    <FacultyHeadSummaryTable
                        faculties={schoolAdmin.faculties}
                    />
                </DashboardSurface>
            </div>
        </div>
    );
}

function AcademicSessionsPage({
    schoolAdmin,
}: {
    schoolAdmin: DashboardSchoolAdmin;
}) {
    return (
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-4">
            <PageHeader
                title="Academic Sessions"
                action={
                    <Button asChild>
                        <Link href={createAcademicSession()}>
                            <RiAddLine />
                            Create Session
                        </Link>
                    </Button>
                }
            />
            <DashboardSurface title="School Sessions">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Session</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead>Start</TableHead>
                            <TableHead>End</TableHead>
                            <TableHead className="text-right">Action</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {schoolAdmin.academicSessions.map((academicSession) => (
                            <TableRow
                                key={academicSession.id}
                                className="odd:bg-background even:bg-muted/25"
                            >
                                <TableCell className="font-medium">
                                    {academicSession.name}
                                </TableCell>
                                <TableCell>
                                    <SessionStatusBadge
                                        status={academicSession.status}
                                    />
                                </TableCell>
                                <TableCell>
                                    {academicSession.startsOn}
                                </TableCell>
                                <TableCell>{academicSession.endsOn}</TableCell>
                                <TableCell className="text-right">
                                    <ActionLink
                                        href={editAcademicSession({
                                            academicSession: academicSession.id,
                                        })}
                                        icon={
                                            <RiPencilLine className="size-3.5" />
                                        }
                                    >
                                        Edit
                                    </ActionLink>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </DashboardSurface>
        </div>
    );
}

function SchoolStructurePage({
    schoolAdmin,
}: {
    schoolAdmin: DashboardSchoolAdmin;
}) {
    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <PageHeader
                title="School Structure"
                action={
                    <div className="flex flex-wrap justify-end gap-2">
                        <Button variant="outline" asChild>
                            <Link href={createSchoolFaculty()}>
                                <RiAddLine />
                                Add Faculty
                            </Link>
                        </Button>
                        <Button asChild>
                            <Link href={createSchoolDepartment()}>
                                <RiAddLine />
                                Add Department
                            </Link>
                        </Button>
                    </div>
                }
            />
            <div className="grid gap-4 lg:grid-cols-2">
                {schoolAdmin.faculties.map((faculty) => (
                    <FacultyStructureCard key={faculty.id} faculty={faculty} />
                ))}
            </div>
        </div>
    );
}

function FacultyHeadsPage({
    schoolAdmin,
}: {
    schoolAdmin: DashboardSchoolAdmin;
}) {
    return (
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-4">
            <PageHeader
                title="Faculty Heads"
                action={
                    <Button asChild>
                        <Link href={createSchoolFacultyHead()}>
                            <RiAddLine />
                            Assign Faculty Head
                        </Link>
                    </Button>
                }
            />
            <DashboardSurface title="Assigned Faculty Heads">
                <FacultyHeadTable faculties={schoolAdmin.faculties} />
            </DashboardSurface>
        </div>
    );
}

function SchoolAdminSettingsPage({
    schoolAdmin,
}: {
    schoolAdmin: DashboardSchoolAdmin;
}) {
    return (
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-4">
            <PageHeader title="Settings" />
            <div className="grid gap-4 lg:grid-cols-2">
                <PasswordSettingsCard admin={schoolAdmin.settings.admin} />
                <OwnershipTransferCard schoolAdmin={schoolAdmin} />
            </div>
        </div>
    );
}

function PasswordSettingsCard({
    admin,
}: {
    admin: DashboardSchoolAdmin['settings']['admin'];
}) {
    const form = useForm<PasswordFormData>({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.submit(updateSchoolAdminPassword(), {
            onSuccess: () => form.reset(),
        });
    }

    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardForm onSubmit={submit}>
                <CardHeader className="gap-2">
                    <CardTitle className="flex items-center gap-2 text-base">
                        <RiLockPasswordLine className="size-4 text-muted-foreground" />
                        Change Password
                    </CardTitle>
                    <p className="text-sm text-muted-foreground">
                        Update the password for {admin.email ?? admin.name}.
                    </p>
                </CardHeader>
                <CardContent className="px-5 pb-6">
                    <FieldGroup className="gap-6">
                        <Field
                            data-invalid={Boolean(form.errors.current_password)}
                        >
                            <FieldLabel htmlFor="current-password">
                                Current Password
                            </FieldLabel>
                            <PasswordInput
                                id="current-password"
                                value={form.data.current_password}
                                autoComplete="current-password"
                                aria-invalid={Boolean(
                                    form.errors.current_password,
                                )}
                                onChange={(event) =>
                                    form.setData(
                                        'current_password',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                            <FieldError>
                                {form.errors.current_password}
                            </FieldError>
                        </Field>
                        <Field data-invalid={Boolean(form.errors.password)}>
                            <FieldLabel htmlFor="new-password">
                                New Password
                            </FieldLabel>
                            <PasswordInput
                                id="new-password"
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
                            <FieldLabel htmlFor="confirm-password">
                                Confirm Password
                            </FieldLabel>
                            <PasswordInput
                                id="confirm-password"
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
                    </FieldGroup>
                </CardContent>
                <CardFooter className="justify-end border-t bg-muted/20 px-5 py-4">
                    <Button type="submit" disabled={form.processing}>
                        <RiSave3Line />
                        {form.processing ? 'Saving...' : 'Save Password'}
                    </Button>
                </CardFooter>
            </CardForm>
        </Card>
    );
}

function OwnershipTransferCard({
    schoolAdmin,
}: {
    schoolAdmin: DashboardSchoolAdmin;
}) {
    const pendingTransfer = schoolAdmin.settings.pendingOwnershipTransfer;
    const form = useForm<OwnershipTransferFormData>({
        email: '',
        name: '',
    });
    const cancelForm = useForm({});
    const emphasisClassName = 'font-medium text-foreground';
    const recipientLabel =
        form.data.name.trim() || form.data.email.trim() || 'the new admin';

    function sendInvite() {
        form.submit(storeSchoolAdminTransfer(), {
            onSuccess: () => form.reset(),
        });
    }

    function cancelInvite() {
        if (pendingTransfer === null) {
            return;
        }

        cancelForm.submit(
            destroySchoolAdminTransfer({
                schoolAdminTransfer: pendingTransfer.id,
            }),
        );
    }

    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardHeader className="gap-2">
                <CardTitle className="flex items-center gap-2 text-base">
                    <RiMailSendLine className="size-4 text-muted-foreground" />
                    Transfer Ownership
                </CardTitle>
                <p className="text-sm text-muted-foreground">
                    Invite another admin to take over this school account.
                </p>
            </CardHeader>
            <CardContent>
                {pendingTransfer ? (
                    <div className="grid gap-4">
                        <div className="grid gap-3 border border-border bg-muted/30 p-3">
                            <div className="grid gap-1">
                                <span className="text-xs font-medium tracking-normal text-muted-foreground">
                                    Pending Invite
                                </span>
                                <span className="font-medium text-foreground">
                                    {pendingTransfer.recipientName ??
                                        pendingTransfer.recipientEmail}
                                </span>
                                {pendingTransfer.recipientName ? (
                                    <span className="text-sm text-muted-foreground">
                                        {pendingTransfer.recipientEmail}
                                    </span>
                                ) : null}
                                <span className="text-xs text-muted-foreground">
                                    Expires {pendingTransfer.expiresAt}
                                </span>
                            </div>
                            <div className="grid gap-1">
                                <span className="text-xs font-medium tracking-normal text-muted-foreground">
                                    Access Code
                                </span>
                                <CopyableValue
                                    value={pendingTransfer.accessCode}
                                    className="w-fit"
                                />
                            </div>
                        </div>

                        <AlertDialog>
                            <AlertDialogTrigger asChild>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={cancelForm.processing}
                                    className="justify-self-start"
                                >
                                    Cancel Invite
                                </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle>
                                        Cancel the invite to{' '}
                                        <span className={emphasisClassName}>
                                            {pendingTransfer.recipientEmail}
                                        </span>
                                        {'?'}
                                    </AlertDialogTitle>
                                    <AlertDialogDescription>
                                        You are cancelling this ownership
                                        transfer for{' '}
                                        <span className={emphasisClassName}>
                                            {schoolAdmin.school.abbreviation}
                                        </span>
                                        . The current school admin account will
                                        stay active.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel
                                        disabled={cancelForm.processing}
                                    >
                                        Keep Invite
                                    </AlertDialogCancel>
                                    <AlertDialogAction
                                        variant="destructive"
                                        disabled={cancelForm.processing}
                                        onClick={cancelInvite}
                                    >
                                        {cancelForm.processing
                                            ? 'Cancelling...'
                                            : 'Cancel Invite'}
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    </div>
                ) : (
                    <form
                        onSubmit={(event) => event.preventDefault()}
                        className="grid gap-4"
                    >
                        <Field data-invalid={Boolean(form.errors.email)}>
                            <FieldLabel htmlFor="transfer-email">
                                New Admin Email
                            </FieldLabel>
                            <Input
                                id="transfer-email"
                                type="email"
                                value={form.data.email}
                                placeholder="admin@example.edu"
                                autoComplete="email"
                                aria-invalid={Boolean(form.errors.email)}
                                onChange={(event) =>
                                    form.setData('email', event.target.value)
                                }
                                required
                            />
                            <FieldError>{form.errors.email}</FieldError>
                        </Field>
                        <Field data-invalid={Boolean(form.errors.name)}>
                            <FieldLabel htmlFor="transfer-name">
                                New Admin Name
                            </FieldLabel>
                            <Input
                                id="transfer-name"
                                type="text"
                                value={form.data.name}
                                placeholder="Optional"
                                autoComplete="name"
                                aria-invalid={Boolean(form.errors.name)}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                            <FieldError>{form.errors.name}</FieldError>
                        </Field>

                        <AlertDialog>
                            <AlertDialogTrigger asChild>
                                <Button
                                    type="button"
                                    disabled={
                                        form.processing ||
                                        form.data.email.trim() === ''
                                    }
                                    className="justify-self-start"
                                >
                                    <RiMailSendLine />
                                    Send Invite
                                </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle>
                                        Transfer{' '}
                                        <span className={emphasisClassName}>
                                            {schoolAdmin.school.abbreviation}
                                        </span>{' '}
                                        ownership to{' '}
                                        <span className={emphasisClassName}>
                                            {recipientLabel}
                                        </span>
                                        {'?'}
                                    </AlertDialogTitle>
                                    <AlertDialogDescription>
                                        You are sending an ownership invite to{' '}
                                        <span className={emphasisClassName}>
                                            {form.data.email}
                                        </span>
                                        . Only one account can be the school
                                        admin, so your school admin access will
                                        be disabled once the invite is accepted.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel
                                        disabled={form.processing}
                                    >
                                        Cancel
                                    </AlertDialogCancel>
                                    <AlertDialogAction
                                        disabled={form.processing}
                                        onClick={sendInvite}
                                    >
                                        {form.processing
                                            ? 'Sending...'
                                            : 'Send Invite'}
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    </form>
                )}
            </CardContent>
        </Card>
    );
}

function SchoolAcademicSessionForm({
    form,
}: {
    form: DashboardSchoolAdminAcademicSessionForm;
}) {
    const academicSessionForm = useForm<AcademicSessionFormData>({
        name: form.academicSession?.name ?? '',
        starts_on: form.academicSession?.startsOn ?? '',
        ends_on: form.academicSession?.endsOn ?? '',
    });
    const title =
        form.mode === 'create'
            ? 'Create Academic Session'
            : `Edit ${form.academicSession?.name}`;
    const backRoute = dashboardSection({ section: 'academic-sessions' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (form.mode === 'create') {
            academicSessionForm.submit(storeAcademicSession());

            return;
        }

        if (form.academicSession) {
            academicSessionForm.submit(
                updateAcademicSession({
                    academicSession: form.academicSession.id,
                }),
            );
        }
    }

    return (
        <DashboardFormShell
            title={title}
            subtitle="Session dates decide whether a session is current, upcoming, or past."
            backRoute={backRoute}
        >
            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardForm onSubmit={submit}>
                    <CardContent className="p-5">
                        <FieldGroup>
                            <Field
                                data-invalid={Boolean(
                                    academicSessionForm.errors.name,
                                )}
                            >
                                <FieldLabel htmlFor="academic-session-name">
                                    Session Name
                                </FieldLabel>
                                <Input
                                    id="academic-session-name"
                                    value={academicSessionForm.data.name}
                                    placeholder="2026/2027"
                                    autoComplete="off"
                                    aria-invalid={Boolean(
                                        academicSessionForm.errors.name,
                                    )}
                                    onChange={(event) =>
                                        academicSessionForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldError>
                                    {academicSessionForm.errors.name}
                                </FieldError>
                            </Field>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    data-invalid={Boolean(
                                        academicSessionForm.errors.starts_on,
                                    )}
                                >
                                    <FieldLabel htmlFor="academic-session-start">
                                        Start Date
                                    </FieldLabel>
                                    <Input
                                        id="academic-session-start"
                                        type="date"
                                        value={
                                            academicSessionForm.data.starts_on
                                        }
                                        aria-invalid={Boolean(
                                            academicSessionForm.errors
                                                .starts_on,
                                        )}
                                        onChange={(event) =>
                                            academicSessionForm.setData(
                                                'starts_on',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <FieldError>
                                        {academicSessionForm.errors.starts_on}
                                    </FieldError>
                                </Field>
                                <Field
                                    data-invalid={Boolean(
                                        academicSessionForm.errors.ends_on,
                                    )}
                                >
                                    <FieldLabel htmlFor="academic-session-end">
                                        End Date
                                    </FieldLabel>
                                    <Input
                                        id="academic-session-end"
                                        type="date"
                                        value={academicSessionForm.data.ends_on}
                                        aria-invalid={Boolean(
                                            academicSessionForm.errors.ends_on,
                                        )}
                                        onChange={(event) =>
                                            academicSessionForm.setData(
                                                'ends_on',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <FieldError>
                                        {academicSessionForm.errors.ends_on}
                                    </FieldError>
                                </Field>
                            </div>
                        </FieldGroup>
                    </CardContent>
                    <FormFooter
                        backRoute={backRoute}
                        processing={academicSessionForm.processing}
                        label="Save Session"
                    />
                </CardForm>
            </Card>
        </DashboardFormShell>
    );
}

function SchoolFacultyForm({
    form,
}: {
    form: DashboardSchoolAdminFacultyForm;
}) {
    const facultyForm = useForm<FacultyFormData>({
        name: form.faculty?.name ?? '',
        abbreviation: form.faculty?.abbreviation ?? '',
    });
    const title =
        form.mode === 'create'
            ? 'Create Faculty'
            : `Edit ${form.faculty?.abbreviation}`;
    const backRoute = dashboardSection({ section: 'structure' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (form.mode === 'create') {
            facultyForm.submit(storeSchoolFaculty());

            return;
        }

        if (form.faculty) {
            facultyForm.submit(
                updateSchoolFaculty({
                    faculty: form.faculty.id,
                }),
            );
        }
    }

    return (
        <DashboardFormShell
            title={title}
            subtitle="Faculties group departments under the school."
            backRoute={backRoute}
        >
            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardForm onSubmit={submit}>
                    <CardContent className="p-5">
                        <FieldGroup>
                            <Field
                                data-invalid={Boolean(facultyForm.errors.name)}
                            >
                                <FieldLabel htmlFor="faculty-name">
                                    Faculty Name
                                </FieldLabel>
                                <Input
                                    id="faculty-name"
                                    value={facultyForm.data.name}
                                    placeholder="Electrical Systems and Engineering Technology"
                                    autoComplete="off"
                                    aria-invalid={Boolean(
                                        facultyForm.errors.name,
                                    )}
                                    onChange={(event) =>
                                        facultyForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldError>
                                    {facultyForm.errors.name}
                                </FieldError>
                            </Field>
                            <Field
                                data-invalid={Boolean(
                                    facultyForm.errors.abbreviation,
                                )}
                            >
                                <FieldLabel htmlFor="faculty-abbreviation">
                                    Abbreviation
                                </FieldLabel>
                                <Input
                                    id="faculty-abbreviation"
                                    value={facultyForm.data.abbreviation}
                                    placeholder="SESET"
                                    autoComplete="off"
                                    aria-invalid={Boolean(
                                        facultyForm.errors.abbreviation,
                                    )}
                                    onChange={(event) =>
                                        facultyForm.setData(
                                            'abbreviation',
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldError>
                                    {facultyForm.errors.abbreviation}
                                </FieldError>
                            </Field>
                        </FieldGroup>
                    </CardContent>
                    <FormFooter
                        backRoute={backRoute}
                        processing={facultyForm.processing}
                        label="Save Faculty"
                    />
                </CardForm>
            </Card>
        </DashboardFormShell>
    );
}

function SchoolDepartmentForm({
    form,
}: {
    form: DashboardSchoolAdminDepartmentForm;
}) {
    const fallbackFacultyId =
        form.department?.facultyId ??
        form.selectedFacultyId ??
        form.faculties[0]?.id ??
        null;
    const departmentForm = useForm<DepartmentFormData>({
        faculty_id: fallbackFacultyId?.toString() ?? '',
        name: form.department?.name ?? '',
        abbreviation: form.department?.abbreviation ?? '',
        minimum_years_to_degree:
            form.department?.minimumYearsToDegree.toString() ?? '5',
    });
    const title =
        form.mode === 'create'
            ? 'Create Department'
            : `Edit ${form.department?.abbreviation}`;
    const backRoute = dashboardSection({ section: 'structure' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (form.mode === 'create') {
            departmentForm.submit(storeSchoolDepartment());

            return;
        }

        if (form.department) {
            departmentForm.submit(
                updateSchoolDepartment({
                    department: form.department.id,
                }),
            );
        }
    }

    return (
        <DashboardFormShell
            title={title}
            subtitle="Departments sit under a faculty and define the minimum years required to earn a degree."
            backRoute={backRoute}
        >
            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardForm onSubmit={submit}>
                    <CardContent className="p-5">
                        <FieldGroup>
                            <Field
                                data-invalid={Boolean(
                                    departmentForm.errors.faculty_id,
                                )}
                            >
                                <FieldLabel htmlFor="department-faculty">
                                    Faculty
                                </FieldLabel>
                                <Select
                                    value={departmentForm.data.faculty_id}
                                    onValueChange={(value) =>
                                        departmentForm.setData(
                                            'faculty_id',
                                            value,
                                        )
                                    }
                                >
                                    <SelectTrigger id="department-faculty">
                                        <SelectValue placeholder="Select faculty" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {form.faculties.map((faculty) => (
                                            <SelectItem
                                                key={faculty.id}
                                                value={faculty.id.toString()}
                                            >
                                                {faculty.abbreviation} -{' '}
                                                {faculty.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <FieldError>
                                    {departmentForm.errors.faculty_id}
                                </FieldError>
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_10rem]">
                                <Field
                                    data-invalid={Boolean(
                                        departmentForm.errors.name,
                                    )}
                                >
                                    <FieldLabel htmlFor="department-name">
                                        Department Name
                                    </FieldLabel>
                                    <Input
                                        id="department-name"
                                        value={departmentForm.data.name}
                                        placeholder="Computer Engineering"
                                        autoComplete="off"
                                        aria-invalid={Boolean(
                                            departmentForm.errors.name,
                                        )}
                                        onChange={(event) =>
                                            departmentForm.setData(
                                                'name',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <FieldError>
                                        {departmentForm.errors.name}
                                    </FieldError>
                                </Field>
                                <Field
                                    data-invalid={Boolean(
                                        departmentForm.errors.abbreviation,
                                    )}
                                >
                                    <FieldLabel htmlFor="department-abbreviation">
                                        Abbreviation
                                    </FieldLabel>
                                    <Input
                                        id="department-abbreviation"
                                        value={departmentForm.data.abbreviation}
                                        placeholder="CPE"
                                        autoComplete="off"
                                        aria-invalid={Boolean(
                                            departmentForm.errors.abbreviation,
                                        )}
                                        onChange={(event) =>
                                            departmentForm.setData(
                                                'abbreviation',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <FieldError>
                                        {departmentForm.errors.abbreviation}
                                    </FieldError>
                                </Field>
                            </div>

                            <Field
                                data-invalid={Boolean(
                                    departmentForm.errors
                                        .minimum_years_to_degree,
                                )}
                            >
                                <FieldLabel htmlFor="minimum-years-to-degree">
                                    Minimum Years To Degree
                                </FieldLabel>
                                <Input
                                    id="minimum-years-to-degree"
                                    type="number"
                                    min={1}
                                    max={7}
                                    value={
                                        departmentForm.data
                                            .minimum_years_to_degree
                                    }
                                    aria-invalid={Boolean(
                                        departmentForm.errors
                                            .minimum_years_to_degree,
                                    )}
                                    onChange={(event) =>
                                        departmentForm.setData(
                                            'minimum_years_to_degree',
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldError>
                                    {
                                        departmentForm.errors
                                            .minimum_years_to_degree
                                    }
                                </FieldError>
                            </Field>
                        </FieldGroup>
                    </CardContent>
                    <FormFooter
                        backRoute={backRoute}
                        processing={departmentForm.processing}
                        label="Save Department"
                    />
                </CardForm>
            </Card>
        </DashboardFormShell>
    );
}

function SchoolFacultyHeadForm({
    form,
}: {
    form: DashboardSchoolAdminFacultyHeadForm;
}) {
    const fallbackFaculty =
        form.faculties.find(
            (faculty) => faculty.id === form.selectedFacultyId,
        ) ??
        form.faculties[0] ??
        null;
    const fallbackDepartment = fallbackFaculty?.departments[0] ?? null;
    const facultyHeadForm = useForm<FacultyHeadFormData>({
        faculty_id: fallbackFaculty?.id.toString() ?? '',
        department_id: fallbackDepartment?.id.toString() ?? '',
        name: '',
        email: '',
        access_code: form.suggestedAccessCode,
    });
    const selectedFaculty = useMemo(
        () =>
            form.faculties.find(
                (faculty) =>
                    faculty.id.toString() === facultyHeadForm.data.faculty_id,
            ) ?? null,
        [facultyHeadForm.data.faculty_id, form.faculties],
    );
    const backRoute = dashboardSection({ section: 'faculty-heads' });

    function changeFaculty(facultyId: string) {
        const nextFaculty =
            form.faculties.find(
                (faculty) => faculty.id.toString() === facultyId,
            ) ?? null;

        facultyHeadForm.setData('faculty_id', facultyId);
        facultyHeadForm.setData(
            'department_id',
            nextFaculty?.departments[0]?.id.toString() ?? '',
        );
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        facultyHeadForm.submit(storeSchoolFacultyHead());
    }

    return (
        <DashboardFormShell
            title="Assign Faculty Head"
            subtitle="Create the staff access record and connect it to the selected faculty."
            backRoute={backRoute}
        >
            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardForm onSubmit={submit}>
                    <CardContent className="p-5">
                        <FieldGroup>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field
                                    data-invalid={Boolean(
                                        facultyHeadForm.errors.faculty_id,
                                    )}
                                >
                                    <FieldLabel htmlFor="faculty-head-faculty">
                                        Faculty
                                    </FieldLabel>
                                    <Select
                                        value={facultyHeadForm.data.faculty_id}
                                        onValueChange={changeFaculty}
                                    >
                                        <SelectTrigger id="faculty-head-faculty">
                                            <SelectValue placeholder="Select faculty" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {form.faculties.map((faculty) => (
                                                <SelectItem
                                                    key={faculty.id}
                                                    value={faculty.id.toString()}
                                                >
                                                    {faculty.abbreviation} -{' '}
                                                    {faculty.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <FieldError>
                                        {facultyHeadForm.errors.faculty_id}
                                    </FieldError>
                                </Field>
                                <Field
                                    data-invalid={Boolean(
                                        facultyHeadForm.errors.department_id,
                                    )}
                                >
                                    <FieldLabel htmlFor="faculty-head-department">
                                        Staff Department
                                    </FieldLabel>
                                    <Select
                                        value={
                                            facultyHeadForm.data.department_id
                                        }
                                        onValueChange={(value) =>
                                            facultyHeadForm.setData(
                                                'department_id',
                                                value,
                                            )
                                        }
                                    >
                                        <SelectTrigger id="faculty-head-department">
                                            <SelectValue placeholder="Select department" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {selectedFaculty?.departments.map(
                                                (department) => (
                                                    <SelectItem
                                                        key={department.id}
                                                        value={department.id.toString()}
                                                    >
                                                        {
                                                            department.abbreviation
                                                        }{' '}
                                                        - {department.name}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <FieldError>
                                        {facultyHeadForm.errors.department_id}
                                    </FieldError>
                                </Field>
                            </div>

                            <Field
                                data-invalid={Boolean(
                                    facultyHeadForm.errors.name,
                                )}
                            >
                                <FieldLabel htmlFor="faculty-head-name">
                                    Full Name
                                </FieldLabel>
                                <Input
                                    id="faculty-head-name"
                                    value={facultyHeadForm.data.name}
                                    placeholder="Nwankwo Chika"
                                    autoComplete="name"
                                    aria-invalid={Boolean(
                                        facultyHeadForm.errors.name,
                                    )}
                                    onChange={(event) =>
                                        facultyHeadForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldError>
                                    {facultyHeadForm.errors.name}
                                </FieldError>
                            </Field>

                            <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_14rem]">
                                <Field
                                    data-invalid={Boolean(
                                        facultyHeadForm.errors.email,
                                    )}
                                >
                                    <FieldLabel htmlFor="faculty-head-email">
                                        Email
                                    </FieldLabel>
                                    <Input
                                        id="faculty-head-email"
                                        type="email"
                                        value={facultyHeadForm.data.email}
                                        placeholder="facultyhead@futo.edu"
                                        autoComplete="email"
                                        aria-invalid={Boolean(
                                            facultyHeadForm.errors.email,
                                        )}
                                        onChange={(event) =>
                                            facultyHeadForm.setData(
                                                'email',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <FieldError>
                                        {facultyHeadForm.errors.email}
                                    </FieldError>
                                </Field>
                                <Field
                                    data-invalid={Boolean(
                                        facultyHeadForm.errors.access_code,
                                    )}
                                >
                                    <FieldLabel htmlFor="faculty-head-access-code">
                                        Access Code
                                    </FieldLabel>
                                    <Input
                                        id="faculty-head-access-code"
                                        value={facultyHeadForm.data.access_code}
                                        placeholder="Auto generated if blank"
                                        autoComplete="off"
                                        aria-invalid={Boolean(
                                            facultyHeadForm.errors.access_code,
                                        )}
                                        onChange={(event) =>
                                            facultyHeadForm.setData(
                                                'access_code',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <FieldError>
                                        {facultyHeadForm.errors.access_code}
                                    </FieldError>
                                </Field>
                            </div>
                        </FieldGroup>
                    </CardContent>
                    <FormFooter
                        backRoute={backRoute}
                        processing={facultyHeadForm.processing}
                        label="Save Faculty Head"
                    />
                </CardForm>
            </Card>
        </DashboardFormShell>
    );
}

function FacultyStructureCard({
    faculty,
}: {
    faculty: DashboardSchoolAdminFaculty;
}) {
    const departmentCount = faculty.departments.length;
    const departmentCountLabel = `${departmentCount} ${
        departmentCount === 1 ? 'Department' : 'Departments'
    }`;

    return (
        <Card className="gap-0 border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardHeader className="shrink-0 border-b border-border">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <div className="flex min-w-0 flex-wrap items-center gap-2">
                            <CardTitle className="truncate text-base">
                                {faculty.abbreviation}
                            </CardTitle>
                            <Badge
                                variant="outline"
                                className="border-border bg-muted/60 text-muted-foreground"
                            >
                                {departmentCountLabel}
                            </Badge>
                        </div>
                        <p className="mt-1 truncate text-sm text-muted-foreground">
                            {faculty.name}
                        </p>
                    </div>
                    <ActionLink
                        href={editSchoolFaculty({ faculty: faculty.id })}
                        icon={<RiPencilLine className="size-3.5" />}
                    >
                        Edit Faculty
                    </ActionLink>
                </div>
            </CardHeader>
            <CardContent className="min-h-0 flex-1 p-0">
                <ScrollArea className={departmentCount > 5 ? 'h-72' : ''}>
                    {departmentCount > 0 ? (
                        <div className="divide-y divide-border">
                            {faculty.departments.map((department) => (
                                <div
                                    key={department.id}
                                    className="flex items-start justify-between gap-4 px-4 py-3 even:bg-muted/30"
                                >
                                    <div className="min-w-0">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <span className="font-medium">
                                                {department.abbreviation}
                                            </span>
                                            <Badge
                                                variant="outline"
                                                className="border-border bg-muted/60 text-muted-foreground"
                                            >
                                                {
                                                    department.minimumYearsToDegree
                                                }{' '}
                                                years
                                            </Badge>
                                        </div>
                                        <div className="mt-1 truncate text-sm text-muted-foreground">
                                            {department.name}
                                        </div>
                                    </div>
                                    <ActionLink
                                        className="shrink-0"
                                        href={editSchoolDepartment({
                                            department: department.id,
                                        })}
                                        icon={
                                            <RiPencilLine className="size-3.5" />
                                        }
                                    >
                                        Edit
                                    </ActionLink>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="px-4 py-6 text-sm text-muted-foreground">
                            No departments have been added yet.
                        </div>
                    )}
                </ScrollArea>
            </CardContent>
            <CardFooter className="shrink-0 border-t border-border bg-background">
                <Button variant="outline" size="sm" asChild>
                    <Link
                        href={createSchoolDepartment({
                            query: {
                                faculty_id: faculty.id,
                            },
                        })}
                    >
                        <RiAddLine />
                        Add Department
                    </Link>
                </Button>
            </CardFooter>
        </Card>
    );
}

function FacultyHeadTable({
    faculties,
}: {
    faculties: DashboardSchoolAdminFaculty[];
}) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Faculty</TableHead>
                    <TableHead>Faculty Head</TableHead>
                    <TableHead>Access Code</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Action</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {faculties.map((faculty) => (
                    <TableRow
                        key={faculty.id}
                        className="odd:bg-background even:bg-muted/25"
                    >
                        <TableCell>
                            <div className="font-medium">
                                {faculty.abbreviation}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                {faculty.name}
                            </div>
                        </TableCell>
                        <TableCell>
                            {faculty.head ? (
                                <PersonCell
                                    name={faculty.head.name}
                                    secondary={faculty.head.email}
                                    avatar={faculty.head.avatar}
                                />
                            ) : (
                                <span className="text-sm text-muted-foreground">
                                    No faculty head assigned
                                </span>
                            )}
                        </TableCell>
                        <TableCell>
                            <CopyableValue
                                value={faculty.head?.accessCode}
                                emptyLabel="No code"
                            />
                        </TableCell>
                        <TableCell>
                            <HeadStatusBadge faculty={faculty} />
                        </TableCell>
                        <TableCell className="text-right">
                            <ActionLink
                                href={createSchoolFacultyHead({
                                    query: {
                                        faculty_id: faculty.id,
                                    },
                                })}
                                icon={
                                    <RiUserSettingsLine className="size-3.5" />
                                }
                            >
                                {faculty.head ? 'Replace' : 'Assign'}
                            </ActionLink>
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

function FacultyHeadSummaryTable({
    faculties,
}: {
    faculties: DashboardSchoolAdminFaculty[];
}) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Faculty</TableHead>
                    <TableHead>Faculty Head</TableHead>
                    <TableHead>Status</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {faculties.slice(0, 5).map((faculty) => (
                    <TableRow
                        key={faculty.id}
                        className="odd:bg-background even:bg-muted/25"
                    >
                        <TableCell>
                            <div className="font-medium">
                                {faculty.abbreviation}
                            </div>
                            <div className="text-xs text-muted-foreground">
                                {faculty.departmentsCount} departments
                            </div>
                        </TableCell>
                        <TableCell>
                            {faculty.head ? (
                                <PersonCell
                                    name={faculty.head.name}
                                    secondary={faculty.head.email}
                                    avatar={faculty.head.avatar}
                                />
                            ) : (
                                <span className="text-sm text-muted-foreground">
                                    Unassigned
                                </span>
                            )}
                        </TableCell>
                        <TableCell>
                            <HeadStatusBadge faculty={faculty} />
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

function SessionSummary({
    label,
    session,
}: {
    label: string;
    session: DashboardAcademicSession | null;
}) {
    return (
        <div className="border border-border bg-muted/30 p-3">
            <div className="text-xs font-medium text-muted-foreground">
                {label}
            </div>
            {session ? (
                <>
                    <div className="mt-2 flex items-center gap-2">
                        <span className="font-semibold">{session.name}</span>
                        <SessionStatusBadge status={session.status} />
                    </div>
                    <div className="mt-1 text-xs text-muted-foreground">
                        {session.startsOn} to {session.endsOn}
                    </div>
                </>
            ) : (
                <div className="mt-2 text-sm text-muted-foreground">
                    Not set
                </div>
            )}
        </div>
    );
}

function HeadStatusBadge({
    faculty,
}: {
    faculty: DashboardSchoolAdminFaculty;
}) {
    if (!faculty.head) {
        return (
            <Badge
                variant="outline"
                className="border-border bg-muted/60 text-muted-foreground"
            >
                No Faculty Head
            </Badge>
        );
    }

    if (faculty.head.activatedAt === null) {
        return (
            <Badge
                variant="outline"
                className="border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-400/30 dark:bg-blue-400/10 dark:text-blue-300"
            >
                Pending Signup
            </Badge>
        );
    }

    return (
        <Badge
            variant="outline"
            className="border-green-200 bg-green-50 text-green-700 dark:border-green-400/30 dark:bg-green-400/10 dark:text-green-300"
        >
            Active
        </Badge>
    );
}

function SessionStatusBadge({
    status,
}: {
    status: DashboardAcademicSession['status'];
}) {
    const className =
        status === 'Current'
            ? 'border-green-200 bg-green-50 text-green-700 dark:border-green-400/30 dark:bg-green-400/10 dark:text-green-300'
            : status === 'Upcoming'
              ? 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-400/30 dark:bg-blue-400/10 dark:text-blue-300'
              : 'border-border bg-muted/60 text-muted-foreground';

    return (
        <Badge variant="outline" className={className}>
            {status}
        </Badge>
    );
}

function PageHeader({ title, action }: { title: string; action?: ReactNode }) {
    return (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h1 className="text-xl font-semibold tracking-normal">{title}</h1>
            {action}
        </div>
    );
}

function DashboardSurface({
    title,
    action,
    children,
}: {
    title: string;
    action?: ReactNode;
    children: ReactNode;
}) {
    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardHeader className="flex-row items-center justify-between gap-4 border-b border-border">
                <CardTitle className="text-base">{title}</CardTitle>
                {action}
            </CardHeader>
            <CardContent className="p-0">{children}</CardContent>
        </Card>
    );
}

function MetricBlock({
    label,
    value,
}: {
    label: string;
    value: number | string;
}) {
    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardContent className="p-4">
                <div className="text-xs font-medium text-muted-foreground">
                    {label}
                </div>
                <div className="mt-2 text-2xl font-semibold tabular-nums">
                    {value}
                </div>
            </CardContent>
        </Card>
    );
}

function DashboardFormShell({
    title,
    subtitle,
    backRoute,
    children,
}: {
    title: string;
    subtitle: string;
    backRoute: ReturnType<typeof dashboardSection>;
    children: ReactNode;
}) {
    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-4">
            <div className="flex items-center justify-between gap-4">
                <div className="min-w-0">
                    <h1 className="truncate text-xl font-semibold tracking-normal">
                        {title}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {subtitle}
                    </p>
                </div>
                <Link
                    href={backRoute}
                    className="inline-flex items-center gap-1 text-sm font-medium text-link underline-offset-4 hover:underline"
                >
                    <RiArrowLeftLine className="size-4" />
                    Back
                </Link>
            </div>
            {children}
        </div>
    );
}

function FormFooter({
    backRoute,
    processing,
    label,
}: {
    backRoute: ReturnType<typeof dashboardSection>;
    processing: boolean;
    label: string;
}) {
    return (
        <CardFooter className="flex justify-end gap-2 border-t border-border">
            <Button asChild type="button" variant="outline">
                <Link href={backRoute}>Cancel</Link>
            </Button>
            <Button type="submit" disabled={processing}>
                <RiSave3Line />
                {processing ? 'Saving...' : label}
            </Button>
        </CardFooter>
    );
}
