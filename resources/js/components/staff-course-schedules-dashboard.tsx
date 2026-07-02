import { Link, useForm } from '@inertiajs/react';
import { RiAddLine, RiArrowLeftLine, RiSave3Line } from '@remixicon/react';
import type { FormEvent } from 'react';

import {
    createCourseOffering,
    editCourseOffering,
    level as dashboardLevel,
    section as dashboardSection,
    storeCourseOffering,
    updateCourseOffering,
} from '@/actions/App/Http/Controllers/DashboardController';
import { ActionLink } from '@/components/action-link';
import { CoursePrerequisitePicker } from '@/components/course-prerequisite-picker';
import { CourseTypeBadge } from '@/components/course-type-badge';
import { QualifiedSemester } from '@/components/qualified-semester';
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
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    DashboardStaffCourseOffering,
    DashboardStaffCourseOfferingForm,
    DashboardStaffCoursePrerequisite,
    DashboardStaffCourseScheduleSemester,
    DashboardStaffCourseSchedules,
} from '@/types';

type CourseOfferingFormData = {
    code: string;
    title: string;
    credit_units: number;
    is_mandatory: boolean;
    prerequisite_course_ids: number[];
};

export function StaffCourseSchedulesDashboard({
    courseSchedules,
}: {
    courseSchedules: DashboardStaffCourseSchedules;
}) {
    const academicSessionLabel = courseSchedules.academicSession
        ? `${courseSchedules.academicSession.name} Academic Session`
        : 'No active academic session';
    const semesters = [...courseSchedules.semesters].sort((first, second) => {
        const order = { harmattan: 0, rain: 1 };

        return (
            (order[first.slug as keyof typeof order] ?? 99) -
            (order[second.slug as keyof typeof order] ?? 99)
        );
    });

    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <div className="text-sm font-medium text-muted-foreground">
                {academicSessionLabel}
            </div>
            <div className="grid gap-4 xl:grid-cols-2">
                {semesters.map((semester) => (
                    <SemesterOfferingCard
                        key={semester.id}
                        level={courseSchedules.level.title}
                        levelSlug={courseSchedules.level.slug}
                        semester={semester}
                    />
                ))}
            </div>
        </div>
    );
}

export function StaffCourseOfferingForm({
    form,
    role = 'staff',
}: {
    form: DashboardStaffCourseOfferingForm;
    role?: string;
}) {
    const courseForm = useForm<CourseOfferingFormData>({
        code: form.offering?.code ?? '',
        title: form.offering?.title ?? '',
        credit_units: form.offering?.creditUnits ?? 3,
        is_mandatory: form.offering?.isMandatory ?? true,
        prerequisite_course_ids: form.offering?.prerequisiteCourseIds ?? [],
    });
    const backRoute =
        role === 'hod'
            ? dashboardSection(
                  { section: 'courses' },
                  {
                      query: {
                          academic_session: form.academicSession.id,
                          level: form.level.slug,
                      },
                  },
              )
            : dashboardLevel({
                  section: 'courses',
                  level: form.level.slug,
              });
    const title =
        form.mode === 'create' ? 'Add Course' : `Edit ${form.offering?.code}`;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (form.mode === 'create') {
            courseForm.submit(
                storeCourseOffering(
                    {
                        level: form.level.slug,
                        semester: form.semester.slug,
                    },
                    {
                        query: {
                            academic_session: form.academicSession.id,
                        },
                    },
                ),
            );

            return;
        }

        if (form.offering) {
            courseForm.submit(
                updateCourseOffering(
                    {
                        level: form.level.slug,
                        semester: form.semester.slug,
                        offering: form.offering.id,
                    },
                    {
                        query: {
                            academic_session: form.academicSession.id,
                        },
                    },
                ),
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
                    </p>
                </div>
                <ActionLink
                    href={backRoute}
                    icon={<RiArrowLeftLine className="size-4" />}
                >
                    Back
                </ActionLink>
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
                                    <FieldLabel htmlFor="course-code">
                                        Course Code
                                    </FieldLabel>
                                    <Input
                                        id="course-code"
                                        value={courseForm.data.code}
                                        placeholder="CPE 309"
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
                                    <FieldLabel htmlFor="course-title">
                                        Course Title
                                    </FieldLabel>
                                    <Input
                                        id="course-title"
                                        value={courseForm.data.title}
                                        placeholder="Computer Engineering Laboratory"
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

                            <div className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_16rem]">
                                <Field
                                    data-invalid={Boolean(
                                        courseForm.errors.credit_units,
                                    )}
                                >
                                    <FieldLabel htmlFor="credit-units">
                                        Course Units
                                    </FieldLabel>
                                    <Input
                                        id="credit-units"
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
                                        courseForm.errors.is_mandatory,
                                    )}
                                >
                                    <div className="flex h-full items-center justify-between gap-4 border border-border bg-background px-3 py-2">
                                        <div className="min-w-0">
                                            <FieldLabel htmlFor="course-type">
                                                Course Type
                                            </FieldLabel>
                                            <div className="mt-1 text-sm font-semibold">
                                                {courseForm.data.is_mandatory
                                                    ? 'Departmental Course'
                                                    : 'Elective Course'}
                                            </div>
                                        </div>
                                        <Switch
                                            id="course-type"
                                            checked={
                                                courseForm.data.is_mandatory
                                            }
                                            aria-invalid={Boolean(
                                                courseForm.errors.is_mandatory,
                                            )}
                                            onCheckedChange={(checked) =>
                                                courseForm.setData(
                                                    'is_mandatory',
                                                    checked,
                                                )
                                            }
                                        />
                                    </div>
                                    <FieldError>
                                        {courseForm.errors.is_mandatory}
                                    </FieldError>
                                </Field>
                            </div>

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

function SemesterOfferingCard({
    level,
    levelSlug,
    semester,
}: {
    level: string;
    levelSlug: string;
    semester: DashboardStaffCourseScheduleSemester;
}) {
    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardHeader className="flex flex-row items-center justify-between gap-4 border-b border-border">
                <div className="min-w-0">
                    <CardTitle>
                        <QualifiedSemester
                            level={level}
                            semester={semester.name}
                        />
                    </CardTitle>
                    <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                        <span>{semester.totalUnits} units</span>
                        <span>{semester.facultyCourseCount} faculty</span>
                        <span>
                            {semester.departmentalCourseCount} departmental
                        </span>
                        <span>{semester.electiveCourseCount} elective</span>
                    </div>
                </div>
                <ActionLink
                    href={createCourseOffering({
                        level: levelSlug,
                        semester: semester.slug,
                    })}
                    icon={<RiAddLine className="size-4" />}
                    className="shrink-0"
                >
                    Add course
                </ActionLink>
            </CardHeader>
            <CardContent className="p-0">
                <CourseOfferingsTable
                    levelSlug={levelSlug}
                    semester={semester}
                />
            </CardContent>
        </Card>
    );
}

function CourseOfferingsTable({
    levelSlug,
    semester,
}: {
    levelSlug: string;
    semester: DashboardStaffCourseScheduleSemester;
}) {
    return (
        <Table className="text-sm">
            <TableHeader>
                <TableRow>
                    <TableHead className="w-[34%] px-4">Course</TableHead>
                    <TableHead className="w-[10%] text-center">Units</TableHead>
                    <TableHead className="w-[14%] text-center">
                        Category
                    </TableHead>
                    <TableHead>Prerequisites</TableHead>
                    <TableHead className="w-[12%] pr-4 text-right">
                        Action
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {semester.offerings.length > 0 ? (
                    semester.offerings.map((offering) => (
                        <CourseOfferingRow
                            key={offering.id}
                            levelSlug={levelSlug}
                            semesterSlug={semester.slug}
                            offering={offering}
                        />
                    ))
                ) : (
                    <TableRow>
                        <TableCell
                            colSpan={5}
                            className="px-4 py-6 text-muted-foreground"
                        >
                            No courses have been added for this semester.
                        </TableCell>
                    </TableRow>
                )}
            </TableBody>
        </Table>
    );
}

function CourseOfferingRow({
    levelSlug,
    semesterSlug,
    offering,
}: {
    levelSlug: string;
    semesterSlug: string;
    offering: DashboardStaffCourseOffering;
}) {
    const editRoute = editCourseOffering({
        level: levelSlug,
        semester: semesterSlug,
        offering: offering.id,
    });

    return (
        <TableRow className="odd:bg-background even:bg-muted/25">
            <TableCell className="px-4">
                <div className="min-w-0">
                    <div className="font-semibold">
                        {offering.code}
                    </div>
                    <div className="mt-0.5 truncate text-xs text-muted-foreground">
                        {offering.title}
                    </div>
                </div>
            </TableCell>
            <TableCell className="text-center font-semibold tabular-nums">
                {offering.units}
            </TableCell>
            <TableCell className="text-center">
                <CourseTypeBadge type={offering.courseType} />
            </TableCell>
            <TableCell>
                <PrerequisiteBadges prerequisites={offering.prerequisites} />
            </TableCell>
            <TableCell className="pr-4 text-right">
                {offering.canEdit ? (
                    <ActionLink href={editRoute} className="text-xs">
                        Edit
                    </ActionLink>
                ) : (
                    <span className="text-xs text-muted-foreground">
                        Faculty course
                    </span>
                )}
            </TableCell>
        </TableRow>
    );
}

function PrerequisiteBadges({
    prerequisites,
}: {
    prerequisites: DashboardStaffCoursePrerequisite[];
}) {
    if (prerequisites.length === 0) {
        return <span className="text-xs text-muted-foreground">None</span>;
    }

    const visiblePrerequisites = prerequisites.slice(0, 3);
    const hiddenPrerequisites = prerequisites.slice(3);

    return (
        <div className="flex flex-wrap gap-1">
            {visiblePrerequisites.map((prerequisite) => (
                <Badge
                    key={prerequisite.id}
                    variant="outline"
                    className="border-border bg-background text-xs"
                >
                    {prerequisite.code}
                </Badge>
            ))}
            {hiddenPrerequisites.length > 0 ? (
                <Badge
                    variant="outline"
                    title={hiddenPrerequisites
                        .map((prerequisite) => prerequisite.code)
                        .join(', ')}
                    className="border-border bg-background text-xs"
                >
                    +{hiddenPrerequisites.length} more
                </Badge>
            ) : null}
        </div>
    );
}
