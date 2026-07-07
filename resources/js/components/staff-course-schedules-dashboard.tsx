import { Link, router, useForm } from '@inertiajs/react';
import { RiAddLine, RiArrowLeftLine, RiSave3Line } from '@remixicon/react';
import type { FormEvent } from 'react';

import {
    createCourseOffering,
    editCourseOffering,
    section as dashboardSection,
    storeCourseOffering,
    updateCourseOffering,
} from '@/actions/App/Http/Controllers/DashboardController';
import { ActionLink } from '@/components/action-link';
import { CourseOfferingBrowser } from '@/components/course-offering-browser';
import { CoursePrerequisitePicker } from '@/components/course-prerequisite-picker';
import { QualifiedSemester } from '@/components/qualified-semester';
import { StudentSetChoiceLabel } from '@/components/student-set-label';
import { StudentSetPicker } from '@/components/student-set-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardForm } from '@/components/ui/card';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import type {
    DashboardStaffCourseOfferingForm,
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
    const selectedLevel = courseSchedules.level;

    function updateSelection(params: {
        entrySession?: string | number | null;
        level?: string | null;
    }) {
        router.get(
            dashboardSection.url(
                { section: 'courses' },
                {
                    query: {
                        entry_session:
                            params.entrySession ??
                            courseSchedules.selectedSet?.entrySession.id,
                        level: params.level ?? selectedLevel?.slug,
                    },
                },
            ),
            {},
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    }

    if (!selectedLevel) {
        return (
            <Card className="mx-auto w-full max-w-7xl border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardContent className="p-6 text-sm text-muted-foreground">
                    No advised set is available for course offerings yet.
                </CardContent>
            </Card>
        );
    }

    return (
        <CourseOfferingBrowser
            title="Course Offerings"
            detail="Manage departmental and elective courses for the selected advised set."
            levels={courseSchedules.levels}
            selectedLevel={selectedLevel}
            semesters={courseSchedules.semesters}
            session={courseSchedules.academicSession}
            headerControl={setHeaderControl(courseSchedules, updateSelection)}
            onSelectionChange={(level) =>
                updateSelection({ level: level.slug })
            }
            renderHeaderAction={(semester) => (
                <ActionLink
                    href={createCourseOffering(
                        {
                            level: selectedLevel.slug,
                            semester: semester.slug,
                        },
                        { query: courseScheduleQuery(courseSchedules) },
                    )}
                    icon={<RiAddLine className="size-4" />}
                >
                    Add Course
                </ActionLink>
            )}
            renderCourseAction={(offering, semester) =>
                offering.canEdit ? (
                    <ActionLink
                        href={editCourseOffering(
                            {
                                level: selectedLevel.slug,
                                semester: semester.slug,
                                offering: offering.id,
                            },
                            { query: courseScheduleQuery(courseSchedules) },
                        )}
                        className="text-xs"
                    >
                        Edit
                    </ActionLink>
                ) : (
                    <span className="text-xs text-muted-foreground">
                        Faculty course
                    </span>
                )
            }
        />
    );
}

function setHeaderControl(
    courseSchedules: DashboardStaffCourseSchedules,
    updateSelection: (params: {
        entrySession?: string | number | null;
        level?: string | null;
    }) => void,
) {
    if (courseSchedules.assignedSets.length === 0) {
        return null;
    }

    if (courseSchedules.assignedSets.length === 1) {
        const selectedSet = courseSchedules.selectedSet;

        if (!selectedSet) {
            return null;
        }

        return (
            <div className="min-w-0 rounded-md border border-border bg-background px-3 py-2 text-sm">
                <div className="text-xs font-medium text-muted-foreground">
                    Advised Set
                </div>
                <div className="mt-1 font-medium text-foreground">
                    <StudentSetChoiceLabel
                        entrySession={selectedSet.entrySession}
                        currentLevel={selectedSet.currentLevel}
                        minimumYearsToDegree={
                            selectedSet.minimumYearsToDegree
                        }
                    />
                </div>
            </div>
        );
    }

    return (
        <Field className="lg:w-96">
            <FieldLabel>Advised Set</FieldLabel>
            <StudentSetPicker
                value={
                    courseSchedules.selectedSet?.entrySession.id.toString() ??
                    ''
                }
                onValueChange={(entrySession) =>
                    updateSelection({ entrySession })
                }
                options={courseSchedules.assignedSets.map((set) => ({
                    value: set.entrySession.id.toString(),
                    entrySession: set.entrySession,
                    currentLevel: set.currentLevel,
                    minimumYearsToDegree: set.minimumYearsToDegree,
                }))}
                ariaLabel="Advised set"
            />
        </Field>
    );
}

function courseScheduleQuery(courseSchedules: DashboardStaffCourseSchedules) {
    return {
        ...(courseSchedules.academicSession
            ? { academic_session: courseSchedules.academicSession.id }
            : {}),
        ...(courseSchedules.selectedSet
            ? { entry_session: courseSchedules.selectedSet.entrySession.id }
            : {}),
    };
}

export function StaffCourseOfferingForm({
    form,
    role = 'staff',
}: {
    form: DashboardStaffCourseOfferingForm;
    role?: string;
}) {
    const courseForm = useForm<CourseOfferingFormData>({
        code: form.offering?.code ?? form.prefill?.code ?? '',
        title: form.offering?.title ?? form.prefill?.title ?? '',
        credit_units:
            form.offering?.creditUnits ?? form.prefill?.creditUnits ?? 3,
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
                          ...(form.entrySessionId
                              ? { entry_session: form.entrySessionId }
                              : {}),
                      },
                  },
              )
            : dashboardSection(
                  { section: 'courses' },
                  {
                      query: {
                          academic_session: form.academicSession.id,
                          level: form.level.slug,
                          ...(form.entrySessionId
                              ? { entry_session: form.entrySessionId }
                              : {}),
                      },
                  },
              );
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
                            ...(form.entrySessionId
                                ? { entry_session: form.entrySessionId }
                                : {}),
                            ...(form.returnTo
                                ? { return_to: form.returnTo }
                                : {}),
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
                            ...(form.entrySessionId
                                ? { entry_session: form.entrySessionId }
                                : {}),
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
