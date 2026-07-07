import { RiSearchLine } from '@remixicon/react';
import { useMemo, useState } from 'react';

import { ActionLink } from '@/components/action-link';
import { CopyableValue } from '@/components/copyable-value';
import { PersonCell } from '@/components/person-cell';
import { studentSetText } from '@/components/student-set-label';
import { StudentSetPicker } from '@/components/student-set-picker';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent } from '@/components/ui/card';
import {
    HoverCard,
    HoverCardContent,
    HoverCardTrigger,
} from '@/components/ui/hover-card';
import { Input } from '@/components/ui/input';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { DashboardStaffStudent, DashboardStaffStudents } from '@/types';

type StaffStudentsDashboardProps = {
    students: DashboardStaffStudents;
};

export function StaffStudentsDashboard({
    students,
}: StaffStudentsDashboardProps) {
    const [query, setQuery] = useState('');
    const [setFilter, setSetFilter] = useState(
        students.assignedSets[0]?.entrySession.id.toString() ?? 'all',
    );
    const visibleStudents = useMemo(
        () =>
            students.students.filter((student) => {
                const searchText = [
                    student.fullName,
                    student.email,
                    student.registrationNumber,
                    student.advisorName,
                    student.entrySession?.name,
                ]
                    .join(' ')
                    .toLowerCase();

                return (
                    searchText.includes(query.trim().toLowerCase()) &&
                    (setFilter === 'all' ||
                        student.entrySession?.id.toString() === setFilter)
                );
            }),
        [students.students, query, setFilter],
    );
    const gpaColumns = useMemo(
        () => gpaColumnsFor(visibleStudents),
        [visibleStudents],
    );

    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div className="text-sm text-muted-foreground">
                    <span className="font-medium text-foreground">
                        {assignedSetLabel(students.assignedSets, setFilter)}
                    </span>{' '}
                    <span className="tabular-nums">
                        {visibleStudents.length}{' '}
                        {visibleStudents.length === 1 ? 'student' : 'students'}
                    </span>
                </div>

                <div className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(20rem,24rem)] lg:min-w-[44rem]">
                    <div className="relative">
                        <RiSearchLine className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Search students"
                            className="bg-background pl-9"
                        />
                    </div>
                    <StudentSetPicker
                        value={setFilter}
                        onValueChange={setSetFilter}
                        options={students.assignedSets.map((set) => ({
                            value: set.entrySession.id.toString(),
                            entrySession: set.entrySession,
                            currentLevel: set.currentLevel,
                            minimumYearsToDegree: set.minimumYearsToDegree,
                        }))}
                        allOption={
                            students.assignedSets.length > 1
                                ? { value: 'all', label: 'All sets' }
                                : undefined
                        }
                        ariaLabel="Filter by set"
                    />
                </div>
            </div>

            <StudentsTable students={visibleStudents} gpaColumns={gpaColumns} />
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
                <ScrollArea className="max-h-[calc(100vh-16rem)]">
                    <Table className="min-w-[920px] text-sm">
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
                                <TableHead className="w-24 pr-4 text-right">
                                    Action
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {students.length > 0 ? (
                                students.map((student, index) => (
                                    <TableRow
                                        key={student.id}
                                        className="odd:bg-background even:bg-muted/25"
                                    >
                                        <TableCell className="px-4 text-center text-muted-foreground tabular-nums">
                                            {index + 1}
                                        </TableCell>
                                        <TableCell>
                                            <PersonCell
                                                name={student.fullName}
                                                secondary={student.email}
                                                avatar={student.avatar}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <CopyableValue
                                                value={
                                                    student.registrationNumber
                                                }
                                            />
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
                                        <TableCell className="pr-4 text-right">
                                            <ActionLink
                                                href={student.profileUrl}
                                            >
                                                View
                                            </ActionLink>
                                        </TableCell>
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell
                                        colSpan={6 + gpaColumns.length}
                                        className="px-4 py-6 text-muted-foreground"
                                    >
                                        No students match the current filters.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                        {students.length > 0 ? (
                            <TableFooter className="sticky bottom-0 z-20 border-t border-border bg-background shadow-[0_-1px_0_hsl(var(--border))]">
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
                                            students.map(
                                                (student) => student.cgpa,
                                            ),
                                        )}
                                    </TableCell>
                                    <TableCell />
                                </TableRow>
                            </TableFooter>
                        ) : null}
                    </Table>
                    <ScrollBar orientation="horizontal" />
                </ScrollArea>
            </CardContent>
        </Card>
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
                            className="text-xs font-medium text-link underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-hidden"
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

function assignedSetLabel(
    sets: DashboardStaffStudents['assignedSets'],
    selectedSetId: string,
): string {
    if (sets.length === 0) {
        return 'No assigned set';
    }

    if (selectedSetId !== 'all') {
        const selectedSet = sets.find(
            (set) => set.entrySession.id.toString() === selectedSetId,
        );

        if (selectedSet) {
            return studentSetText(selectedSet);
        }
    }

    return sets.map((set) => studentSetText(set)).join(', ');
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
