import { router } from '@inertiajs/react';
import {
    RiArrowDownSLine,
    RiErrorWarningLine,
    RiSendPlaneLine,
    RiSave3Line,
} from '@remixicon/react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';

import { storeCourseRegistration } from '@/actions/App/Http/Controllers/DashboardController';
import { QualifiedSemester } from '@/components/qualified-semester';
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
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type {
    DashboardCourseRegistrationCourse,
    DashboardCourseRegistrationSource,
    DashboardCourseRegistrationSummary,
    DashboardStudentCourseRegistration,
} from '@/types';

type StudentCourseRegistrationDashboardProps = {
    registration: DashboardStudentCourseRegistration;
};

const previousCourseSources = new Set<DashboardCourseRegistrationSource>([
    'carryover',
    'outstanding_requirement',
]);

export function StudentCourseRegistrationDashboard({
    registration,
}: StudentCourseRegistrationDashboardProps) {
    const [selectedCourses, setSelectedCourses] = useState<
        DashboardCourseRegistrationCourse[]
    >(registration.selectedCourses);
    const [isHistoryOpen, setIsHistoryOpen] = useState(false);
    const selectedKeys = useMemo(
        () => new Set(selectedCourses.map(courseKey)),
        [selectedCourses],
    );
    const selectedUnits = selectedCourses.reduce(
        (sum, course) => sum + course.units,
        0,
    );
    const initialSelectedKeys = useMemo(
        () => new Set(registration.selectedCourses.map(courseKey)),
        [registration.selectedCourses],
    );
    const exceedsMaximum = selectedUnits > registration.maximumCourseUnits;
    const selectedBlockReasons = selectedCourses.flatMap((course) =>
        course.blockReasons.map((reason) => `${course.code}: ${reason}`),
    );
    const isLocked = ['In review', 'Approved'].includes(
        registration.reviewStatus,
    );
    const isDirty =
        selectedCourses.length !== registration.selectedCourses.length ||
        selectedCourses.some(
            (course) => !initialSelectedKeys.has(courseKey(course)),
        );
    const allCurrentCourses = registration.courseGroups
        .filter((group) => !previousCourseSources.has(group.key))
        .flatMap((group) => group.courses);
    const allPreviousCourses = registration.courseGroups
        .filter((group) => previousCourseSources.has(group.key))
        .flatMap((group) => group.courses);
    const displayCourses = isLocked
        ? selectedCourses.filter(
              (course) => !previousCourseSources.has(course.source),
          )
        : allCurrentCourses;
    const displayPreviousCourses = isLocked
        ? selectedCourses.filter((course) =>
              previousCourseSources.has(course.source),
          )
        : allPreviousCourses;

    function toggleCourse(course: DashboardCourseRegistrationCourse) {
        if (isLocked || !course.selectable) {
            return;
        }

        setSelectedCourses((courses) =>
            courses.some(
                (selected) => courseKey(selected) === courseKey(course),
            )
                ? courses.filter(
                      (selected) => courseKey(selected) !== courseKey(course),
                  )
                : [...courses, course],
        );
    }

    function submit(action: 'save' | 'submit') {
        router.post(
            storeCourseRegistration(),
            {
                action,
                items: selectedCourses.map((course) => ({
                    course_offering_id: course.courseOfferingId,
                    source: course.source,
                })),
            },
            { preserveScroll: true },
        );
    }

    return (
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 className="text-lg font-semibold tracking-normal">
                        <QualifiedSemester
                            level={registration.currentSemester.level}
                            semester={registration.currentSemester.name}
                        />{' '}
                        Course Registration
                    </h1>
                    <div className="text-xs text-muted-foreground">
                        {registration.currentSemester.session} Session
                    </div>
                </div>
                {!isLocked && (
                    <div className="flex flex-wrap items-center gap-3">
                        <span className="text-sm text-muted-foreground">
                            <span className="font-semibold text-foreground tabular-nums">
                                {selectedUnits}
                            </span>{' '}
                            / {registration.maximumCourseUnits} units
                        </span>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={
                                    selectedCourses.length === 0 ||
                                    exceedsMaximum ||
                                    !isDirty
                                }
                                onClick={() => submit('save')}
                            >
                                <RiSave3Line />
                                Save Draft
                            </Button>
                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button
                                        type="button"
                                        size="sm"
                                        disabled={
                                            selectedCourses.length === 0 ||
                                            exceedsMaximum ||
                                            selectedBlockReasons.length > 0
                                        }
                                    >
                                        <RiSendPlaneLine />
                                        Submit for Review
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>
                                            Send course registration for review?
                                        </AlertDialogTitle>
                                        <AlertDialogDescription>
                                            Your course adviser will review the{' '}
                                            <span className="font-medium text-foreground">
                                                {selectedUnits} selected units
                                            </span>
                                            . You can make changes again if this
                                            is returned for correction.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>
                                            Keep Editing
                                        </AlertDialogCancel>
                                        <AlertDialogAction
                                            onClick={() => submit('submit')}
                                        >
                                            Send for Review
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        </div>
                    </div>
                )}
            </div>

            {registration.feedbackNote ? (
                <Notice tone="warning" title="Returned feedback">
                    {registration.feedbackNote}
                </Notice>
            ) : null}

            {exceedsMaximum ? (
                <Notice tone="danger">
                    You have selected {selectedUnits} units. The maximum for
                    this registration is {registration.maximumCourseUnits}{' '}
                    units.
                </Notice>
            ) : null}

            {selectedBlockReasons.length > 0 ? (
                <Notice
                    tone="danger"
                    title="Some selected courses cannot be submitted yet"
                >
                    <ul className="mt-1 list-inside list-disc">
                        {selectedBlockReasons.slice(0, 3).map((reason) => (
                            <li key={reason}>{reason}</li>
                        ))}
                    </ul>
                </Notice>
            ) : null}

            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardHeader className="flex flex-col gap-2 border-b border-border sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle>Current Semester Courses</CardTitle>
                        <div className="text-xs text-muted-foreground">
                            {isLocked
                                ? 'Courses included in this registration.'
                                : 'Toggle courses on or off to build your registration.'}
                        </div>
                    </div>
                    <div className="flex items-center gap-2">
                        <StatusBadge status={registration.reviewStatus} />
                        <span className="text-sm text-muted-foreground">
                            <span className="font-semibold text-foreground tabular-nums">
                                {selectedUnits}
                            </span>{' '}
                            / {registration.maximumCourseUnits} units
                        </span>
                    </div>
                </CardHeader>
                <CardContent className="p-0">
                    <CourseOptionsTable
                        courses={displayCourses}
                        selectedKeys={selectedKeys}
                        isLocked={isLocked}
                        onToggleCourse={toggleCourse}
                    />
                </CardContent>
            </Card>

            {displayPreviousCourses.length > 0 ? (
                <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                    <CardHeader className="border-b border-border">
                        <CardTitle>Carryover & Outstanding Courses</CardTitle>
                        <div className="text-xs text-muted-foreground">
                            {isLocked
                                ? 'Carryover and outstanding courses included in this registration.'
                                : 'Courses from previous semesters that still need to be satisfied.'}
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <CourseOptionsTable
                            courses={displayPreviousCourses}
                            selectedKeys={selectedKeys}
                            isLocked={isLocked}
                            onToggleCourse={toggleCourse}
                        />
                    </CardContent>
                </Card>
            ) : null}

            {registration.previousRegistrations.length > 0 ? (
                <PreviousRegistrations
                    registrations={registration.previousRegistrations}
                    open={isHistoryOpen}
                    onOpenChange={setIsHistoryOpen}
                />
            ) : null}
        </div>
    );
}

function CourseOptionsTable({
    courses,
    isLocked,
    onToggleCourse,
    selectedKeys,
}: {
    courses: DashboardCourseRegistrationCourse[];
    isLocked: boolean;
    onToggleCourse: (course: DashboardCourseRegistrationCourse) => void;
    selectedKeys: Set<string>;
}) {
    return (
        <Table className="text-sm">
            <TableHeader>
                <TableRow>
                    {!isLocked && (
                        <TableHead className="w-12 px-4">Take</TableHead>
                    )}
                    <TableHead className="w-[18%]">Course</TableHead>
                    <TableHead>Title</TableHead>
                    <TableHead className="w-[10%] text-center">Units</TableHead>
                    <TableHead className="hidden w-[18%] md:table-cell">
                        Category
                    </TableHead>
                    <TableHead className="w-[14%] pr-4 text-right">
                        Source
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {courses.length === 0 ? (
                    <TableRow>
                        <TableCell
                            colSpan={isLocked ? 5 : 6}
                            className="px-4 py-8 text-center text-muted-foreground"
                        >
                            {isLocked
                                ? 'No courses in this registration.'
                                : 'No courses available.'}
                        </TableCell>
                    </TableRow>
                ) : (
                    courses.map((course) => {
                        const selected = selectedKeys.has(courseKey(course));

                        return (
                            <TableRow
                                key={courseKey(course)}
                                className="odd:bg-muted/25"
                            >
                                {!isLocked && (
                                    <TableCell className="px-4">
                                        <Checkbox
                                            checked={selected}
                                            disabled={!course.selectable}
                                            aria-label={`Select ${course.code}`}
                                            onCheckedChange={() =>
                                                onToggleCourse(course)
                                            }
                                        />
                                    </TableCell>
                                )}
                                <TableCell className="font-semibold">
                                    {course.code}
                                </TableCell>
                                <TableCell>
                                    <div className="min-w-0">
                                        <div className="font-medium">
                                            {course.title}
                                        </div>
                                        {course.originSemester ? (
                                            <div className="text-xs text-muted-foreground">
                                                From {course.originSemester}
                                            </div>
                                        ) : null}
                                        {!isLocked && (
                                            <CourseWarnings course={course} />
                                        )}
                                    </div>
                                </TableCell>
                                <TableCell className="text-center font-semibold tabular-nums">
                                    {course.units}
                                </TableCell>
                                <TableCell className="hidden md:table-cell">
                                    {course.category}
                                </TableCell>
                                <TableCell className="pr-4 text-right">
                                    <SourceBadge course={course} />
                                </TableCell>
                            </TableRow>
                        );
                    })
                )}
            </TableBody>
        </Table>
    );
}

function PreviousRegistrations({
    onOpenChange,
    open,
    registrations,
}: {
    onOpenChange: (open: boolean) => void;
    open: boolean;
    registrations: DashboardCourseRegistrationSummary[];
}) {
    return (
        <Collapsible open={open} onOpenChange={onOpenChange}>
            <CollapsibleTrigger asChild>
                <button
                    type="button"
                    className="flex w-full items-center justify-between gap-4 border border-border bg-background px-4 py-3 text-left shadow-sm ring-1 ring-border/70 transition-colors hover:bg-muted/50"
                >
                    <span className="min-w-0">
                        <span className="block text-sm font-semibold tracking-normal">
                            Previous Course Registrations
                        </span>
                        <span className="mt-0.5 block text-xs text-muted-foreground">
                            {registrations.length} previous semesters
                        </span>
                    </span>
                    <RiArrowDownSLine
                        className={cn(
                            'size-5 shrink-0 text-muted-foreground transition-transform',
                            open && 'rotate-180',
                        )}
                    />
                </button>
            </CollapsibleTrigger>
            <CollapsibleContent>
                <div className="mt-4 grid gap-4">
                    {registrations.map((registration) => (
                        <Card
                            key={registration.id}
                            className="border border-border bg-background shadow-sm ring-1 ring-border/70"
                        >
                            <CardHeader className="flex flex-row items-center justify-between gap-3 border-b border-border">
                                <div>
                                    <CardTitle>
                                        <QualifiedSemester
                                            level={registration.semester.level}
                                            semester={
                                                registration.semester.name
                                            }
                                        />
                                    </CardTitle>
                                    <div className="mt-1 text-xs text-muted-foreground">
                                        {registration.semester.session} Session
                                    </div>
                                </div>
                                <StatusBadge status={registration.status} />
                            </CardHeader>
                            <CardContent className="p-0">
                                <ReadonlyCoursesTable
                                    courses={registration.courses}
                                />
                            </CardContent>
                        </Card>
                    ))}
                </div>
            </CollapsibleContent>
        </Collapsible>
    );
}

function ReadonlyCoursesTable({
    courses,
}: {
    courses: DashboardCourseRegistrationCourse[];
}) {
    return (
        <Table className="text-sm">
            <TableHeader>
                <TableRow>
                    <TableHead className="w-[20%] px-4">Course</TableHead>
                    <TableHead>Title</TableHead>
                    <TableHead className="w-[12%] text-center">Units</TableHead>
                    <TableHead className="w-[18%] pr-4 text-right">
                        Source
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {courses.map((course) => (
                    <TableRow
                        key={courseKey(course)}
                        className="odd:bg-muted/25"
                    >
                        <TableCell className="px-4 font-semibold">
                            {course.code}
                        </TableCell>
                        <TableCell className="font-medium">
                            {course.title}
                        </TableCell>
                        <TableCell className="text-center font-semibold tabular-nums">
                            {course.units}
                        </TableCell>
                        <TableCell className="pr-4 text-right">
                            <SourceBadge course={course} />
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

function CourseWarnings({
    course,
}: {
    course: DashboardCourseRegistrationCourse;
}) {
    if (course.blockReasons.length === 0) {
        return null;
    }

    return (
        <div className="mt-1 flex items-start gap-1.5 text-xs text-red-700 dark:text-red-300">
            <RiErrorWarningLine className="mt-0.5 size-3.5 shrink-0" />
            <span>{course.blockReasons.join(' ')}</span>
        </div>
    );
}

function SourceBadge({
    course,
}: {
    course: DashboardCourseRegistrationCourse;
}) {
    return (
        <Badge
            variant="outline"
            className={
                {
                    current_set:
                        'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-500/30 dark:bg-blue-500/15 dark:text-blue-300',
                    carryover:
                        'border-red-200 bg-red-50 text-red-700 dark:border-red-500/30 dark:bg-red-500/15 dark:text-red-300',
                    outstanding_requirement:
                        'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/15 dark:text-amber-300',
                    elective:
                        'border-violet-200 bg-violet-50 text-violet-700 dark:border-violet-500/30 dark:bg-violet-500/15 dark:text-violet-300',
                }[course.source]
            }
        >
            {course.sourceLabel}
        </Badge>
    );
}

function StatusBadge({ status }: { status: string }) {
    return (
        <Badge variant="outline" className={statusBadgeClass(status)}>
            {status}
        </Badge>
    );
}

function Notice({
    children,
    title,
    tone,
}: {
    children: ReactNode;
    title?: string;
    tone: 'danger' | 'warning';
}) {
    return (
        <div
            className={cn(
                'border px-4 py-3 text-sm shadow-sm',
                tone === 'danger'
                    ? 'border-red-200 bg-red-50 text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200'
                    : 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100',
            )}
        >
            {title ? <div className="font-semibold">{title}</div> : null}
            <div className={title ? 'mt-1' : undefined}>{children}</div>
        </div>
    );
}

function statusBadgeClass(status: string): string {
    return (
        {
            'In review':
                'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-500/30 dark:bg-blue-500/15 dark:text-blue-300',
            Approved:
                'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-300',
            Returned:
                'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/15 dark:text-amber-300',
            Draft: 'border-border bg-secondary text-secondary-foreground',
        }[status] ?? 'border-border bg-secondary text-secondary-foreground'
    );
}

function courseKey(course: DashboardCourseRegistrationCourse): string {
    return `${course.source}-${course.courseOfferingId}`;
}
