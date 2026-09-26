import { RiSearchLine } from '@remixicon/react';
import { useMemo, useState } from 'react';

import { AdviserSetWorkspace } from '@/components/adviser-set-workspace-nav';
import { CopyableValue } from '@/components/copyable-value';
import { PersonCell } from '@/components/person-cell';
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

export function AdviserStudentsDashboard({
    students,
}: {
    students: DashboardAdviserStudents;
}) {
    const [query, setQuery] = useState('');
    const selectedSet = students.assignedSets.find(
        (set) => set.id === students.selectedSetId,
    );
    const visibleStudents = useMemo(() => {
        const search = query.trim().toLowerCase();

        return students.students.filter((student) =>
            [student.fullName, student.email, student.registrationNumber]
                .join(' ')
                .toLowerCase()
                .includes(search),
        );
    }, [students.students, query]);

    if (!selectedSet) {
        return (
            <p className="text-sm text-muted-foreground">
                Create a student set before inviting students.
            </p>
        );
    }

    return (
        <div className="flex min-h-0 w-full flex-1 flex-col">
            <AdviserSetWorkspace
                studentSet={selectedSet}
                activeSection="students"
            >
                <div className="mx-auto flex min-h-0 w-full max-w-[48rem] flex-1 flex-col">
                    <StudentsTable
                        students={visibleStudents}
                        totalStudents={students.students.length}
                        query={query}
                        onQueryChange={setQuery}
                        emptyLabel={
                            students.students.length === 0
                                ? 'No students have joined this set yet.'
                                : 'No students match your search.'
                        }
                    />
                </div>
            </AdviserSetWorkspace>
        </div>
    );
}

function StudentsTable({
    students,
    totalStudents,
    query,
    onQueryChange,
    emptyLabel,
}: {
    students: DashboardAdviserStudent[];
    totalStudents: number;
    query: string;
    onQueryChange: (value: string) => void;
    emptyLabel: string;
}) {
    return (
        <>
            <div className="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-xs text-muted-foreground">
                    {students.length === totalStudents
                        ? `${totalStudents} ${totalStudents === 1 ? 'student' : 'students'}`
                        : `${students.length} of ${totalStudents} students`}
                </p>
                <div className="relative w-full sm:w-80">
                    <RiSearchLine className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        aria-label="Search students"
                        value={query}
                        onChange={(event) => onQueryChange(event.target.value)}
                        placeholder="Search students"
                        className="pl-9"
                    />
                </div>
            </div>
            <ScrollArea className="h-0 min-h-0 w-full flex-1">
                <Table className="min-w-[620px] text-sm">
                    <TableHeader>
                        <TableRow>
                            <TableHead className="w-14 px-4 text-center">
                                S/N
                            </TableHead>
                            <TableHead className="min-w-52">Student</TableHead>
                            <TableHead className="w-44">Reg. No.</TableHead>
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
                                            href={student.profileUrl}
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <CopyableValue
                                            value={student.registrationNumber}
                                        />
                                    </TableCell>
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell
                                    colSpan={3}
                                    className="px-4 py-8 text-center text-muted-foreground"
                                >
                                    {emptyLabel}
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
                <ScrollBar orientation="horizontal" />
            </ScrollArea>
        </>
    );
}
