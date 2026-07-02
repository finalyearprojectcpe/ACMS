import { Link, router, useForm } from '@inertiajs/react';
import {
    RiFileExcel2Line,
    RiLockPasswordLine,
    RiMailSendLine,
    RiSave3Line,
    RiUserAddLine,
} from '@remixicon/react';
import { useMemo, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';

import {
    createDepartmentHod,
    createFacultyStaff,
    destroyFacultyHeadTransfer,
    importFacultyStaff,
    storeFacultyHeadTransfer,
    storeFacultyStaff,
    storeFacultyHod,
    storeImportedFacultyStaff,
    storeImportedFacultyHods,
    updateFacultyHeadPassword,
} from '@/actions/App/Http/Controllers/DashboardController';
import { section as dashboardSection } from '@/actions/App/Http/Controllers/DashboardController';
import { ActionLink } from '@/components/action-link';
import { CopyableValue } from '@/components/copyable-value';
import {
    AverageGpaByLevelBarChart,
    ChartCard,
    ChartSelect,
    ResultClassificationPieChart,
    averageGpaByLevelRows,
    classificationRowsForStudents,
} from '@/components/dashboard-charts';
import {
    FacultyHeadCourseOfferingForm,
    FacultyHeadCoursesDashboard,
} from '@/components/faculty-head-courses-dashboard';
import { PersonCell } from '@/components/person-cell';
import { ProfilePhotoSettingsCard } from '@/components/profile-photo-settings-card';
import { RoleBadge } from '@/components/role-badge';
import { SpreadsheetImportPreview } from '@/components/spreadsheet-import-preview';
import type {
    SpreadsheetImportBatch,
    SpreadsheetImportCallbacks,
} from '@/components/spreadsheet-import-preview';
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
    Combobox,
    ComboboxContent,
    ComboboxEmpty,
    ComboboxInput,
    ComboboxItem,
    ComboboxList,
} from '@/components/ui/combobox';
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
    DashboardFacultyHead,
    DashboardFacultyHeadCourseOfferingForm,
    DashboardFacultyHeadCourses,
    DashboardFacultyHeadDepartment,
    DashboardFacultyHeadHodForm,
    DashboardFacultyHeadStaffForm,
    DashboardFacultyHeadStaff,
    DashboardFacultyHeadTask,
} from '@/types';

type FacultyHeadDashboardProps = {
    facultyHead: DashboardFacultyHead;
    hodForm: DashboardFacultyHeadHodForm | null;
    staffForm: DashboardFacultyHeadStaffForm | null;
    courses: DashboardFacultyHeadCourses | null;
    courseForm: DashboardFacultyHeadCourseOfferingForm | null;
    section: string;
    sectionTitle: string;
};

type HodFormData = {
    department_id: string;
    staff_profile_id: string;
    redirect_section: string;
};

type StaffFormData = {
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

type FacultyHeadTransferFormData = {
    email: string;
    name: string;
};

export function FacultyHeadDashboard({
    facultyHead,
    hodForm,
    staffForm,
    courses,
    courseForm,
    section,
    sectionTitle,
}: FacultyHeadDashboardProps) {
    if (courseForm !== null) {
        return <FacultyHeadCourseOfferingForm form={courseForm} />;
    }

    if (hodForm !== null) {
        return hodForm.mode === 'import' ? (
            <FacultyHeadHodImport form={hodForm} />
        ) : (
            <FacultyHeadHodForm form={hodForm} />
        );
    }

    if (staffForm !== null) {
        return staffForm.mode === 'import' ? (
            <FacultyHeadStaffImport form={staffForm} />
        ) : (
            <FacultyHeadStaffForm form={staffForm} />
        );
    }

    if (section === 'departments') {
        return (
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-4">
                <h1 className="text-xl font-semibold tracking-normal">
                    Departments
                </h1>
                <DepartmentMetrics departments={facultyHead.departments} />
                <DepartmentCardGrid departments={facultyHead.departments} />
            </div>
        );
    }

    if (section === 'staff') {
        return <FacultyHeadStaffPage staff={facultyHead.staff} />;
    }

    if (section === 'sessions-courses' && courses !== null) {
        return <FacultyHeadCoursesDashboard courses={courses} />;
    }

    if (section === 'settings') {
        return <FacultyHeadSettingsPage facultyHead={facultyHead} />;
    }

    if (section !== 'home') {
        return (
            <h1 className="text-2xl font-semibold tracking-normal">
                {sectionTitle}
            </h1>
        );
    }

    return <FacultyHeadHome facultyHead={facultyHead} />;
}

function FacultyHeadHome({
    facultyHead,
}: {
    facultyHead: DashboardFacultyHead;
}) {
    const departmentOptions = useMemo(
        () => [
            { value: 'all', label: 'All Departments' },
            ...facultyHead.departments.map((department) => ({
                value: department.abbreviation,
                label: `${department.abbreviation} Department`,
            })),
        ],
        [facultyHead.departments],
    );
    const [gpaDepartment, setGpaDepartment] = useState('all');
    const [classificationDepartment, setClassificationDepartment] =
        useState('all');
    const gpaStudents = useMemo(
        () =>
            gpaDepartment === 'all'
                ? facultyHead.students
                : facultyHead.students.filter(
                      (student) =>
                          student.department.abbreviation === gpaDepartment,
                  ),
        [facultyHead.students, gpaDepartment],
    );
    const classificationStudents = useMemo(
        () =>
            classificationDepartment === 'all'
                ? facultyHead.students
                : facultyHead.students.filter(
                      (student) =>
                          student.department.abbreviation ===
                          classificationDepartment,
                  ),
        [classificationDepartment, facultyHead.students],
    );

    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <MetricBlock
                    label="Departments"
                    value={facultyHead.metrics.departments}
                />
                <MetricBlock
                    label="Active Staff"
                    value={facultyHead.metrics.activeStaff}
                />
                <MetricBlock
                    label="Pending Signups"
                    value={facultyHead.metrics.pendingSignups}
                />
                <MetricBlock
                    label="Departments Without HOD"
                    value={facultyHead.metrics.departmentsWithoutHod}
                />
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <ChartCard
                    title="GPA by Level"
                    detail="Academic performance across the selected faculty scope."
                    control={
                        <ChartSelect
                            value={gpaDepartment}
                            options={departmentOptions}
                            label="Select department"
                            onChange={setGpaDepartment}
                        />
                    }
                >
                    <AverageGpaByLevelBarChart
                        rows={averageGpaByLevelRows(
                            gpaStudents,
                            facultyHead.levels,
                        )}
                    />
                </ChartCard>

                <ChartCard
                    title="Result Class Distribution"
                    detail="CGPA classification across the selected faculty scope."
                    control={
                        <ChartSelect
                            value={classificationDepartment}
                            options={departmentOptions}
                            label="Select department"
                            onChange={setClassificationDepartment}
                        />
                    }
                >
                    <ResultClassificationPieChart
                        rows={classificationRowsForStudents(
                            classificationStudents,
                        )}
                    />
                </ChartCard>
            </div>

            <div className="grid gap-4 xl:grid-cols-[minmax(0,1fr)_minmax(0,0.9fr)]">
                <NeedsAttentionPanel tasks={facultyHead.pendingTasks} />
                <PendingSignupPanel
                    pendingAccessCodes={facultyHead.pendingAccessCodes}
                />
            </div>

            <FacultyCourseSetupPanel facultyHead={facultyHead} />
        </div>
    );
}

function FacultyHeadSettingsPage({
    facultyHead,
}: {
    facultyHead: DashboardFacultyHead;
}) {
    return (
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-4">
            <h1 className="text-xl font-semibold tracking-normal">
                Profile Settings
            </h1>
            <div className="grid gap-4 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
                <div className="grid content-start gap-4">
                    <ProfilePhotoSettingsCard
                        inputId="faculty-head-profile-photo"
                        user={facultyHead.settings.head}
                    />
                    <PasswordSettingsCard />
                </div>
                <FacultyHeadTransferCard facultyHead={facultyHead} />
            </div>
        </div>
    );
}

function PasswordSettingsCard() {
    const form = useForm<PasswordFormData>({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.submit(updateFacultyHeadPassword(), {
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
                </CardHeader>
                <CardContent className="px-5 pb-6">
                    <FieldGroup className="gap-6">
                        <Field
                            data-invalid={Boolean(form.errors.current_password)}
                        >
                            <FieldLabel htmlFor="faculty-current-password">
                                Current Password
                            </FieldLabel>
                            <PasswordInput
                                id="faculty-current-password"
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
                            <FieldLabel htmlFor="faculty-new-password">
                                New Password
                            </FieldLabel>
                            <PasswordInput
                                id="faculty-new-password"
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
                            <FieldLabel htmlFor="faculty-confirm-password">
                                Confirm Password
                            </FieldLabel>
                            <PasswordInput
                                id="faculty-confirm-password"
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

function FacultyHeadTransferCard({
    facultyHead,
}: {
    facultyHead: DashboardFacultyHead;
}) {
    const pendingTransfer = facultyHead.settings.pendingOwnershipTransfer;
    const form = useForm<FacultyHeadTransferFormData>({
        email: '',
        name: '',
    });
    const cancelForm = useForm({});
    const emphasisClassName = 'font-medium text-foreground';
    const facultyLabel = facultyHead.settings.faculty.abbreviation;
    const recipientLabel =
        form.data.name.trim() ||
        form.data.email.trim() ||
        'the new faculty head';

    function sendInvite() {
        form.submit(storeFacultyHeadTransfer(), {
            onSuccess: () => form.reset(),
        });
    }

    function cancelInvite() {
        if (pendingTransfer === null) {
            return;
        }

        cancelForm.submit(
            destroyFacultyHeadTransfer({
                facultyHeadTransfer: pendingTransfer.id,
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
                    Invite another {facultyLabel} staff member to take over.
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
                                        You are cancelling this faculty head
                                        invite for{' '}
                                        <span className={emphasisClassName}>
                                            {facultyLabel}
                                        </span>
                                        . You will remain the faculty head.
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
                        <div className="grid gap-4 md:grid-cols-2">
                            <Field data-invalid={Boolean(form.errors.email)}>
                                <FieldLabel htmlFor="faculty-transfer-email">
                                    New Faculty Head Email
                                </FieldLabel>
                                <Input
                                    id="faculty-transfer-email"
                                    type="email"
                                    value={form.data.email}
                                    placeholder="staff@example.edu"
                                    autoComplete="email"
                                    aria-invalid={Boolean(form.errors.email)}
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
                            <Field data-invalid={Boolean(form.errors.name)}>
                                <FieldLabel htmlFor="faculty-transfer-name">
                                    Display Name
                                </FieldLabel>
                                <Input
                                    id="faculty-transfer-name"
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
                        </div>

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
                                            {facultyLabel}
                                        </span>{' '}
                                        faculty head access to{' '}
                                        <span className={emphasisClassName}>
                                            {recipientLabel}
                                        </span>
                                        {'?'}
                                    </AlertDialogTitle>
                                    <AlertDialogDescription>
                                        You are sending a faculty head invite to{' '}
                                        <span className={emphasisClassName}>
                                            {form.data.email}
                                        </span>
                                        . Your staff account will stay active.
                                        Once the invite is accepted, you will no
                                        longer be listed as the faculty head for{' '}
                                        <span className={emphasisClassName}>
                                            {facultyLabel}
                                        </span>
                                        .
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

function NeedsAttentionPanel({ tasks }: { tasks: DashboardFacultyHeadTask[] }) {
    return (
        <DashboardSurface
            title="Needs Attention"
            action={
                <ActionLink href={dashboardSection({ section: 'departments' })}>
                    View departments
                </ActionLink>
            }
        >
            {tasks.length > 0 ? (
                <ScrollArea className="max-h-80">
                    <div className="grid divide-y divide-border">
                        {tasks.map((task) => (
                            <div
                                key={task.id}
                                className="grid gap-2 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
                            >
                                <div className="min-w-0">
                                    <div className="font-medium">
                                        {task.title}
                                    </div>
                                    <div className="mt-0.5 text-xs text-muted-foreground">
                                        {task.detail}
                                    </div>
                                </div>
                                <StatusBadge status={task.status} />
                            </div>
                        ))}
                    </div>
                </ScrollArea>
            ) : (
                <EmptyPanelMessage>
                    No faculty setup needs attention right now.
                </EmptyPanelMessage>
            )}
        </DashboardSurface>
    );
}

function PendingSignupPanel({
    pendingAccessCodes,
}: {
    pendingAccessCodes: DashboardFacultyHead['pendingAccessCodes'];
}) {
    return (
        <DashboardSurface
            title="Pending Staff Signups"
            action={
                <ActionLink href={dashboardSection({ section: 'staff' })}>
                    View staff
                </ActionLink>
            }
        >
            {pendingAccessCodes.length > 0 ? (
                <ScrollArea className="max-h-80">
                    <div className="grid divide-y divide-border">
                        {pendingAccessCodes.slice(0, 6).map((member) => (
                            <div
                                key={member.id}
                                className="grid gap-3 px-4 py-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
                            >
                                <PersonCell
                                    name={member.name}
                                    secondary={[member.department, member.email]
                                        .filter(Boolean)
                                        .join(' · ')}
                                    avatar={member.avatar}
                                />
                                <div className="flex flex-wrap items-center gap-2 sm:justify-end">
                                    <RoleBadge
                                        role={member.role}
                                        context={member.roleContext}
                                    />
                                    <CopyableValue
                                        value={member.accessCode}
                                        className="max-w-40"
                                    />
                                </div>
                            </div>
                        ))}
                    </div>
                </ScrollArea>
            ) : (
                <EmptyPanelMessage>
                    All provisioned staff have completed signup.
                </EmptyPanelMessage>
            )}
        </DashboardSurface>
    );
}

function FacultyCourseSetupPanel({
    facultyHead,
}: {
    facultyHead: DashboardFacultyHead;
}) {
    const session = facultyHead.courseSetupSession;

    return (
        <DashboardSurface
            title="Current Session Faculty Course Setup"
            action={
                <ActionLink
                    href={dashboardSection({
                        section: 'sessions-courses',
                    })}
                >
                    Manage faculty courses
                </ActionLink>
            }
        >
            {session ? (
                <div className="grid gap-0">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-border px-4 py-3 text-sm">
                        <div className="min-w-0">
                            <div className="font-medium">{session.name}</div>
                            <div className="text-xs text-muted-foreground">
                                {session.startsOn} to {session.endsOn}
                            </div>
                        </div>
                        <StatusBadge status={session.status} />
                    </div>
                    <Table className="text-sm">
                        <TableHeader>
                            <TableRow>
                                <TableHead className="px-4">Semester</TableHead>
                                <TableHead className="text-center">
                                    Faculty Courses
                                </TableHead>
                                <TableHead className="text-center">
                                    Units
                                </TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {facultyHead.courseSetup.map((item) => (
                                <TableRow
                                    key={item.id}
                                    className="odd:bg-background even:bg-muted/25"
                                >
                                    <TableCell className="px-4 font-medium">
                                        {item.label}
                                    </TableCell>
                                    <TableCell className="text-center tabular-nums">
                                        {item.courseCount}
                                    </TableCell>
                                    <TableCell className="text-center tabular-nums">
                                        {item.totalUnits}
                                    </TableCell>
                                    <TableCell>
                                        <StatusBadge status={item.status} />
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            ) : (
                <EmptyPanelMessage>
                    No current academic session has been set for this school.
                </EmptyPanelMessage>
            )}
        </DashboardSurface>
    );
}

function EmptyPanelMessage({ children }: { children: ReactNode }) {
    return (
        <div className="px-4 py-6 text-sm text-muted-foreground">
            {children}
        </div>
    );
}

function FacultyHeadHodForm({
    form: hodForm,
}: {
    form: DashboardFacultyHeadHodForm;
}) {
    const scopedDepartment = hodForm.department;
    const selectedDepartmentId =
        scopedDepartment?.id.toString() ??
        hodForm.selectedDepartmentId?.toString() ??
        hodForm.departments[0]?.id.toString() ??
        '';
    const staffOptions = scopedDepartment
        ? hodForm.staff
        : hodForm.staff.filter(
              (staff) =>
                  staff.department?.id?.toString() === selectedDepartmentId,
          );
    const assignableStaff = staffOptions.filter((staff) => staff.canAssignHod);
    const form = useForm<HodFormData>({
        department_id: selectedDepartmentId,
        staff_profile_id:
            hodForm.selectedStaffProfileId?.toString() ??
            assignableStaff[0]?.staffProfileId.toString() ??
            '',
        redirect_section: 'departments',
    });
    const selectedStaff = staffOptions.find(
        (staff) =>
            staff.staffProfileId.toString() === form.data.staff_profile_id,
    );
    const backRoute = dashboardSection({ section: 'departments' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.submit(storeFacultyHod());
    }

    return (
        <div className="mx-auto flex w-full max-w-3xl flex-col gap-4">
            <div className="flex items-center justify-between gap-4">
                <div className="min-w-0">
                    <h1 className="truncate text-xl font-semibold tracking-normal">
                        {scopedDepartment
                            ? `Assign HOD for ${scopedDepartment.abbreviation}`
                            : 'Assign Department HOD'}
                    </h1>
                    {scopedDepartment ? (
                        <p className="mt-1 text-sm text-muted-foreground">
                            {scopedDepartment.name}
                        </p>
                    ) : null}
                </div>
                <Link
                    href={backRoute}
                    className="text-sm font-medium text-link underline-offset-4 hover:underline"
                >
                    Back
                </Link>
            </div>

            {scopedDepartment ? (
                <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                    <CardContent className="grid gap-3 p-4 sm:grid-cols-2">
                        <DepartmentContextBlock
                            label="Department"
                            title={scopedDepartment.abbreviation}
                            detail={scopedDepartment.name}
                        />
                        <div className="border border-border bg-muted/35 px-3 py-2">
                            <div className="mb-2 text-xs text-muted-foreground">
                                Current HOD
                            </div>
                            {scopedDepartment.currentHod ? (
                                <PersonCell
                                    name={scopedDepartment.currentHod.name}
                                    secondary={
                                        scopedDepartment.currentHod.email
                                    }
                                    avatar={scopedDepartment.currentHod.avatar}
                                />
                            ) : (
                                <div className="text-sm text-muted-foreground">
                                    No HOD assigned
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>
            ) : null}

            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardHeader className="border-b border-border">
                    <CardTitle>
                        {scopedDepartment
                            ? `Select HOD From ${scopedDepartment.abbreviation} Staff`
                            : 'Select Department HOD'}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit}>
                        <FieldGroup className="gap-5">
                            {!scopedDepartment ? (
                                <Field
                                    data-invalid={Boolean(
                                        form.errors.department_id,
                                    )}
                                >
                                    <FieldLabel htmlFor="department-id">
                                        Department
                                    </FieldLabel>
                                    <Select
                                        name="department_id"
                                        value={form.data.department_id}
                                        onValueChange={(value) =>
                                            form.setData('department_id', value)
                                        }
                                        required
                                    >
                                        <SelectTrigger id="department-id">
                                            <SelectValue placeholder="Select department" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {hodForm.departments.map(
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
                                        {form.errors.department_id}
                                    </FieldError>
                                </Field>
                            ) : null}

                            <Field
                                data-invalid={Boolean(
                                    form.errors.staff_profile_id,
                                )}
                            >
                                <FieldLabel htmlFor="hod-staff-profile-id">
                                    {scopedDepartment
                                        ? `HOD Candidate From ${scopedDepartment.abbreviation}`
                                        : 'HOD Candidate'}
                                </FieldLabel>
                                <StaffCombobox
                                    id="hod-staff-profile-id"
                                    staff={staffOptions}
                                    value={form.data.staff_profile_id}
                                    placeholder={
                                        scopedDepartment
                                            ? `Search ${scopedDepartment.abbreviation} staff`
                                            : 'Search staff'
                                    }
                                    onChange={(value) =>
                                        form.setData('staff_profile_id', value)
                                    }
                                />
                                <FieldError>
                                    {form.errors.staff_profile_id}
                                </FieldError>
                            </Field>

                            {selectedStaff ? (
                                <div className="border border-border bg-muted/35 px-3 py-2">
                                    <div className="mb-2 text-xs text-muted-foreground">
                                        Selected Candidate
                                    </div>
                                    <PersonCell
                                        name={selectedStaff.name}
                                        secondary={selectedStaff.email}
                                        avatar={selectedStaff.avatar}
                                    />
                                </div>
                            ) : null}

                            <div className="flex justify-end gap-2 pt-2">
                                <Button variant="outline" asChild>
                                    <Link href={backRoute}>Cancel</Link>
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={
                                        form.processing ||
                                        !selectedStaff?.canAssignHod
                                    }
                                >
                                    {form.processing
                                        ? 'Saving...'
                                        : 'Assign HOD'}
                                </Button>
                            </div>
                        </FieldGroup>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}

function DepartmentContextBlock({
    label,
    title,
    detail,
}: {
    label: string;
    title: string;
    detail: string;
}) {
    return (
        <div className="border border-border bg-muted/35 px-3 py-2">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-lg font-semibold">{title}</div>
            <div className="mt-0.5 truncate text-xs text-muted-foreground">
                {detail}
            </div>
        </div>
    );
}

function StaffCombobox({
    id,
    staff,
    value,
    placeholder,
    onChange,
}: {
    id: string;
    staff: DashboardFacultyHeadStaff[];
    value: string;
    placeholder: string;
    onChange: (value: string) => void;
}) {
    const staffIds = staff.map((member) => member.staffProfileId.toString());
    const selectedStaff = staff.find(
        (member) => member.staffProfileId.toString() === value,
    );

    function labelFor(staffProfileId: string): string {
        return (
            staff.find(
                (member) => member.staffProfileId.toString() === staffProfileId,
            )?.name ?? ''
        );
    }

    return (
        <Combobox<string>
            items={staffIds}
            value={value || null}
            onValueChange={(nextValue) => onChange(nextValue ?? '')}
            itemToStringLabel={labelFor}
            itemToStringValue={(staffProfileId) => staffProfileId}
        >
            <ComboboxInput
                id={id}
                placeholder={selectedStaff?.name ?? placeholder}
                className="w-full"
                showClear
            />
            <ComboboxContent>
                <ComboboxEmpty>No staff found.</ComboboxEmpty>
                <ComboboxList>
                    {staff.map((member) => (
                        <ComboboxItem
                            key={member.staffProfileId}
                            value={member.staffProfileId.toString()}
                            disabled={!member.canAssignHod}
                        >
                            <div className="flex min-w-0 flex-col gap-1">
                                <PersonCell
                                    name={member.name}
                                    secondary={member.email}
                                    avatar={member.avatar}
                                    className="min-w-0"
                                />
                                <div className="truncate pl-11 text-xs text-muted-foreground">
                                    {staffAssignmentDetail(member)}
                                </div>
                            </div>
                        </ComboboxItem>
                    ))}
                </ComboboxList>
            </ComboboxContent>
        </Combobox>
    );
}

function staffAssignmentDetail(staff: DashboardFacultyHeadStaff): string {
    const department = staff.department?.abbreviation ?? 'No department';

    if (staff.assignmentBlockReason !== null) {
        return `${department} - ${staff.assignmentBlockReason}`;
    }

    return `${department} - ${staff.status}`;
}

function FacultyHeadHodImport({
    form: hodForm,
}: {
    form: DashboardFacultyHeadHodForm;
}) {
    function submitRows(
        rows: Array<Record<string, string>>,
        fileName: string | null,
        batch: SpreadsheetImportBatch,
        callbacks: SpreadsheetImportCallbacks,
    ) {
        router.post(
            storeImportedFacultyHods.url(),
            {
                file_name: fileName ?? '',
                import_key: batch.importKey,
                batch_number: batch.batchNumber,
                total_batches: batch.totalBatches,
                start_row: batch.startRow,
                total_rows: batch.totalRows,
                batch_size: batch.batchSize,
                rows,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onError: callbacks.onError,
                onSuccess: callbacks.onSuccess,
                onFinish: callbacks.onFinish,
            },
        );
    }

    return (
        <div className="mx-auto flex w-full max-w-5xl flex-col gap-4">
            <div className="flex justify-end">
                <Button variant="outline" asChild>
                    <Link href={dashboardSection({ section: 'departments' })}>
                        Cancel
                    </Link>
                </Button>
            </div>
            <SpreadsheetImportPreview
                title="Import HODs From Spreadsheet"
                submitLabel="Import HODs"
                submitDisabled={hodForm.departments.length === 0}
                fields={[
                    {
                        key: 'department',
                        label: 'Department',
                        aliases: [
                            'department code',
                            'department abbreviation',
                            'dept',
                        ],
                        defaultColumnIndex: 0,
                        required: true,
                    },
                    {
                        key: 'name',
                        label: 'Full Name',
                        aliases: ['name', 'staff name'],
                        defaultColumnIndex: 1,
                    },
                    {
                        key: 'email',
                        label: 'Email',
                        aliases: ['email address', 'staff email'],
                        defaultColumnIndex: 2,
                        required: true,
                    },
                    {
                        key: 'accessCode',
                        label: 'Access Code',
                        aliases: ['access code', 'code'],
                        defaultColumnIndex: 3,
                        description:
                            'Leave unmapped or blank to auto-generate one.',
                        emptyValueLabel: 'Auto-generated',
                    },
                ]}
                onSubmit={submitRows}
            />
            {hodForm.departments.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No departments are available for this faculty.
                </p>
            ) : null}
        </div>
    );
}

function FacultyHeadStaffForm({
    form: staffForm,
}: {
    form: DashboardFacultyHeadStaffForm;
}) {
    const form = useForm<StaffFormData>({
        department_id: staffForm.departments[0]?.id.toString() ?? '',
        name: '',
        email: '',
        access_code: staffForm.suggestedAccessCode,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.submit(storeFacultyStaff());
    }

    return (
        <div className="mx-auto w-full max-w-3xl">
            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardHeader className="border-b border-border">
                    <CardTitle>Add Single Staff</CardTitle>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit}>
                        <FieldGroup className="gap-5">
                            <Field
                                data-invalid={Boolean(
                                    form.errors.department_id,
                                )}
                            >
                                <FieldLabel htmlFor="staff-department-id">
                                    Department
                                </FieldLabel>
                                <Select
                                    name="department_id"
                                    value={form.data.department_id}
                                    onValueChange={(value) =>
                                        form.setData('department_id', value)
                                    }
                                    required
                                >
                                    <SelectTrigger id="staff-department-id">
                                        <SelectValue placeholder="Select department" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {staffForm.departments.map(
                                            (department) => (
                                                <SelectItem
                                                    key={department.id}
                                                    value={department.id.toString()}
                                                >
                                                    {department.abbreviation} -{' '}
                                                    {department.name}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <FieldError>
                                    {form.errors.department_id}
                                </FieldError>
                            </Field>

                            <Field data-invalid={Boolean(form.errors.name)}>
                                <FieldLabel htmlFor="staff-name">
                                    Full Name
                                </FieldLabel>
                                <Input
                                    id="staff-name"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    required
                                />
                                <FieldError>{form.errors.name}</FieldError>
                            </Field>

                            <Field data-invalid={Boolean(form.errors.email)}>
                                <FieldLabel htmlFor="staff-email">
                                    Email
                                </FieldLabel>
                                <Input
                                    id="staff-email"
                                    type="email"
                                    value={form.data.email}
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
                                data-invalid={Boolean(form.errors.access_code)}
                            >
                                <FieldLabel htmlFor="staff-access-code">
                                    Access Code
                                </FieldLabel>
                                <Input
                                    id="staff-access-code"
                                    value={form.data.access_code}
                                    onChange={(event) =>
                                        form.setData(
                                            'access_code',
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldError>
                                    {form.errors.access_code}
                                </FieldError>
                            </Field>

                            <div className="flex justify-end gap-2 pt-2">
                                <Button variant="outline" asChild>
                                    <Link
                                        href={dashboardSection({
                                            section: 'staff',
                                        })}
                                    >
                                        Cancel
                                    </Link>
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={
                                        form.processing ||
                                        staffForm.departments.length === 0
                                    }
                                >
                                    {form.processing
                                        ? 'Saving...'
                                        : 'Add Staff'}
                                </Button>
                            </div>
                        </FieldGroup>
                    </form>
                </CardContent>
            </Card>
        </div>
    );
}

function FacultyHeadStaffImport({
    form: staffForm,
}: {
    form: DashboardFacultyHeadStaffForm;
}) {
    function submitRows(
        rows: Array<Record<string, string>>,
        fileName: string | null,
        batch: SpreadsheetImportBatch,
        callbacks: SpreadsheetImportCallbacks,
    ) {
        router.post(
            storeImportedFacultyStaff.url(),
            {
                file_name: fileName ?? '',
                import_key: batch.importKey,
                batch_number: batch.batchNumber,
                total_batches: batch.totalBatches,
                start_row: batch.startRow,
                total_rows: batch.totalRows,
                batch_size: batch.batchSize,
                rows,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onError: callbacks.onError,
                onSuccess: callbacks.onSuccess,
                onFinish: callbacks.onFinish,
            },
        );
    }

    return (
        <div className="mx-auto flex w-full max-w-5xl flex-col gap-4">
            <div className="flex justify-end">
                <Button variant="outline" asChild>
                    <Link href={dashboardSection({ section: 'staff' })}>
                        Cancel
                    </Link>
                </Button>
            </div>
            <SpreadsheetImportPreview
                title="Import Staffs From Spreadsheet"
                submitLabel="Import Staffs"
                submitDisabled={staffForm.departments.length === 0}
                fields={[
                    {
                        key: 'department',
                        label: 'Department',
                        aliases: [
                            'department code',
                            'department abbreviation',
                            'dept',
                        ],
                        defaultColumnIndex: 0,
                        required: true,
                    },
                    {
                        key: 'name',
                        label: 'Full Name',
                        aliases: ['name', 'staff name', 'full name'],
                        defaultColumnIndex: 1,
                        required: true,
                    },
                    {
                        key: 'email',
                        label: 'Email',
                        aliases: ['email address', 'staff email'],
                        defaultColumnIndex: 2,
                        required: true,
                    },
                    {
                        key: 'accessCode',
                        label: 'Access Code',
                        aliases: ['access code', 'code'],
                        defaultColumnIndex: 3,
                        description:
                            'Leave unmapped or blank to auto-generate one.',
                        emptyValueLabel: 'Auto-generated',
                    },
                ]}
                onSubmit={submitRows}
            />
            {staffForm.departments.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No departments are available for this faculty.
                </p>
            ) : null}
        </div>
    );
}

function DepartmentMetrics({
    departments,
}: {
    departments: DashboardFacultyHeadDepartment[];
}) {
    const assignedHods = departments.filter(
        (department) => department.hod !== null,
    ).length;
    const vacantHods = departments.length - assignedHods;
    const totalStaff = departments.reduce(
        (total, department) => total + department.staffCount,
        0,
    );
    const totalStudents = departments.reduce(
        (total, department) => total + department.studentCount,
        0,
    );

    return (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <MetricBlock label="Departments" value={departments.length} />
            <MetricBlock label="Students" value={totalStudents} />
            <MetricBlock label="Faculty Staff" value={totalStaff} />
            <MetricBlock label="Needs HOD" value={vacantHods} />
        </div>
    );
}

function FacultyHeadStaffPage({
    staff,
}: {
    staff: DashboardFacultyHeadStaff[];
}) {
    const [query, setQuery] = useState('');
    const [departmentFilter, setDepartmentFilter] = useState('all');
    const [statusFilter, setStatusFilter] = useState('all');
    const departments = useMemo(
        () =>
            Array.from(
                new Map(
                    staff
                        .filter((member) => member.department !== null)
                        .map((member) => [
                            member.department?.abbreviation ?? '',
                            member.department,
                        ]),
                ).values(),
            ),
        [staff],
    );
    const visibleStaff = useMemo(
        () =>
            staff.filter((member) => {
                const searchText = [
                    member.name,
                    member.email ?? '',
                    member.department?.name ?? '',
                    member.department?.abbreviation ?? '',
                    member.faculty?.abbreviation ?? '',
                ]
                    .join(' ')
                    .toLowerCase();
                const matchesQuery = searchText.includes(
                    query.trim().toLowerCase(),
                );
                const matchesDepartment =
                    departmentFilter === 'all' ||
                    member.department?.abbreviation === departmentFilter;
                const matchesStatus =
                    statusFilter === 'all' || member.status === statusFilter;

                return matchesQuery && matchesDepartment && matchesStatus;
            }),
        [departmentFilter, query, staff, statusFilter],
    );

    return (
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h1 className="text-xl font-semibold tracking-normal">Staff</h1>
                <div className="flex flex-col gap-2 sm:flex-row">
                    <Button asChild>
                        <Link href={importFacultyStaff()}>
                            <RiFileExcel2Line />
                            Import Staffs From Spreadsheet
                        </Link>
                    </Button>
                    <Button variant="ghost" asChild>
                        <Link href={createFacultyStaff()}>
                            <RiUserAddLine />
                            Add Single Staff
                        </Link>
                    </Button>
                </div>
            </div>

            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardHeader className="border-b border-border">
                    <CardTitle>Faculty Staff</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-4 p-4">
                    <div className="grid gap-3 md:grid-cols-[minmax(0,1fr)_12rem_12rem]">
                        <Input
                            value={query}
                            placeholder="Search staff, email, department"
                            onChange={(event) => setQuery(event.target.value)}
                        />
                        <Select
                            value={departmentFilter}
                            onValueChange={setDepartmentFilter}
                        >
                            <SelectTrigger aria-label="Filter by department">
                                <SelectValue placeholder="Department" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All departments
                                </SelectItem>
                                {departments.map((department) =>
                                    department ? (
                                        <SelectItem
                                            key={department.abbreviation}
                                            value={department.abbreviation}
                                        >
                                            {department.abbreviation}
                                        </SelectItem>
                                    ) : null,
                                )}
                            </SelectContent>
                        </Select>
                        <Select
                            value={statusFilter}
                            onValueChange={setStatusFilter}
                        >
                            <SelectTrigger aria-label="Filter by status">
                                <SelectValue placeholder="Status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All statuses
                                </SelectItem>
                                <SelectItem value="Active">Active</SelectItem>
                                <SelectItem value="Pending signup">
                                    Pending signup
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <div className="overflow-hidden border border-border">
                        <Table className="text-sm">
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="px-4">
                                        Staff
                                    </TableHead>
                                    <TableHead>Department</TableHead>
                                    <TableHead>Role</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Access Code</TableHead>
                                    <TableHead className="pr-4 text-right">
                                        Action
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {visibleStaff.length > 0 ? (
                                    visibleStaff.map((member) => (
                                        <TableRow
                                            key={member.staffProfileId}
                                            className="odd:bg-background even:bg-muted/25"
                                        >
                                            <TableCell className="px-4">
                                                <PersonCell
                                                    name={member.name}
                                                    secondary={member.email}
                                                    avatar={member.avatar}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <div className="font-medium">
                                                    {member.department
                                                        ?.abbreviation ??
                                                        'Unassigned'}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {member.faculty
                                                        ?.abbreviation ??
                                                        'No faculty'}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <RoleBadge
                                                    role={member.role}
                                                    context={member.roleContext}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <StatusBadge
                                                    status={member.status}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <CopyableValue
                                                    value={member.accessCode}
                                                    className="max-w-44"
                                                />
                                            </TableCell>
                                            <TableCell className="pr-4 text-right">
                                                <StaffHodAction
                                                    member={member}
                                                    currentHod={currentHodForDepartment(
                                                        staff,
                                                        member,
                                                    )}
                                                />
                                            </TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <EmptyTableRow colSpan={6}>
                                        No staff match the current filters.
                                    </EmptyTableRow>
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

function StaffHodAction({
    member,
    currentHod,
}: {
    member: DashboardFacultyHeadStaff;
    currentHod: DashboardFacultyHeadStaff | null;
}) {
    const department = member.department;
    const form = useForm<HodFormData>({
        department_id: department?.id?.toString() ?? '',
        staff_profile_id: member.staffProfileId.toString(),
        redirect_section: 'staff',
    });

    if (!department?.id) {
        return (
            <span className="text-xs text-muted-foreground">
                No department assigned
            </span>
        );
    }

    if (!member.canAssignHod) {
        return (
            <span className="text-xs text-muted-foreground">
                {member.assignmentBlockReason ?? 'Unavailable'}
            </span>
        );
    }

    function assignHod() {
        form.submit(storeFacultyHod());
    }

    const emphasisClassName = 'font-medium text-foreground';

    return (
        <AlertDialog>
            <AlertDialogTrigger asChild>
                <button
                    type="button"
                    className="text-xs font-medium text-link underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-hidden"
                >
                    Make HOD of {department.abbreviation}
                </button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        Make{' '}
                        <span className={emphasisClassName}>{member.name}</span>{' '}
                        HOD of{' '}
                        <span className={emphasisClassName}>
                            {department.abbreviation}
                        </span>
                        {'?'}
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        {currentHod ? (
                            <>
                                You are replacing{' '}
                                <span className={emphasisClassName}>
                                    {currentHod.name}
                                </span>{' '}
                                as Head of Department for {department.name}.{' '}
                                <span className={emphasisClassName}>
                                    {member.name}
                                </span>{' '}
                                will be listed as HOD for{' '}
                                <span className={emphasisClassName}>
                                    {department.abbreviation}
                                </span>
                                {'.'}
                            </>
                        ) : (
                            <>
                                <span className={emphasisClassName}>
                                    {department.abbreviation}
                                </span>{' '}
                                does not have a HOD yet. You are making{' '}
                                <span className={emphasisClassName}>
                                    {member.name}
                                </span>{' '}
                                Head of Department for {department.name}.
                            </>
                        )}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={form.processing}>
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        disabled={form.processing}
                        onClick={assignHod}
                    >
                        {form.processing ? 'Saving...' : 'Make HOD'}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

function currentHodForDepartment(
    staff: DashboardFacultyHeadStaff[],
    member: DashboardFacultyHeadStaff,
): DashboardFacultyHeadStaff | null {
    const departmentId = member.department?.id;

    if (!departmentId) {
        return null;
    }

    return (
        staff.find(
            (staffMember) =>
                staffMember.department?.id === departmentId &&
                staffMember.role === 'HOD',
        ) ?? null
    );
}

function MetricBlock({ label, value }: { label: string; value: ReactNode }) {
    return (
        <Card
            size="sm"
            className="border border-border bg-background shadow-sm ring-1 ring-border/70"
        >
            <CardHeader>
                <CardTitle className="text-xs text-muted-foreground">
                    {label}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-semibold tracking-normal tabular-nums">
                    {value}
                </div>
            </CardContent>
        </Card>
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
            <CardHeader className="flex flex-row items-center justify-between gap-3 border-b border-border">
                <CardTitle>{title}</CardTitle>
                {action}
            </CardHeader>
            <CardContent className="p-0">{children}</CardContent>
        </Card>
    );
}

function DepartmentCardGrid({
    departments,
}: {
    departments: DashboardFacultyHeadDepartment[];
}) {
    if (departments.length === 0) {
        return (
            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardContent className="p-6 text-sm text-muted-foreground">
                    No departments have been added.
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            {departments.map((department) => (
                <DepartmentCard key={department.id} department={department} />
            ))}
        </div>
    );
}

function DepartmentCard({
    department,
}: {
    department: DashboardFacultyHeadDepartment;
}) {
    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardContent className="flex h-full flex-col gap-4 p-4">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <div className="truncate text-2xl font-semibold tracking-normal">
                            {department.abbreviation}
                        </div>
                        <div className="mt-1 line-clamp-2 text-sm text-muted-foreground">
                            {department.name}
                        </div>
                    </div>
                    <HodStatusBadge status={department.status} />
                </div>

                <div className="grid grid-cols-2 gap-2">
                    <DepartmentStat
                        label="Students"
                        value={department.studentCount}
                    />
                    <DepartmentStat
                        label="Staff"
                        value={department.staffCount}
                    />
                </div>

                <div className="mt-auto border-t border-border pt-3">
                    <div className="mb-2 text-xs font-medium text-muted-foreground">
                        Head of Department
                    </div>
                    {department.hod ? (
                        <div className="flex items-center justify-between gap-3">
                            <PersonCell
                                name={department.hod.name}
                                secondary={department.hod.email}
                                avatar={department.hod.avatar}
                                className="min-w-0"
                            />
                            <Link
                                href={createDepartmentHod({
                                    department: department.id,
                                })}
                                className="shrink-0 text-xs font-medium text-link underline-offset-4 hover:underline"
                            >
                                Change HOD
                            </Link>
                        </div>
                    ) : (
                        <div className="flex items-center justify-between gap-3">
                            <span className="text-sm text-muted-foreground">
                                No HOD assigned
                            </span>
                            <Link
                                href={createDepartmentHod({
                                    department: department.id,
                                })}
                                className="shrink-0 text-xs font-medium text-link underline-offset-4 hover:underline"
                            >
                                Assign HOD
                            </Link>
                        </div>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function DepartmentStat({ label, value }: { label: string; value: number }) {
    return (
        <div className="border border-border bg-muted/35 px-3 py-2">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-lg font-semibold tabular-nums">
                {value}
            </div>
        </div>
    );
}

function StatusBadge({ status }: { status: string }) {
    const normalized = status.toLowerCase();
    const className =
        normalized.includes('sent') ||
        normalized.includes('approved') ||
        normalized.includes('active')
            ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300'
            : normalized.includes('failed') || normalized.includes('rejected')
              ? 'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300'
              : normalized.includes('queued') ||
                  normalized.includes('pending') ||
                  normalized.includes('review')
                ? 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950/40 dark:text-blue-300'
                : 'border-border bg-muted text-muted-foreground';

    return (
        <Badge variant="outline" className={className}>
            {status}
        </Badge>
    );
}

function HodStatusBadge({ status }: { status: string }) {
    return <StatusBadge status={hodStatusLabel(status)} />;
}

function hodStatusLabel(status: string): string {
    const normalized = status.toLowerCase();

    if (normalized.includes('active')) {
        return 'HOD Active';
    }

    if (normalized.includes('pending')) {
        return 'HOD Pending';
    }

    if (normalized.includes('vacant')) {
        return 'No HOD';
    }

    return `HOD ${status}`;
}

function EmptyTableRow({
    colSpan,
    children,
}: {
    colSpan: number;
    children: ReactNode;
}) {
    return (
        <TableRow>
            <TableCell
                colSpan={colSpan}
                className="px-4 py-6 text-muted-foreground"
            >
                {children}
            </TableCell>
        </TableRow>
    );
}
