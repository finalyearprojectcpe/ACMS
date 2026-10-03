import { Link, router, usePage } from '@inertiajs/react';
import {
    RiArrowLeftLine,
    RiArrowRightLine,
    RiSave3Line,
    RiSearchLine,
} from '@remixicon/react';
import { useState } from 'react';

import { store as saveAssignment } from '@/actions/App/Http/Controllers/CourseRegistrationController';
import {
    showCourseSchedule,
    showStudentProfile,
} from '@/actions/App/Http/Controllers/DashboardController';
import { CopyableValue } from '@/components/copyable-value';
import { CourseRegistrationCourseTable } from '@/components/course-registration-course-table';
import { StudentPortrait } from '@/components/student-portrait';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Spinner } from '@/components/ui/spinner';
import { studentSetRouteParameters } from '@/lib/student-sets';
import { dashboard as adviserHome } from '@/routes';
import type {
    DashboardAdviserCourseAssignment,
    DashboardCourseRegistrationCourse,
} from '@/types';

export function AdviserCourseAssignmentDashboard({
    assignment,
}: {
    assignment: DashboardAdviserCourseAssignment;
}) {
    const { url } = usePage();
    const [selected, setSelected] = useState(assignment.selectedCourses);
    const [step, setStep] = useState<'select' | 'review'>('select');
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [search, setSearch] = useState('');
    const available = assignment.courseGroups.flatMap((group) => group.courses);
    const availableById = new Map(
        available.map((course) => [course.courseOfferingId, course]),
    );
    const selectedKeys = new Set(
        selected.map((course) => String(course.courseOfferingId)),
    );
    const totalUnits = selected.reduce(
        (sum, course) =>
            sum +
            (availableById.get(course.courseOfferingId)?.units ?? course.units),
        0,
    );
    const courses = [
        ...available,
        ...selected.filter(
            (course) => !availableById.has(course.courseOfferingId),
        ),
    ];
    const visibleCourses = courses.filter((course) =>
        `${course.code} ${course.title}`
            .toLowerCase()
            .includes(search.trim().toLowerCase()),
    );
    const params = studentSetRouteParameters(assignment.set);
    const origin = new URLSearchParams(url.split('?')[1] ?? '').get('from');
    const backUrl =
        origin === 'home'
            ? adviserHome()
            : origin === 'profile'
              ? showStudentProfile(assignment.student.registrationNumber)
              : showCourseSchedule(params, {
                    query: {
                        session: assignment.period.sessionYears,
                        semester: assignment.period.slug,
                    },
                });
    const savedKeys = new Set(
        assignment.selectedCourses.map((course) => course.courseOfferingId),
    );
    const changed =
        savedKeys.size !== selected.length ||
        selected.some((course) => !savedKeys.has(course.courseOfferingId));
    const removedCourses = assignment.selectedCourses.filter(
        (course) => !selectedKeys.has(String(course.courseOfferingId)),
    );
    const reviewCourses = [...selected].sort((a, b) =>
        a.code.localeCompare(b.code),
    );

    function issue(
        course: DashboardCourseRegistrationCourse,
        isSelected: boolean,
    ): string | null {
        const offered = availableById.get(course.courseOfferingId);

        if (!offered) {
            return 'This course is no longer offered in this semester.';
        }

        if (offered.missingPrerequisites.length) {
            return `Passing results required in ${offered.missingPrerequisites.join(', ')}.`;
        }

        if (
            !isSelected &&
            totalUnits + offered.units > assignment.maximumUnits
        ) {
            return `Exceeds the ${assignment.maximumUnits}-unit limit.`;
        }

        return null;
    }

    function toggle(course: DashboardCourseRegistrationCourse) {
        if (
            !selectedKeys.has(String(course.courseOfferingId)) &&
            issue(course, false)
        ) {
            return;
        }

        setError(null);
        setSelected((current) =>
            current.some(
                (item) => item.courseOfferingId === course.courseOfferingId,
            )
                ? current.filter(
                      (item) =>
                          item.courseOfferingId !== course.courseOfferingId,
                  )
                : [...current, course],
        );
    }

    function save(action: 'draft' | 'assign') {
        if (processing) {
            return;
        }

        router.patch(
            saveAssignment.url({
                ...params,
                sessionYears: assignment.period.sessionYears,
                semester: assignment.period.slug,
                registrationNumber: assignment.student.registrationNumber,
            }),
            {
                action,
                ...(origin === 'home' || origin === 'profile'
                    ? { from: origin }
                    : {}),
                items: selected.map((course) => ({
                    course_offering_id: course.courseOfferingId,
                })),
            },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: (errors) =>
                    setError(
                        errors.items ??
                            errors.action ??
                            'Courses could not be saved.',
                    ),
            },
        );
    }

    const canReview =
        selected.length > 0 &&
        totalUnits <= assignment.maximumUnits &&
        selected.every((course) => issue(course, true) === null);

    return (
        <div className="mx-auto flex min-h-0 w-full max-w-5xl flex-1 flex-col">
            <header className="flex shrink-0 flex-wrap items-center gap-5 border-b pb-5">
                <StudentPortrait
                    name={assignment.student.name}
                    image={assignment.student.avatar}
                />
                <div className="min-w-0 flex-1">
                    <h1 className="text-xl font-semibold">
                        {assignment.student.name}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {assignment.period.level} · {assignment.period.semester}{' '}
                        · {assignment.period.session} Session
                    </p>
                    <div className="mt-3 w-fit">
                        <CopyableValue
                            value={assignment.student.registrationNumber}
                        />
                    </div>
                </div>
            </header>
            <ScrollArea className="min-h-0 flex-1">
                <div className="py-5">
                    <div className="mb-4 flex items-baseline justify-between gap-3">
                        <h2 className="text-base font-semibold">
                            {step === 'review'
                                ? 'Review Courses'
                                : 'Choose Courses'}
                        </h2>
                        {step === 'review' ? (
                            <span className="text-sm text-muted-foreground tabular-nums">
                                {selected.length}{' '}
                                {selected.length === 1 ? 'course' : 'courses'}
                            </span>
                        ) : null}
                    </div>
                    {step === 'select' && courses.length > 10 && (
                        <div className="relative mb-4 w-full max-w-xs">
                            <RiSearchLine className="absolute top-1/2 left-2 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                className="pl-8"
                                aria-label="Find a course"
                                placeholder="Find a course"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                            />
                        </div>
                    )}
                    {step === 'select' ? (
                        <CourseRegistrationCourseTable
                            courses={visibleCourses}
                            selectedKeys={selectedKeys}
                            selectable
                            onToggleCourse={toggle}
                            selectionIssue={issue}
                            emptyLabel={
                                search
                                    ? 'No matching courses.'
                                    : 'No courses are available for this semester.'
                            }
                        />
                    ) : (
                        <>
                            <CourseRegistrationCourseTable
                                courses={reviewCourses}
                                totalUnits={totalUnits}
                            />
                            {removedCourses.length > 0 ? (
                                <p className="mt-4 text-sm text-muted-foreground">
                                    Removing{' '}
                                    {removedCourses
                                        .map((course) => course.code)
                                        .join(', ')}{' '}
                                    from the existing assignment.
                                </p>
                            ) : null}
                        </>
                    )}
                </div>
            </ScrollArea>
            <footer className="flex shrink-0 flex-wrap items-center justify-between gap-3 border-t pt-4">
                <div className="text-sm">
                    {error ? (
                        <span role="alert" className="text-destructive">
                            {error}
                        </span>
                    ) : step === 'select' ? (
                        <span className="text-muted-foreground tabular-nums">
                            {selected.length}{' '}
                            {selected.length === 1 ? 'course' : 'courses'} ·{' '}
                            <strong
                                className={
                                    totalUnits > assignment.maximumUnits
                                        ? 'text-destructive'
                                        : 'text-foreground'
                                }
                            >
                                {totalUnits} / {assignment.maximumUnits} units
                            </strong>
                        </span>
                    ) : null}
                </div>
                <div className="ml-auto flex flex-wrap gap-2">
                    {step === 'select' ? (
                        <Button variant="outline" asChild>
                            <Link href={backUrl}>Cancel</Link>
                        </Button>
                    ) : (
                        <Button
                            variant="outline"
                            disabled={processing}
                            onClick={() => {
                                setError(null);
                                setStep('select');
                            }}
                        >
                            <RiArrowLeftLine /> Edit Selection
                        </Button>
                    )}
                    {step === 'select' ? (
                        <>
                            {assignment.status !== 'assigned' ? (
                                <Button
                                    variant="outline"
                                    disabled={processing || !canReview}
                                    onClick={() => save('draft')}
                                >
                                    {processing ? <Spinner /> : <RiSave3Line />}{' '}
                                    Save Draft
                                </Button>
                            ) : null}
                            <Button
                                disabled={
                                    processing ||
                                    !canReview ||
                                    (assignment.status === 'assigned' &&
                                        !changed)
                                }
                                onClick={() => {
                                    setError(null);
                                    setStep('review');
                                }}
                            >
                                Review Courses <RiArrowRightLine />
                            </Button>
                        </>
                    ) : (
                        <Button
                            disabled={processing || !canReview}
                            onClick={() => save('assign')}
                        >
                            {processing ? <Spinner /> : <RiSave3Line />}
                            {assignment.status === 'assigned'
                                ? 'Confirm Changes'
                                : 'Confirm Assignment'}
                        </Button>
                    )}
                </div>
            </footer>
        </div>
    );
}
