import { useForm } from '@inertiajs/react';
import {
    RiArrowLeftLine,
    RiCheckLine,
    RiFileWarningLine,
} from '@remixicon/react';
import { useState } from 'react';

import {
    approve as approveCourseRegistration,
    returnForCorrection as returnCourseRegistration,
} from '@/actions/App/Http/Controllers/CourseRegistrationController';
import { showStudentSetRegistrations } from '@/actions/App/Http/Controllers/DashboardController';
import { ActionLink } from '@/components/action-link';
import { CopyableValue } from '@/components/copyable-value';
import { CourseRegistrationCourseTable } from '@/components/course-registration-course-table';
import { CourseRegistrationStatusBadge } from '@/components/course-registration-status-badge';
import { PersonCell } from '@/components/person-cell';
import { QualifiedSemester } from '@/components/qualified-semester';
import { StudentPortrait } from '@/components/student-portrait';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { studentSetRouteParameters } from '@/lib/student-sets';
import type {
    DashboardAdviserCourseRegistration,
    DashboardAdviserCourseRegistrationListItem,
    DashboardCourseRegistrationReview,
} from '@/types';

type AdviserCourseRegistrationDashboardProps = {
    registration: DashboardAdviserCourseRegistration;
};

export function AdviserCourseRegistrationDashboard({
    registration,
}: AdviserCourseRegistrationDashboardProps) {
    return (
        <div className="mx-auto flex w-full max-w-5xl min-w-0 flex-col">
            {registration.review ? (
                <RegistrationReview review={registration.review} />
            ) : null}
        </div>
    );
}

export function RegistrationsTable({
    registrations,
    showStatus,
}: {
    registrations: DashboardAdviserCourseRegistrationListItem[];
    showStatus: boolean;
}) {
    return (
        <ScrollArea className="h-0 min-h-0 w-full flex-1">
            <Table
                className={
                    showStatus
                        ? 'min-w-[540px] text-sm'
                        : 'min-w-[300px] table-fixed text-sm'
                }
            >
                <TableHeader>
                    <TableRow>
                        <TableHead
                            className={
                                showStatus
                                    ? 'px-2 sm:px-4'
                                    : 'w-[60%] px-2 sm:px-4'
                            }
                        >
                            Student
                        </TableHead>
                        <TableHead
                            className={
                                showStatus
                                    ? 'text-center'
                                    : 'w-[15%] text-center'
                            }
                        >
                            Units
                        </TableHead>
                        {showStatus && <TableHead>Status</TableHead>}
                        <TableHead
                            className={
                                showStatus
                                    ? 'pr-2 text-right sm:pr-4'
                                    : 'w-[25%] pr-2 text-right sm:pr-4'
                            }
                        >
                            Action
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {registrations.length > 0 ? (
                        registrations.map((registration) => (
                            <TableRow
                                key={registration.id}
                                className="odd:bg-muted/25"
                            >
                                <TableCell className="px-2 sm:px-4">
                                    <PersonCell
                                        className="gap-2 sm:gap-3"
                                        name={registration.student.name}
                                        avatar={registration.student.avatar}
                                        secondary={`Reg No. ${registration.student.registrationNumber}`}
                                        href={registration.student.profileUrl}
                                    />
                                </TableCell>
                                <TableCell className="text-center font-semibold tabular-nums">
                                    {registration.totalUnits}
                                </TableCell>
                                {showStatus && (
                                    <TableCell>
                                        <CourseRegistrationStatusBadge
                                            status={registration.status}
                                        />
                                    </TableCell>
                                )}
                                <TableCell className="pr-2 text-right sm:pr-4">
                                    <ActionLink href={registration.reviewUrl}>
                                        {registration.status === 'In review'
                                            ? 'Review'
                                            : 'View'}
                                    </ActionLink>
                                </TableCell>
                            </TableRow>
                        ))
                    ) : (
                        <TableRow>
                            <TableCell
                                colSpan={showStatus ? 4 : 3}
                                className="px-4 py-8 text-muted-foreground"
                            >
                                No submitted registrations match this view.
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
            <ScrollBar orientation="horizontal" />
        </ScrollArea>
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
            approveCourseRegistration.url(review.routeParameters),
            { preserveScroll: true },
        );
    }

    function returnForCorrection() {
        returnForm.patch(returnCourseRegistration.url(review.routeParameters), {
            preserveScroll: true,
            onSuccess: () => setIsReturnOpen(false),
        });
    }

    return (
        <section className="min-w-0">
            <header className="border-b pb-5">
                {review.set ? (
                    <ActionLink
                        href={showStudentSetRegistrations(
                            studentSetRouteParameters(review.set),
                            {
                                query: {
                                    session:
                                        review.routeParameters.sessionYears,
                                    semester: review.routeParameters.semester,
                                },
                            },
                        )}
                        icon={<RiArrowLeftLine className="size-4" />}
                    >
                        Back to Registrations
                    </ActionLink>
                ) : null}
                <div className="mt-5 grid min-w-0 grid-cols-[auto_minmax(0,1fr)] items-center gap-x-4 gap-y-4 sm:gap-x-6 lg:grid-cols-[auto_minmax(0,1fr)_minmax(12rem,auto)]">
                    <StudentPortrait
                        name={review.student.name}
                        image={review.student.avatar}
                    />
                    <div className="min-w-0">
                        <h1 className="text-lg font-semibold break-words sm:text-xl">
                            {review.student.name}
                        </h1>
                        {review.set ? (
                            <p className="mt-1 text-sm break-words text-muted-foreground">
                                {review.set.name}
                            </p>
                        ) : null}
                        <div className="mt-3">
                            <CopyableValue
                                value={review.student.registrationNumber}
                            />
                        </div>
                        <div className="mt-2">
                            <ActionLink href={review.student.profileUrl}>
                                View Profile
                            </ActionLink>
                        </div>
                    </div>
                    <div className="col-span-2 border-t pt-4 lg:col-span-1 lg:border-t-0 lg:border-l lg:py-2 lg:pl-6">
                        <h2 className="text-base font-semibold">
                            <QualifiedSemester
                                level={review.semester.level}
                                semester={review.semester.name}
                            />
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {review.semester.session} Session
                        </p>
                        {review.status !== 'In review' ? (
                            <p className="mt-2 text-sm text-muted-foreground">
                                {review.status}
                            </p>
                        ) : null}
                    </div>
                </div>
            </header>

            {review.feedbackNote ? (
                <div className="border-b border-amber-200 bg-amber-50 py-4 text-sm text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
                    <div className="font-semibold">Returned feedback</div>
                    <div className="mt-1">{review.feedbackNote}</div>
                </div>
            ) : null}

            <div className="min-w-0 py-5">
                <CourseRegistrationCourseTable
                    courses={review.courses}
                    totalUnits={review.totalUnits}
                />
            </div>

            {isReturnOpen ? (
                <form
                    className="border-t border-border pt-5"
                    onSubmit={(event) => {
                        event.preventDefault();
                        returnForCorrection();
                    }}
                >
                    <Field
                        data-invalid={Boolean(returnForm.errors.feedback_note)}
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
                    <div className="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-end">
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
                </form>
            ) : (
                <footer className="flex justify-end border-t border-border pt-5">
                    <div className="flex flex-col gap-2 sm:flex-row sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={
                                !review.canReview || returnForm.processing
                            }
                            onClick={() => setIsReturnOpen(true)}
                        >
                            <RiFileWarningLine />
                            Return for Correction
                        </Button>
                        <Button
                            type="button"
                            disabled={
                                !review.canReview || approveForm.processing
                            }
                            onClick={approve}
                        >
                            <RiCheckLine />
                            {approveForm.processing
                                ? 'Approving...'
                                : 'Approve Registration'}
                        </Button>
                    </div>
                </footer>
            )}
        </section>
    );
}
