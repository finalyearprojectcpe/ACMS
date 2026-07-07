import { router } from '@inertiajs/react';
import {
    RiAddLine,
    RiArrowDownSLine,
    RiCloseLine,
    RiErrorWarningLine,
    RiSearchLine,
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
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
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
    DashboardCourseRegistrationGroup,
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
    const [previousQuery, setPreviousQuery] = useState('');
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
        selectedCourses.some((course) => !initialSelectedKeys.has(courseKey(course)));
    const currentGroups = registration.courseGroups.filter(
        (group) => !previousCourseSources.has(group.key),
    );
    const previousGroups = registration.courseGroups
        .filter((group) => previousCourseSources.has(group.key))
        .map((group) => ({
            ...group,
            courses: filterCourses(group.courses, previousQuery),
        }))
        .filter((group) => group.courses.length > 0 || previousQuery === '');

    function toggleCourse(course: DashboardCourseRegistrationCourse) {
        if (isLocked || !course.selectable) {
            return;
        }

        setSelectedCourses((courses) =>
            courses.some((selected) => courseKey(selected) === courseKey(course))
                ? courses.filter(
                      (selected) => courseKey(selected) !== courseKey(course),
                  )
                : [...courses, course],
        );
    }

    function selectCurrentSetCourses() {
        if (isLocked) {
            return;
        }

        setSelectedCourses((courses) => {
            const currentSetCourses = registration.availableCourses.filter(
                (course) =>
                    course.source === 'current_set' && course.selectable,
            );
            const existingKeys = new Set(courses.map(courseKey));

            return [
                ...courses,
                ...currentSetCourses.filter(
                    (course) => !existingKeys.has(courseKey(course)),
                ),
            ];
        });
    }

    function clearSelection() {
        if (!isLocked) {
            setSelectedCourses([]);
        }
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
            <div className="grid gap-3 sm:grid-cols-3">
                <SummaryBlock
                    label="Maximum Units"
                    value={registration.maximumCourseUnits}
                    detail={`${selectedUnits} units selected`}
                />
                <SummaryBlock
                    label="Course Adviser"
                    value={registration.advisorName}
                    detail="Reviews submitted registration"
                    compact
                />
                <SummaryBlock
                    label="Status"
                    value={registration.reviewStatus}
                    detail="Current registration state"
                    compact
                />
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
                <Notice tone="danger" title="Some selected courses cannot be submitted yet">
                    <ul className="mt-1 list-inside list-disc">
                        {selectedBlockReasons.slice(0, 3).map((reason) => (
                            <li key={reason}>{reason}</li>
                        ))}
                    </ul>
                </Notice>
            ) : null}

            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardHeader className="flex flex-col gap-3 border-b border-border lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <CardTitle>
                            <QualifiedSemester
                                level={registration.currentSemester.level}
                                semester={registration.currentSemester.name}
                            />{' '}
                            Course Registration
                        </CardTitle>
                        <div className="mt-1 text-xs text-muted-foreground">
                            {registration.currentSemester.session} Session
                        </div>
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={isLocked}
                            onClick={selectCurrentSetCourses}
                        >
                            <RiAddLine />
                            Select Current Set Courses
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            disabled={isLocked || selectedCourses.length === 0}
                            onClick={clearSelection}
                        >
                            <RiCloseLine />
                            Clear Selection
                        </Button>
                    </div>
                </CardHeader>
                <CardContent className="p-0">
                    <SelectedCoursesTable
                        courses={selectedCourses}
                        isLocked={isLocked}
                        onRemove={toggleCourse}
                    />
                </CardContent>
                <CardFooter className="flex flex-col items-stretch justify-between gap-3 border-t border-border sm:flex-row sm:items-center">
                    <div className="text-xs text-muted-foreground">
                        <span className="font-semibold text-foreground tabular-nums">
                            {selectedUnits}
                        </span>{' '}
                        selected units
                    </div>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={
                                isLocked ||
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
                                    disabled={
                                        isLocked ||
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
                                        . You can make changes again if this is
                                        returned for correction.
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
                </CardFooter>
            </Card>

            <div className="grid gap-4">
                {currentGroups.map((group) => (
                    <CourseGroupCard
                        key={group.key}
                        group={group}
                        selectedKeys={selectedKeys}
                        isLocked={isLocked}
                        onToggleCourse={toggleCourse}
                    />
                ))}
            </div>

            {registration.courseGroups.some((group) =>
                previousCourseSources.has(group.key),
            ) ? (
                <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                    <CardHeader className="gap-3 border-b border-border">
                        <div className="grid gap-3 lg:grid-cols-[minmax(0,1fr)_18rem] lg:items-center">
                            <div>
                                <CardTitle>Previous Courses</CardTitle>
                                <div className="mt-1 text-xs text-muted-foreground">
                                    Search your previous required and failed
                                    courses, then add the ones you are taking
                                    now.
                                </div>
                            </div>
                            <div className="relative">
                                <RiSearchLine className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    value={previousQuery}
                                    onChange={(event) =>
                                        setPreviousQuery(event.target.value)
                                    }
                                    placeholder="Search previous courses"
                                    className="pl-9"
                                />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-4 p-4">
                        {previousGroups.length > 0 ? (
                            previousGroups.map((group) => (
                                <CourseGroupTable
                                    key={group.key}
                                    group={group}
                                    selectedKeys={selectedKeys}
                                    isLocked={isLocked}
                                    onToggleCourse={toggleCourse}
                                />
                            ))
                        ) : (
                            <div className="py-6 text-sm text-muted-foreground">
                                No previous courses match your search.
                            </div>
                        )}
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

function SelectedCoursesTable({
    courses,
    isLocked,
    onRemove,
}: {
    courses: DashboardCourseRegistrationCourse[];
    isLocked: boolean;
    onRemove: (course: DashboardCourseRegistrationCourse) => void;
}) {
    return (
        <Table className="text-sm">
            <TableHeader>
                <TableRow>
                    <TableHead className="w-[20%] px-4">Course</TableHead>
                    <TableHead>Title</TableHead>
                    <TableHead className="hidden w-[18%] md:table-cell">
                        Source
                    </TableHead>
                    <TableHead className="w-[10%] text-center">Units</TableHead>
                    <TableHead className="w-[10%] pr-4 text-right">
                        Action
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {courses.length > 0 ? (
                    courses.map((course) => (
                        <TableRow key={courseKey(course)} className="odd:bg-muted/25">
                            <TableCell className="px-4 font-semibold">
                                {course.code}
                            </TableCell>
                            <TableCell>
                                <div className="font-medium">
                                    {course.title}
                                </div>
                                <CourseWarnings course={course} />
                            </TableCell>
                            <TableCell className="hidden md:table-cell">
                                <SourceBadge course={course} />
                            </TableCell>
                            <TableCell className="text-center font-semibold tabular-nums">
                                {course.units}
                            </TableCell>
                            <TableCell className="pr-4 text-right">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    disabled={isLocked}
                                    onClick={() => onRemove(course)}
                                    aria-label={`Remove ${course.code}`}
                                >
                                    <RiCloseLine />
                                </Button>
                            </TableCell>
                        </TableRow>
                    ))
                ) : (
                    <TableRow>
                        <TableCell
                            colSpan={5}
                            className="px-4 py-8 text-muted-foreground"
                        >
                            No courses selected yet.
                        </TableCell>
                    </TableRow>
                )}
            </TableBody>
        </Table>
    );
}

function CourseGroupCard({
    group,
    isLocked,
    onToggleCourse,
    selectedKeys,
}: {
    group: DashboardCourseRegistrationGroup;
    isLocked: boolean;
    onToggleCourse: (course: DashboardCourseRegistrationCourse) => void;
    selectedKeys: Set<string>;
}) {
    return (
        <Card
            className={cn(
                'border bg-background shadow-sm ring-1',
                group.key === 'carryover'
                    ? 'border-red-200 ring-red-200/80 dark:border-red-500/30 dark:ring-red-500/20'
                    : 'border-border ring-border/70',
            )}
        >
            <CardHeader className="border-b border-border">
                <CardTitle>{group.title}</CardTitle>
                <div className="text-xs text-muted-foreground">
                    {group.detail}
                </div>
            </CardHeader>
            <CardContent className="p-0">
                <CourseOptionsTable
                    courses={group.courses}
                    selectedKeys={selectedKeys}
                    isLocked={isLocked}
                    onToggleCourse={onToggleCourse}
                />
            </CardContent>
        </Card>
    );
}

function CourseGroupTable({
    group,
    isLocked,
    onToggleCourse,
    selectedKeys,
}: {
    group: DashboardCourseRegistrationGroup;
    isLocked: boolean;
    onToggleCourse: (course: DashboardCourseRegistrationCourse) => void;
    selectedKeys: Set<string>;
}) {
    return (
        <div
            className={cn(
                'border bg-background',
                group.key === 'carryover'
                    ? 'border-red-200 dark:border-red-500/30'
                    : 'border-border',
            )}
        >
            <div className="border-b border-border px-4 py-3">
                <div className="font-semibold">{group.title}</div>
                <div className="mt-0.5 text-xs text-muted-foreground">
                    {group.detail}
                </div>
            </div>
            <CourseOptionsTable
                courses={group.courses}
                selectedKeys={selectedKeys}
                isLocked={isLocked}
                onToggleCourse={onToggleCourse}
            />
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
                    <TableHead className="w-12 px-4">Take</TableHead>
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
                {courses.map((course) => {
                    const selected = selectedKeys.has(courseKey(course));

                    return (
                        <TableRow key={courseKey(course)} className="odd:bg-muted/25">
                            <TableCell className="px-4">
                                <Checkbox
                                    checked={selected}
                                    disabled={
                                        isLocked || !course.selectable
                                    }
                                    aria-label={`Select ${course.code}`}
                                    onCheckedChange={() =>
                                        onToggleCourse(course)
                                    }
                                />
                            </TableCell>
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
                                    <CourseWarnings course={course} />
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
                })}
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
                    <TableRow key={courseKey(course)} className="odd:bg-muted/25">
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

function SummaryBlock({
    compact = false,
    detail,
    label,
    value,
}: {
    compact?: boolean;
    detail: string;
    label: string;
    value: string | number;
}) {
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
                <div
                    className={cn(
                        'truncate font-semibold tracking-normal tabular-nums',
                        compact ? 'text-lg' : 'text-2xl',
                    )}
                >
                    {value}
                </div>
                <div className="mt-1 text-xs text-muted-foreground">
                    {detail}
                </div>
            </CardContent>
        </Card>
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

function filterCourses(
    courses: DashboardCourseRegistrationCourse[],
    query: string,
): DashboardCourseRegistrationCourse[] {
    const normalized = query.trim().toLowerCase();

    if (!normalized) {
        return courses;
    }

    return courses.filter((course) =>
        [course.code, course.title, course.category, course.sourceLabel]
            .join(' ')
            .toLowerCase()
            .includes(normalized),
    );
}
