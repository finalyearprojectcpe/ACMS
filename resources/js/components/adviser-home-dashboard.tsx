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
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Empty,
    EmptyContent,
    EmptyDescription,
    EmptyHeader,
    EmptyTitle,
} from '@/components/ui/empty';
import { cn } from '@/lib/utils';
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

    const hasRegistrations = home.pendingRegistrations.length > 0;
    const hasPayments = home.recentPayments.length > 0;

    if (!hasRegistrations && !hasPayments) {
        return (
            <Empty className="mx-auto min-h-48 w-full max-w-6xl border border-border bg-background">
                <EmptyHeader>
                    <EmptyTitle>No recent activity</EmptyTitle>
                </EmptyHeader>
            </Empty>
        );
    }

    return (
        <Card className="mx-auto w-full max-w-6xl gap-0 overflow-hidden border-border bg-background py-0 shadow-sm">
            <div
                className={cn(
                    'grid',
                    hasRegistrations && hasPayments && '2xl:grid-cols-2',
                )}
            >
                {hasRegistrations && (
                    <section className="min-w-0">
                        <CardHeader className="flex flex-row items-center justify-between gap-3 border-b border-border p-4 sm:px-5">
                            <CardTitle className="text-base">
                                Course Registrations to Review
                            </CardTitle>
                            <span className="text-sm text-muted-foreground tabular-nums">
                                {home.pendingCount} waiting
                            </span>
                        </CardHeader>
                        <CardContent className="p-0">
                            <ActivityRegistrationsTable
                                registrations={home.pendingRegistrations}
                                compact
                            />
                        </CardContent>
                        {home.pendingCount >
                            home.pendingRegistrations.length && (
                            <CardFooter className="justify-end border-t p-3 px-5">
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
                            </CardFooter>
                        )}
                    </section>
                )}
                {hasPayments && (
                    <section
                        className={cn(
                            'min-w-0',
                            hasRegistrations &&
                                'border-t border-border 2xl:border-t-0 2xl:border-l',
                        )}
                    >
                        <CardHeader className="border-b border-border p-4 sm:px-5">
                            <CardTitle className="text-base">
                                Recent Dues Payments
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <ActivityPaymentsTable
                                payments={home.recentPayments}
                                compact
                            />
                        </CardContent>
                        {home.paymentCount > home.recentPayments.length && (
                            <CardFooter className="justify-end border-t p-3 px-5">
                                <ActionLink href={paymentHistory()}>
                                    +
                                    {home.paymentCount -
                                        home.recentPayments.length}{' '}
                                    more
                                </ActionLink>
                            </CardFooter>
                        )}
                    </section>
                )}
            </div>
        </Card>
    );
}
