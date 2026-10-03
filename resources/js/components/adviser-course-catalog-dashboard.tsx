import { Link, router, useForm } from '@inertiajs/react';
import { RiAddLine, RiDeleteBinLine, RiSave3Line } from '@remixicon/react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';

import {
    destroy,
    store,
    update,
} from '@/actions/App/Http/Controllers/CourseCatalogController';
import {
    editCatalogCourse,
    showCourseSchedule,
} from '@/actions/App/Http/Controllers/DashboardController';
import { ActionLink } from '@/components/action-link';
import { CourseCategoryBadge } from '@/components/course-category-badge';
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
import { Button } from '@/components/ui/button';
import { FieldError } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { courseToneClasses } from '@/lib/course-presentation';
import { studentSetRouteParameters } from '@/lib/student-sets';
import type { DashboardAdviserCourseCatalog } from '@/types';

type Entry = {
    code: string;
    title: string;
    credit_units: number;
    is_elective: boolean;
};
const blank = (): Entry => ({
    code: '',
    title: '',
    credit_units: 3,
    is_elective: false,
});

export function AdviserCourseCatalogDashboard({
    catalog,
}: {
    catalog: DashboardAdviserCourseCatalog;
}) {
    const [adding, setAdding] = useState(
        catalog.courses.length === 0 && !catalog.editingCode,
    );
    const params = studentSetRouteParameters(catalog.set);
    const editing = catalog.courses.find(
        (course) => course.urlCode === catalog.editingCode,
    );
    const form = useForm<{ courses: Entry[] }>({ courses: [blank()] });
    const lastEntryRef = useRef<HTMLDivElement>(null);
    const editForm = useForm<Entry>(
        editing
            ? {
                  code: editing.code,
                  title: editing.title,
                  credit_units: editing.units,
                  is_elective: editing.isElective,
              }
            : blank(),
    );

    useEffect(() => {
        if (!adding) {
            return;
        }

        lastEntryRef.current?.scrollIntoView({ block: 'nearest' });
        lastEntryRef.current
            ?.querySelector('input')
            ?.focus({ preventScroll: true });
    }, [adding, form.data.courses.length]);

    function submit(event: FormEvent) {
        event.preventDefault();

        if (editing) {
            editForm.patch(
                update.url({ ...params, courseCode: editing.urlCode }),
            );

            return;
        }

        form.post(store.url(params), {
            onSuccess: () => {
                form.reset();
                setAdding(false);
            },
        });
    }

    function changeRow(index: number, patch: Partial<Entry>) {
        form.setData(
            'courses',
            form.data.courses.map((row, rowIndex) =>
                rowIndex === index ? { ...row, ...patch } : row,
            ),
        );
    }

    return (
        <div className="mx-auto flex min-h-0 w-full max-w-5xl flex-1 flex-col">
            <header className="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b pb-4">
                <div>
                    <h1 className="text-xl font-semibold">
                        {editing ? 'Edit Course' : 'Course Catalog'}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {catalog.set.name}
                    </p>
                </div>
                <ActionLink href={showCourseSchedule(params)}>
                    Go to Course Schedule
                </ActionLink>
            </header>
            <ScrollArea className="min-h-0 flex-1">
                {editing ? (
                    <form onSubmit={submit} className="max-w-3xl py-6">
                        <div className="grid gap-4 sm:grid-cols-[9rem_minmax(0,1fr)_6rem]">
                            <label className="grid gap-1 text-sm font-medium">
                                Code
                                <Input
                                    value={editForm.data.code}
                                    onChange={(event) =>
                                        editForm.setData(
                                            'code',
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldError>{editForm.errors.code}</FieldError>
                            </label>
                            <label className="grid gap-1 text-sm font-medium">
                                Title
                                <Input
                                    value={editForm.data.title}
                                    onChange={(event) =>
                                        editForm.setData(
                                            'title',
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldError>{editForm.errors.title}</FieldError>
                            </label>
                            <label className="grid gap-1 text-sm font-medium">
                                Units
                                <Input
                                    type="number"
                                    min={1}
                                    max={10}
                                    value={editForm.data.credit_units}
                                    onChange={(event) =>
                                        editForm.setData(
                                            'credit_units',
                                            Number(event.target.value),
                                        )
                                    }
                                />
                                <FieldError>
                                    {editForm.errors.credit_units}
                                </FieldError>
                            </label>
                        </div>
                        <label className="mt-5 flex w-fit items-center gap-3 text-sm font-medium">
                            <Switch
                                checked={editForm.data.is_elective}
                                onCheckedChange={(value) =>
                                    editForm.setData('is_elective', value)
                                }
                            />{' '}
                            Elective Course
                        </label>
                        <div className="mt-6 flex gap-2">
                            <Button disabled={editForm.processing}>
                                <RiSave3Line /> Save Course
                            </Button>
                            <Button variant="outline" asChild>
                                <Link href={showCourseSchedule(params)}>
                                    Cancel
                                </Link>
                            </Button>
                        </div>
                    </form>
                ) : (
                    <div className="py-5">
                        <ScrollArea className="w-full border-y">
                            <Table className="min-w-[620px]">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12 text-center">
                                            S/N
                                        </TableHead>
                                        <TableHead className="w-28">
                                            Course
                                        </TableHead>
                                        <TableHead>Title</TableHead>
                                        <TableHead className="w-20 text-center">
                                            Units
                                        </TableHead>
                                        <TableHead className="w-24">
                                            Type
                                        </TableHead>
                                        <TableHead className="w-28 text-right">
                                            Action
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {catalog.courses.length ? (
                                        catalog.courses.map((course, index) => (
                                            <TableRow
                                                key={course.urlCode}
                                                className={
                                                    course.isElective
                                                        ? courseToneClasses.row
                                                              .elective
                                                        : 'odd:bg-muted/25'
                                                }
                                            >
                                                <TableCell className="text-center text-muted-foreground tabular-nums">
                                                    {index + 1}
                                                </TableCell>
                                                <TableCell className="font-semibold">
                                                    {course.code}
                                                </TableCell>
                                                <TableCell>
                                                    {course.title}
                                                </TableCell>
                                                <TableCell className="text-center tabular-nums">
                                                    {course.units}
                                                </TableCell>
                                                <TableCell>
                                                    <CourseCategoryBadge
                                                        category={
                                                            course.isElective
                                                                ? 'elective'
                                                                : 'normal'
                                                        }
                                                    />
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <ActionLink
                                                        href={editCatalogCourse(
                                                            {
                                                                ...params,
                                                                courseCode:
                                                                    course.urlCode,
                                                            },
                                                        )}
                                                    >
                                                        Edit
                                                    </ActionLink>
                                                    {course.scheduledCount ===
                                                    0 ? (
                                                        <AlertDialog>
                                                            <AlertDialogTrigger
                                                                asChild
                                                            >
                                                                <Button
                                                                    variant="ghost"
                                                                    size="icon-sm"
                                                                    className="ml-2 text-destructive"
                                                                    aria-label={`Remove ${course.code}`}
                                                                >
                                                                    <RiDeleteBinLine />
                                                                </Button>
                                                            </AlertDialogTrigger>
                                                            <AlertDialogContent>
                                                                <AlertDialogHeader>
                                                                    <AlertDialogTitle>
                                                                        Remove{' '}
                                                                        {
                                                                            course.code
                                                                        }
                                                                        ?
                                                                    </AlertDialogTitle>
                                                                    <AlertDialogDescription>
                                                                        {
                                                                            course.title
                                                                        }{' '}
                                                                        will be
                                                                        removed
                                                                        from
                                                                        this
                                                                        set's
                                                                        catalog.
                                                                    </AlertDialogDescription>
                                                                </AlertDialogHeader>
                                                                <AlertDialogFooter>
                                                                    <AlertDialogCancel>
                                                                        Keep
                                                                        Course
                                                                    </AlertDialogCancel>
                                                                    <AlertDialogAction
                                                                        variant="destructive"
                                                                        onClick={() =>
                                                                            router.delete(
                                                                                destroy.url(
                                                                                    {
                                                                                        ...params,
                                                                                        courseCode:
                                                                                            course.urlCode,
                                                                                    },
                                                                                ),
                                                                            )
                                                                        }
                                                                    >
                                                                        Remove
                                                                        Course
                                                                    </AlertDialogAction>
                                                                </AlertDialogFooter>
                                                            </AlertDialogContent>
                                                        </AlertDialog>
                                                    ) : null}
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    ) : (
                                        <TableRow>
                                            <TableCell
                                                colSpan={6}
                                                className="py-8 text-center text-muted-foreground"
                                            >
                                                No courses in this set yet.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                            <ScrollBar orientation="horizontal" />
                        </ScrollArea>
                        {adding ? (
                            <form
                                onSubmit={submit}
                                className="mt-7 border-t pt-5"
                            >
                                <h2 className="mb-3 font-semibold">
                                    Add Courses
                                </h2>
                                <div className="space-y-2">
                                    {form.data.courses.map((row, index) => (
                                        <div
                                            key={index}
                                            ref={
                                                index ===
                                                form.data.courses.length - 1
                                                    ? lastEntryRef
                                                    : undefined
                                            }
                                            className="grid items-center gap-2 sm:grid-cols-[2rem_9rem_minmax(0,1fr)_6rem_7rem_2rem]"
                                        >
                                            <span className="hidden text-sm text-muted-foreground sm:block">
                                                {index + 1}.
                                            </span>
                                            <Input
                                                aria-label={`Course ${index + 1} code`}
                                                placeholder="CPE 301"
                                                value={row.code}
                                                onChange={(event) =>
                                                    changeRow(index, {
                                                        code: event.target
                                                            .value,
                                                    })
                                                }
                                            />
                                            <Input
                                                aria-label={`Course ${index + 1} title`}
                                                placeholder="Course title"
                                                value={row.title}
                                                onChange={(event) =>
                                                    changeRow(index, {
                                                        title: event.target
                                                            .value,
                                                    })
                                                }
                                            />
                                            <Input
                                                aria-label={`Course ${index + 1} units`}
                                                type="number"
                                                min={1}
                                                max={10}
                                                value={row.credit_units}
                                                onChange={(event) =>
                                                    changeRow(index, {
                                                        credit_units: Number(
                                                            event.target.value,
                                                        ),
                                                    })
                                                }
                                            />
                                            <label className="flex items-center gap-2 text-sm">
                                                <Switch
                                                    checked={row.is_elective}
                                                    onCheckedChange={(value) =>
                                                        changeRow(index, {
                                                            is_elective: value,
                                                        })
                                                    }
                                                />{' '}
                                                Elective
                                            </label>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon-sm"
                                                aria-label={`Remove row ${index + 1}`}
                                                disabled={
                                                    form.data.courses.length ===
                                                    1
                                                }
                                                onClick={() =>
                                                    form.setData(
                                                        'courses',
                                                        form.data.courses.filter(
                                                            (_, rowIndex) =>
                                                                rowIndex !==
                                                                index,
                                                        ),
                                                    )
                                                }
                                            >
                                                <RiDeleteBinLine />
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                                <FieldError className="mt-2">
                                    {form.errors.courses}
                                </FieldError>
                                <div className="mt-5 flex flex-wrap justify-between gap-3">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            form.setData('courses', [
                                                ...form.data.courses,
                                                blank(),
                                            ])
                                        }
                                    >
                                        <RiAddLine /> Add Row
                                    </Button>
                                    <div className="flex gap-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => setAdding(false)}
                                        >
                                            Cancel
                                        </Button>
                                        <Button disabled={form.processing}>
                                            <RiSave3Line /> Save Courses
                                        </Button>
                                    </div>
                                </div>
                            </form>
                        ) : null}
                    </div>
                )}
            </ScrollArea>
            {!adding && !editing ? (
                <footer className="flex justify-end border-t pt-4">
                    <Button onClick={() => setAdding(true)}>
                        <RiAddLine /> Add Courses
                    </Button>
                </footer>
            ) : null}
        </div>
    );
}
