import { Link, router, useForm } from '@inertiajs/react';
import { RiAddLine, RiSearchLine } from '@remixicon/react';
import {
    createSortedRowModel,
    rowSortingFeature,
    tableFeatures,
    useTable,
} from '@tanstack/react-table';
import type { ColumnDef } from '@tanstack/react-table';
import { useEffect, useRef, useState } from 'react';

import { approve } from '@/actions/App/Http/Controllers/AdviserJoinRequestController';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/StudentRosterController';
import { AdviserSetWorkspace } from '@/components/adviser-set-workspace-nav';
import { CopyableValue } from '@/components/copyable-value';
import { PersonCell } from '@/components/person-cell';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
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
import { create as rejectCreate } from '@/routes/student-joins/adviser/reject';
import type {
    DashboardAdviserStudent,
    DashboardAdviserStudents,
    DashboardStudentSetContext,
} from '@/types';

const features = tableFeatures({
    rowSortingFeature,
    sortedRowModel: createSortedRowModel(),
});

function setRoute(set: DashboardStudentSetContext) {
    return {
        departmentSlug: set.entrySession.departmentSlug,
        entrySession: `${set.entrySession.startYear}${set.entrySession.endYear}`,
    };
}

function AccountState({ student }: { student: DashboardAdviserStudent }) {
    if (student.accountStatus === 'active') {
        return (
            <Badge className="bg-emerald-500/10 text-emerald-600">Active</Badge>
        );
    }

    if (student.accountStatus === 'review') {
        return (
            <Badge className="bg-amber-500/10 text-amber-700">
                Awaiting review
            </Badge>
        );
    }

    if (student.accountStatus === 'verify_email') {
        return <span className="text-muted-foreground">Request started</span>;
    }

    if (student.accountStatus === 'declined') {
        return <Badge variant="destructive">Declined</Badge>;
    }

    return <span className="text-muted-foreground">Not joined</span>;
}

function ReviewActions({ student }: { student: DashboardAdviserStudent }) {
    if (student.accountStatus !== 'review' || !student.claimId) {
        return null;
    }

    return (
        <div className="flex items-center justify-end gap-3">
            <AlertDialog>
                <AlertDialogTrigger asChild>
                    <Button size="sm" variant="outline">
                        Accept
                    </Button>
                </AlertDialogTrigger>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Accept {student.fullName}?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            This links the verified account to the student
                            record. The student can then access their dashboard.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction
                            onClick={() =>
                                router.patch(
                                    approve({
                                        studentJoinRequest: student.claimId!,
                                    }).url,
                                )
                            }
                        >
                            Accept student
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
            <Link
                className="text-sm text-destructive hover:underline"
                href={rejectCreate({ studentJoinRequest: student.claimId })}
            >
                Decline
            </Link>
        </div>
    );
}

function RosterEditor({
    set,
    student,
    onCancel,
}: {
    set: DashboardStudentSetContext;
    student?: DashboardAdviserStudent;
    onCancel: () => void;
}) {
    const nameInputRef = useRef<HTMLInputElement>(null);
    const form = useForm({
        official_name: student?.fullName ?? '',
        registration_number: student?.registrationNumber ?? '',
        email: student?.pendingEmail ?? student?.email ?? '',
    });

    useEffect(() => {
        nameInputRef.current?.scrollIntoView({ block: 'nearest' });
        nameInputRef.current?.focus({ preventScroll: true });
    }, []);

    return (
        <TableRow className="bg-muted/30">
            <TableCell colSpan={6} className="p-4">
                <div className="grid items-start gap-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1.3fr)_minmax(0,2fr)_auto]">
                    <div>
                        <Input
                            ref={nameInputRef}
                            aria-label="Official name"
                            placeholder="Official name"
                            value={form.data.official_name}
                            onChange={(event) =>
                                form.setData(
                                    'official_name',
                                    event.target.value,
                                )
                            }
                            aria-invalid={Boolean(form.errors.official_name)}
                        />
                        {form.errors.official_name && (
                            <p className="mt-1 text-xs text-destructive">
                                {form.errors.official_name}
                            </p>
                        )}
                    </div>
                    <div>
                        <Input
                            aria-label="Registration number"
                            placeholder="Registration number"
                            value={form.data.registration_number}
                            onChange={(event) =>
                                form.setData(
                                    'registration_number',
                                    event.target.value,
                                )
                            }
                            aria-invalid={Boolean(
                                form.errors.registration_number,
                            )}
                        />
                        {form.errors.registration_number && (
                            <p className="mt-1 text-xs text-destructive">
                                {form.errors.registration_number}
                            </p>
                        )}
                    </div>
                    {student?.accountStatus === 'active' ? (
                        <div>
                            <Input
                                aria-label="Email address"
                                placeholder="Email address"
                                type="email"
                                value={form.data.email}
                                onChange={(event) =>
                                    form.setData('email', event.target.value)
                                }
                                aria-invalid={Boolean(form.errors.email)}
                            />
                            {form.errors.email && (
                                <p className="mt-1 text-xs text-destructive">
                                    {form.errors.email}
                                </p>
                            )}
                        </div>
                    ) : (
                        <div className="self-center text-xs text-muted-foreground">
                            Email is added when the student joins.
                        </div>
                    )}
                    <div className="flex justify-end gap-2">
                        <Button size="sm" variant="outline" onClick={onCancel}>
                            Cancel
                        </Button>
                        <Button
                            size="sm"
                            disabled={form.processing}
                            onClick={() =>
                                form.submit(
                                    student
                                        ? update({
                                              ...setRoute(set),
                                              registrationNumber:
                                                  student.registrationRouteValue,
                                          })
                                        : store(setRoute(set)),
                                    { onSuccess: onCancel },
                                )
                            }
                        >
                            Save
                        </Button>
                    </div>
                </div>
            </TableCell>
        </TableRow>
    );
}

export function AdviserStudentsDashboard({
    students,
}: {
    students: DashboardAdviserStudents;
}) {
    const [query, setQuery] = useState('');
    const [editing, setEditing] = useState<string | 'new' | null>(null);
    const selectedSet = students.assignedSets.find(
        (set) => set.id === students.selectedSetId,
    );
    const search = query.trim().toLocaleLowerCase();
    const data = students.students.filter((student) =>
        [student.fullName, student.registrationNumber, student.email ?? '']
            .join(' ')
            .toLocaleLowerCase()
            .includes(search),
    );
    const rosterNumberById = new Map(
        students.students.map((student, index) => [student.id, index + 1]),
    );
    const columns: Array<ColumnDef<typeof features, DashboardAdviserStudent>> =
        [
            {
                id: 'number',
                header: 'S/N',
                enableSorting: false,
                cell: ({ row }) => rosterNumberById.get(row.original.id),
            },
            {
                id: 'student',
                accessorKey: 'fullName',
                header: 'Student',
                cell: ({ row }) => (
                    <PersonCell
                        name={row.original.fullName}
                        avatar={row.original.avatar}
                        href={row.original.profileUrl ?? undefined}
                    />
                ),
            },
            {
                id: 'registration',
                accessorKey: 'registrationNumber',
                header: 'Reg. No.',
                enableSorting: false,
                cell: ({ row }) => (
                    <CopyableValue value={row.original.registrationNumber} />
                ),
            },
            {
                id: 'email',
                accessorKey: 'email',
                header: 'Email',
                enableSorting: false,
                cell: ({ row }) => (
                    <div className="flex flex-wrap items-center gap-2">
                        {row.original.email ? (
                            <CopyableValue value={row.original.email} />
                        ) : !row.original.emailUnverified ? (
                            <span className="text-muted-foreground">-</span>
                        ) : null}
                        {row.original.emailUnverified && (
                            <>
                                {row.original.pendingEmail &&
                                    row.original.email && (
                                        <span className="text-xs text-muted-foreground">
                                            New address
                                        </span>
                                    )}
                                <Badge className="bg-amber-500/10 text-amber-700 dark:text-amber-400">
                                    Unverified
                                </Badge>
                            </>
                        )}
                    </div>
                ),
            },
            {
                id: 'cgpa',
                accessorKey: 'cgpa',
                header: 'CGPA',
                cell: ({ row }) => row.original.cgpa?.toFixed(2) ?? '-',
            },
            {
                id: 'account',
                accessorKey: 'accountStatus',
                header: 'Account',
                enableSorting: false,
                cell: ({ row }) => (
                    <div className="flex items-center justify-end gap-3">
                        {row.original.accountStatus === 'review' ? (
                            <ReviewActions student={row.original} />
                        ) : (
                            <AccountState student={row.original} />
                        )}
                        <Button
                            size="sm"
                            variant="link"
                            className="px-0 text-link"
                            onClick={() =>
                                setEditing(row.original.registrationRouteValue)
                            }
                        >
                            Edit
                        </Button>
                    </div>
                ),
            },
        ];
    const table = useTable({ features, columns, data });

    if (!selectedSet) {
        return (
            <p className="text-sm text-muted-foreground">
                Create a student set to start a class list.
            </p>
        );
    }

    return (
        <AdviserSetWorkspace studentSet={selectedSet} activeSection="students">
            <div className="mx-auto flex min-h-0 w-full max-w-6xl flex-1 flex-col">
                <div className="flex flex-wrap items-center justify-between gap-3 py-4">
                    <p className="text-xs text-muted-foreground">
                        {data.length} of {students.students.length} students
                    </p>
                    <div className="flex items-center gap-3">
                        <div className="relative w-52 sm:w-64">
                            <RiSearchLine className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                aria-label="Search students"
                                placeholder="Search students"
                                className="pl-9"
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                            />
                        </div>
                        <Button size="sm" onClick={() => setEditing('new')}>
                            <RiAddLine className="size-4" />
                            Add student
                        </Button>
                    </div>
                </div>
                <ScrollArea className="h-0 min-h-0 w-full flex-1">
                    <Table className="min-w-[850px] text-sm">
                        <TableHeader>
                            {table.getHeaderGroups().map((group) => (
                                <TableRow key={group.id}>
                                    {group.headers.map((header) => (
                                        <TableHead
                                            key={header.id}
                                            className={
                                                header.column.id === 'account'
                                                    ? 'pr-4 text-right'
                                                    : header.column.id ===
                                                        'number'
                                                      ? 'w-12 text-center'
                                                      : ''
                                            }
                                        >
                                            {header.isPlaceholder ? null : header.column.getCanSort() ? (
                                                <button
                                                    type="button"
                                                    onClick={header.column.getToggleSortingHandler()}
                                                    className="font-medium hover:text-link"
                                                >
                                                    <table.FlexRender
                                                        header={header}
                                                    />{' '}
                                                    {header.column.getIsSorted() ===
                                                    'asc'
                                                        ? '↑'
                                                        : header.column.getIsSorted() ===
                                                            'desc'
                                                          ? '↓'
                                                          : ''}
                                                </button>
                                            ) : (
                                                <table.FlexRender
                                                    header={header}
                                                />
                                            )}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            ))}
                        </TableHeader>
                        <TableBody>
                            {editing === 'new' && (
                                <RosterEditor
                                    set={selectedSet}
                                    onCancel={() => setEditing(null)}
                                />
                            )}
                            {table.getRowModel().rows.map((row) =>
                                editing ===
                                row.original.registrationRouteValue ? (
                                    <RosterEditor
                                        key={row.id}
                                        set={selectedSet}
                                        student={row.original}
                                        onCancel={() => setEditing(null)}
                                    />
                                ) : (
                                    <TableRow
                                        key={row.id}
                                        className="odd:bg-background even:bg-muted/25"
                                    >
                                        {row.getAllCells().map((cell) => (
                                            <TableCell
                                                key={cell.id}
                                                className={
                                                    cell.column.id === 'account'
                                                        ? 'pr-4 text-right'
                                                        : cell.column.id ===
                                                            'number'
                                                          ? 'text-center text-muted-foreground tabular-nums'
                                                          : ''
                                                }
                                            >
                                                {cell.column.id === 'number' ? (
                                                    rosterNumberById.get(
                                                        row.original.id,
                                                    )
                                                ) : (
                                                    <table.FlexRender
                                                        cell={cell}
                                                    />
                                                )}
                                            </TableCell>
                                        ))}
                                    </TableRow>
                                ),
                            )}
                            {table.getRowModel().rows.length === 0 &&
                                editing !== 'new' && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={6}
                                            className="py-10 text-center text-muted-foreground"
                                        >
                                            {students.students.length === 0
                                                ? 'No students in this set yet.'
                                                : 'No students match your search.'}
                                        </TableCell>
                                    </TableRow>
                                )}
                        </TableBody>
                    </Table>
                    <ScrollBar orientation="horizontal" />
                </ScrollArea>
            </div>
        </AdviserSetWorkspace>
    );
}
