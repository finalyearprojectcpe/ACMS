import { ActionLink } from '@/components/action-link';
import { PersonCell } from '@/components/person-cell';
import { TableEmptyRow } from '@/components/table-empty-row';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { DashboardAdviserHomePayment } from '@/types';

export function ActivityPaymentsTable({
    payments,
    showSet = false,
    compact = false,
    startNumber = 1,
}: {
    payments: DashboardAdviserHomePayment[];
    showSet?: boolean;
    compact?: boolean;
    startNumber?: number;
}) {
    return (
        <ScrollArea className="w-full">
            <Table
                className={
                    compact ? 'table-fixed text-sm' : 'min-w-[670px] text-sm'
                }
            >
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-10 text-center sm:w-12">
                            S/N
                        </TableHead>
                        <TableHead
                            className={compact ? 'px-2 sm:px-4' : 'px-5'}
                        >
                            Student
                        </TableHead>
                        {compact ? (
                            <TableHead className="hidden sm:table-cell sm:w-[42%]">
                                Payment
                            </TableHead>
                        ) : (
                            <>
                                <TableHead>Due</TableHead>
                                {showSet && <TableHead>Set</TableHead>}
                                <TableHead className="text-right">
                                    Paid
                                </TableHead>
                            </>
                        )}
                        <TableHead
                            className={
                                compact
                                    ? 'w-20 pr-4 text-right sm:w-24'
                                    : 'pr-5 text-right'
                            }
                        >
                            Receipt
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {payments.length ? (
                        payments.map((item, index) => (
                            <TableRow key={item.id} className="odd:bg-muted/25">
                                <TableCell className="text-center text-muted-foreground tabular-nums">
                                    {startNumber + index}
                                </TableCell>
                                <TableCell
                                    className={
                                        compact
                                            ? 'min-w-0 px-4 whitespace-normal'
                                            : 'px-5'
                                    }
                                >
                                    <PersonCell
                                        name={item.studentName}
                                        avatar={item.studentAvatar}
                                        href={item.studentUrl}
                                    />
                                    {compact && (
                                        <div className="mt-1 min-w-0 pl-11 sm:hidden">
                                            <ActionLink
                                                href={item.dueUrl}
                                                className="max-w-full text-xs whitespace-normal [&>span]:line-clamp-2"
                                            >
                                                {item.dueName}
                                            </ActionLink>
                                            <span className="block text-xs font-medium tabular-nums">
                                                {item.amount}
                                            </span>
                                        </div>
                                    )}
                                </TableCell>
                                {compact ? (
                                    <TableCell className="hidden min-w-0 whitespace-normal sm:table-cell">
                                        <ActionLink
                                            href={item.dueUrl}
                                            className="max-w-full whitespace-normal [&>span]:line-clamp-2"
                                        >
                                            {item.dueName}
                                        </ActionLink>
                                        <span className="block text-xs text-muted-foreground">
                                            {item.amount} · {item.paidAt}
                                        </span>
                                    </TableCell>
                                ) : (
                                    <>
                                        <TableCell>
                                            <ActionLink href={item.dueUrl}>
                                                {item.dueName}
                                            </ActionLink>
                                        </TableCell>
                                        {showSet && (
                                            <TableCell className="text-muted-foreground">
                                                {item.set}
                                            </TableCell>
                                        )}
                                        <TableCell className="text-right">
                                            <span className="font-semibold tabular-nums">
                                                {item.amount}
                                            </span>
                                            <span className="block text-xs text-muted-foreground">
                                                {item.paidAt}
                                            </span>
                                        </TableCell>
                                    </>
                                )}
                                <TableCell
                                    className={
                                        compact
                                            ? 'pr-4 text-right'
                                            : 'pr-5 text-right'
                                    }
                                >
                                    {item.receiptUrl ? (
                                        <a
                                            className="text-link hover:underline"
                                            href={item.receiptUrl}
                                        >
                                            Download
                                        </a>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            Preparing
                                        </span>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))
                    ) : (
                        <TableEmptyRow colSpan={compact ? 4 : showSet ? 6 : 5}>
                            No completed payments yet.
                        </TableEmptyRow>
                    )}
                </TableBody>
            </Table>
            {!compact && <ScrollBar orientation="horizontal" />}
        </ScrollArea>
    );
}
