import { Link } from '@inertiajs/react';
import { RiFileExcel2Line, RiSearchLine } from '@remixicon/react';
import { useMemo, useState } from 'react';

import {
    createResultSheetImport,
    showResultSheet,
} from '@/actions/App/Http/Controllers/DashboardController';
import { ActionLink } from '@/components/action-link';
import { qualifiedSemesterLabel } from '@/components/qualified-semester';
import { StudentSetChoiceLabel } from '@/components/student-set-label';
import { StudentSetPicker } from '@/components/student-set-picker';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { DashboardResultSheet, DashboardStaffResults } from '@/types';

type StaffResultsDashboardProps = {
    results: DashboardStaffResults;
};

export function StaffResultsDashboard({ results }: StaffResultsDashboardProps) {
    const [search, setSearch] = useState('');
    const [setFilter, setSetFilter] = useState(
        results.assignedSets[0]?.entrySession.id.toString() ?? 'all',
    );
    const [statusFilter, setStatusFilter] = useState('all');
    const statusOptions = useMemo(
        () => unique(results.resultSheets.map((sheet) => sheet.statusLabel)),
        [results.resultSheets],
    );
    const visibleSheets = useMemo(
        () =>
            results.resultSheets.filter((sheet) => {
                const text = [
                    qualifiedSemesterLabel(
                        sheet.level.title,
                        sheet.semester.name,
                    ),
                    sheet.academicSession.name,
                    sheet.entrySession.name,
                    sheet.statusLabel,
                ]
                    .join(' ')
                    .toLowerCase();

                return (
                    text.includes(search.trim().toLowerCase()) &&
                    (setFilter === 'all' ||
                        sheet.entrySession.id.toString() === setFilter) &&
                    (statusFilter === 'all' ||
                        sheet.statusLabel === statusFilter)
                );
            }),
        [results.resultSheets, search, setFilter, statusFilter],
    );

    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <h1 className="text-xl font-semibold tracking-normal">
                        Result Management
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Import, review, and follow result sheets for the
                        student sets assigned to you.
                    </p>
                </div>
                <Button asChild>
                    <Link href={createResultSheetImport()}>
                        <RiFileExcel2Line />
                        Import Results from Spreadsheet
                    </Link>
                </Button>
            </div>

            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardHeader className="border-b border-border">
                    <CardTitle>Result Sheets</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-4 p-4">
                    <div className="grid gap-3 lg:grid-cols-[minmax(0,1.35fr)_minmax(20rem,24rem)_minmax(0,1fr)]">
                        <div className="relative">
                            <RiSearchLine className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search result sheets"
                                className="pl-9"
                            />
                        </div>
                        <StudentSetPicker
                            value={setFilter}
                            onValueChange={setSetFilter}
                            options={results.assignedSets.map((set) => ({
                                value: set.entrySession.id.toString(),
                                entrySession: set.entrySession,
                                currentLevel: set.currentLevel,
                                minimumYearsToDegree:
                                    results.department?.minimumYearsToDegree,
                            }))}
                            allOption={{ value: 'all', label: 'All sets' }}
                            ariaLabel="Filter by set"
                        />
                        <Select
                            value={statusFilter}
                            onValueChange={setStatusFilter}
                        >
                            <SelectTrigger aria-label="Filter by status">
                                <SelectValue placeholder="All statuses" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">
                                    All statuses
                                </SelectItem>
                                {statusOptions.map((status) => (
                                    <SelectItem key={status} value={status}>
                                        {status}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <ResultsTable sheets={visibleSheets} results={results} />
                </CardContent>
            </Card>
        </div>
    );
}

function ResultsTable({
    sheets,
    results,
}: {
    sheets: DashboardResultSheet[];
    results: DashboardStaffResults;
}) {
    return (
        <div className="border border-border bg-background">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead className="px-4">Result Sheet</TableHead>
                        <TableHead>Set</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead className="text-center">Students</TableHead>
                        <TableHead className="text-center">Courses</TableHead>
                        <TableHead className="text-center">Avg GPA</TableHead>
                        <TableHead className="pr-4 text-right">
                            Action
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {sheets.length > 0 ? (
                        sheets.map((sheet) => (
                            <TableRow key={sheet.id}>
                                <TableCell className="px-4">
                                    <div className="font-semibold">
                                        {qualifiedSemesterLabel(
                                            sheet.level.title,
                                            sheet.semester.name,
                                        )}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {sheet.academicSession.name} Session
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <StudentSetChoiceLabel
                                        entrySession={sheet.entrySession}
                                        currentLevel={
                                            setContextForSheet(sheet, results)
                                                ?.currentLevel ?? sheet.level
                                        }
                                        minimumYearsToDegree={
                                            setContextForSheet(sheet, results)
                                                ?.minimumYearsToDegree ??
                                            results.department
                                                ?.minimumYearsToDegree
                                        }
                                    />
                                </TableCell>
                                <TableCell>
                                    <ResultSheetStatusBadge
                                        status={sheet.status}
                                        label={sheet.statusLabel}
                                    />
                                </TableCell>
                                <TableCell className="text-center font-medium tabular-nums">
                                    {sheet.studentCount}
                                </TableCell>
                                <TableCell className="text-center font-medium tabular-nums">
                                    {sheet.courseCount}
                                </TableCell>
                                <TableCell className="text-center font-medium tabular-nums">
                                    {sheet.averageGpa}
                                </TableCell>
                                <TableCell className="pr-4 text-right">
                                    <ActionLink
                                        href={showResultSheet({
                                            resultSheet: sheet.id,
                                        })}
                                    >
                                        View Sheet
                                    </ActionLink>
                                </TableCell>
                            </TableRow>
                        ))
                    ) : (
                        <TableRow>
                            <TableCell
                                colSpan={7}
                                className="px-4 py-8 text-muted-foreground"
                            >
                                No result sheets match these filters.
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
        </div>
    );
}

function ResultSheetStatusBadge({
    status,
    label,
}: {
    status: string;
    label: string;
}) {
    return (
        <Badge
            variant="outline"
            className={
                status === 'approved'
                    ? 'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300'
                    : status === 'submitted'
                      ? 'border-sky-300 bg-sky-50 text-sky-800 dark:border-sky-900 dark:bg-sky-950/40 dark:text-sky-300'
                      : status === 'returned'
                        ? 'border-amber-300 bg-amber-50 text-amber-800 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-300'
                        : 'border-border bg-muted/60 text-foreground'
            }
        >
            {label}
        </Badge>
    );
}

function unique(values: string[]): string[] {
    return [...new Set(values)].filter(Boolean);
}

function setContextForSheet(
    sheet: DashboardResultSheet,
    results: DashboardStaffResults,
) {
    return results.assignedSets.find(
        (set) => set.entrySession.id === sheet.entrySession.id,
    );
}
