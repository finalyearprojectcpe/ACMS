import { router } from '@inertiajs/react';
import { RiDeleteBinLine, RiEditLine } from '@remixicon/react';

import { destroy as destroyCourseOffering } from '@/actions/App/Http/Controllers/CourseOfferingController';
import { editCourseOffering } from '@/actions/App/Http/Controllers/DashboardController';
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
import { Badge } from '@/components/ui/badge';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
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
import { cn } from '@/lib/utils';
import type {
    DashboardAdviserCourseOffering,
    DashboardStudentSetContext,
} from '@/types';

export function CourseTable({
    courses,
    studentSet,
    sessionYears,
    semester,
}: {
    courses: DashboardAdviserCourseOffering[];
    studentSet: DashboardStudentSetContext;
    sessionYears: string;
    semester: string;
}) {
    const routeParameters = studentSetRouteParameters(studentSet);

    return (
        <ScrollArea className="h-full min-h-0 w-full">
            <Table className="min-w-[680px] text-sm">
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-24 px-4 sm:px-5">
                            Course
                        </TableHead>
                        <TableHead className="max-w-72 min-w-48">
                            Title
                        </TableHead>
                        <TableHead className="w-16 text-center">
                            Units
                        </TableHead>
                        <TableHead className="w-28">Category</TableHead>
                        <TableHead className="w-28">Results</TableHead>
                        <TableHead className="w-24 pr-4 text-right sm:pr-5">
                            Actions
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {courses.length === 0 ? (
                        <TableRow>
                            <TableCell
                                colSpan={6}
                                className="px-4 py-10 text-center text-muted-foreground"
                            >
                                No courses have been added to this semester.
                            </TableCell>
                        </TableRow>
                    ) : (
                        courses.map((course) => (
                            <TableRow
                                key={course.id}
                                className={cn(
                                    course.category === 'elective'
                                        ? courseToneClasses.row.elective
                                        : 'odd:bg-background even:bg-muted/25',
                                )}
                            >
                                <TableCell className="px-4 font-semibold sm:px-5">
                                    <ActionLink href={course.gradeUrl}>
                                        {course.code}
                                    </ActionLink>
                                </TableCell>
                                <TableCell className="max-w-72 min-w-48 whitespace-normal">
                                    <div className="font-medium">
                                        {course.title}
                                    </div>
                                    {course.retakeOnly && (
                                        <div className="text-xs text-muted-foreground">
                                            Retake roster
                                        </div>
                                    )}
                                    {course.prerequisites.length > 0 ? (
                                        <div className="mt-0.5 text-xs text-muted-foreground">
                                            Requires{' '}
                                            {course.prerequisites
                                                .map((item) => item.code)
                                                .join(', ')}
                                        </div>
                                    ) : null}
                                </TableCell>
                                <TableCell className="text-center font-semibold tabular-nums">
                                    {course.units}
                                </TableCell>
                                <TableCell>
                                    <CourseCategoryBadge
                                        category={course.category}
                                    />
                                </TableCell>
                                <TableCell>
                                    {course.publishedAt ? (
                                        <Badge className="bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">
                                            Published
                                        </Badge>
                                    ) : (
                                        <span className="text-sm text-muted-foreground tabular-nums">
                                            {course.enteredCount}/
                                            {course.assignedCount} entered
                                        </span>
                                    )}
                                </TableCell>
                                <TableCell className="pr-4 text-right sm:pr-5">
                                    <div className="inline-flex items-center gap-3">
                                        <ActionLink
                                            href={editCourseOffering({
                                                ...routeParameters,
                                                sessionYears,
                                                semester,
                                                courseCode: course.urlCode,
                                            })}
                                            icon={
                                                <RiEditLine className="size-4" />
                                            }
                                        >
                                            Edit
                                        </ActionLink>
                                        <AlertDialog>
                                            <AlertDialogTrigger asChild>
                                                <button
                                                    type="button"
                                                    className="text-sm font-semibold text-destructive hover:underline"
                                                >
                                                    Remove
                                                </button>
                                            </AlertDialogTrigger>
                                            <AlertDialogContent>
                                                <AlertDialogHeader>
                                                    <AlertDialogTitle>
                                                        Remove {course.code}?
                                                    </AlertDialogTitle>
                                                    <AlertDialogDescription>
                                                        You are removing{' '}
                                                        <strong>
                                                            {course.code} ·{' '}
                                                            {course.title}
                                                        </strong>{' '}
                                                        from this semester.
                                                        Existing student
                                                        registrations will
                                                        prevent removal.
                                                    </AlertDialogDescription>
                                                </AlertDialogHeader>
                                                <AlertDialogFooter>
                                                    <AlertDialogCancel>
                                                        Keep Course
                                                    </AlertDialogCancel>
                                                    <AlertDialogAction
                                                        variant="destructive"
                                                        onClick={() =>
                                                            router.delete(
                                                                destroyCourseOffering(
                                                                    {
                                                                        ...routeParameters,
                                                                        sessionYears,
                                                                        semester,
                                                                        courseCode:
                                                                            course.urlCode,
                                                                    },
                                                                ),
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <RiDeleteBinLine />
                                                        Remove Course
                                                    </AlertDialogAction>
                                                </AlertDialogFooter>
                                            </AlertDialogContent>
                                        </AlertDialog>
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))
                    )}
                </TableBody>
            </Table>
            <ScrollBar orientation="horizontal" />
        </ScrollArea>
    );
}
