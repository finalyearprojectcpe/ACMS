import {
    RiArrowDownLine,
    RiArrowUpDownLine,
    RiArrowUpLine,
} from '@remixicon/react';
import {
    createSortedRowModel,
    rowSortingFeature,
    tableFeatures,
    useTable,
} from '@tanstack/react-table';
import type { ColumnDef } from '@tanstack/react-table';

import { ActionLink } from '@/components/action-link';
import { DueStatusBadge } from '@/components/adviser-due-ui';
import { TableEmptyRow } from '@/components/table-empty-row';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { DashboardAdviserDueSummary } from '@/types';

const features = tableFeatures({
    rowSortingFeature,
    sortedRowModel: createSortedRowModel(),
});

const columns: Array<ColumnDef<typeof features, DashboardAdviserDueSummary>> = [
    {
        id: 'due',
        accessorKey: 'title',
        header: 'Due',
        enableSorting: false,
        cell: ({ row }) => (
            <div className="grid justify-items-start gap-1">
                <ActionLink href={row.original.detailUrl}>
                    {row.original.title}
                </ActionLink>
                <span className="sm:hidden">
                    <DueStatusBadge status={row.original.status} />
                </span>
            </div>
        ),
    },
    {
        id: 'period',
        accessorFn: (due) => due.periodParts.levelNumber,
        header: 'Level / Semester',
        sortDescFirst: false,
        sortFn: (left, right) => {
            const a = left.original.periodParts;
            const b = right.original.periodParts;

            return (
                a.levelNumber - b.levelNumber ||
                a.sessionStartYear - b.sessionStartYear ||
                a.semesterOrder - b.semesterOrder ||
                left.original.title.localeCompare(right.original.title)
            );
        },
        cell: ({ row }) => (
            <div className="grid gap-0.5 leading-snug">
                <span className="font-medium">
                    {row.original.periodParts.level}
                </span>
                <span className="text-xs text-muted-foreground">
                    {row.original.periodParts.session}
                </span>
                <span className="text-xs text-muted-foreground">
                    {row.original.periodParts.semester}
                </span>
            </div>
        ),
    },
    {
        id: 'amount',
        accessorKey: 'amount',
        header: 'Amount',
        enableSorting: false,
    },
    {
        id: 'paid',
        accessorKey: 'paidCount',
        header: 'Paid',
        enableSorting: false,
    },
    {
        id: 'outstanding',
        accessorKey: 'outstandingAmount',
        header: 'Outstanding',
        enableSorting: false,
    },
    {
        id: 'status',
        accessorKey: 'status',
        header: 'Status',
        enableSorting: false,
        cell: ({ row }) => <DueStatusBadge status={row.original.status} />,
    },
];

function columnClassName(id: string): string {
    switch (id) {
        case 'due':
            return 'px-4 sm:px-5 whitespace-normal';
        case 'period':
            return 'w-36 whitespace-normal sm:w-44';
        case 'amount':
            return 'w-24 text-right tabular-nums';
        case 'paid':
            return 'hidden w-16 text-center tabular-nums sm:table-cell';
        case 'outstanding':
            return 'hidden w-32 text-right tabular-nums sm:table-cell';
        case 'status':
            return 'hidden w-24 pr-4 text-right sm:table-cell sm:pr-5';
        default:
            return '';
    }
}

export function AdviserDuesTable({
    dues,
}: {
    dues: DashboardAdviserDueSummary[];
}) {
    const table = useTable({ features, columns, data: dues });

    return (
        <Table className="text-sm">
            <TableHeader>
                {table.getHeaderGroups().map((group) => (
                    <TableRow key={group.id}>
                        {group.headers.map((header) => {
                            const sorted = header.column.getIsSorted();

                            return (
                                <TableHead
                                    key={header.id}
                                    className={columnClassName(
                                        header.column.id,
                                    )}
                                    aria-sort={
                                        header.column.id === 'period'
                                            ? sorted === 'asc'
                                                ? 'ascending'
                                                : sorted === 'desc'
                                                  ? 'descending'
                                                  : 'none'
                                            : undefined
                                    }
                                >
                                    {header.column.id === 'period' ? (
                                        <button
                                            type="button"
                                            className="inline-flex items-center gap-1.5 text-left font-medium text-foreground hover:text-link focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-hidden"
                                            onClick={header.column.getToggleSortingHandler()}
                                        >
                                            Level / Semester
                                            {sorted === 'asc' ? (
                                                <RiArrowUpLine className="size-3.5" />
                                            ) : sorted === 'desc' ? (
                                                <RiArrowDownLine className="size-3.5" />
                                            ) : (
                                                <RiArrowUpDownLine className="size-3.5 text-muted-foreground" />
                                            )}
                                        </button>
                                    ) : header.isPlaceholder ? null : (
                                        <table.FlexRender header={header} />
                                    )}
                                </TableHead>
                            );
                        })}
                    </TableRow>
                ))}
            </TableHeader>
            <TableBody>
                {table.getRowModel().rows.length ? (
                    table.getRowModel().rows.map((row) => (
                        <TableRow
                            key={row.id}
                            className="odd:bg-background even:bg-muted/25"
                        >
                            {row.getAllCells().map((cell) => (
                                <TableCell
                                    key={cell.id}
                                    className={cn(
                                        columnClassName(cell.column.id),
                                        cell.column.id === 'amount' &&
                                            'font-medium',
                                    )}
                                >
                                    <table.FlexRender cell={cell} />
                                </TableCell>
                            ))}
                        </TableRow>
                    ))
                ) : (
                    <TableEmptyRow colSpan={columns.length} className="py-10">
                        No dues in this view.
                    </TableEmptyRow>
                )}
            </TableBody>
        </Table>
    );
}
