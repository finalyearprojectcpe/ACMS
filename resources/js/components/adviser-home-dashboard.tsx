import {
    createStudentSet,
    paymentHistory,
} from '@/actions/App/Http/Controllers/DashboardController';
import { ActionLink } from '@/components/action-link';
import { ActivityPaymentsTable } from '@/components/adviser-activity-tables';
import { JoinRequestsTable } from '@/components/adviser-join-requests-dashboard';
import { PersonCell } from '@/components/person-cell';
import { TableEmptyRow } from '@/components/table-empty-row';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { DashboardAdviserHome } from '@/types';

export function AdviserHomeDashboard({ home }: { home: DashboardAdviserHome }) {
    if (!home.hasSets) {
        return (
            <Empty className="mx-auto min-h-80 w-full max-w-4xl border border-border bg-background shadow-sm">
                <EmptyHeader>
                    <EmptyTitle>Set up your workspace</EmptyTitle>
                    <EmptyDescription>
                        Create a student set, then invite your students to join.
                    </EmptyDescription>
                </EmptyHeader>
                <EmptyContent>
                    <ActionLink href={createStudentSet()}>
                        Create Student Set
                    </ActionLink>
                </EmptyContent>
            </Empty>
        );
    }

    return (
        <div className="mx-auto w-full max-w-6xl">
            <div className="grid gap-8">
                <section className="min-w-0">
                    <header className="flex flex-row items-center justify-between gap-3 border-b border-border px-2 py-4 sm:px-3">
                        <h2 className="text-base font-semibold">
                            Students Needing Courses
                        </h2>
                        <span className="text-sm text-muted-foreground tabular-nums">
                            {home.unassignedCount} unassigned
                        </span>
                    </header>
                    <div className="border-b">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-12 text-center">
                                        S/N
                                    </TableHead>
                                    <TableHead>Student</TableHead>
                                    <TableHead>Semester</TableHead>
                                    <TableHead className="text-right">
                                        Action
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {home.unassignedStudents.length === 0 ? (
                                    <TableEmptyRow colSpan={4}>
                                        All current students have courses
                                        assigned.
                                    </TableEmptyRow>
                                ) : (
                                    home.unassignedStudents.map(
                                        (student, index) => (
                                            <TableRow key={student.url}>
                                                <TableCell className="text-center text-muted-foreground tabular-nums">
                                                    {index + 1}
                                                </TableCell>
                                                <TableCell>
                                                    <PersonCell
                                                        name={student.name}
                                                        avatar={student.avatar}
                                                        secondary={`${student.registrationNumber} · ${student.set}`}
                                                        href={
                                                            student.profileUrl
                                                        }
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    {student.period}
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <ActionLink
                                                        href={student.url}
                                                    >
                                                        Assign Courses
                                                    </ActionLink>
                                                </TableCell>
                                            </TableRow>
                                        ),
                                    )
                                )}
                            </TableBody>
                        </Table>
                    </div>
                </section>
                <section className="min-w-0">
                    <header className="flex items-center justify-between gap-3 border-b border-border px-2 py-4 sm:px-3">
                        <div>
                            <h2 className="text-base font-semibold">
                                Student Join Requests
                            </h2>
                            <span className="text-sm text-muted-foreground tabular-nums">
                                {home.requestCount} pending
                            </span>
                        </div>
                    </header>
                    <div className="border-b">
                        <JoinRequestsTable requests={home.recentRequests} />
                    </div>
                </section>
                <section className="min-w-0">
                    <header className="border-b border-border px-2 py-4 sm:px-3">
                        <h2 className="text-base font-semibold">
                            Recent Dues Payments
                        </h2>
                    </header>
                    <div className="border-b">
                        <ActivityPaymentsTable
                            payments={home.recentPayments}
                            compact
                        />
                    </div>
                    {home.paymentCount > home.recentPayments.length && (
                        <footer className="flex justify-end px-3 py-3">
                            <ActionLink href={paymentHistory()}>
                                +
                                {home.paymentCount - home.recentPayments.length}{' '}
                                more
                            </ActionLink>
                        </footer>
                    )}
                </section>
            </div>
        </div>
    );
}
