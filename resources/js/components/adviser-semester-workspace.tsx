import { Link } from '@inertiajs/react';
import { RiAddLine, RiSearchLine } from '@remixicon/react';
import { useState } from 'react';

import {
    createCourseOffering,
    editStudentSet,
    showCourseSchedule,
    showCourseCatalog,
} from '@/actions/App/Http/Controllers/DashboardController';
import { AcademicPeriodLayout } from '@/components/academic-period-layout';
import { AcademicPeriodNavigation } from '@/components/academic-period-navigation';
import { ActionLink } from '@/components/action-link';
import { CourseTable } from '@/components/adviser-course-schedules-dashboard';
import { PersonCell } from '@/components/person-cell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    sessionRouteYears,
    studentSetRouteParameters,
} from '@/lib/student-sets';
import type { DashboardAdviserCourseSchedules } from '@/types';

export function AdviserSemesterWorkspace({
    schedules,
}: {
    schedules: DashboardAdviserCourseSchedules;
}) {
    const [search, setSearch] = useState('');
    const set = schedules.selectedSet;

    if (!set) {
        return (
            <div className="grid flex-1 place-items-center text-sm text-muted-foreground">
                Add a student set to manage courses.
            </div>
        );
    }

    const params = studentSetRouteParameters(set);
    const semester = schedules.selectedSemester;
    const session = schedules.sessions.find((item) =>
        item.semesters.some((period) => period.id === semester?.id),
    );
    const sessionYears = session ? sessionRouteYears(session) : '';
    const students =
        semester?.students.filter((student) =>
            `${student.name} ${student.registrationNumber}`
                .toLowerCase()
                .includes(search.trim().toLowerCase()),
        ) ?? [];
    const studentNumbers = new Map(
        semester?.students.map((student, index) => [
            student.registrationNumber,
            index + 1,
        ]),
    );

    return (
        <div className="flex min-h-0 min-w-0 flex-1 flex-col">
            <header className="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b pb-5">
                <div>
                    <h1 className="text-xl font-semibold">Courses</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {set.name}
                    </p>
                </div>
                <ActionLink href={showCourseCatalog(params)}>
                    Course Catalog
                </ActionLink>
            </header>
            {schedules.sessions.length === 0 ? (
                <div className="grid flex-1 place-content-center gap-3 text-center">
                    <p className="text-sm text-muted-foreground">
                        Add an academic session to this set first.
                    </p>
                    <ActionLink href={editStudentSet(params)}>
                        Open Set Settings
                    </ActionLink>
                </div>
            ) : (
                <AcademicPeriodLayout
                    navigation={(collapsed) => (
                        <AcademicPeriodNavigation
                            collapsed={collapsed}
                            ariaLabel="Academic semesters"
                            groups={schedules.sessions.map((session) => ({
                                key: session.publicId,
                                label: session.level.title,
                                detail: `${session.name} Session`,
                                compactLabel: `${session.level.yearNumber}00L`,
                                links: session.semesters.map((period) => ({
                                    key: `${session.publicId}:${period.slug}`,
                                    href: showCourseSchedule(params, {
                                        query: {
                                            session: sessionRouteYears(session),
                                            semester: period.slug,
                                        },
                                    }).url,
                                    label: period.name,
                                    compactLabel:
                                        period.slug === 'first' ? '1st' : '2nd',
                                    selected: period.id === semester?.id,
                                })),
                            }))}
                        />
                    )}
                >
                    <div className="mx-auto flex min-h-0 w-full max-w-5xl min-w-0 flex-col md:pl-5">
                        {semester && session ? (
                            <>
                                <div className="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b py-4">
                                    <div>
                                        <h2 className="text-base font-semibold">
                                            {session.level.title} ·{' '}
                                            {semester.name}
                                        </h2>
                                        <p className="text-xs text-muted-foreground">
                                            {session.name} Session
                                        </p>
                                    </div>
                                    <span className="text-sm text-muted-foreground tabular-nums">
                                        {semester.maximumCourseUnits} unit limit
                                    </span>
                                </div>
                                <Tabs
                                    defaultValue="students"
                                    className="min-h-0 flex-1 gap-0"
                                >
                                    <TabsList
                                        variant="line"
                                        className="h-11 w-full justify-between border-b px-1"
                                    >
                                        <TabsTrigger
                                            value="students"
                                            className="h-full text-sm"
                                        >
                                            Students ({semester.students.length}
                                            )
                                        </TabsTrigger>
                                        <TabsTrigger
                                            value="courses"
                                            className="h-full text-sm"
                                        >
                                            Courses ({semester.offerings.length}
                                            )
                                        </TabsTrigger>
                                    </TabsList>
                                    <TabsContent
                                        value="courses"
                                        className="flex min-h-0 flex-col"
                                    >
                                        <div className="flex min-h-0 flex-1 flex-col py-4">
                                            <h3 className="mb-3 font-semibold">
                                                Scheduled Courses{' '}
                                                <span className="ml-1 text-sm font-normal text-muted-foreground tabular-nums">
                                                    {semester.offerings.length}
                                                </span>
                                            </h3>
                                            <div className="min-h-0 flex-1 border-y">
                                                <CourseTable
                                                    courses={semester.offerings}
                                                    studentSet={set}
                                                    sessionYears={sessionYears}
                                                    semester={semester.slug}
                                                />
                                            </div>
                                        </div>
                                        <footer className="flex shrink-0 justify-end border-t pt-4">
                                            <Button size="sm" asChild>
                                                <Link
                                                    href={createCourseOffering({
                                                        ...params,
                                                        sessionYears,
                                                        semester: semester.slug,
                                                    })}
                                                >
                                                    <RiAddLine /> Add Courses
                                                </Link>
                                            </Button>
                                        </footer>
                                    </TabsContent>
                                    <TabsContent
                                        value="students"
                                        className="flex min-h-0 flex-col py-4"
                                    >
                                        <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
                                            <h3 className="font-semibold">
                                                Students{' '}
                                                <span className="ml-1 text-sm font-normal text-muted-foreground tabular-nums">
                                                    {semester.students.length}
                                                </span>
                                            </h3>
                                            <div className="relative w-full max-w-56">
                                                <RiSearchLine className="absolute top-1/2 left-2 size-4 -translate-y-1/2 text-muted-foreground" />
                                                <Input
                                                    className="pl-8"
                                                    aria-label="Find a student"
                                                    placeholder="Find a student"
                                                    value={search}
                                                    onChange={(event) =>
                                                        setSearch(
                                                            event.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                        </div>
                                        <ScrollArea className="min-h-0 flex-1 border-y">
                                            <Table className="min-w-[500px]">
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead className="w-12 text-center">
                                                            S/N
                                                        </TableHead>
                                                        <TableHead>
                                                            Student
                                                        </TableHead>
                                                        <TableHead className="w-20 text-center">
                                                            Courses
                                                        </TableHead>
                                                        <TableHead className="w-20 text-center">
                                                            Units
                                                        </TableHead>
                                                        <TableHead className="w-28 text-right">
                                                            Action
                                                        </TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {students.length ? (
                                                        students.map(
                                                            (student) => (
                                                                <TableRow
                                                                    key={
                                                                        student.registrationNumber
                                                                    }
                                                                    className="odd:bg-muted/25"
                                                                >
                                                                    <TableCell className="text-center text-muted-foreground tabular-nums">
                                                                        {studentNumbers.get(
                                                                            student.registrationNumber,
                                                                        )}
                                                                    </TableCell>
                                                                    <TableCell>
                                                                        <PersonCell
                                                                            name={
                                                                                student.name
                                                                            }
                                                                            avatar={
                                                                                student.avatar
                                                                            }
                                                                            secondary={
                                                                                student.registrationNumber
                                                                            }
                                                                            href={
                                                                                student.profileUrl ??
                                                                                undefined
                                                                            }
                                                                        />
                                                                    </TableCell>
                                                                    <TableCell className="text-center tabular-nums">
                                                                        {
                                                                            student.courseCount
                                                                        }
                                                                    </TableCell>
                                                                    <TableCell className="text-center tabular-nums">
                                                                        {
                                                                            student.units
                                                                        }
                                                                    </TableCell>
                                                                    <TableCell className="text-right">
                                                                        <ActionLink
                                                                            href={
                                                                                student.assignmentUrl
                                                                            }
                                                                        >
                                                                            {student.courseCount
                                                                                ? 'Edit'
                                                                                : 'Assign'}
                                                                        </ActionLink>
                                                                    </TableCell>
                                                                </TableRow>
                                                            ),
                                                        )
                                                    ) : (
                                                        <TableRow>
                                                            <TableCell
                                                                colSpan={5}
                                                                className="py-8 text-center text-muted-foreground"
                                                            >
                                                                {search
                                                                    ? 'No matching students.'
                                                                    : 'No students in this set yet.'}
                                                            </TableCell>
                                                        </TableRow>
                                                    )}
                                                </TableBody>
                                            </Table>
                                            <ScrollBar orientation="horizontal" />
                                        </ScrollArea>
                                    </TabsContent>
                                </Tabs>
                            </>
                        ) : (
                            <div className="grid flex-1 place-items-center text-sm text-muted-foreground">
                                Choose a semester.
                            </div>
                        )}
                    </div>
                </AcademicPeriodLayout>
            )}
        </div>
    );
}
