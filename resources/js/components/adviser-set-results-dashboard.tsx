import { Link, router } from '@inertiajs/react';
import { RiSearchLine } from '@remixicon/react';
import { useVirtualizer } from '@tanstack/react-virtual';
import { useCallback, useMemo, useRef, useState } from 'react';
import type { PointerEvent } from 'react';

import { AcademicPeriodLayout } from '@/components/academic-period-layout';
import { AcademicPeriodNavigation } from '@/components/academic-period-navigation';
import {
    columnBoundary,
    SetResultRow,
} from '@/components/adviser-set-results-row';
import { DownloadButton } from '@/components/download-button';
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
import { Tabs, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { courseToneClasses } from '@/lib/course-presentation';
import { studentSetRouteParameters } from '@/lib/student-sets';
import {
    exportMethod as exportSetResults,
    show as showSetResults,
} from '@/routes/student-sets/matrix';
import type { DashboardAdviserSetResults } from '@/types';

function LegendToken({ symbol }: { symbol: string }) {
    return (
        <span
            aria-hidden="true"
            className="inline-flex h-5 min-w-5 items-center justify-center border border-border px-1 font-mono text-[11px] font-medium text-foreground"
        >
            {symbol}
        </span>
    );
}

export function AdviserSetResultsDashboard({
    matrix,
}: {
    matrix: DashboardAdviserSetResults;
}) {
    const [search, setSearch] = useState('');
    const [dragging, setDragging] = useState(false);
    const scrollArea = useRef<HTMLDivElement>(null);
    const drag = useRef<{
        pointerId: number;
        startX: number;
        startScrollLeft: number;
        viewport: HTMLElement;
        moved: boolean;
    } | null>(null);
    const normalizedSearch = search.trim().toLowerCase();
    const students = useMemo(
        () =>
            matrix.students.filter((student) =>
                `${student.name} ${student.registrationNumber}`
                    .toLowerCase()
                    .includes(normalizedSearch),
            ),
        [matrix.students, normalizedSearch],
    );
    const getScrollElement = useCallback(
        () =>
            scrollArea.current?.querySelector<HTMLElement>(
                '[data-slot="scroll-area-viewport"]',
            ) ?? null,
        [],
    );
    const rowVirtualizer = useVirtualizer({
        count: students.length,
        getScrollElement,
        estimateSize: () => 53,
        overscan: 5,
    });
    const visibleRows = rowVirtualizer.getVirtualItems();
    const topSpace = visibleRows[0]?.start ?? 0;
    const bottomSpace = Math.max(
        0,
        rowVirtualizer.getTotalSize() - (visibleRows.at(-1)?.end ?? 0),
    );
    const studentNumbers = useMemo(
        () =>
            new Map(
                matrix.students.map((student, index) => [
                    student.registrationNumber,
                    index + 1,
                ]),
            ),
        [matrix.students],
    );
    const groups = useMemo(
        () =>
            matrix.columns.reduce<
                Array<{ label: string; session: string; count: number }>
            >((items, column) => {
                const last = items[items.length - 1];

                if (last?.label === column.group) {
                    last.count += 1;
                } else {
                    items.push({
                        label: column.group,
                        session: column.session,
                        count: 1,
                    });
                }

                return items;
            }, []),
        [matrix.columns],
    );
    const selectedSession = matrix.periods.find((session) =>
        matrix.allHistory
            ? session.publicId === matrix.selectedSessionPublicId
            : session.semesters.some(
                  (semester) => semester.id === matrix.selectedSemesterId,
              ),
    );
    const selectedSemester = selectedSession?.semesters.find(
        (semester) => semester.id === matrix.selectedSemesterId,
    );
    const setRoute = studentSetRouteParameters(matrix.set);

    function exportUrl() {
        return exportSetResults.url(setRoute, {
            query: matrix.allHistory
                ? { all: 1 }
                : {
                      session: selectedSession?.publicId,
                      semester: selectedSemester?.publicId,
                  },
        });
    }

    function beginDrag(event: PointerEvent<HTMLDivElement>) {
        if (
            event.pointerType !== 'mouse' ||
            event.button !== 0 ||
            (event.target as Element).closest(
                'a, button, input, select, [data-slot="scroll-area-scrollbar"]',
            )
        ) {
            return;
        }

        const viewport = event.currentTarget.querySelector<HTMLElement>(
            '[data-slot="scroll-area-viewport"]',
        );

        if (!viewport || viewport.scrollWidth <= viewport.clientWidth) {
            return;
        }

        drag.current = {
            pointerId: event.pointerId,
            startX: event.clientX,
            startScrollLeft: viewport.scrollLeft,
            viewport,
            moved: false,
        };
        event.currentTarget.setPointerCapture(event.pointerId);
        event.preventDefault();
    }

    function moveDrag(event: PointerEvent<HTMLDivElement>) {
        const state = drag.current;

        if (!state || state.pointerId !== event.pointerId) {
            return;
        }

        const distance = event.clientX - state.startX;

        if (!state.moved && Math.abs(distance) < 4) {
            return;
        }

        state.moved = true;
        state.viewport.scrollLeft = state.startScrollLeft - distance;
        setDragging(true);
        event.preventDefault();
    }

    function endDrag(event: PointerEvent<HTMLDivElement>) {
        if (drag.current?.pointerId !== event.pointerId) {
            return;
        }

        drag.current = null;
        setDragging(false);

        if (event.currentTarget.hasPointerCapture(event.pointerId)) {
            event.currentTarget.releasePointerCapture(event.pointerId);
        }
    }

    return (
        <div className="flex min-h-0 min-w-0 flex-1 flex-col">
            <header className="flex shrink-0 flex-wrap items-end justify-between gap-3 border-b pb-5">
                <div>
                    <h1 className="text-xl font-semibold">Set Results</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {matrix.set.name}
                    </p>
                </div>
                <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto">
                    <div className="relative min-w-48 flex-1 sm:w-64">
                        <RiSearchLine className="absolute top-1/2 left-2 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            className="pl-8"
                            aria-label="Find a student"
                            placeholder="Find a student"
                            value={search}
                            onChange={(event) => {
                                setSearch(event.target.value);
                                rowVirtualizer.scrollToOffset(0);
                            }}
                        />
                    </div>
                    {(matrix.allHistory
                        ? matrix.exportAvailable
                        : matrix.columns.length > 0) && (
                        <DownloadButton href={exportUrl()}>
                            Export as XLSX
                        </DownloadButton>
                    )}
                </div>
            </header>
            <AcademicPeriodLayout
                navigation={(collapsed) => (
                    <AcademicPeriodNavigation
                        collapsed={collapsed}
                        overview={{
                            key: 'all-history',
                            href: matrix.allHistoryUrl,
                            label: 'All History',
                            compactLabel: 'All',
                            selected: matrix.allHistory,
                        }}
                        ariaLabel="Result periods"
                        groups={matrix.periods.map((session) => ({
                            key: session.publicId,
                            label: session.level.title,
                            detail: `${session.name} Session`,
                            compactLabel: `${session.level.yearNumber}00L`,
                            links: session.semesters.map((semester) => ({
                                key: `${session.publicId}:${semester.slug}`,
                                href: showSetResults.url(setRoute, {
                                    query: {
                                        session: session.publicId,
                                        semester: semester.slug,
                                    },
                                }),
                                label: semester.name,
                                compactLabel:
                                    semester.slug === 'first' ? '1st' : '2nd',
                                selected:
                                    semester.id === matrix.selectedSemesterId,
                            })),
                        }))}
                    />
                )}
            >
                <div className="flex min-h-0 min-w-0 flex-col md:pl-4">
                    {matrix.allHistory && (
                        <Tabs
                            value={matrix.selectedSessionPublicId ?? ''}
                            onValueChange={(session) => {
                                router.visit(
                                    showSetResults.url(setRoute, {
                                        query: { all: 1, session },
                                    }),
                                    { preserveScroll: true },
                                );
                            }}
                            className="shrink-0 border-b"
                        >
                            <ScrollArea className="w-full">
                                <TabsList
                                    variant="line"
                                    aria-label="Result sessions"
                                    className="w-max min-w-full justify-start gap-3 px-1 py-2 group-data-horizontal/tabs:h-auto"
                                >
                                    {matrix.periods.map((session) => (
                                        <TabsTrigger
                                            key={session.publicId}
                                            value={session.publicId}
                                            className="h-auto min-w-36 flex-none flex-col items-start gap-0 px-3 py-2 text-left"
                                        >
                                            <span className="text-sm font-semibold">
                                                {session.level.title}
                                            </span>
                                            <span className="text-xs font-normal text-muted-foreground">
                                                {session.name} Session
                                            </span>
                                        </TabsTrigger>
                                    ))}
                                </TabsList>
                                <ScrollBar orientation="horizontal" />
                            </ScrollArea>
                        </Tabs>
                    )}
                    <div
                        className={`flex shrink-0 flex-wrap items-center gap-2 ${matrix.allHistory ? 'justify-end py-3' : 'justify-between py-4'}`}
                    >
                        {!matrix.allHistory && (
                            <h2 className="font-semibold">
                                {selectedSession && selectedSemester
                                    ? `${selectedSession.level.title} · ${selectedSemester.name}`
                                    : 'Results'}
                            </h2>
                        )}
                        <span className="text-sm text-muted-foreground tabular-nums">
                            {students.length}{' '}
                            {students.length === 1 ? 'student' : 'students'} ·{' '}
                            {matrix.columns.length}{' '}
                            {matrix.columns.length === 1 ? 'course' : 'courses'}
                        </span>
                    </div>
                    <div
                        className="flex shrink-0 flex-wrap items-center gap-x-4 gap-y-1 pb-3 text-xs text-muted-foreground"
                        aria-label="Result table key"
                    >
                        <span className="inline-flex items-center gap-1.5">
                            <span
                                className="size-2.5 bg-amber-400/40"
                                aria-hidden="true"
                            />
                            Elective
                        </span>
                        <span className="inline-flex items-center gap-1.5">
                            <span
                                className="size-2.5 bg-red-500/35"
                                aria-hidden="true"
                            />
                            Carryover
                        </span>
                        <span className="inline-flex items-center gap-1.5">
                            <LegendToken symbol="·" /> Awaiting result
                        </span>
                        <span className="inline-flex items-center gap-1.5">
                            <LegendToken symbol="*" /> Entered, not published
                        </span>
                        <span className="inline-flex items-center gap-1.5">
                            <LegendToken symbol="—" /> Not registered
                        </span>
                        <span className="inline-flex items-center gap-1.5">
                            <LegendToken symbol="MS" /> Missing script
                        </span>
                    </div>
                    <ScrollArea
                        ref={scrollArea}
                        className={`min-h-0 min-w-0 flex-1 border-y ${dragging ? 'cursor-grabbing select-none' : 'cursor-grab'}`}
                        onPointerDown={beginDrag}
                        onPointerMove={moveDrag}
                        onPointerUp={endDrag}
                        onPointerCancel={endDrag}
                    >
                        <Table
                            containerClassName="w-max min-w-full overflow-visible"
                            className="w-max min-w-full text-sm"
                        >
                            <TableHeader className="sticky top-0 z-30 bg-background">
                                <TableRow>
                                    <TableHead
                                        rowSpan={3}
                                        className="sticky left-0 z-20 w-12 min-w-12 border-r bg-background px-1 text-center"
                                    >
                                        S/N
                                    </TableHead>
                                    <TableHead
                                        rowSpan={3}
                                        className="sticky left-12 z-20 w-40 min-w-40 border-r-[3px] border-r-foreground/30 bg-background px-2 md:w-56 md:min-w-56 md:px-3"
                                    >
                                        Student
                                    </TableHead>
                                    {groups.map((group, index) => (
                                        <TableHead
                                            key={group.label}
                                            colSpan={group.count}
                                            className={`text-center font-semibold text-foreground ${groups[index + 1]?.session !== group.session ? 'border-r-[3px] border-r-link/35 dark:border-r-link/45' : 'border-r-2 border-r-border'}`}
                                        >
                                            {group.label}
                                        </TableHead>
                                    ))}
                                    <TableHead
                                        rowSpan={3}
                                        className="w-16 text-center"
                                    >
                                        <abbr
                                            title="Total Grade Points"
                                            aria-label="Total Grade Points"
                                            className="cursor-help no-underline"
                                        >
                                            TGP
                                        </abbr>
                                    </TableHead>
                                    <TableHead
                                        rowSpan={3}
                                        className="w-16 text-center"
                                    >
                                        <abbr
                                            title="Total Number of Units"
                                            aria-label="Total Number of Units"
                                            className="cursor-help no-underline"
                                        >
                                            TNU
                                        </abbr>
                                    </TableHead>
                                    <TableHead
                                        rowSpan={3}
                                        className="w-20 min-w-20 bg-background text-center lg:sticky lg:right-40 lg:z-20 lg:border-l-[3px] lg:border-l-foreground/30"
                                    >
                                        <abbr
                                            title="Grade Point Average"
                                            aria-label="Grade Point Average"
                                            className="cursor-help no-underline"
                                        >
                                            GPA
                                        </abbr>
                                    </TableHead>
                                    <TableHead
                                        rowSpan={3}
                                        className="w-20 min-w-20 bg-background text-center lg:sticky lg:right-20 lg:z-20"
                                    >
                                        <abbr
                                            title="Cumulative Grade Point Average"
                                            aria-label="Cumulative Grade Point Average"
                                            className="cursor-help no-underline"
                                        >
                                            CGPA
                                        </abbr>
                                    </TableHead>
                                    <TableHead
                                        rowSpan={3}
                                        className="w-20 min-w-20 bg-background text-center lg:sticky lg:right-0 lg:z-20"
                                    >
                                        <abbr
                                            title="Current Standing"
                                            className="cursor-help no-underline"
                                        >
                                            Standing
                                        </abbr>
                                    </TableHead>
                                </TableRow>
                                <TableRow className="border-b-0">
                                    {matrix.columns.map((column, index) => {
                                        const [subject, ...number] =
                                            column.code.split(/\s+/);

                                        return (
                                            <TableHead
                                                key={column.id}
                                                className={`w-16 min-w-16 px-1 text-center ${columnBoundary(matrix.columns, index)} ${courseToneClasses.header[column.carryoverOnly ? 'carryover' : column.isElective ? 'elective' : 'normal']}`}
                                            >
                                                <Link
                                                    href={column.url}
                                                    title={column.title}
                                                    aria-label={`${column.code}: ${column.title}${column.carryoverOnly ? ', carryover' : column.isElective ? ', elective' : ''}`}
                                                    className="flex flex-col items-center leading-4 break-all text-muted-foreground hover:text-link hover:underline"
                                                >
                                                    <span className="font-medium">
                                                        {subject}
                                                    </span>
                                                    {number.length > 0 && (
                                                        <span className="text-xs">
                                                            {number.join(' ')}
                                                        </span>
                                                    )}
                                                </Link>
                                            </TableHead>
                                        );
                                    })}
                                </TableRow>
                                <TableRow>
                                    {matrix.columns.map((column, index) => (
                                        <TableHead
                                            key={column.id}
                                            aria-label={`${column.code}: ${column.units} ${column.units === 1 ? 'unit' : 'units'}`}
                                            className={`h-6 w-16 min-w-16 px-1 py-1 text-center text-[11px] font-normal text-muted-foreground tabular-nums ${columnBoundary(matrix.columns, index)} ${courseToneClasses.header[column.carryoverOnly ? 'carryover' : column.isElective ? 'elective' : 'normal']}`}
                                        >
                                            {column.units}{' '}
                                            {column.units === 1
                                                ? 'unit'
                                                : 'units'}
                                        </TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {students.length ? (
                                    <>
                                        {topSpace > 0 && (
                                            <TableRow
                                                aria-hidden="true"
                                                className="bg-background"
                                            >
                                                <TableCell
                                                    colSpan={
                                                        matrix.columns.length +
                                                        7
                                                    }
                                                    className="border-0 p-0"
                                                    style={{ height: topSpace }}
                                                />
                                            </TableRow>
                                        )}
                                        {visibleRows.map((virtualRow) => (
                                            <SetResultRow
                                                key={
                                                    students[virtualRow.index]
                                                        .registrationNumber
                                                }
                                                student={
                                                    students[virtualRow.index]
                                                }
                                                columns={matrix.columns}
                                                index={virtualRow.index}
                                                measureRow={
                                                    rowVirtualizer.measureElement
                                                }
                                                number={
                                                    studentNumbers.get(
                                                        students[
                                                            virtualRow.index
                                                        ].registrationNumber,
                                                    ) ?? 0
                                                }
                                            />
                                        ))}
                                        {bottomSpace > 0 && (
                                            <TableRow
                                                aria-hidden="true"
                                                className="bg-background"
                                            >
                                                <TableCell
                                                    colSpan={
                                                        matrix.columns.length +
                                                        7
                                                    }
                                                    className="border-0 p-0"
                                                    style={{
                                                        height: bottomSpace,
                                                    }}
                                                />
                                            </TableRow>
                                        )}
                                    </>
                                ) : (
                                    <TableRow>
                                        <TableCell
                                            colSpan={matrix.columns.length + 7}
                                            className="py-10 text-center text-muted-foreground"
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
                </div>
            </AcademicPeriodLayout>
        </div>
    );
}
