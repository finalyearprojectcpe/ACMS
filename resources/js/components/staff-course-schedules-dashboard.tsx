import { Link, useForm } from '@inertiajs/react';
import {
    RiAddLine,
    RiArrowLeftLine,
    RiCalendarTodoLine,
    RiDeleteBinLine,
    RiSave3Line,
} from '@remixicon/react';
import type { FormEvent } from 'react';

import {
    createCourseOffering,
    deleteCourseTimetableSlot,
    editCourseOffering,
    courseTimetable,
    storeCourseOffering,
    storeCourseTimetableSlot,
    updateCourseOffering,
    updateCourseTimetableSlot,
} from '@/actions/App/Http/Controllers/DashboardController';
import { level as dashboardLevel } from '@/actions/App/Http/Controllers/DashboardController';
import { QualifiedSemester } from '@/components/qualified-semester';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Select, SelectOption } from '@/components/ui/select';
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
    DashboardStaffCoursePrerequisiteOption,
    DashboardStaffCourseScheduleSemester,
    DashboardStaffCourseSchedules,
    DashboardStaffCourseTimetable,
    DashboardStaffCourseTimetableSlot,
} from '@/types';

type CourseOfferingFormData = {
    code: string;
    title: string;
    credit_units: number;
    is_mandatory: boolean;
    prerequisite_course_ids: number[];
};

type TimetableSlotFormData = {
    day_of_week: string;
    starts_at: string;
    ends_at: string;
    venue: string;
};

export function StaffCourseSchedulesDashboard({
    courseSchedules,
}: {
    courseSchedules: DashboardStaffCourseSchedules;
}) {
    return (
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-4">
            {courseSchedules.semesters.map((semester) => (
                <SemesterScheduleCard
                    key={semester.id}
                    level={courseSchedules.level.title}
                    levelSlug={courseSchedules.level.slug}
                    semester={semester}
                />
            ))}
        </div>
    );
}

export function StaffCourseOfferingForm({
    form,
}: {
    form: DashboardStaffCourseOfferingForm;
}) {
    const courseForm = useForm<CourseOfferingFormData>({
        code: form.offering?.code ?? '',
        title: form.offering?.title ?? '',
        credit_units: form.offering?.creditUnits ?? 3,
        is_mandatory: form.offering?.isMandatory ?? true,
        prerequisite_course_ids: form.offering?.prerequisiteCourseIds ?? [],
    });
    const backRoute = dashboardLevel({
        section: 'courses',
        level: form.level.slug,
    });
    const title =
        form.mode === 'create' ? 'Add Course' : `Edit ${form.offering?.code}`;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (form.mode === 'create') {
            courseForm.submit(
                storeCourseOffering({
                    level: form.level.slug,
                    semester: form.semester.slug,
                }),
            );

            return;
        }

        if (form.offering) {
            courseForm.submit(
                updateCourseOffering({
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
                <form onSubmit={submit}>
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

                            <div className="grid gap-4 sm:grid-cols-2">
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
                                    <FieldLabel htmlFor="course-requirement">
                                        Requirement
                                    </FieldLabel>
                                    <Select
                                        id="course-requirement"
                                        value={
                                            courseForm.data.is_mandatory
                                                ? '1'
                                                : '0'
                                        }
                                        aria-invalid={Boolean(
                                            courseForm.errors.is_mandatory,
                                        )}
                                        onChange={(event) =>
                                            courseForm.setData(
                                                'is_mandatory',
                                                event.target.value === '1',
                                            )
                                        }
                                    >
                                        <SelectOption value="1">
                                            Mandatory
                                        </SelectOption>
                                        <SelectOption value="0">
                                            Optional
                                        </SelectOption>
                                    </Select>
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
                                <PrerequisitePicker
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
                </form>
            </Card>
        </div>
    );
}

export function StaffCourseTimetableDashboard({
    timetable,
}: {
    timetable: DashboardStaffCourseTimetable;
}) {
    const addForm = useForm<TimetableSlotFormData>({
        day_of_week: timetable.weekdays[0] ?? 'Monday',
        starts_at: '08:00',
        ends_at: '10:00',
        venue: '',
    });
    const backRoute = dashboardLevel({
        section: 'courses',
        level: timetable.level.slug,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        addForm.submit(
            storeCourseTimetableSlot({
                level: timetable.level.slug,
                semester: timetable.semester.slug,
                offering: timetable.offering.id,
            }),
            {
                onSuccess: () => addForm.reset('venue'),
            },
        );
    }

    return (
        <div className="mx-auto flex w-full max-w-5xl flex-col gap-4">
            <div className="flex items-center justify-between gap-4">
                <div className="min-w-0">
                    <h1 className="truncate text-xl font-semibold tracking-normal">
                        {timetable.offering.code} Timetable
                    </h1>
                    <p className="mt-1 truncate text-sm text-muted-foreground">
                        {timetable.offering.title} ·{' '}
                        <QualifiedSemester
                            level={timetable.level.title}
                            semester={timetable.semester.name}
                        />
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
                <CardHeader>
                    <CardTitle>Add Class Time</CardTitle>
                </CardHeader>
                <CardContent>
                    <form onSubmit={submit}>
                        <div className="grid gap-4 lg:grid-cols-[1fr_1fr_1fr_1.4fr_auto]">
                            <Field
                                data-invalid={Boolean(
                                    addForm.errors.day_of_week,
                                )}
                            >
                                <FieldLabel htmlFor="new-day">Day</FieldLabel>
                                <WeekdaySelect
                                    id="new-day"
                                    weekdays={timetable.weekdays}
                                    value={addForm.data.day_of_week}
                                    onChange={(value) =>
                                        addForm.setData('day_of_week', value)
                                    }
                                />
                                <FieldError>
                                    {addForm.errors.day_of_week}
                                </FieldError>
                            </Field>
                            <Field
                                data-invalid={Boolean(addForm.errors.starts_at)}
                            >
                                <FieldLabel htmlFor="new-start">
                                    Starts
                                </FieldLabel>
                                <Input
                                    id="new-start"
                                    type="time"
                                    value={addForm.data.starts_at}
                                    aria-invalid={Boolean(
                                        addForm.errors.starts_at,
                                    )}
                                    onChange={(event) =>
                                        addForm.setData(
                                            'starts_at',
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldError>
                                    {addForm.errors.starts_at}
                                </FieldError>
                            </Field>
                            <Field
                                data-invalid={Boolean(addForm.errors.ends_at)}
                            >
                                <FieldLabel htmlFor="new-end">Ends</FieldLabel>
                                <Input
                                    id="new-end"
                                    type="time"
                                    value={addForm.data.ends_at}
                                    aria-invalid={Boolean(
                                        addForm.errors.ends_at,
                                    )}
                                    onChange={(event) =>
                                        addForm.setData(
                                            'ends_at',
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldError>
                                    {addForm.errors.ends_at}
                                </FieldError>
                            </Field>
                            <Field data-invalid={Boolean(addForm.errors.venue)}>
                                <FieldLabel htmlFor="new-venue">
                                    Venue
                                </FieldLabel>
                                <Input
                                    id="new-venue"
                                    value={addForm.data.venue}
                                    placeholder="ETF 204"
                                    autoComplete="off"
                                    aria-invalid={Boolean(addForm.errors.venue)}
                                    onChange={(event) =>
                                        addForm.setData(
                                            'venue',
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldError>{addForm.errors.venue}</FieldError>
                            </Field>
                            <div className="flex items-end">
                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={addForm.processing}
                                >
                                    <RiAddLine />
                                    Add
                                </Button>
                            </div>
                        </div>
                    </form>
                </CardContent>
            </Card>

            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardHeader>
                    <CardTitle>Class Times</CardTitle>
                </CardHeader>
                <CardContent className="p-0">
                    <TimetableSlotsTable
                        timetable={timetable}
                        slots={timetable.slots}
                    />
                </CardContent>
            </Card>
        </div>
    );
}

function SemesterScheduleCard({
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
                        <span>{semester.mandatoryCount} mandatory</span>
                        <span>{semester.optionalCount} optional</span>
                    </div>
                </div>
                <Link
                    href={createCourseOffering({
                        level: levelSlug,
                        semester: semester.slug,
                    })}
                    className="inline-flex shrink-0 items-center gap-1 text-sm font-medium text-link underline-offset-4 hover:underline"
                >
                    <RiAddLine className="size-4" />
                    Add course
                </Link>
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
                    <TableHead className="w-[28%] px-4">Course</TableHead>
                    <TableHead className="w-[10%] text-center">Units</TableHead>
                    <TableHead className="w-[14%] text-center">Type</TableHead>
                    <TableHead>Prerequisites</TableHead>
                    <TableHead className="w-[18%] pr-4 text-right">
                        Timetable
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
    const timetableRoute = courseTimetable({
        level: levelSlug,
        semester: semesterSlug,
        offering: offering.id,
    });

    return (
        <TableRow>
            <TableCell className="px-4">
                <div className="min-w-0">
                    <div className="flex items-center gap-2">
                        {offering.canEdit ? (
                            <Link
                                href={editRoute}
                                className="font-semibold text-link underline-offset-4 hover:underline"
                            >
                                {offering.code}
                            </Link>
                        ) : (
                            <span className="font-semibold">
                                {offering.code}
                            </span>
                        )}
                        <OwnerBadge owner={offering.owner} />
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
                <RequirementBadge requirement={offering.requirement} />
            </TableCell>
            <TableCell>
                <PrerequisiteBadges prerequisites={offering.prerequisites} />
            </TableCell>
            <TableCell className="pr-4 text-right">
                <Link
                    href={timetableRoute}
                    className="inline-flex items-center gap-1 text-xs font-medium text-link underline-offset-4 hover:underline"
                >
                    <RiCalendarTodoLine className="size-4" />
                    {offering.timetableSlotsCount} slots
                </Link>
            </TableCell>
        </TableRow>
    );
}

function PrerequisitePicker({
    options,
    selectedIds,
    onChange,
}: {
    options: DashboardStaffCoursePrerequisiteOption[];
    selectedIds: number[];
    onChange: (courseId: number, checked: boolean) => void;
}) {
    if (options.length === 0) {
        return (
            <div className="border border-border bg-muted/20 px-3 py-3 text-sm text-muted-foreground">
                No earlier courses are available.
            </div>
        );
    }

    return (
        <div className="grid gap-2 rounded-none border border-border bg-background p-2 sm:grid-cols-2">
            {options.map((option) => {
                const id = `prerequisite-${option.id}`;

                return (
                    <label
                        key={option.id}
                        htmlFor={id}
                        className="flex items-start gap-3 border border-border bg-background p-3"
                    >
                        <Checkbox
                            id={id}
                            checked={selectedIds.includes(option.id)}
                            onCheckedChange={(checked) =>
                                onChange(option.id, checked === true)
                            }
                        />
                        <span className="min-w-0">
                            <span className="block text-sm font-semibold">
                                {option.code}
                            </span>
                            <span className="block truncate text-xs text-muted-foreground">
                                {option.title}
                            </span>
                            <span className="mt-1 block text-xs text-muted-foreground">
                                <QualifiedSemester
                                    level={option.level}
                                    semester={option.semester}
                                />{' '}
                                · {option.units} units
                            </span>
                        </span>
                    </label>
                );
            })}
        </div>
    );
}

function TimetableSlotsTable({
    timetable,
    slots,
}: {
    timetable: DashboardStaffCourseTimetable;
    slots: DashboardStaffCourseTimetableSlot[];
}) {
    return (
        <Table className="text-sm">
            <TableHeader>
                <TableRow>
                    <TableHead className="w-[22%] px-4">Day</TableHead>
                    <TableHead className="w-[18%]">Starts</TableHead>
                    <TableHead className="w-[18%]">Ends</TableHead>
                    <TableHead>Venue</TableHead>
                    <TableHead className="w-[16%] pr-4 text-right">
                        Action
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {slots.length > 0 ? (
                    slots.map((slot) => (
                        <TimetableSlotRow
                            key={slot.id}
                            timetable={timetable}
                            slot={slot}
                        />
                    ))
                ) : (
                    <TableRow>
                        <TableCell
                            colSpan={5}
                            className="px-4 py-6 text-muted-foreground"
                        >
                            No class times have been added.
                        </TableCell>
                    </TableRow>
                )}
            </TableBody>
        </Table>
    );
}

function TimetableSlotRow({
    timetable,
    slot,
}: {
    timetable: DashboardStaffCourseTimetable;
    slot: DashboardStaffCourseTimetableSlot;
}) {
    const slotForm = useForm<TimetableSlotFormData>({
        day_of_week: slot.dayOfWeek,
        starts_at: slot.startsAt,
        ends_at: slot.endsAt,
        venue: slot.venue,
    });

    function update(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        slotForm.submit(
            updateCourseTimetableSlot({
                level: timetable.level.slug,
                semester: timetable.semester.slug,
                offering: timetable.offering.id,
                slot: slot.id,
            }),
        );
    }

    function destroy() {
        slotForm.submit(
            deleteCourseTimetableSlot({
                level: timetable.level.slug,
                semester: timetable.semester.slug,
                offering: timetable.offering.id,
                slot: slot.id,
            }),
        );
    }

    return (
        <TableRow>
            <TableCell colSpan={5} className="p-0">
                <form onSubmit={update}>
                    <div className="grid grid-cols-[1fr_0.8fr_0.8fr_1.2fr_auto] items-start gap-2 px-4 py-2">
                        <Field
                            data-invalid={Boolean(slotForm.errors.day_of_week)}
                        >
                            <WeekdaySelect
                                weekdays={timetable.weekdays}
                                value={slotForm.data.day_of_week}
                                onChange={(value) =>
                                    slotForm.setData('day_of_week', value)
                                }
                            />
                            <FieldError>
                                {slotForm.errors.day_of_week}
                            </FieldError>
                        </Field>
                        <Field
                            data-invalid={Boolean(slotForm.errors.starts_at)}
                        >
                            <Input
                                type="time"
                                value={slotForm.data.starts_at}
                                aria-invalid={Boolean(
                                    slotForm.errors.starts_at,
                                )}
                                onChange={(event) =>
                                    slotForm.setData(
                                        'starts_at',
                                        event.target.value,
                                    )
                                }
                            />
                            <FieldError>{slotForm.errors.starts_at}</FieldError>
                        </Field>
                        <Field data-invalid={Boolean(slotForm.errors.ends_at)}>
                            <Input
                                type="time"
                                value={slotForm.data.ends_at}
                                aria-invalid={Boolean(slotForm.errors.ends_at)}
                                onChange={(event) =>
                                    slotForm.setData(
                                        'ends_at',
                                        event.target.value,
                                    )
                                }
                            />
                            <FieldError>{slotForm.errors.ends_at}</FieldError>
                        </Field>
                        <Field data-invalid={Boolean(slotForm.errors.venue)}>
                            <Input
                                value={slotForm.data.venue}
                                aria-invalid={Boolean(slotForm.errors.venue)}
                                onChange={(event) =>
                                    slotForm.setData(
                                        'venue',
                                        event.target.value,
                                    )
                                }
                            />
                            <FieldError>{slotForm.errors.venue}</FieldError>
                        </Field>
                        <div className="flex justify-end gap-1">
                            <Button
                                type="submit"
                                size="icon-sm"
                                variant="outline"
                                disabled={slotForm.processing}
                                aria-label="Save timetable slot"
                            >
                                <RiSave3Line />
                            </Button>
                            <Button
                                type="button"
                                size="icon-sm"
                                variant="ghost"
                                disabled={slotForm.processing}
                                aria-label="Delete timetable slot"
                                onClick={destroy}
                            >
                                <RiDeleteBinLine />
                            </Button>
                        </div>
                    </div>
                </form>
            </TableCell>
        </TableRow>
    );
}

function WeekdaySelect({
    id,
    weekdays,
    value,
    onChange,
}: {
    id?: string;
    weekdays: string[];
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <Select
            id={id}
            value={value}
            onChange={(event) => onChange(event.target.value)}
        >
            {weekdays.map((weekday) => (
                <SelectOption key={weekday} value={weekday}>
                    {weekday}
                </SelectOption>
            ))}
        </Select>
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

function RequirementBadge({ requirement }: { requirement: string }) {
    return (
        <Badge
            variant="outline"
            className={
                requirement === 'Mandatory'
                    ? 'border-foreground/20 bg-foreground/5 text-foreground'
                    : 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-500/30 dark:bg-blue-500/15 dark:text-blue-300'
            }
        >
            {requirement}
        </Badge>
    );
}

function OwnerBadge({ owner }: { owner: string }) {
    return (
        <Badge
            variant="outline"
            className="border-border bg-background text-xs"
        >
            {owner}
        </Badge>
    );
}
