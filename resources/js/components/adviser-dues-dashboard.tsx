import { Link } from '@inertiajs/react';
import { RiAddLine, RiErrorWarningLine } from '@remixicon/react';

import {
    createStudentSet,
    createStudentSetDue,
    createStudentSetSemesterDue,
    createStudentSetSessionDue,
    showStudentSetDues,
    showStudentSetSemesterDues,
    showStudentSetSessionDues,
} from '@/actions/App/Http/Controllers/DashboardController';
import { AcademicPeriodLayout } from '@/components/academic-period-layout';
import { AcademicPeriodNavigation } from '@/components/academic-period-navigation';
import { ActionLink } from '@/components/action-link';
import { AdviserDuesTable } from '@/components/adviser-dues-table';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
import { studentSetRouteParameters } from '@/lib/student-sets';
import type { DashboardAdviserDues } from '@/types';

export function AdviserDuesDashboard({ dues }: { dues: DashboardAdviserDues }) {
    if (!dues.selectedSet) {
        return (
            <Card className="mx-auto w-full max-w-4xl bg-background">
                <CardContent className="flex flex-col items-center gap-3 py-8 text-center text-sm text-muted-foreground">
                    <span>Add a student set before creating dues.</span>
                    <Button asChild size="sm">
                        <Link href={createStudentSet()}>Add New Set</Link>
                    </Button>
                </CardContent>
            </Card>
        );
    }

    const parameters = studentSetRouteParameters(dues.selectedSet);
    const selectedPeriodId =
        dues.selectedPeriod.kind === 'all' ? null : dues.selectedPeriod.id;
    const selectedSession = dues.sessions.find(
        (session) =>
            (dues.selectedPeriod.kind === 'session' &&
                session.id === selectedPeriodId) ||
            (dues.selectedPeriod.kind === 'semester' &&
                session.semesters.some(
                    (semester) => semester.id === selectedPeriodId,
                )),
    );
    const selectedSemester = selectedSession?.semesters.find(
        (semester) =>
            dues.selectedPeriod.kind === 'semester' &&
            semester.id === selectedPeriodId,
    );
    const createDueHref = selectedSession
        ? selectedSemester
            ? createStudentSetSemesterDue({
                  ...parameters,
                  sessionYears: selectedSession.routeYears,
                  semester: selectedSemester.slug,
              })
            : createStudentSetSessionDue({
                  ...parameters,
                  sessionYears: selectedSession.routeYears,
              })
        : createStudentSetDue(parameters);

    return (
        <div className="flex min-h-0 w-full flex-1 flex-col">
            <Card className="min-h-0 flex-1 gap-0 overflow-hidden border-0 bg-transparent py-0 shadow-none ring-0">
                <CardHeader className="border-b border-border px-0 py-4">
                    <CardTitle className="text-xl">
                        {dues.selectedSet.name}
                    </CardTitle>
                </CardHeader>
                <CardContent className="flex min-h-0 flex-1 flex-col p-0">
                    <AcademicPeriodLayout
                        navigation={(collapsed) => (
                            <AcademicPeriodNavigation
                                collapsed={collapsed}
                                overview={{
                                    key: 'all-dues',
                                    href: showStudentSetDues(parameters).url,
                                    label: 'All Dues',
                                    compactLabel: 'All',
                                    selected:
                                        dues.selectedPeriod.kind === 'all',
                                }}
                                ariaLabel="Due periods"
                                groups={dues.sessions.map((session) => ({
                                    key: session.routeYears,
                                    label: session.level,
                                    detail: `${session.name} Session`,
                                    compactLabel: session.level.replace(
                                        /\s*Level$/i,
                                        'L',
                                    ),
                                    links: [
                                        {
                                            key: `${session.routeYears}:whole`,
                                            href: showStudentSetSessionDues({
                                                ...parameters,
                                                sessionYears:
                                                    session.routeYears,
                                            }).url,
                                            label: 'Whole Session',
                                            compactLabel: 'Both',
                                            selected:
                                                dues.selectedPeriod.kind ===
                                                    'session' &&
                                                dues.selectedPeriod.id ===
                                                    session.id,
                                        },
                                        ...session.semesters.map(
                                            (semester) => ({
                                                key: `${session.routeYears}:${semester.slug}`,
                                                href: showStudentSetSemesterDues(
                                                    {
                                                        ...parameters,
                                                        sessionYears:
                                                            session.routeYears,
                                                        semester: semester.slug,
                                                    },
                                                ).url,
                                                label: semester.name,
                                                compactLabel:
                                                    semester.slug === 'first'
                                                        ? '1st'
                                                        : '2nd',
                                                selected:
                                                    dues.selectedPeriod.kind ===
                                                        'semester' &&
                                                    dues.selectedPeriod.id ===
                                                        semester.id,
                                            }),
                                        ),
                                    ],
                                }))}
                            />
                        )}
                    >
                        <div className="mx-auto flex min-h-0 w-full max-w-5xl min-w-0 flex-col md:pl-4">
                            <div className="flex shrink-0 flex-wrap items-baseline justify-between gap-x-5 gap-y-1 border-b border-border px-4 py-4">
                                <h2 className="text-base font-semibold">
                                    {dues.periodLabel}
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    {dues.dues.length}{' '}
                                    {dues.dues.length === 1 ? 'due' : 'dues'} ·
                                    Collected {dues.collectedAmount} ·
                                    Outstanding {dues.outstandingAmount}
                                </p>
                            </div>
                            {!dues.hasActivePaymentAccount ? (
                                <Alert className="m-4 w-auto shrink-0 border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
                                    <RiErrorWarningLine className="size-4" />
                                    <AlertTitle>
                                        Payout account needed
                                    </AlertTitle>
                                    <AlertDescription>
                                        Save dues as drafts, or{' '}
                                        <ActionLink
                                            href={dues.paymentSettingsUrl}
                                        >
                                            connect a payout account
                                        </ActionLink>{' '}
                                        to publish them.
                                    </AlertDescription>
                                </Alert>
                            ) : null}
                            <ScrollArea className="h-0 min-h-0 w-full flex-1">
                                <AdviserDuesTable dues={dues.dues} />
                            </ScrollArea>
                            <CardFooter className="shrink-0 justify-end border-t border-border px-4 py-3">
                                {dues.sessions.length > 0 ? (
                                    <Button size="sm" asChild>
                                        <Link href={createDueHref}>
                                            <RiAddLine /> Add Due
                                        </Link>
                                    </Button>
                                ) : null}
                            </CardFooter>
                        </div>
                    </AcademicPeriodLayout>
                </CardContent>
            </Card>
        </div>
    );
}
