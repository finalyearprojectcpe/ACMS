import { Link, useForm } from '@inertiajs/react';
import { RiCheckLine, RiFileWarningLine, RiSearchLine } from '@remixicon/react';
import { useMemo, useState } from 'react';

import {
    approveCourseRegistration,
    returnCourseRegistration,
} from '@/actions/App/Http/Controllers/DashboardController';
import { ActionLink } from '@/components/action-link';
import { CopyableValue } from '@/components/copyable-value';
import { PersonCell } from '@/components/person-cell';
import { QualifiedSemester } from '@/components/qualified-semester';
import { StudentSetChoiceLabel } from '@/components/student-set-label';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardForm,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import type {
    DashboardCourseRegistrationCourse,
    DashboardCourseRegistrationReview,
    DashboardStaffCourseRegistration,
    DashboardStaffCourseRegistrationListItem,
} from '@/types';

type StaffCourseRegistrationDashboardProps = {
    registration: DashboardStaffCourseRegistration;
};

export function StaffCourseRegistrationDashboard({
    registration,
}: StaffCourseRegistrationDashboardProps) {
    return (
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-4">
            {registration.review ? (
                <RegistrationReview review={registration.review} />
            ) : (
                <RegistrationIndex registration={registration} />
            )}
        </div>
    );
}

function RegistrationIndex({
    registration,
}: {
    registration: DashboardStaffCourseRegistration;
}) {
    const [query, setQuery] = useState('');
    const filteredRegistrations = useMemo(() => {
        const normalized = query.trim().toLowerCase();

        if (!normalized) {
            return registration.registrations;
        }

        return registration.registrations.filter((item) =>
            [
                item.student.name,
                item.student.registrationNumber,
                item.status,
                item.semester.level,
                item.semester.name,
                item.semester.session,
            ]
                .join(' ')
                .toLowerCase()
                .includes(normalized),
        );
    }, [query, registration.registrations]);

    return (
        <>
            <div className="flex flex-col gap-2">
                <h1 className="text-xl font-semibold tracking-normal">
                    Course Registrations
                </h1>
                <p className="max-w-2xl text-sm text-muted-foreground">
                    Review course registrations submitted by students in your
                    assigned sets.
                </p>
            </div>

            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardHeader className="gap-3 border-b border-border">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <CardTitle>Submitted Registrations</CardTitle>
                        <div className="relative w-full sm:w-72">
                            <RiSearchLine className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                                placeholder="Search registrations"
                                className="pl-9"
                            />
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="p-0">
                    <RegistrationsTable registrations={filteredRegistrations} />
                </CardContent>
            </Card>
        </>
    );
}

function RegistrationsTable({
    registrations,
}: {
    registrations: DashboardStaffCourseRegistrationListItem[];
}) {
    return (
        <Table className="text-sm">
            <TableHeader>
                <TableRow>
                    <TableHead className="px-4">Student</TableHead>
                    <TableHead>Semester</TableHead>
                    <TableHead className="text-center">Units</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="pr-4 text-right">Action</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {registrations.length > 0 ? (
                    registrations.map((registration) => (
                        <TableRow
                            key={registration.id}
                            className="odd:bg-muted/25"
                        >
                            <TableCell className="px-4">
                                <PersonCell
                                    name={registration.student.name}
                                    avatar={registration.student.avatar}
                                    secondary={`Reg No. ${registration.student.registrationNumber}`}
                                />
                            </TableCell>
                            <TableCell>
                                <div className="font-medium">
                                    <QualifiedSemester
                                        level={registration.semester.level}
                                        semester={registration.semester.name}
                                    />
                                </div>
                                <div className="text-xs text-muted-foreground">
                                    {registration.semester.session} Session
                                </div>
                            </TableCell>
                            <TableCell className="text-center font-semibold tabular-nums">
                                {registration.totalUnits}
                            </TableCell>
                            <TableCell>
                                <StatusBadge status={registration.status} />
                            </TableCell>
                            <TableCell className="pr-4 text-right">
                                <ActionLink href={registration.reviewUrl}>
                                    Review
                                </ActionLink>
                            </TableCell>
                        </TableRow>
                    ))
                ) : (
                    <TableRow>
                        <TableCell
                            colSpan={5}
                            className="px-4 py-8 text-muted-foreground"
                        >
                            No submitted registrations match this view.
                        </TableCell>
                    </TableRow>
                )}
            </TableBody>
        </Table>
    );
}

function RegistrationReview({
    review,
}: {
    review: DashboardCourseRegistrationReview;
}) {
    const [isReturnOpen, setIsReturnOpen] = useState(false);
    const approveForm = useForm({});
    const returnForm = useForm({
        feedback_note: review.feedbackNote ?? '',
    });

    function approve() {
        approveForm.patch(
            approveCourseRegistration.url({ courseRegistration: review.id }),
            { preserveScroll: true },
        );
    }

    function returnForCorrection() {
        returnForm.patch(
            returnCourseRegistration.url({ courseRegistration: review.id }),
            {
                preserveScroll: true,
                onSuccess: () => setIsReturnOpen(false),
            },
        );
    }

    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardHeader className="border-b border-border">
                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto] lg:items-center">
                    <div className="min-w-0">
                        <PersonCell
                            name={review.student.name}
                            avatar={review.student.avatar}
                            secondary={`${review.department.abbreviation} · CGPA ${review.student.cgpa}`}
                        />
                        <div className="mt-3 flex flex-wrap items-center gap-2">
                            <CopyableValue
                                value={review.student.registrationNumber}
                                className="h-8"
                                valueClassName="text-xs"
                            />
                            {review.set ? (
                                <div className="min-w-64 max-w-full">
                                    <StudentSetChoiceLabel
                                        entrySession={review.set.entrySession}
                                        currentLevel={review.set.currentLevel}
                                        minimumYearsToDegree={
                                            review.set.minimumYearsToDegree
                                        }
                                    />
                                </div>
                            ) : null}
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2 lg:justify-end">
                        <StatusBadge status={review.status} />
                        <Badge
                            variant="outline"
                            className="border-border bg-muted/60 text-foreground"
                        >
                            {review.totalUnits} units
                        </Badge>
                        <Link
                            href={review.student.profileUrl}
                            className="text-sm font-semibold text-link underline-offset-4 hover:underline"
                        >
                            View Profile
                        </Link>
                    </div>
                </div>
            </CardHeader>

            {review.feedbackNote ? (
                <div className="border-b border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
                    <div className="font-semibold">Returned feedback</div>
                    <div className="mt-1">{review.feedbackNote}</div>
                </div>
            ) : null}

            <CardContent className="p-0">
                <div className="flex flex-col gap-1 border-b border-border bg-muted/20 px-4 py-3">
                    <CardTitle>
                        <QualifiedSemester
                            level={review.semester.level}
                            semester={review.semester.name}
                        />{' '}
                        Registration Review
                    </CardTitle>
                    <div className="text-xs text-muted-foreground">
                        {review.semester.session} Session
                    </div>
                </div>
                <ReviewCourseGroups courses={review.courses} />
            </CardContent>

            <CardFooter className="grid gap-3 border-t border-border sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
                <div className="text-xs text-muted-foreground">
                    <span className="font-semibold text-foreground tabular-nums">
                        {review.totalUnits}
                    </span>{' '}
                    total units
                    {review.carryoverUnits > 0 ? (
                        <>
                            {' '}
                            ·{' '}
                            <span className="font-semibold text-red-700 tabular-nums dark:text-red-300">
                                {review.carryoverUnits}
                            </span>{' '}
                            carryover units
                        </>
                    ) : null}
                </div>
                <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={!review.canReview || returnForm.processing}
                        onClick={() => setIsReturnOpen((open) => !open)}
                    >
                        <RiFileWarningLine />
                        Return for Correction
                    </Button>
                    <Button
                        type="button"
                        disabled={!review.canReview || approveForm.processing}
                        onClick={approve}
                    >
                        <RiCheckLine />
                        {approveForm.processing
                            ? 'Approving...'
                            : 'Approve Registration'}
                    </Button>
                </div>
            </CardFooter>

            {isReturnOpen ? (
                <div className="border-t border-border bg-muted/20 p-4">
                    <CardForm
                        className="gap-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            returnForCorrection();
                        }}
                    >
                        <Field
                            data-invalid={Boolean(
                                returnForm.errors.feedback_note,
                            )}
                        >
                            <FieldLabel htmlFor="feedback-note">
                                Feedback for student
                            </FieldLabel>
                            <Textarea
                                id="feedback-note"
                                value={returnForm.data.feedback_note}
                                onChange={(event) =>
                                    returnForm.setData(
                                        'feedback_note',
                                        event.target.value,
                                    )
                                }
                                placeholder="Tell the student what to correct."
                                disabled={!review.canReview}
                            />
                            <FieldError>
                                {returnForm.errors.feedback_note}
                            </FieldError>
                        </Field>
                        <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={returnForm.processing}
                                onClick={() => setIsReturnOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={
                                    !review.canReview || returnForm.processing
                                }
                            >
                                <RiFileWarningLine />
                                {returnForm.processing
                                    ? 'Returning...'
                                    : 'Return for Correction'}
                            </Button>
                        </div>
                    </CardForm>
                </div>
            ) : null}
        </Card>
    );
}

function ReviewCourseGroups({
    courses,
}: {
    courses: DashboardCourseRegistrationCourse[];
}) {
    const groups = groupCoursesBySource(courses);

    return (
        <div className="grid gap-4 p-4">
            {groups.map((group) => (
                <div
                    key={group.source}
                    className={cn(
                        'border bg-background',
                        group.source === 'carryover'
                            ? 'border-red-200 dark:border-red-500/30'
                            : 'border-border',
                    )}
                >
                    <div className="border-b border-border px-4 py-3">
                        <div className="font-semibold">{group.label}</div>
                    </div>
                    <ReviewCoursesTable courses={group.courses} />
                </div>
            ))}
        </div>
    );
}

function ReviewCoursesTable({
    courses,
}: {
    courses: DashboardCourseRegistrationCourse[];
}) {
    return (
        <Table className="text-sm">
            <TableHeader>
                <TableRow>
                    <TableHead className="w-[16%] px-4">Course</TableHead>
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
                {courses.map((course) => (
                    <TableRow
                        key={`${course.source}-${course.courseOfferingId}`}
                        className="odd:bg-muted/25"
                    >
                        <TableCell className="px-4 font-semibold">
                            {course.code}
                        </TableCell>
                        <TableCell>
                            <div className="font-medium">{course.title}</div>
                            <div className="text-xs text-muted-foreground md:hidden">
                                {course.category}
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
                ))}
            </TableBody>
        </Table>
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

function groupCoursesBySource(courses: DashboardCourseRegistrationCourse[]) {
    const labels: Record<string, string> = {
        current_set: 'Current Set Courses',
        elective: 'Elective Courses',
        carryover: 'Carryover Courses',
        outstanding_requirement: 'Outstanding Previous Requirements',
    };
    const order = [
        'current_set',
        'elective',
        'carryover',
        'outstanding_requirement',
    ];

    return order
        .map((source) => ({
            source,
            label: labels[source] ?? 'Courses',
            courses: courses.filter((course) => course.source === source),
        }))
        .filter((group) => group.courses.length > 0);
}

function StatusBadge({ status }: { status: string }) {
    return (
        <Badge variant="outline" className={statusBadgeClass(status)}>
            {status}
        </Badge>
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
        }[status] ?? 'border-border bg-secondary text-secondary-foreground'
    );
}
