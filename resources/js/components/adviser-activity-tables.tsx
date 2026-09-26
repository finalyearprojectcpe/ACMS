import { ActionLink } from '@/components/action-link';
import { CourseRegistrationStatusBadge } from '@/components/course-registration-status-badge';
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
import type {
    DashboardActivityRegistration,
    DashboardAdviserHomePayment,
} from '@/types';

export function ActivityRegistrationsTable({
    registrations,
    showStatus = false,
    compact = false,
}: {
    registrations: DashboardActivityRegistration[];
    showStatus?: boolean;
    compact?: boolean;
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
                        <TableHead
                            className={
                                compact ? 'w-[74%] px-4 sm:w-[44%]' : 'px-5'
                            }
                        >
                            Student
                        </TableHead>
                        <TableHead
                            className={
                                compact
                                    ? 'hidden sm:table-cell sm:w-[40%]'
                                    : undefined
                            }
                        >
                            Semester
                        </TableHead>
                        {showStatus && <TableHead>Status</TableHead>}
                        <TableHead
                            className={
                                compact
                                    ? 'w-[26%] pr-4 text-right sm:w-[16%]'
                                    : 'pr-5 text-right'
                            }
                        >
                            Action
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {registrations.length ? (
                        registrations.map((item) => (
                            <TableRow key={item.id} className="odd:bg-muted/25">
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
                                        secondary={item.set}
                                        href={item.studentUrl}
                                    />
                                    {compact && (
                                        <span className="mt-1 block pl-11 text-xs text-muted-foreground sm:hidden">
                                            {item.period}
                                            <span className="block">
                                                {item.submittedAt}
                                            </span>
                                        </span>
                                    )}
                                </TableCell>
                                <TableCell
                                    className={
                                        compact
                                            ? 'hidden whitespace-normal sm:table-cell'
                                            : undefined
                                    }
                                >
                                    <span
                                        className={
                                            compact
                                                ? 'line-clamp-2 font-medium'
                                                : 'font-medium'
                                        }
                                    >
                                        {item.period}
                                    </span>
                                    <span className="block text-xs text-muted-foreground">
                                        {item.submittedAt}
                                    </span>
                                </TableCell>
                                {showStatus && (
                                    <TableCell>
                                        <CourseRegistrationStatusBadge
                                            status={item.status}
                                        />
                                    </TableCell>
                                )}
                                <TableCell
                                    className={
                                        compact
                                            ? 'pr-4 text-right'
                                            : 'pr-5 text-right'
                                    }
                                >
                                    <ActionLink href={item.url}>
                                        {item.status === 'In review'
                                            ? 'Review'
                                            : 'View'}
                                    </ActionLink>
                                </TableCell>
                            </TableRow>
                        ))
                    ) : (
                        <TableEmptyRow colSpan={showStatus ? 4 : 3}>
                            No registrations in this view.
                        </TableEmptyRow>
                    )}
                </TableBody>
            </Table>
            {!compact && <ScrollBar orientation="horizontal" />}
        </ScrollArea>
    );
}

export function ActivityPaymentsTable({
    payments,
    showSet = false,
    compact = false,
}: {
    payments: DashboardAdviserHomePayment[];
    showSet?: boolean;
    compact?: boolean;
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
                        <TableHead
                            className={
                                compact ? 'w-[74%] px-4 sm:w-[40%]' : 'px-5'
                            }
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
                                    ? 'w-[26%] pr-4 text-right sm:w-[18%]'
                                    : 'pr-5 text-right'
                            }
                        >
                            Receipt
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {payments.length ? (
                        payments.map((item) => (
                            <TableRow key={item.id} className="odd:bg-muted/25">
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
                        <TableEmptyRow colSpan={showSet ? 5 : 4}>
                            No completed payments yet.
                        </TableEmptyRow>
                    )}
                </TableBody>
            </Table>
            {!compact && <ScrollBar orientation="horizontal" />}
        </ScrollArea>
    );
}
