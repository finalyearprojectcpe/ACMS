import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { section as dashboardSection } from '@/actions/App/Http/Controllers/DashboardController';
import {
    QualifiedSemester,
    qualifiedSemesterLabel,
} from '@/components/qualified-semester';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    DashboardStudentHome,
    DashboardStudentHomeCourse,
    DashboardStudentHomeDue,
} from '@/types';

type StudentHomeDashboardProps = {
    home: DashboardStudentHome;
};

export function StudentHomeDashboard({ home }: StudentHomeDashboardProps) {
    const semesterLabel = home.currentSemester
        ? qualifiedSemesterLabel(
              home.currentSemester.level,
              home.currentSemester.name,
          )
        : null;

    return (
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-4">
            <SolidCard>
                <CardHeader className="flex flex-row items-center justify-between gap-3 border-b border-border">
                    <CardTitle>
                        {home.currentSemester ? (
                            <>
                                <QualifiedSemester
                                    level={home.currentSemester.level}
                                    semester={home.currentSemester.name}
                                />{' '}
                                Course Load
                            </>
                        ) : (
                            'Course Load'
                        )}
                    </CardTitle>
                </CardHeader>
                <CardContent className="p-0">
                    <CourseLoadTable courses={home.courses} />
                </CardContent>
            </SolidCard>

            <div className="grid gap-3 md:grid-cols-2">
                <MetricBlock
                    label="Total Units"
                    value={home.totalCourseUnits}
                    detail={
                        semesterLabel
                            ? `For ${semesterLabel}`
                            : 'No active registration period'
                    }
                />
                <MetricBlock
                    label="Course Adviser"
                    value={home.courseAdvisor}
                    detail="Reviews course registration"
                    valueClassName="text-lg"
                />
            </div>

            <SolidCard>
                <CardHeader className="flex flex-row items-center justify-between gap-3 border-b border-border">
                    <CardTitle>Pending Dues</CardTitle>
                    <Link
                        href={dashboardSection({ section: 'dues' })}
                        className="text-xs font-medium text-link"
                    >
                        View dues
                    </Link>
                </CardHeader>
                <CardContent className="p-0">
                    <DueList dues={home.pendingDues} />
                </CardContent>
            </SolidCard>
        </div>
    );
}

function SolidCard({ children }: { children: ReactNode }) {
    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            {children}
        </Card>
    );
}

function MetricBlock({
    label,
    value,
    detail,
    valueClassName,
}: {
    label: string;
    value: ReactNode;
    detail: string;
    valueClassName?: string;
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
                    className={[
                        'font-semibold tracking-normal tabular-nums',
                        valueClassName ?? 'text-3xl',
                    ].join(' ')}
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

function CourseLoadTable({
    courses,
}: {
    courses: DashboardStudentHomeCourse[];
}) {
    return (
        <Table className="text-sm">
            <TableHeader>
                <TableRow>
                    <TableHead className="w-[44%] px-4">Course</TableHead>
                    <TableHead className="w-[12%] text-center">Units</TableHead>
                    <TableHead className="pr-4">Type</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {courses.length > 0 ? (
                    courses.map((course) => (
                        <TableRow key={course.code} className="odd:bg-muted/25">
                            <TableCell className="px-4">
                                <div className="flex min-w-0 flex-col gap-0.5">
                                    <span className="font-semibold">
                                        {course.code}
                                    </span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        {course.title}
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell className="text-center font-medium tabular-nums">
                                {course.units}
                            </TableCell>
                            <TableCell className="pr-4">
                                {course.type}
                            </TableCell>
                        </TableRow>
                    ))
                ) : (
                    <TableRow>
                        <TableCell
                            colSpan={3}
                            className="px-4 py-6 text-muted-foreground"
                        >
                            No courses have been added for this semester.
                        </TableCell>
                    </TableRow>
                )}
            </TableBody>
        </Table>
    );
}

function DueList({ dues }: { dues: DashboardStudentHomeDue[] }) {
    if (dues.length === 0) {
        return <EmptyLine>No pending dues.</EmptyLine>;
    }

    return (
        <div className="divide-y">
            {dues.map((due) => (
                <div
                    key={`${due.title}-${due.dueDate}`}
                    className="grid grid-cols-[minmax(0,1fr)_7rem_minmax(3.5rem,1fr)] items-center gap-3 px-4 py-3 sm:gap-4"
                >
                    <div className="min-w-0">
                        <div className="truncate text-sm font-medium">
                            {due.title}
                        </div>
                    </div>
                    <div className="text-center text-sm font-semibold tabular-nums">
                        {due.amount}
                    </div>
                    <Link
                        href={dashboardSection({ section: 'dues' })}
                        className="justify-self-end text-xs font-medium text-link"
                    >
                        Pay now
                    </Link>
                </div>
            ))}
        </div>
    );
}

function EmptyLine({ children }: { children: string }) {
    return (
        <div className="px-4 py-6 text-sm text-muted-foreground">
            {children}
        </div>
    );
}
