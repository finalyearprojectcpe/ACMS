import { Link, useForm } from '@inertiajs/react';
import { RiSave3Line } from '@remixicon/react';
import type { FormEvent } from 'react';

import {
    store as storeCourseOffering,
    update as updateCourseOffering,
} from '@/actions/App/Http/Controllers/CourseOfferingController';
import { showCourseSchedule } from '@/actions/App/Http/Controllers/DashboardController';
import { ScheduleCatalogCourses } from '@/components/adviser-course-schedule-catalog-form';
import { CoursePrerequisitePicker } from '@/components/course-prerequisite-picker';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Switch } from '@/components/ui/switch';
import { semesterLabel } from '@/lib/academic';
import {
    sessionRouteYears,
    studentSetRouteParameters,
} from '@/lib/student-sets';
import type { DashboardAdviserCourseOfferingForm } from '@/types';

export function AdviserCourseOfferingForm({
    form,
}: {
    form: DashboardAdviserCourseOfferingForm;
}) {
    if (form.mode === 'create') {
        return <ScheduleCatalogCourses form={form} />;
    }

    return <EditScheduledCourse form={form} />;
}

function EditScheduledCourse({
    form,
}: {
    form: DashboardAdviserCourseOfferingForm;
}) {
    const routeParameters = studentSetRouteParameters(form.set);
    const courseForm = useForm({
        code: form.offering?.code ?? '',
        title: form.offering?.title ?? '',
        credit_units: form.offering?.creditUnits.toString() ?? '3',
        is_elective: form.offering?.category === 'elective',
        prerequisite_course_ids:
            form.offering?.prerequisiteCourseIds ?? ([] as number[]),
    });

    function submit(event: FormEvent) {
        event.preventDefault();

        if (form.mode === 'edit' && form.offering) {
            courseForm.patch(
                updateCourseOffering.url({
                    ...routeParameters,
                    sessionYears: sessionRouteYears(form.academicSession),
                    semester: form.semester.slug,
                    courseCode: form.offering.urlCode,
                }),
            );

            return;
        }

        courseForm.post(
            storeCourseOffering.url({
                ...routeParameters,
                sessionYears: sessionRouteYears(form.academicSession),
                semester: form.semester.slug,
            }),
        );
    }

    const cancelRoute = showCourseSchedule(routeParameters, {
        query: {
            session: sessionRouteYears(form.academicSession),
            semester: form.semester.slug,
        },
    });

    return (
        <div className="mx-auto flex min-h-0 w-full max-w-4xl flex-1 flex-col">
            <form className="flex min-h-0 flex-1 flex-col" onSubmit={submit}>
                <header className="shrink-0 border-b border-border pb-5">
                    <h1 className="text-lg font-semibold">
                        {form.mode === 'edit' ? 'Edit Course' : 'Add Course'}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {form.set.name} · {form.level.title} ·{' '}
                        {semesterLabel(form.semester.name)} ·{' '}
                        {form.academicSession.name} Session
                    </p>
                </header>
                <ScrollArea className="h-0 min-h-0 flex-1">
                    <div className="grid gap-6 py-6 pr-3">
                        <div className="grid gap-5 lg:grid-cols-[10rem_minmax(0,1fr)_7rem]">
                            <Field
                                data-invalid={Boolean(courseForm.errors.code)}
                            >
                                <FieldLabel htmlFor="course-code">
                                    Course Code
                                </FieldLabel>
                                <Input
                                    id="course-code"
                                    value={courseForm.data.code}
                                    readOnly
                                    placeholder="CPE 301"
                                />
                                <FieldError>
                                    {courseForm.errors.code}
                                </FieldError>
                            </Field>
                            <Field
                                data-invalid={Boolean(courseForm.errors.title)}
                            >
                                <FieldLabel htmlFor="course-title">
                                    Course Title
                                </FieldLabel>
                                <Input
                                    id="course-title"
                                    value={courseForm.data.title}
                                    onChange={(event) =>
                                        courseForm.setData(
                                            'title',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Computer Architecture"
                                />
                                <FieldError>
                                    {courseForm.errors.title}
                                </FieldError>
                            </Field>
                            <Field
                                data-invalid={Boolean(
                                    courseForm.errors.credit_units,
                                )}
                            >
                                <FieldLabel htmlFor="credit-units">
                                    Units
                                </FieldLabel>
                                <Input
                                    id="credit-units"
                                    type="number"
                                    min={1}
                                    max={10}
                                    value={courseForm.data.credit_units}
                                    onChange={(event) =>
                                        courseForm.setData(
                                            'credit_units',
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldError>
                                    {courseForm.errors.credit_units}
                                </FieldError>
                            </Field>
                        </div>

                        <Field orientation="horizontal">
                            <div className="min-w-0 flex-1">
                                <FieldLabel htmlFor="elective-course">
                                    Elective Course
                                </FieldLabel>
                            </div>
                            <Switch
                                id="elective-course"
                                checked={courseForm.data.is_elective}
                                onCheckedChange={(checked) =>
                                    courseForm.setData('is_elective', checked)
                                }
                            />
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
                                onChange={(courseId, checked) =>
                                    courseForm.setData(
                                        'prerequisite_course_ids',
                                        checked
                                            ? [
                                                  ...courseForm.data
                                                      .prerequisite_course_ids,
                                                  courseId,
                                              ]
                                            : courseForm.data.prerequisite_course_ids.filter(
                                                  (id) => id !== courseId,
                                              ),
                                    )
                                }
                            />
                            <FieldError>
                                {courseForm.errors.prerequisite_course_ids}
                            </FieldError>
                        </Field>
                    </div>
                </ScrollArea>
                <footer className="flex shrink-0 flex-wrap justify-end gap-2 border-t border-border pt-4">
                    <Button asChild type="button" variant="outline">
                        <Link href={cancelRoute}>Cancel</Link>
                    </Button>
                    <Button type="submit" disabled={courseForm.processing}>
                        <RiSave3Line />
                        {courseForm.processing ? 'Saving...' : 'Save Course'}
                    </Button>
                </footer>
            </form>
        </div>
    );
}
