import {
    createStudentSet,
    paymentHistory,
    registrationActivity,
} from '@/actions/App/Http/Controllers/DashboardController';
import { ActionLink } from '@/components/action-link';
import {
    ActivityPaymentsTable,
    ActivityRegistrationsTable,
} from '@/components/adviser-activity-tables';
import { JoinRequestsTable } from '@/components/adviser-join-requests-dashboard';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyTitle,
} from '@/components/ui/empty';
import { index as joinRequests } from '@/routes/student-joins/adviser';
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
                            Course Registrations to Review
                        </h2>
                        <span className="text-sm text-muted-foreground tabular-nums">
                            {home.pendingCount} waiting
                        </span>
                    </header>
                    <div className="border-b">
                        <ActivityRegistrationsTable
                            registrations={home.pendingRegistrations}
                            compact
                        />
                    </div>
                    {home.pendingCount > home.pendingRegistrations.length && (
                        <footer className="flex justify-end px-3 py-3">
                            <ActionLink
                                href={registrationActivity({
                                    query: { status: 'submitted' },
                                })}
                            >
                                +
                                {home.pendingCount -
                                    home.pendingRegistrations.length}{' '}
                                more
                            </ActionLink>
                        </footer>
                    )}
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
                        <ActionLink href={joinRequests()}>View all</ActionLink>
                    </header>
                    <div className="border-b">
                        <JoinRequestsTable
                            requests={home.recentRequests}
                            compact
                        />
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
