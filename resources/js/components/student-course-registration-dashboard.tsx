import { router } from '@inertiajs/react';
import { RiSave3Line, RiSendPlaneLine } from '@remixicon/react';
import { useMemo, useState } from 'react';

import { store as storeCourseRegistration } from '@/actions/App/Http/Controllers/CourseRegistrationController';
import { section as dashboardSection } from '@/actions/App/Http/Controllers/DashboardController';
import { CourseRegistrationCourseTable } from '@/components/course-registration-course-table';
import { CourseRegistrationStatusBadge } from '@/components/course-registration-status-badge';
import { QualifiedSemester } from '@/components/qualified-semester';
import { SetAcademicPeriodNavigation } from '@/components/set-academic-period-navigation';
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
import { ScrollArea } from '@/components/ui/scroll-area';
import type {
    DashboardCourseRegistrationCourse,
    DashboardStudentCourseRegistration,
} from '@/types';

export function StudentCourseRegistrationDashboard({
    registration,
}: {
    registration: DashboardStudentCourseRegistration;
}) {
    const [selectedCourses, setSelectedCourses] = useState(
        registration.selectedCourses,
    );
    const [validationError, setValidationError] = useState<string | null>(null);
    const [processing, setProcessing] = useState(false);
    const selectedKeys = useMemo(
        () =>
            new Set(
                selectedCourses.map((course) =>
                    String(course.courseOfferingId),
                ),
            ),
        [selectedCourses],
    );
    const originalKeys = useMemo(
        () =>
            new Set(
                registration.selectedCourses.map((course) =>
                    String(course.courseOfferingId),
                ),
            ),
        [registration.selectedCourses],
    );
    const offeredCourses = registration.courseGroups.flatMap(
        (group) => group.courses,
    );
    const offeredById = new Map(
        offeredCourses.map((course) => [course.courseOfferingId, course]),
    );
    const selectedUnits = selectedCourses.reduce(
        (sum, course) =>
            sum +
            (offeredById.get(course.courseOfferingId)?.units ?? course.units),
        0,
    );
    const overLimit = selectedUnits > registration.maximumCourseUnits;
    const isDirty =
        selectedCourses.length !== registration.selectedCourses.length ||
        selectedCourses.some(
            (course) => !originalKeys.has(String(course.courseOfferingId)),
        );
    const editable =
        registration.registrationOpen &&
        !['In review', 'Approved'].includes(registration.reviewStatus);
    const courses = editable
        ? [
              ...offeredCourses,
              ...selectedCourses.filter(
                  (course) => !offeredById.has(course.courseOfferingId),
              ),
          ]
        : selectedCourses;
    const hasIneligibleSelection =
        editable &&
        selectedCourses.some((course) => {
            const offered = offeredById.get(course.courseOfferingId);

            return !offered || offered.missingPrerequisites.length > 0;
        });
    const period = registration.currentSemester;

    function selectionIssue(
        course: DashboardCourseRegistrationCourse,
        selected: boolean,
    ): string | null {
        const offered = offeredById.get(course.courseOfferingId);

        if (!offered) {
            return 'No longer offered this semester. Remove this course.';
        }

        if (offered.missingPrerequisites.length > 0) {
            return `Pass ${offered.missingPrerequisites.join(', ')} before registering this course.`;
        }

        if (
            !selected &&
            selectedUnits + course.units > registration.maximumCourseUnits
        ) {
            return `Exceeds the ${registration.maximumCourseUnits}-unit limit.`;
        }

        return null;
    }

    function toggleCourse(course: DashboardCourseRegistrationCourse) {
        const selected = selectedKeys.has(String(course.courseOfferingId));

        if (!selected && selectionIssue(course, false)) {
            return;
        }

        setValidationError(null);
        setSelectedCourses((existing) =>
            existing.some(
                (item) => item.courseOfferingId === course.courseOfferingId,
            )
                ? existing.filter(
                      (item) =>
                          item.courseOfferingId !== course.courseOfferingId,
                  )
                : [...existing, course],
        );
    }

    function submit(action: 'save' | 'submit') {
        if (!period || processing) {
            return;
        }

        router.post(
            storeCourseRegistration().url,
            {
                action,
                session: period.sessionYears,
                semester: period.slug,
                items: selectedCourses.map((course) => ({
                    course_offering_id: course.courseOfferingId,
                })),
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: (errors) =>
                    setValidationError(
                        errors.items ??
                            errors.registration ??
                            'This registration could not be saved. Review your courses and try again.',
                    ),
                onSuccess: () => setValidationError(null),
            },
        );
    }

    return (
        <div className="mx-auto grid min-h-0 w-full max-w-6xl flex-1 grid-cols-1 grid-rows-[auto_minmax(0,1fr)] md:grid-cols-[13rem_minmax(0,1fr)] md:grid-rows-1">
            <div className="min-h-0 border-b border-border md:border-r md:border-b-0">
                <SetAcademicPeriodNavigation
                    sessions={registration.sessions}
                    selectedSemesterId={registration.selectedSemesterId}
                    ariaLabel="Course registration semesters"
                    buildHref={(session, semester) =>
                        dashboardSection.url(
                            { section: 'course-registration' },
                            {
                                query: {
                                    session,
                                    semester: semester ?? undefined,
                                },
                            },
                        )
                    }
                />
            </div>
            <div className="flex min-h-0 min-w-0 flex-col">
                <ScrollArea className="min-h-0 min-w-0 flex-1">
                    <div className="mx-auto w-full max-w-4xl px-0 py-5 md:px-5 md:py-6">
                        {period ? (
                            <>
                                <header className="flex flex-wrap items-start justify-between gap-3 border-b border-border px-3 pb-5 md:px-0">
                                    <div>
                                        <h1 className="text-lg font-semibold">
                                            <QualifiedSemester
                                                level={period.level}
                                                semester={period.name}
                                            />
                                        </h1>
                                        <p className="text-sm text-muted-foreground">
                                            {period.session} Session
                                        </p>
                                    </div>
                                    {registration.reviewStatus !==
                                    'Not submitted' ? (
                                        <CourseRegistrationStatusBadge
                                            status={registration.reviewStatus}
                                        />
                                    ) : null}
                                </header>

                                {registration.feedbackNote ? (
                                    <div className="mt-4 border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
                                        <strong className="block">
                                            Changes requested
                                        </strong>
                                        {registration.feedbackNote}
                                    </div>
                                ) : null}

                                <div className="mt-5 border border-border">
                                    <CourseRegistrationCourseTable
                                        courses={courses}
                                        selectedKeys={selectedKeys}
                                        selectable={editable}
                                        onToggleCourse={
                                            editable ? toggleCourse : undefined
                                        }
                                        selectionIssue={
                                            editable
                                                ? selectionIssue
                                                : undefined
                                        }
                                        totalUnits={
                                            editable ? undefined : selectedUnits
                                        }
                                        emptyLabel={
                                            !registration.hasAvailableCourses
                                                ? 'Your course adviser has not assigned any courses for this semester yet.'
                                                : 'You have not selected any courses for this semester yet.'
                                        }
                                    />
                                </div>
                            </>
                        ) : (
                            <div className="px-4 py-12 text-center text-sm text-muted-foreground">
                                {registration.sessions.length
                                    ? 'Choose a semester to see its course registration.'
                                    : 'Your course adviser has not added any sessions yet.'}
                            </div>
                        )}
                    </div>
                </ScrollArea>
                {period &&
                editable &&
                (registration.hasAvailableCourses ||
                    selectedCourses.length > 0) ? (
                    <div className="border-t border-border px-3 py-3 md:px-5">
                        <div className="mx-auto flex max-w-4xl flex-wrap items-center justify-between gap-3">
                            <div aria-live="polite" className="min-w-0 text-sm">
                                <span
                                    className={
                                        overLimit
                                            ? 'font-medium text-destructive'
                                            : 'font-medium tabular-nums'
                                    }
                                >
                                    {selectedUnits} /{' '}
                                    {registration.maximumCourseUnits} units
                                </span>
                                {overLimit ? (
                                    <span className="ml-2 text-destructive">
                                        Remove a course to stay within the
                                        limit.
                                    </span>
                                ) : null}
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={
                                        !isDirty ||
                                        overLimit ||
                                        hasIneligibleSelection ||
                                        processing
                                    }
                                    onClick={() => submit('save')}
                                >
                                    <RiSave3Line /> Save Draft
                                </Button>
                                <AlertDialog>
                                    <AlertDialogTrigger asChild>
                                        <Button
                                            size="sm"
                                            disabled={
                                                selectedCourses.length === 0 ||
                                                overLimit ||
                                                hasIneligibleSelection ||
                                                processing
                                            }
                                        >
                                            <RiSendPlaneLine /> Submit for
                                            Review
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>
                                                Submit {period.level},{' '}
                                                {period.name}?
                                            </AlertDialogTitle>
                                            <AlertDialogDescription>
                                                Your course adviser will review
                                                the {selectedUnits} units
                                                selected for {period.session}{' '}
                                                Session.
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel>
                                                Keep Editing
                                            </AlertDialogCancel>
                                            <AlertDialogAction
                                                onClick={() => submit('submit')}
                                            >
                                                Submit for Review
                                            </AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>
                            </div>
                        </div>
                        {validationError ? (
                            <p
                                role="alert"
                                className="mx-auto mt-2 max-w-4xl text-sm text-destructive"
                            >
                                {validationError}
                            </p>
                        ) : null}
                    </div>
                ) : null}
            </div>
        </div>
    );
}
