import { Link } from '@inertiajs/react';
import { useMemo, useState, useSyncExternalStore } from 'react';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import type {
    DashboardStudentHome,
    DashboardStudentHomeDue,
    DashboardStudentHomeTimetableItem,
    DashboardStudentHomeResit,
} from '@/types';

type StudentHomeDashboardProps = {
    home: DashboardStudentHome;
};

const weekdays = [
    'Monday',
    'Tuesday',
    'Wednesday',
    'Thursday',
    'Friday',
] as const;

type Weekday = (typeof weekdays)[number];

function subscribeToClientSnapshot() {
    return () => {};
}

export function StudentHomeDashboard({ home }: StudentHomeDashboardProps) {
    const currentDay = useSyncExternalStore(
        subscribeToClientSnapshot,
        defaultWeekday,
        () => 'Monday',
    );
    const [selectedDay, setSelectedDay] = useState<Weekday | null>(null);
    const semesterLabel = qualifiedSemesterLabel(
        home.currentSemester.level,
        home.currentSemester.name,
    );
    const timetableDownloadHref = useMemo(
        () =>
            timetableTextHref(
                semesterLabel,
                home.currentSemester.session,
                home.timetable,
            ),
        [home.currentSemester.session, home.timetable, semesterLabel],
    );

    return (
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-4">
            <div className="grid gap-3 md:grid-cols-2">
                <MetricBlock
                    label="CGPA"
                    value={home.cgpa}
                    detail="Cumulative grade point average"
                />
                <MetricBlock
                    label="Total Units"
                    value={home.totalCourseUnits}
                    detail={`For ${semesterLabel}`}
                />
            </div>

            <SolidCard>
                <CardHeader className="flex flex-row items-center justify-between gap-3 border-b border-border">
                    <CardTitle>
                        <QualifiedSemester
                            level={home.currentSemester.level}
                            semester={home.currentSemester.name}
                        />{' '}
                        Course Timetable
                    </CardTitle>
                    <a
                        href={timetableDownloadHref}
                        download={timetableFileName(semesterLabel)}
                        className="text-xs font-medium text-link"
                    >
                        Download timetable
                    </a>
                </CardHeader>
                <CardContent className="p-0">
                    <Tabs
                        value={selectedDay ?? currentDay}
                        onValueChange={(value) =>
                            setSelectedDay(value as Weekday)
                        }
                        orientation="vertical"
                        className="grid gap-0 md:grid-cols-[9rem_minmax(0,1fr)]"
                    >
                        <div className="border-b border-border p-3 md:border-r md:border-b-0">
                            <TabsList
                                variant="line"
                                className="grid! h-auto w-full grid-cols-5 gap-1 bg-transparent p-0 md:flex! md:flex-col md:items-stretch"
                            >
                                {weekdays.map((day) => (
                                    <TabsTrigger
                                        key={day}
                                        value={day}
                                        className="h-8 justify-center! px-2 md:justify-start! data-active:bg-primary! data-active:text-primary-foreground! data-active:after:opacity-0! data-active:hover:text-primary-foreground!"
                                    >
                                        <span className="md:hidden">
                                            {day.slice(0, 3)}
                                        </span>
                                        <span className="hidden md:inline">
                                            {day}
                                        </span>
                                    </TabsTrigger>
                                ))}
                            </TabsList>
                        </div>

                        <div className="min-w-0">
                            {weekdays.map((day) => (
                                <TabsContent
                                    key={day}
                                    value={day}
                                    className="m-0"
                                >
                                    <TimetableTable
                                        courses={home.timetable.filter(
                                            (course) => course.day === day,
                                        )}
                                    />
                                </TabsContent>
                            ))}
                        </div>
                    </Tabs>
                </CardContent>
            </SolidCard>

            <div className="grid gap-4 lg:grid-cols-2">
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

                <SolidCard>
                    <CardHeader className="border-b border-border">
                        <CardTitle>Pending Carryover Courses</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <ResitList resits={home.resits} />
                    </CardContent>
                </SolidCard>
            </div>
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
}: {
    label: string;
    value: ReactNode;
    detail: string;
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
                <div className="text-3xl font-semibold tracking-normal tabular-nums">
                    {value}
                </div>
                <div className="mt-1 text-xs text-muted-foreground">
                    {detail}
                </div>
            </CardContent>
        </Card>
    );
}

function TimetableTable({
    courses,
}: {
    courses: DashboardStudentHomeTimetableItem[];
}) {
    return (
        <Table className="text-sm">
            <TableHeader>
                <TableRow>
                    <TableHead className="w-[44%] px-4">Course</TableHead>
                    <TableHead className="w-[12%] text-center">Units</TableHead>
                    <TableHead>Time</TableHead>
                    <TableHead className="pr-4">Venue</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {courses.length > 0 ? (
                    courses.map((course) => (
                        <TableRow key={course.code}>
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
                            <TableCell className="tabular-nums">
                                {course.time}
                            </TableCell>
                            <TableCell className="pr-4">
                                {course.venue}
                            </TableCell>
                        </TableRow>
                    ))
                ) : (
                    <TableRow>
                        <TableCell
                            colSpan={4}
                            className="px-4 py-6 text-muted-foreground"
                        >
                            No classes scheduled.
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

function ResitList({ resits }: { resits: DashboardStudentHomeResit[] }) {
    if (resits.length === 0) {
        return <EmptyLine>No carryover courses.</EmptyLine>;
    }

    return (
        <div className="divide-y">
            {resits.map((resit) => (
                <div
                    key={`${resit.code}-${resit.resitWindow}`}
                    className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-4 px-4 py-3"
                >
                    <div className="min-w-0">
                        <div className="truncate text-sm font-semibold">
                            {resit.code}
                        </div>
                        <div className="truncate text-xs text-muted-foreground">
                            {resit.title}
                        </div>
                    </div>
                    <div className="text-right text-sm font-medium tabular-nums">
                        {resit.units} units
                    </div>
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

function defaultWeekday(): Weekday {
    const day = new Date().getDay();

    if (day >= 1 && day <= 5) {
        return weekdays[day - 1];
    }

    return 'Monday';
}

function timetableTextHref(
    semesterLabel: string,
    session: string,
    courses: DashboardStudentHomeTimetableItem[],
): string {
    const lines = [
        `${semesterLabel} Course Timetable`,
        `${session} Academic Session`,
        '',
        ...courses.map(
            (course) =>
                `${course.day} | ${course.time} | ${course.code} | ${course.title} | ${course.units} units | ${course.venue}`,
        ),
    ];

    return `data:text/plain;charset=utf-8,${encodeURIComponent(lines.join('\n'))}`;
}

function timetableFileName(semesterLabel: string): string {
    const slug = semesterLabel
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');

    return `${slug}-timetable.txt`;
}
