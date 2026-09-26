import { router } from '@inertiajs/react';
import {
    RiArrowDownSLine,
    RiSendPlaneLine,
    RiSave3Line,
} from '@remixicon/react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';

import { store as storeCourseRegistration } from '@/actions/App/Http/Controllers/CourseRegistrationController';
import { CourseRegistrationCourseTable } from '@/components/course-registration-course-table';
import { CourseRegistrationStatusBadge } from '@/components/course-registration-status-badge';
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
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { cn } from '@/lib/utils';
import type {
    DashboardCourseRegistrationCourse,
    DashboardCourseRegistrationSummary,
    DashboardStudentCourseRegistration,
} from '@/types';

type StudentCourseRegistrationDashboardProps = {
    registration: DashboardStudentCourseRegistration;
};

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
    const exceedsMaximumUnits =
        registration.available &&
        selectedUnits > registration.maximumCourseUnits;
    const initialSelectedKeys = useMemo(
        () => new Set(registration.selectedCourses.map(courseKey)),
        [registration.selectedCourses],
    );
    const isLocked = ['In review', 'Approved'].includes(
        registration.reviewStatus,
    );
    const isDirty =
        selectedCourses.length !== registration.selectedCourses.length ||
        selectedCourses.some(
            (course) => !initialSelectedKeys.has(courseKey(course)),
        );
    const displayCourses = isLocked
        ? selectedCourses
        : registration.courseGroups.flatMap((group) => group.courses);

    if (!registration.available || !registration.currentSemester) {
        return (
            <div className="mx-auto flex w-full max-w-6xl flex-col gap-4">
                <Card className="border border-border bg-background">
                    <CardContent className="py-10 text-center">
                        <div className="font-medium">
                            Course registration is closed
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {registration.unavailableReason}
                        </p>
                    </CardContent>
                </Card>
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

    const currentSemester = registration.currentSemester;

    function toggleCourse(course: DashboardCourseRegistrationCourse) {
        if (isLocked) {
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
            storeCourseRegistration().url,
            {
                action,
                items: selectedCourses.map((course) => ({
                    course_offering_id: course.courseOfferingId,
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
                            level={currentSemester.level}
                            semester={currentSemester.name}
                        />{' '}
                        Course Registration
                    </h1>
                    <div className="text-xs text-muted-foreground">
                        {currentSemester.session} Session
                    </div>
                </div>
                {!isLocked && (
                    <div className="flex flex-wrap items-center gap-3">
                        <span className="text-sm text-muted-foreground">
                            <span className="font-semibold text-foreground tabular-nums">
                                {selectedUnits}/
                                {registration.maximumCourseUnits}
                            </span>{' '}
                            units
                        </span>
                        <div className="flex gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                disabled={
                                    selectedCourses.length === 0 ||
                                    !isDirty ||
                                    exceedsMaximumUnits
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
                                            exceedsMaximumUnits
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

            {exceedsMaximumUnits ? (
                <Notice tone="danger">
                    Remove at least{' '}
                    {selectedUnits - registration.maximumCourseUnits}{' '}
                    {selectedUnits - registration.maximumCourseUnits === 1
                        ? 'unit'
                        : 'units'}{' '}
                    before saving or submitting this registration.
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
                        <CourseRegistrationStatusBadge
                            status={registration.reviewStatus}
                        />
                        <span className="text-sm text-muted-foreground">
                            <span className="font-semibold text-foreground tabular-nums">
                                {selectedUnits}/
                                {registration.maximumCourseUnits}
                            </span>{' '}
                            units
                        </span>
                    </div>
                </CardHeader>
                <CardContent className="p-0">
                    <CourseRegistrationCourseTable
                        courses={displayCourses}
                        selectedKeys={selectedKeys}
                        selectable={!isLocked}
                        onToggleCourse={isLocked ? undefined : toggleCourse}
                        emptyLabel={
                            isLocked
                                ? 'No courses in this registration.'
                                : 'No courses available.'
                        }
                    />
                </CardContent>
            </Card>

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
                                <CourseRegistrationStatusBadge
                                    status={registration.status}
                                />
                            </CardHeader>
                            <CardContent className="p-0">
                                <CourseRegistrationCourseTable
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

function courseKey(course: DashboardCourseRegistrationCourse): string {
    return course.courseOfferingId.toString();
}
