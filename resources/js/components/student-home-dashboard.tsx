import { Link } from '@inertiajs/react';

import { section as dashboardSection } from '@/actions/App/Http/Controllers/DashboardController';
import { CopyableValue } from '@/components/copyable-value';
import { CourseRegistrationStatusBadge } from '@/components/course-registration-status-badge';
import { StudentPortrait } from '@/components/student-portrait';
import { TableEmptyRow } from '@/components/table-empty-row';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { DashboardStudentHome } from '@/types';

export function StudentHomeDashboard({ home }: { home: DashboardStudentHome }) {
    const period = home.currentSemester;

    return (
        <div className="mx-auto flex w-full max-w-5xl flex-col gap-8 py-2">
            <header className="grid gap-5 border-b border-border pb-7 sm:grid-cols-[auto_minmax(0,1fr)] sm:gap-7">
                <StudentPortrait
                    name={home.student.name}
                    image={home.student.avatar}
                />
                <div className="min-w-0">
                    <h1 className="text-xl font-semibold break-words sm:text-2xl">
                        {home.student.name}
                    </h1>
                    <dl className="mt-5 grid gap-x-8 gap-y-4 text-sm sm:grid-cols-2 lg:grid-cols-3">
                        <IdentityField label="Registration Number">
                            <CopyableValue
                                value={home.student.registrationNumber}
                            />
                        </IdentityField>
                        <IdentityField label="Department">
                            {home.student.department}
                        </IdentityField>
                        <IdentityField label="Current Period">
                            {period
                                ? `${period.level}, ${period.name}`
                                : 'Not selected'}
                        </IdentityField>
                        <IdentityField label="Entry Set">
                            {home.student.entrySet}
                        </IdentityField>
                        <IdentityField label="CGPA">
                            <span className="font-semibold tabular-nums">
                                {home.cgpa?.toFixed(2) ?? '—'}
                            </span>
                        </IdentityField>
                        <IdentityField label="Course Adviser">
                            {home.courseAdvisor}
                        </IdentityField>
                    </dl>
                </div>
            </header>

            <section>
                <div className="mb-3 flex flex-wrap items-baseline justify-between gap-2">
                    <h2 className="text-base font-semibold">
                        Current Course Load
                    </h2>
                    {period ? (
                        <div className="flex items-center gap-2">
                            <span className="text-sm text-muted-foreground">
                                {period.session} Session
                            </span>
                            {home.courses.length > 0 &&
                            home.registrationStatus ? (
                                <CourseRegistrationStatusBadge
                                    status={home.registrationStatus}
                                />
                            ) : null}
                        </div>
                    ) : null}
                </div>
                <div className="border border-border">
                    <Table className="min-w-[440px] text-sm">
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-28 px-4">
                                    Course
                                </TableHead>
                                <TableHead>Title</TableHead>
                                <TableHead className="w-20 pr-4 text-right">
                                    Units
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {home.courses.length ? (
                                home.courses.map((course) => (
                                    <TableRow key={course.code}>
                                        <TableCell className="px-4 font-semibold">
                                            {course.code}
                                        </TableCell>
                                        <TableCell className="whitespace-normal">
                                            {course.title}
                                        </TableCell>
                                        <TableCell className="pr-4 text-right tabular-nums">
                                            {course.units}
                                        </TableCell>
                                    </TableRow>
                                ))
                            ) : (
                                <TableEmptyRow colSpan={3} className="py-0">
                                    <div className="flex min-h-36 flex-col items-center justify-center gap-3 text-center">
                                        <span>
                                            {!period
                                                ? 'No current semester has been selected.'
                                                : !home.hasAvailableCourses
                                                  ? 'Your course adviser has not assigned any courses for this semester yet.'
                                                  : home.registrationStatus ===
                                                      'Draft'
                                                    ? 'Your course registration is still a draft.'
                                                    : home.registrationStatus ===
                                                        'Changes requested'
                                                      ? 'Your course registration needs changes.'
                                                      : 'You have not selected any courses for this semester yet.'}
                                        </span>
                                        {period &&
                                        home.registrationOpen &&
                                        home.hasAvailableCourses ? (
                                            <Button asChild size="sm">
                                                <Link
                                                    href={dashboardSection({
                                                        section:
                                                            'course-registration',
                                                    })}
                                                >
                                                    {home.registrationStatus ===
                                                        'Draft' ||
                                                    home.registrationStatus ===
                                                        'Changes requested'
                                                        ? 'Continue registration'
                                                        : 'Register courses'}
                                                </Link>
                                            </Button>
                                        ) : null}
                                    </div>
                                </TableEmptyRow>
                            )}
                        </TableBody>
                        {home.courses.length > 0 ? (
                            <TableFooter>
                                <TableRow>
                                    <TableCell
                                        colSpan={2}
                                        className="px-4 text-right font-semibold"
                                    >
                                        Total Units
                                    </TableCell>
                                    <TableCell className="pr-4 text-right font-semibold tabular-nums">
                                        {home.totalCourseUnits}
                                    </TableCell>
                                </TableRow>
                            </TableFooter>
                        ) : null}
                    </Table>
                </div>
            </section>

            <section>
                <h2 className="mb-3 text-base font-semibold">Unpaid Dues</h2>
                <div className="border border-border">
                    <Table className="min-w-[420px] text-sm">
                        <TableHeader>
                            <TableRow>
                                <TableHead className="px-4">Due</TableHead>
                                <TableHead className="w-32 text-right">
                                    Amount
                                </TableHead>
                                <TableHead className="w-28 pr-4 text-right">
                                    Action
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {home.pendingDues.length ? (
                                home.pendingDues.map((due) => (
                                    <TableRow key={due.id}>
                                        <TableCell className="px-4 font-medium whitespace-normal">
                                            {due.name}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {due.amount}
                                        </TableCell>
                                        <TableCell className="pr-4 text-right">
                                            {due.paymentUrl ? (
                                                <Link
                                                    href={due.paymentUrl}
                                                    method="post"
                                                    as="button"
                                                    className="font-medium text-link hover:underline"
                                                >
                                                    {due.paymentActionLabel}
                                                </Link>
                                            ) : (
                                                <span
                                                    className="text-muted-foreground"
                                                    title={
                                                        due.paymentUnavailableReason ??
                                                        undefined
                                                    }
                                                >
                                                    Unavailable
                                                </span>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))
                            ) : (
                                <TableEmptyRow colSpan={3}>
                                    No unpaid dues.
                                </TableEmptyRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
            </section>
        </div>
    );
}

function IdentityField({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="min-w-0">
            <dt className="mb-1 text-xs text-muted-foreground">{label}</dt>
            <dd className="break-words">{children}</dd>
        </div>
    );
}
