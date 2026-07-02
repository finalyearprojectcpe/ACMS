import { Link, router, useForm } from '@inertiajs/react';
import { RiAddLine, RiArrowLeftLine, RiSave3Line } from '@remixicon/react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';

import {
    copyFacultyCourseOfferings,
    copyFacultyCourseSemesterOfferings,
    createFacultyCourseOffering,
    deleteFacultyCourseOffering,
    editFacultyCourseOffering,
    section as dashboardSection,
    storeFacultyCourseOffering,
    updateFacultyCourseOffering,
} from '@/actions/App/Http/Controllers/DashboardController';
import { ActionLink } from '@/components/action-link';
import { CoursePrerequisitePicker } from '@/components/course-prerequisite-picker';
import {
    qualifiedSemesterLabel,
    QualifiedSemester,
} from '@/components/qualified-semester';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type {
    DashboardFacultyHeadCourseOffering,
    DashboardFacultyHeadCourseOfferingForm,
    DashboardFacultyHeadCourseSemester,
    DashboardFacultyHeadCourses,
    DashboardAcademicSession,
} from '@/types';

type FacultyCourseFormData = {
    code: string;
    title: string;
    credit_units: number;
    prerequisite_course_ids: number[];
};

export function FacultyHeadCoursesDashboard({
    courses,
}: {
    courses: DashboardFacultyHeadCourses;
}) {
    const [activeSemesterId, setActiveSemesterId] = useState(
        courses.semesters[0]?.id ?? '',
    );
    const activeSemester = useMemo(
        () =>
            courses.semesters.find(
                (semester) => semester.id === activeSemesterId,
            ) ??
            courses.semesters[0] ??
            null,
        [activeSemesterId, courses.semesters],
    );
    const activeSemesterValue = activeSemester?.id ?? '';
    const selectedAcademicSession = courses.academicSession;
    const selectedAcademicSessionId =
        selectedAcademicSession?.id.toString() ?? '';

    function selectAcademicSession(academicSessionId: string) {
        router.visit(
            dashboardSection(
                { section: 'sessions-courses' },
                {
                    query: {
                        academic_session: academicSessionId,
                    },
                },
            ).url,
        );
    }

    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div className="min-w-0">
                    <h1 className="text-xl font-semibold tracking-normal">
                        Faculty Courses
                    </h1>
                </div>
            </div>

            {courses.academicSession ? (
                <SessionControlCard
                    courses={courses}
                    selectedAcademicSessionId={selectedAcademicSessionId}
                    onSelectAcademicSession={selectAcademicSession}
                />
            ) : (
                <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                    <CardContent className="flex flex-col gap-3 p-5 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
                        <span>
                            No academic sessions have been created for this
                            school yet.
                        </span>
                    </CardContent>
                </Card>
            )}

            {activeSemester && selectedAcademicSession ? (
                <Tabs
                    value={activeSemesterValue}
                    onValueChange={setActiveSemesterId}
                    orientation="vertical"
                    className="grid gap-4 lg:grid-cols-[15rem_minmax(0,1fr)]"
                >
                    <div className="lg:hidden">
                        <Select
                            value={activeSemesterValue}
                            onValueChange={setActiveSemesterId}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select semester" />
                            </SelectTrigger>
                            <SelectContent>
                                {courses.semesters.map((semester) => (
                                    <SelectItem
                                        key={semester.id}
                                        value={semester.id}
                                    >
                                        <SemesterSelectLabel
                                            semester={semester}
                                        />
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <TabsList
                        variant="line"
                        className="hidden! h-auto w-full gap-2 bg-background/95 p-2 shadow-sm ring-1 ring-border/70 lg:flex! lg:flex-col lg:items-stretch"
                    >
                        {courses.semesters.map((semester) => (
                            <TabsTrigger
                                key={semester.id}
                                value={semester.id}
                                className="min-h-14 justify-start! px-3 py-3 text-left data-active:bg-primary! data-active:text-primary-foreground! data-active:after:opacity-0! data-active:hover:text-primary-foreground!"
                            >
                                <SemesterTabLabel semester={semester} />
                            </TabsTrigger>
                        ))}
                    </TabsList>

                    {courses.semesters.map((semester) => (
                        <TabsContent
                            key={semester.id}
                            value={semester.id}
                            className="m-0"
                        >
                            <FacultyCourseSemesterWorkspace
                                semester={semester}
                                academicSession={selectedAcademicSession}
                                canManage={courses.canManageSelectedSession}
                                copySourceSession={courses.copySourceSession}
                            />
                        </TabsContent>
                    ))}
                </Tabs>
            ) : (
                <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                    <CardContent className="p-6 text-sm text-muted-foreground">
                        No academic years are available for this faculty.
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

function SessionControlCard({
    courses,
    selectedAcademicSessionId,
    onSelectAcademicSession,
}: {
    courses: DashboardFacultyHeadCourses;
    selectedAcademicSessionId: string;
    onSelectAcademicSession: (academicSessionId: string) => void;
}) {
    const academicSession = courses.academicSession;
    const copyForm = useForm<Record<string, never>>({});
    const emphasisClassName = 'font-medium text-foreground';
    const copySourceSession = courses.copySourceSession;

    if (academicSession === null) {
        return null;
    }

    const selectedAcademicSession = academicSession;

    function copyCourses() {
        copyForm.submit(
            copyFacultyCourseOfferings({
                academicSession: selectedAcademicSession.id,
            }),
        );
    }

    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardContent className="grid gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-center">
                <div className="min-w-0">
                    <div className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                        <h2 className="truncate text-base font-semibold tracking-normal">
                            {academicSession.name}
                        </h2>
                        <SessionStatusBadge status={academicSession.status} />
                    </div>
                    <div className="mt-1 text-xs text-muted-foreground">
                        {academicSession.startsOn} to {academicSession.endsOn}
                        {' · '}
                        Shared across {courses.metrics.departments} departments
                    </div>

                    {courses.canManageSelectedSession && copySourceSession ? (
                        <AlertDialog>
                            <AlertDialogTrigger asChild>
                                <Button
                                    type="button"
                                    variant="link"
                                    disabled={copyForm.processing}
                                    className="mt-2 h-auto min-h-0 max-w-full justify-start p-0 text-left font-medium whitespace-normal"
                                >
                                    Copy Faculty Courses From{' '}
                                    {copySourceSession.name}
                                </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle>
                                        Copy all faculty courses from{' '}
                                        <span className={emphasisClassName}>
                                            {copySourceSession.name}
                                        </span>{' '}
                                        into{' '}
                                        <span className={emphasisClassName}>
                                            {academicSession.name}
                                        </span>
                                        {'?'}
                                    </AlertDialogTitle>
                                    <AlertDialogDescription>
                                        You are copying every faculty course
                                        that matches a level and semester from{' '}
                                        <span className={emphasisClassName}>
                                            {copySourceSession.name}
                                        </span>{' '}
                                        into{' '}
                                        <span className={emphasisClassName}>
                                            {academicSession.name}
                                        </span>
                                        . Existing faculty courses in{' '}
                                        <span className={emphasisClassName}>
                                            {academicSession.name}
                                        </span>{' '}
                                        stay unchanged, and duplicate faculty
                                        courses will be skipped.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel
                                        disabled={copyForm.processing}
                                    >
                                        Cancel
                                    </AlertDialogCancel>
                                    <AlertDialogAction
                                        disabled={copyForm.processing}
                                        onClick={copyCourses}
                                    >
                                        {copyForm.processing
                                            ? 'Copying...'
                                            : 'Copy All Faculty Courses'}
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    ) : null}
                </div>

                <Field>
                    <FieldLabel htmlFor="faculty-course-session">
                        Academic Session
                    </FieldLabel>
                    <Select
                        value={selectedAcademicSessionId}
                        onValueChange={onSelectAcademicSession}
                    >
                        <SelectTrigger id="faculty-course-session">
                            <SelectValue placeholder="Select academic session" />
                        </SelectTrigger>
                        <SelectContent>
                            {courses.academicSessions.map(
                                (academicSessionOption) => (
                                    <SelectItem
                                        key={academicSessionOption.id}
                                        value={academicSessionOption.id.toString()}
                                    >
                                        {academicSessionOption.name} ·{' '}
                                        {academicSessionOption.status}
                                    </SelectItem>
                                ),
                            )}
                        </SelectContent>
                    </Select>
                </Field>
            </CardContent>
        </Card>
    );
}

function SemesterSelectLabel({
    semester,
}: {
    semester: DashboardFacultyHeadCourseSemester;
}) {
    return (
        <span>
            {semesterLabel(semester)} · {semester.courseCount} courses ·{' '}
            {semester.totalUnits} units
        </span>
    );
}

function SemesterTabLabel({
    semester,
}: {
    semester: DashboardFacultyHeadCourseSemester;
}) {
    return (
        <span className="grid min-w-0 gap-1">
            <span className="truncate text-sm font-semibold">
                {semesterLabel(semester)}
            </span>
            <span className="truncate text-xs font-normal text-current opacity-70">
                {semester.courseCount} courses · {semester.totalUnits} units
            </span>
        </span>
    );
}

function SemesterMetric({
    label,
    value,
}: {
    label: string;
    value: string | number;
}) {
    return (
        <div className="min-w-24 border border-border bg-muted/20 px-3 py-2">
            <div className="text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                {label}
            </div>
            <div className="mt-0.5 text-sm font-semibold text-foreground tabular-nums">
                {value}
            </div>
        </div>
    );
}

function SessionStatusBadge({
    status,
}: {
    status: DashboardAcademicSession['status'];
}) {
    const className =
        status === 'Current'
            ? 'bg-green-600 text-white border-green-600'
            : status === 'Upcoming'
              ? 'bg-blue-600 text-white border-blue-600'
              : 'bg-muted text-muted-foreground border-border';

    return (
        <Badge variant="outline" className={className}>
            {status}
        </Badge>
    );
}

export function FacultyHeadCourseOfferingForm({
    form,
}: {
    form: DashboardFacultyHeadCourseOfferingForm;
}) {
    const courseForm = useForm<FacultyCourseFormData>({
        code: form.offering?.code ?? '',
        title: form.offering?.title ?? '',
        credit_units: form.offering?.creditUnits ?? 2,
        prerequisite_course_ids: form.offering?.prerequisiteCourseIds ?? [],
    });
    const backRoute = dashboardSection(
        { section: 'sessions-courses' },
        {
            query: {
                academic_session: form.academicSession.id,
            },
        },
    );
    const title =
        form.mode === 'create'
            ? 'Add Faculty Course'
            : `Edit Faculty Course ${form.offering?.code}`;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (form.mode === 'create') {
            courseForm.submit(
                storeFacultyCourseOffering({
                    academicSession: form.academicSession.id,
                    level: form.level.slug,
                    semester: form.semester.slug,
                }),
            );

            return;
        }

        if (form.offering) {
            courseForm.submit(
                updateFacultyCourseOffering({
                    academicSession: form.academicSession.id,
                    level: form.level.slug,
                    semester: form.semester.slug,
                    offering: form.offering.id,
                }),
            );
        }
    }

    function updatePrerequisite(courseId: number, checked: boolean) {
        courseForm.setData(
            'prerequisite_course_ids',
            checked
                ? [...courseForm.data.prerequisite_course_ids, courseId]
                : courseForm.data.prerequisite_course_ids.filter(
                      (id) => id !== courseId,
                  ),
        );
    }

    return (
        <div className="mx-auto flex w-full max-w-4xl flex-col gap-4">
            <div className="flex items-center justify-between gap-4">
                <div className="min-w-0">
                    <h1 className="truncate text-xl font-semibold tracking-normal">
                        {title}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        <QualifiedSemester
                            level={form.level.title}
                            semester={form.semester.name}
                        />
                        {' · '}
                        {form.academicSession.name}
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

            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardForm onSubmit={submit}>
                    <CardContent className="p-5">
                        <FieldGroup>
                            <div className="grid gap-4 sm:grid-cols-[0.7fr_1.3fr]">
                                <Field
                                    data-invalid={Boolean(
                                        courseForm.errors.code,
                                    )}
                                >
                                    <FieldLabel htmlFor="faculty-course-code">
                                        Course Code
                                    </FieldLabel>
                                    <Input
                                        id="faculty-course-code"
                                        value={courseForm.data.code}
                                        placeholder="GST 101"
                                        autoComplete="off"
                                        aria-invalid={Boolean(
                                            courseForm.errors.code,
                                        )}
                                        onChange={(event) =>
                                            courseForm.setData(
                                                'code',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <FieldError>
                                        {courseForm.errors.code}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={Boolean(
                                        courseForm.errors.title,
                                    )}
                                >
                                    <FieldLabel htmlFor="faculty-course-title">
                                        Course Title
                                    </FieldLabel>
                                    <Input
                                        id="faculty-course-title"
                                        value={courseForm.data.title}
                                        placeholder="Use of English I"
                                        autoComplete="off"
                                        aria-invalid={Boolean(
                                            courseForm.errors.title,
                                        )}
                                        onChange={(event) =>
                                            courseForm.setData(
                                                'title',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <FieldError>
                                        {courseForm.errors.title}
                                    </FieldError>
                                </Field>
                            </div>

                            <Field
                                data-invalid={Boolean(
                                    courseForm.errors.credit_units,
                                )}
                            >
                                <FieldLabel htmlFor="faculty-course-units">
                                    Course Units
                                </FieldLabel>
                                <Input
                                    id="faculty-course-units"
                                    type="number"
                                    min={1}
                                    max={10}
                                    value={courseForm.data.credit_units}
                                    aria-invalid={Boolean(
                                        courseForm.errors.credit_units,
                                    )}
                                    onChange={(event) =>
                                        courseForm.setData(
                                            'credit_units',
                                            Number(event.target.value),
                                        )
                                    }
                                />
                                <FieldError>
                                    {courseForm.errors.credit_units}
                                </FieldError>
                            </Field>

                            <Field
                                data-invalid={Boolean(
                                    courseForm.errors.prerequisite_course_ids,
                                )}
                            >
                                <FieldLabel>Prerequisites</FieldLabel>
                                <CoursePrerequisitePicker
                                    options={form.prerequisiteOptions}
                                    selectedIds={
                                        courseForm.data.prerequisite_course_ids
                                    }
                                    onChange={updatePrerequisite}
                                />
                                <FieldError>
                                    {courseForm.errors.prerequisite_course_ids}
                                </FieldError>
                            </Field>
                        </FieldGroup>
                    </CardContent>
                    <CardFooter className="flex justify-end gap-2 border-t border-border">
                        <Button asChild type="button" variant="outline">
                            <Link href={backRoute}>Cancel</Link>
                        </Button>
                        <Button type="submit" disabled={courseForm.processing}>
                            <RiSave3Line />
                            {courseForm.processing ? 'Saving...' : 'Save'}
                        </Button>
                    </CardFooter>
                </CardForm>
            </Card>
        </div>
    );
}

function FacultyCourseSemesterWorkspace({
    semester,
    academicSession,
    canManage,
    copySourceSession,
}: {
    semester: DashboardFacultyHeadCourseSemester;
    academicSession: DashboardAcademicSession;
    canManage: boolean;
    copySourceSession: DashboardAcademicSession | null;
}) {
    const coursesWithPrerequisites = semester.offerings.filter(
        (offering) => offering.prerequisites.length > 0,
    ).length;

    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardHeader className="gap-4 border-b border-border">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div className="min-w-0">
                        <CardTitle className="text-base">
                            <QualifiedSemester
                                level={semester.level.title}
                                semester={semester.semester.name}
                            />
                            {' Faculty Courses'}
                        </CardTitle>
                        <div className="mt-3 flex flex-wrap gap-2">
                            <SemesterMetric
                                label="Courses"
                                value={semester.courseCount}
                            />
                            <SemesterMetric
                                label="Units"
                                value={semester.totalUnits}
                            />
                            <SemesterMetric
                                label="Prerequisites"
                                value={coursesWithPrerequisites}
                            />
                        </div>
                    </div>
                    {canManage ? (
                        <div className="flex shrink-0 flex-wrap justify-start gap-2 lg:justify-end">
                            {copySourceSession ? (
                                <SemesterCopyAction
                                    semester={semester}
                                    academicSession={academicSession}
                                    copySourceSession={copySourceSession}
                                />
                            ) : null}
                            <Button asChild>
                                <Link
                                    href={createFacultyCourseOffering({
                                        academicSession: academicSession.id,
                                        level: semester.level.slug,
                                        semester: semester.semester.slug,
                                    })}
                                >
                                    <RiAddLine />
                                    Add Faculty Course
                                </Link>
                            </Button>
                        </div>
                    ) : null}
                </div>
            </CardHeader>
            <CardContent className="p-0">
                {semester.offerings.length > 0 ? (
                    <FacultyCourseOfferingsTable
                        semester={semester}
                        academicSession={academicSession}
                        canManage={canManage}
                    />
                ) : (
                    <FacultyCourseEmptyState
                        semester={semester}
                        academicSession={academicSession}
                        canManage={canManage}
                        copySourceSession={copySourceSession}
                    />
                )}
            </CardContent>
        </Card>
    );
}

function SemesterCopyAction({
    semester,
    academicSession,
    copySourceSession,
}: {
    semester: DashboardFacultyHeadCourseSemester;
    academicSession: DashboardAcademicSession;
    copySourceSession: DashboardAcademicSession;
}) {
    const copyForm = useForm<Record<string, never>>({});
    const emphasisClassName = 'font-medium text-foreground';
    const label = semesterLabel(semester);

    function copySemesterCourses() {
        copyForm.submit(
            copyFacultyCourseSemesterOfferings({
                academicSession: academicSession.id,
                level: semester.level.slug,
                semester: semester.semester.slug,
            }),
        );
    }

    return (
        <AlertDialog>
            <AlertDialogTrigger asChild>
                <Button
                    type="button"
                    variant="link"
                    disabled={copyForm.processing}
                    className="h-auto min-h-0 max-w-full justify-start p-0 text-left font-medium whitespace-normal"
                >
                    Copy From {copySourceSession.name}
                </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        Copy <span className={emphasisClassName}>{label}</span>{' '}
                        faculty courses into{' '}
                        <span className={emphasisClassName}>
                            {academicSession.name}
                        </span>
                        {'?'}
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        You are copying only{' '}
                        <span className={emphasisClassName}>{label}</span>{' '}
                        faculty courses from{' '}
                        <span className={emphasisClassName}>
                            {copySourceSession.name}
                        </span>{' '}
                        into{' '}
                        <span className={emphasisClassName}>
                            {academicSession.name}
                        </span>
                        . Other semesters will not be changed. Existing faculty
                        courses in this semester stay unchanged, and duplicate
                        faculty courses will be skipped.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={copyForm.processing}>
                        Cancel
                    </AlertDialogCancel>
                    <AlertDialogAction
                        disabled={copyForm.processing}
                        onClick={copySemesterCourses}
                    >
                        {copyForm.processing
                            ? 'Copying...'
                            : 'Copy Faculty Courses'}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

function FacultyCourseOfferingsTable({
    semester,
    academicSession,
    canManage,
}: {
    semester: DashboardFacultyHeadCourseSemester;
    academicSession: DashboardAcademicSession;
    canManage: boolean;
}) {
    return (
        <Table className="text-sm">
            <TableHeader>
                <TableRow>
                    <TableHead className="w-[45%] px-4">
                        Faculty Course
                    </TableHead>
                    <TableHead className="w-[12%] text-center">Units</TableHead>
                    <TableHead>Prerequisites</TableHead>
                    <TableHead className="w-[7rem] pr-4 text-right">
                        Action
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {semester.offerings.map((offering) => (
                    <FacultyCourseOfferingRow
                        key={offering.id}
                        semester={semester}
                        academicSession={academicSession}
                        offering={offering}
                        canManage={canManage}
                    />
                ))}
            </TableBody>
        </Table>
    );
}

function FacultyCourseEmptyState({
    semester,
    academicSession,
    canManage,
    copySourceSession,
}: {
    semester: DashboardFacultyHeadCourseSemester;
    academicSession: DashboardAcademicSession;
    canManage: boolean;
    copySourceSession: DashboardAcademicSession | null;
}) {
    return (
        <div className="flex flex-col gap-3 px-4 py-8 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
            <div>No faculty courses added for {semesterLabel(semester)}.</div>
            {canManage ? (
                <div className="flex flex-wrap gap-3">
                    {copySourceSession ? (
                        <SemesterCopyAction
                            semester={semester}
                            academicSession={academicSession}
                            copySourceSession={copySourceSession}
                        />
                    ) : null}
                    <ActionLink
                        href={createFacultyCourseOffering({
                            academicSession: academicSession.id,
                            level: semester.level.slug,
                            semester: semester.semester.slug,
                        })}
                    >
                        Add Faculty Course
                    </ActionLink>
                </div>
            ) : null}
        </div>
    );
}

function FacultyCourseOfferingRow({
    semester,
    academicSession,
    offering,
    canManage,
}: {
    semester: DashboardFacultyHeadCourseSemester;
    academicSession: DashboardAcademicSession;
    offering: DashboardFacultyHeadCourseOffering;
    canManage: boolean;
}) {
    const deleteForm = useForm<Record<string, never>>({});
    const emphasisClassName = 'font-medium text-foreground';

    function destroy() {
        deleteForm.submit(
            deleteFacultyCourseOffering({
                academicSession: academicSession.id,
                level: semester.level.slug,
                semester: semester.semester.slug,
                offering: offering.id,
            }),
        );
    }

    return (
        <TableRow className="odd:bg-background even:bg-muted/25">
            <TableCell className="px-4">
                <div className="min-w-0">
                    <div className="font-semibold">{offering.code}</div>
                    <div className="mt-0.5 truncate text-xs text-muted-foreground">
                        {offering.title}
                    </div>
                </div>
            </TableCell>
            <TableCell className="text-center font-semibold tabular-nums">
                {offering.units}
            </TableCell>
            <TableCell>
                <PrerequisiteBadges prerequisites={offering.prerequisites} />
            </TableCell>
            <TableCell className="pr-4">
                {canManage ? (
                    <div className="flex justify-end gap-3">
                        <ActionLink
                            href={editFacultyCourseOffering({
                                academicSession: academicSession.id,
                                level: semester.level.slug,
                                semester: semester.semester.slug,
                                offering: offering.id,
                            })}
                            className="text-xs"
                        >
                            Edit
                        </ActionLink>

                        <AlertDialog>
                            <AlertDialogTrigger asChild>
                                <Button
                                    type="button"
                                    variant="link"
                                    size="sm"
                                    disabled={deleteForm.processing}
                                    className="h-auto min-h-0 p-0 text-xs font-semibold text-destructive hover:text-destructive hover:underline"
                                >
                                    Delete
                                </Button>
                            </AlertDialogTrigger>
                            <AlertDialogContent>
                                <AlertDialogHeader>
                                    <AlertDialogTitle>
                                        Remove{' '}
                                        <span className={emphasisClassName}>
                                            {offering.code}
                                        </span>{' '}
                                        from{' '}
                                        <span className={emphasisClassName}>
                                            {semesterLabel(semester)}
                                        </span>{' '}
                                        for{' '}
                                        <span className={emphasisClassName}>
                                            {academicSession.name}
                                        </span>
                                        {'?'}
                                    </AlertDialogTitle>
                                    <AlertDialogDescription>
                                        You are removing{' '}
                                        <span className={emphasisClassName}>
                                            {offering.code}
                                        </span>{' '}
                                        from{' '}
                                        <span className={emphasisClassName}>
                                            {semesterLabel(semester)}
                                        </span>{' '}
                                        for{' '}
                                        <span className={emphasisClassName}>
                                            {academicSession.name}
                                        </span>
                                        {'.'} This removes the faculty course
                                        offering from the selected session and
                                        semester only. The course record stays
                                        in the catalog, so you can add it again
                                        later.
                                    </AlertDialogDescription>
                                </AlertDialogHeader>
                                <AlertDialogFooter>
                                    <AlertDialogCancel
                                        disabled={deleteForm.processing}
                                    >
                                        Cancel
                                    </AlertDialogCancel>
                                    <AlertDialogAction
                                        variant="destructive"
                                        disabled={deleteForm.processing}
                                        onClick={destroy}
                                    >
                                        Remove Faculty Course
                                    </AlertDialogAction>
                                </AlertDialogFooter>
                            </AlertDialogContent>
                        </AlertDialog>
                    </div>
                ) : (
                    <span className="text-xs text-muted-foreground">
                        Read only
                    </span>
                )}
            </TableCell>
        </TableRow>
    );
}

function PrerequisiteBadges({
    prerequisites,
}: {
    prerequisites: DashboardFacultyHeadCourseOffering['prerequisites'];
}) {
    if (prerequisites.length === 0) {
        return <span className="text-xs text-muted-foreground">None</span>;
    }

    return (
        <div className="flex flex-wrap gap-1">
            {prerequisites.map((course) => (
                <Badge key={course.id} variant="secondary">
                    {course.code}
                </Badge>
            ))}
        </div>
    );
}

function semesterLabel(semester: DashboardFacultyHeadCourseSemester): string {
    return qualifiedSemesterLabel(semester.level.title, semester.semester.name);
}
