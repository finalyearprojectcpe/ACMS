import { RiLayoutGridLine, RiTableLine } from '@remixicon/react';
import { useMemo, useState } from 'react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import {
    HoverCard,
    HoverCardContent,
    HoverCardTrigger,
} from '@/components/ui/hover-card';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { initialsFor } from '@/lib/person';
import type {
    DashboardStaffStudent,
    DashboardStaffStudents,
} from '@/types';

type StaffStudentsDashboardProps = {
    students: DashboardStaffStudents;
};

type StudentView = 'table' | 'cards';

export function StaffStudentsDashboard({
    students,
}: StaffStudentsDashboardProps) {
    const [view, setView] = useState<StudentView>('table');
    const gpaColumns = useMemo(
        () => gpaColumnsFor(students.students),
        [students.students],
    );

    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="text-sm text-muted-foreground">
                    <span className="font-medium text-foreground">
                        {assignedLevelLabel(students.assignedLevels)}
                    </span>{' '}
                    <span className="tabular-nums">
                        {students.totalStudents}{' '}
                        {students.totalStudents === 1 ? 'student' : 'students'}
                    </span>
                </div>

                <ToggleGroup
                    type="single"
                    value={view}
                    onValueChange={(value) => {
                        if (value) {
                            setView(value as StudentView);
                        }
                    }}
                    variant="outline"
                    size="sm"
                    aria-label="Student roster view"
                >
                    <ToggleGroupItem value="table" aria-label="Table view">
                        <RiTableLine className="size-4" />
                    </ToggleGroupItem>
                    <ToggleGroupItem value="cards" aria-label="Card view">
                        <RiLayoutGridLine className="size-4" />
                    </ToggleGroupItem>
                </ToggleGroup>
            </div>

            {view === 'table' ? (
                <StudentsTable
                    students={students.students}
                    gpaColumns={gpaColumns}
                />
            ) : (
                <StudentsCards students={students.students} />
            )}
        </div>
    );
}

function StudentsTable({
    students,
    gpaColumns,
}: {
    students: DashboardStaffStudent[];
    gpaColumns: string[];
}) {
    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardContent className="p-0">
                <Table className="text-sm">
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-14 px-4 text-center">
                                S/N
                            </TableHead>
                            <TableHead className="min-w-48">
                                Full Name
                            </TableHead>
                            <TableHead className="min-w-32">
                                Reg. No.
                            </TableHead>
                            <TableHead className="min-w-44">
                                Pending Carryovers
                            </TableHead>
                            {gpaColumns.map((level) => (
                                <TableHead
                                    key={level}
                                    className="min-w-24 text-center"
                                >
                                    {level} GPA
                                </TableHead>
                            ))}
                            <TableHead className="w-24 pr-4 text-center">
                                CGPA
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {students.length > 0 ? (
                            students.map((student, index) => (
                                <TableRow key={student.id}>
                                    <TableCell className="px-4 text-center text-muted-foreground tabular-nums">
                                        {index + 1}
                                    </TableCell>
                                    <TableCell className="font-medium">
                                        {student.fullName}
                                    </TableCell>
                                    <TableCell>
                                        <StudentProfileLink student={student} />
                                    </TableCell>
                                    <TableCell className="whitespace-normal">
                                        <CarryoverBadges
                                            courses={
                                                student.pendingCarryovers
                                            }
                                            limit={2}
                                        />
                                    </TableCell>
                                    {gpaColumns.map((level) => (
                                        <TableCell
                                            key={`${student.id}-${level}`}
                                            className="text-center font-medium tabular-nums"
                                        >
                                            {gpaForLevel(student, level)}
                                        </TableCell>
                                    ))}
                                    <TableCell className="pr-4 text-center font-semibold tabular-nums">
                                        {student.cgpa}
                                    </TableCell>
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell
                                    colSpan={5 + gpaColumns.length}
                                    className="px-4 py-6 text-muted-foreground"
                                >
                                    No students assigned to this set.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                    {students.length > 0 ? (
                        <TableFooter>
                            <TableRow>
                                <TableCell
                                    colSpan={4}
                                    className="px-4 font-semibold"
                                >
                                    Set average
                                </TableCell>
                                {gpaColumns.map((level) => (
                                    <TableCell
                                        key={`average-${level}`}
                                        className="text-center font-semibold tabular-nums"
                                    >
                                        {averageGpa(
                                            students.map((student) =>
                                                gpaForLevel(student, level),
                                            ),
                                        )}
                                    </TableCell>
                                ))}
                                <TableCell className="pr-4 text-center font-semibold tabular-nums">
                                    {averageGpa(
                                        students.map((student) => student.cgpa),
                                    )}
                                </TableCell>
                            </TableRow>
                        </TableFooter>
                    ) : null}
                </Table>
            </CardContent>
        </Card>
    );
}

function StudentsCards({ students }: { students: DashboardStaffStudent[] }) {
    if (students.length === 0) {
        return (
            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardContent className="p-6 text-sm text-muted-foreground">
                    No students assigned to this set.
                </CardContent>
            </Card>
        );
    }

    return (
        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
            {students.map((student) => (
                <Card
                    key={student.id}
                    className="border border-border bg-background shadow-sm ring-1 ring-border/70"
                >
                    <CardContent className="flex flex-col gap-3 p-3">
                        <div className="flex min-w-0 items-center gap-3">
                            <Avatar className="size-10 rounded-none after:rounded-none">
                                <AvatarImage
                                    src={student.avatar ?? undefined}
                                    alt={`${student.fullName} avatar`}
                                    className="rounded-none"
                                />
                                <AvatarFallback className="rounded-none font-medium">
                                    {initialsFor(student.fullName)}
                                </AvatarFallback>
                            </Avatar>
                            <div className="min-w-0">
                                <div className="truncate font-semibold">
                                    {student.fullName}
                                </div>
                                <div className="truncate text-xs text-muted-foreground">
                                    {student.email}
                                </div>
                                <StudentProfileLink
                                    student={student}
                                    className="text-xs"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-[5.25rem_minmax(0,1fr)] gap-2 text-sm">
                            <StudentStat label="CGPA" value={student.cgpa} />
                            <div className="border border-border bg-muted/40 px-2 py-1.5">
                                <div className="mb-1 text-xs text-muted-foreground">
                                    Carryovers
                                </div>
                                <CarryoverBadges
                                    courses={student.pendingCarryovers}
                                    limit={2}
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-3 gap-1.5">
                            {student.levelGpas.map((levelGpa) => (
                                <div
                                    key={levelGpa.level}
                                    className="border border-border bg-muted/40 px-2 py-1"
                                >
                                    <div className="text-xs text-muted-foreground">
                                        {levelGpa.level}
                                    </div>
                                    <div className="text-sm font-semibold tabular-nums">
                                        {levelGpa.gpa}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

function StudentStat({ label, value }: { label: string; value: string }) {
    return (
        <div className="border border-border bg-muted/40 px-2 py-1.5">
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="mt-1 text-lg font-semibold tabular-nums">
                {value}
            </div>
        </div>
    );
}

function StudentProfileLink({
    student,
    className,
}: {
    student: DashboardStaffStudent;
    className?: string;
}) {
    return (
        <a
            href={student.profileUrl}
            className={`font-medium text-link underline-offset-4 hover:underline ${className ?? 'text-sm'}`}
        >
            {student.registrationNumber}
        </a>
    );
}

function CarryoverBadges({
    courses,
    limit,
}: {
    courses: string[];
    limit: number;
}) {
    if (courses.length === 0) {
        return <span className="text-sm text-muted-foreground">None</span>;
    }

    const visibleCourses = courses.slice(0, limit);
    const hiddenCourses = courses.slice(limit);

    return (
        <div className="flex flex-wrap items-center gap-1.5">
            {visibleCourses.map((course) => (
                <Badge
                    key={course}
                    variant="outline"
                    className="border-destructive/30 bg-destructive/10 text-destructive"
                >
                    {course}
                </Badge>
            ))}
            {hiddenCourses.length > 0 ? (
                <HoverCard>
                    <HoverCardTrigger asChild>
                        <button
                            type="button"
                            className="text-xs font-medium text-link underline-offset-4 hover:underline focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background"
                        >
                            +{hiddenCourses.length} more
                        </button>
                    </HoverCardTrigger>
                    <HoverCardContent align="start" className="w-52">
                        <div className="font-medium text-foreground">
                            Other carryovers
                        </div>
                        <div className="mt-2 flex flex-wrap gap-1.5">
                            {hiddenCourses.map((course) => (
                                <Badge
                                    key={course}
                                    variant="outline"
                                    className="border-destructive/30 bg-destructive/10 text-destructive"
                                >
                                    {course}
                                </Badge>
                            ))}
                        </div>
                    </HoverCardContent>
                </HoverCard>
            ) : null}
        </div>
    );
}

function assignedLevelLabel(
    levels: DashboardStaffStudents['assignedLevels'],
): string {
    if (levels.length === 0) {
        return 'No assigned set';
    }

    return `${levels.map((level) => level.title).join(', ')} student set`;
}

function gpaColumnsFor(students: DashboardStaffStudent[]): string[] {
    return Array.from(
        new Set(
            students.flatMap((student) =>
                student.levelGpas.map((levelGpa) => levelGpa.level),
            ),
        ),
    ).sort((first, second) => parseInt(first, 10) - parseInt(second, 10));
}

function gpaForLevel(student: DashboardStaffStudent, level: string): string {
    return (
        student.levelGpas.find((levelGpa) => levelGpa.level === level)?.gpa ??
        '-'
    );
}

function averageGpa(values: string[]): string {
    const scores = values
        .map((value) => Number.parseFloat(value))
        .filter((value) => Number.isFinite(value));

    if (scores.length === 0) {
        return '-';
    }

    const average =
        scores.reduce((total, score) => total + score, 0) / scores.length;

    return average.toFixed(2);
}
