import {
    RiArrowDownLine,
    RiArrowUpDownLine,
    RiArrowUpLine,
    RiSearchLine,
} from '@remixicon/react';
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

type StudentSort = 'name' | 'cgpa-asc' | 'cgpa-desc';

export function AdviserStudentsDashboard({
    students,
}: {
    students: DashboardAdviserStudents;
}) {
    const [query, setQuery] = useState('');
    const [sort, setSort] = useState<StudentSort>('name');
    const selectedSet = students.assignedSets.find(
        (set) => set.id === students.selectedSetId,
    );
    const visibleStudents = useMemo(() => {
        const search = query.trim().toLowerCase();

        const filtered = students.students.filter((student) =>
            [student.fullName, student.email, student.registrationNumber]
                .join(' ')
                .toLowerCase()
                .includes(search),
        );

        if (sort === 'name') {
            return filtered;
        }

        return filtered.sort((left, right) => {
            const leftCgpa = left.cgpa ?? null;
            const rightCgpa = right.cgpa ?? null;

            if (leftCgpa === null) {
                return rightCgpa === null
                    ? left.fullName.localeCompare(right.fullName)
                    : 1;
            }

            if (rightCgpa === null) {
                return -1;
            }

            const difference = leftCgpa - rightCgpa;

            return (
                (sort === 'cgpa-asc' ? difference : -difference) ||
                left.fullName.localeCompare(right.fullName)
            );
        });
    }, [students.students, query, sort]);

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
                        sort={sort}
                        onSortChange={setSort}
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
    sort,
    onSortChange,
    emptyLabel,
}: {
    students: DashboardAdviserStudent[];
    totalStudents: number;
    query: string;
    onQueryChange: (value: string) => void;
    sort: StudentSort;
    onSortChange: (value: StudentSort) => void;
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
                            <TableHead
                                className="min-w-52"
                                aria-sort={
                                    sort === 'name' ? 'ascending' : 'none'
                                }
                            >
                                <button
                                    type="button"
                                    className="inline-flex items-center gap-1.5 font-medium text-foreground hover:text-link focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden"
                                    onClick={() => onSortChange('name')}
                                >
                                    Student
                                    {sort === 'name' && (
                                        <RiArrowUpLine className="size-3.5" />
                                    )}
                                </button>
                            </TableHead>
                            <TableHead className="w-44">Reg. No.</TableHead>
                            <TableHead
                                className="w-24 pr-4 text-right"
                                aria-sort={
                                    sort === 'cgpa-asc'
                                        ? 'ascending'
                                        : sort === 'cgpa-desc'
                                          ? 'descending'
                                          : 'none'
                                }
                            >
                                <button
                                    type="button"
                                    className="inline-flex items-center gap-1.5 font-medium text-foreground hover:text-link focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden"
                                    onClick={() =>
                                        onSortChange(
                                            sort === 'cgpa-asc'
                                                ? 'cgpa-desc'
                                                : 'cgpa-asc',
                                        )
                                    }
                                >
                                    CGPA
                                    {sort === 'cgpa-asc' ? (
                                        <RiArrowUpLine className="size-3.5" />
                                    ) : sort === 'cgpa-desc' ? (
                                        <RiArrowDownLine className="size-3.5" />
                                    ) : (
                                        <RiArrowUpDownLine className="size-3.5 text-muted-foreground" />
                                    )}
                                </button>
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
                                            href={student.profileUrl}
                                        />
                                    </TableCell>
                                    <TableCell>
                                        <CopyableValue
                                            value={student.registrationNumber}
                                        />
                                    </TableCell>
                                    <TableCell className="pr-4 text-right tabular-nums">
                                        {student.cgpa?.toFixed(2) ?? '-'}
                                    </TableCell>
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell
                                    colSpan={4}
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
