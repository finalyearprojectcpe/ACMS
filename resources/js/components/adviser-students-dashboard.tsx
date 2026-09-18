import { Link } from '@inertiajs/react';
import {
    RiFileExcel2Line,
    RiSearchLine,
    RiUserAddLine,
} from '@remixicon/react';
import { useMemo, useState } from 'react';

import {
    createAdvisedStudent,
    importAdvisedStudents,
} from '@/actions/App/Http/Controllers/DashboardController';
import { ActionLink } from '@/components/action-link';
import { CopyableValue } from '@/components/copyable-value';
import { PersonCell } from '@/components/person-cell';
import {
    studentSetListText,
    studentSetText,
} from '@/components/student-set-label';
import { StudentSetPicker } from '@/components/student-set-picker';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
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
import type {
    DashboardAdviserStudent,
    DashboardAdviserStudents,
} from '@/types';

type AdviserStudentsDashboardProps = {
    students: DashboardAdviserStudents;
};

export function AdviserStudentsDashboard({
    students,
}: AdviserStudentsDashboardProps) {
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
    const showAccessCodes = visibleStudents.some(
        (student) => student.accessCode !== null,
    );

    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="text-sm text-muted-foreground">
                    <span className="font-medium text-foreground">
                        {assignedSetLabel(students.assignedSets, setFilter)}
                    </span>{' '}
                    <span className="tabular-nums">
                        {visibleStudents.length}{' '}
                        {visibleStudents.length === 1 ? 'student' : 'students'}
                    </span>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button asChild>
                        <Link href={importAdvisedStudents()}>
                            <RiFileExcel2Line />
                            Import Students
                        </Link>
                    </Button>
                    <Button variant="outline" asChild>
                        <Link href={createAdvisedStudent()}>
                            <RiUserAddLine />
                            Add One Student
                        </Link>
                    </Button>
                </div>
            </div>

            <div className="grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(20rem,24rem)] lg:ml-auto lg:min-w-[44rem]">
                <div className="relative">
                    <RiSearchLine className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Search students"
                        className="pl-9"
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
                        isGraduated: set.isGraduated,
                    }))}
                    allOption={
                        students.assignedSets.length > 1
                            ? { value: 'all', label: 'All sets' }
                            : undefined
                    }
                    ariaLabel="Filter by set"
                />
            </div>

            <StudentsTable
                students={visibleStudents}
                showAccessCodes={showAccessCodes}
            />
        </div>
    );
}

function StudentsTable({
    students,
    showAccessCodes,
}: {
    students: DashboardAdviserStudent[];
    showAccessCodes: boolean;
}) {
    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardContent className="p-0">
                <ScrollArea className="max-h-[calc(100vh-16rem)]">
                    <Table className="min-w-[760px] text-sm">
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
                                {showAccessCodes ? (
                                    <TableHead className="min-w-44">
                                        Access Code
                                    </TableHead>
                                ) : null}
                                <TableHead className="min-w-36">
                                    Student Set
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
                                                secondary={
                                                    student.email ??
                                                    student.accountStatus
                                                }
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
                                        {showAccessCodes ? (
                                            <TableCell>
                                                {student.accessCode ? (
                                                    <CopyableValue
                                                        value={
                                                            student.accessCode
                                                        }
                                                    />
                                                ) : (
                                                    <span className="text-muted-foreground">
                                                        {student.accountStatus}
                                                    </span>
                                                )}
                                            </TableCell>
                                        ) : null}
                                        <TableCell className="font-medium">
                                            {studentSetText(student.studentSet)}
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
                                        colSpan={5 + (showAccessCodes ? 1 : 0)}
                                        className="px-4 py-6 text-muted-foreground"
                                    >
                                        No students match the current filters.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                    <ScrollBar orientation="horizontal" />
                </ScrollArea>
            </CardContent>
        </Card>
    );
}

function assignedSetLabel(
    sets: DashboardAdviserStudents['assignedSets'],
    selectedSetId: string,
): string {
    if (selectedSetId !== 'all') {
        const selectedSet = sets.find(
            (set) => set.entrySession.id.toString() === selectedSetId,
        );

        if (selectedSet) {
            return studentSetText(selectedSet);
        }
    }

    return studentSetListText(sets);
}
