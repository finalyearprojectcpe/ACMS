import { Link, useForm } from '@inertiajs/react';
import {
    RiAddLine,
    RiArrowDownSLine,
    RiArrowRightLine,
} from '@remixicon/react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { store as storeCourseOffering } from '@/actions/App/Http/Controllers/CourseOfferingController';
import { showCourseSchedule } from '@/actions/App/Http/Controllers/DashboardController';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import { FieldError } from '@/components/ui/field';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { semesterLabel } from '@/lib/academic';
import { courseToneClasses } from '@/lib/course-presentation';
import {
    sessionRouteYears,
    studentSetRouteParameters,
} from '@/lib/student-sets';
import type { DashboardAdviserCourseOfferingForm } from '@/types';

export function ScheduleCatalogCourses({
    form,
}: {
    form: DashboardAdviserCourseOfferingForm;
}) {
    const [open, setOpen] = useState(false);
    const [step, setStep] = useState<'select' | 'review'>('select');
    const scheduleForm = useForm<{ course_ids: number[] }>({ course_ids: [] });
    const params = studentSetRouteParameters(form.set);
    const backUrl = showCourseSchedule(params, {
        query: {
            session: sessionRouteYears(form.academicSession),
            semester: form.semester.slug,
        },
    });
    const selected = form.catalogCourses.filter((course) =>
        scheduleForm.data.course_ids.includes(course.id),
    );
    const remaining = form.catalogCourses.filter(
        (course) => !scheduleForm.data.course_ids.includes(course.id),
    );
    const totalUnits = selected.reduce(
        (total, course) => total + course.units,
        0,
    );

    function submit(event: FormEvent) {
        event.preventDefault();

        if (step === 'select') {
            setStep('review');

            return;
        }

        scheduleForm.post(
            storeCourseOffering.url({
                ...params,
                sessionYears: sessionRouteYears(form.academicSession),
                semester: form.semester.slug,
            }),
        );
    }

    return (
        <form
            className="mx-auto flex min-h-0 w-full max-w-4xl flex-1 flex-col"
            onSubmit={submit}
        >
            <header className="shrink-0 border-b pb-5">
                <h1 className="text-lg font-semibold">Add Courses</h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    {form.set.name} · {form.level.title} ·{' '}
                    {semesterLabel(form.semester.name)} ·{' '}
                    {form.academicSession.name} Session
                </p>
            </header>
            {step === 'select' ? (
                <div className="shrink-0 py-5">
                    <Popover open={open} onOpenChange={setOpen}>
                        <PopoverTrigger asChild>
                            <Button
                                variant="outline"
                                type="button"
                                className="w-full max-w-md justify-between font-normal"
                            >
                                <span>Search the set catalog</span>
                                <RiArrowDownSLine />
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent
                            align="start"
                            className="w-[min(30rem,calc(100vw-2rem))] p-0"
                        >
                            <Command>
                                <CommandInput placeholder="Search by code or title..." />
                                <CommandList>
                                    <CommandEmpty>
                                        No available course found.
                                    </CommandEmpty>
                                    <CommandGroup>
                                        {remaining.map((course) => (
                                            <CommandItem
                                                key={course.id}
                                                className={
                                                    course.isElective
                                                        ? courseToneClasses.row
                                                              .elective
                                                        : undefined
                                                }
                                                value={`${course.code} ${course.title}`}
                                                onSelect={() => {
                                                    scheduleForm.setData(
                                                        'course_ids',
                                                        [
                                                            ...scheduleForm.data
                                                                .course_ids,
                                                            course.id,
                                                        ],
                                                    );
                                                    setOpen(false);
                                                }}
                                            >
                                                <span className="w-24 shrink-0 font-semibold">
                                                    {course.code}
                                                </span>
                                                <span className="min-w-0 flex-1 truncate">
                                                    {course.title}
                                                </span>
                                                <span className="ml-3 text-xs text-muted-foreground tabular-nums">
                                                    {course.units} units
                                                </span>
                                            </CommandItem>
                                        ))}
                                    </CommandGroup>
                                </CommandList>
                            </Command>
                        </PopoverContent>
                    </Popover>
                </div>
            ) : null}
            <ScrollArea className="min-h-0 flex-1">
                <div className={step === 'select' ? 'pb-5' : 'py-5'}>
                    <h2 className="mb-4 text-base font-semibold">
                        {step === 'select'
                            ? 'Selected Courses'
                            : 'Review Courses'}
                    </h2>
                    <div className="border-y">
                        <Table className="table-fixed">
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-24">
                                        Course
                                    </TableHead>
                                    <TableHead>Title</TableHead>
                                    <TableHead className="w-14 text-center">
                                        Units
                                    </TableHead>
                                    {step === 'select' ? (
                                        <TableHead className="w-16 text-right">
                                            Action
                                        </TableHead>
                                    ) : null}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {selected.length ? (
                                    selected.map((course) => (
                                        <TableRow
                                            key={course.id}
                                            className={
                                                course.isElective
                                                    ? courseToneClasses.row
                                                          .elective
                                                    : undefined
                                            }
                                        >
                                            <TableCell className="break-words whitespace-normal font-semibold">
                                                {course.code}
                                            </TableCell>
                                            <TableCell className="break-words whitespace-normal">
                                                {course.title}
                                                {course.isElective ? (
                                                    <span className="ml-2 text-xs text-amber-600">
                                                        Elective
                                                    </span>
                                                ) : null}
                                            </TableCell>
                                            <TableCell className="text-center tabular-nums">
                                                {course.units}
                                            </TableCell>
                                            {step === 'select' ? (
                                                <TableCell className="text-right">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="px-1"
                                                        onClick={() =>
                                                            scheduleForm.setData(
                                                                'course_ids',
                                                                scheduleForm.data.course_ids.filter(
                                                                    (id) =>
                                                                        id !==
                                                                        course.id,
                                                                ),
                                                            )
                                                        }
                                                    >
                                                        Remove
                                                    </Button>
                                                </TableCell>
                                            ) : null}
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell
                                            colSpan={step === 'select' ? 4 : 3}
                                            className="py-10 text-center text-muted-foreground"
                                        >
                                            No courses selected.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                            {step === 'review' ? (
                                <TableFooter>
                                    <TableRow>
                                        <TableCell
                                            colSpan={2}
                                            className="text-right font-semibold"
                                        >
                                            Total Units
                                        </TableCell>
                                        <TableCell className="text-center font-semibold tabular-nums">
                                            {totalUnits}
                                        </TableCell>
                                    </TableRow>
                                </TableFooter>
                            ) : null}
                        </Table>
                    </div>
                    {step === 'select' && form.catalogCourses.length === 0 ? (
                        <p className="mt-4 text-sm">
                            The catalog has no unscheduled courses.{' '}
                            <Link
                                className="font-medium text-primary underline"
                                href={form.catalogUrl}
                            >
                                Open Course Catalog
                            </Link>
                        </p>
                    ) : null}
                </div>
            </ScrollArea>
            <footer className="flex shrink-0 flex-wrap items-center justify-between gap-3 border-t pt-4">
                {scheduleForm.errors.course_ids ? (
                    <FieldError role="alert">
                        {scheduleForm.errors.course_ids}
                    </FieldError>
                ) : step === 'select' ? (
                    <span className="text-sm text-muted-foreground tabular-nums">
                        {selected.length}{' '}
                        {selected.length === 1 ? 'course' : 'courses'} selected
                    </span>
                ) : null}
                <div className="ml-auto flex flex-wrap gap-2">
                    {step === 'select' ? (
                        <Button type="button" variant="outline" asChild>
                            <Link href={backUrl}>Cancel</Link>
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setStep('select')}
                        >
                            Edit Selection
                        </Button>
                    )}
                    <Button
                        type="submit"
                        disabled={
                            scheduleForm.processing || selected.length === 0
                        }
                    >
                        {step === 'select' ? (
                            <RiArrowRightLine />
                        ) : (
                            <RiAddLine />
                        )}
                        {step === 'select'
                            ? 'Review Courses'
                            : `Add ${selected.length} ${selected.length === 1 ? 'Course' : 'Courses'}`}
                    </Button>
                </div>
            </footer>
        </form>
    );
}
