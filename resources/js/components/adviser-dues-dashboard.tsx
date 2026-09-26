import { Link, router } from '@inertiajs/react';
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
import { ActionLink } from '@/components/action-link';
import { AdviserDuesTable } from '@/components/adviser-dues-table';
import {
    LevelSemesterRail,
    LevelSemesterRailGroup,
    levelSemesterRailItemClassName,
} from '@/components/level-semester-rail';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
    const dueHref = (period: DashboardAdviserDues['selectedPeriod']) => {
        if (period.kind === 'all') {
            return showStudentSetDues(parameters).url;
        }

        const session = dues.sessions.find(
            (item) =>
                (period.kind === 'session' && item.id === period.id) ||
                (period.kind === 'semester' &&
                    item.semesters.some(
                        (semester) => semester.id === period.id,
                    )),
        );

        if (!session) {
            return showStudentSetDues(parameters).url;
        }

        if (period.kind === 'session') {
            return showStudentSetSessionDues({
                ...parameters,
                sessionYears: session.routeYears,
            }).url;
        }

        const semester = session.semesters.find(
            (item) => item.id === period.id,
        );

        return semester
            ? showStudentSetSemesterDues({
                  ...parameters,
                  sessionYears: session.routeYears,
                  semester: semester.slug,
              }).url
            : showStudentSetDues(parameters).url;
    };
    const options = dues.sessions.flatMap((session) => [
        {
            value: `session-${session.id}`,
            label: `${session.level} · ${session.name} Session · Whole Session`,
            href: dueHref({ kind: 'session', id: session.id }),
        },
        ...session.semesters.map((semester) => ({
            value: `semester-${semester.id}`,
            label: `${session.level} · ${session.name} Session · ${semester.name}`,
            href: dueHref({ kind: 'semester', id: semester.id }),
        })),
    ]);
    const selectedValue =
        dues.selectedPeriod.kind === 'all'
            ? 'all'
            : `${dues.selectedPeriod.kind}-${dues.selectedPeriod.id}`;
    const selectedSemesterId =
        dues.selectedPeriod.kind === 'semester' ? dues.selectedPeriod.id : null;
    const selectedSessionId =
        dues.selectedPeriod.kind === 'session'
            ? dues.selectedPeriod.id
            : selectedSemesterId !== null
              ? dues.sessions.find((session) =>
                    session.semesters.some(
                        (semester) => semester.id === selectedSemesterId,
                    ),
                )?.id
              : undefined;
    const selectedSession = dues.sessions.find(
        (session) => session.id === selectedSessionId,
    );
    const selectedSemester = selectedSession?.semesters.find(
        (semester) => semester.id === selectedSemesterId,
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
                    <div className="grid min-h-0 flex-1 grid-cols-[minmax(0,1fr)] grid-rows-[auto_minmax(0,1fr)] md:grid-cols-[13rem_minmax(0,1fr)] md:grid-rows-[minmax(0,1fr)]">
                        <nav
                            aria-label="Due periods"
                            className="flex min-h-0 flex-col border-border max-md:border-b md:border-r"
                        >
                            <div className="p-4 md:hidden">
                                <Select
                                    value={selectedValue}
                                    onValueChange={(value) =>
                                        router.visit(
                                            value === 'all'
                                                ? dueHref({ kind: 'all' })
                                                : (options.find(
                                                      (option) =>
                                                          option.value ===
                                                          value,
                                                  )?.href ??
                                                      dueHref({ kind: 'all' })),
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <SelectTrigger
                                        aria-label="Due period"
                                        className="w-full"
                                    >
                                        <SelectValue placeholder="Select a period" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">
                                            All Dues
                                        </SelectItem>
                                        {options.map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                            <ScrollArea
                                scrollRegion
                                className="hidden h-0 min-h-0 flex-1 md:block"
                            >
                                <LevelSemesterRail className="pr-2 pl-0">
                                    <Link
                                        preserveScroll
                                        href={dueHref({ kind: 'all' })}
                                        aria-current={
                                            dues.selectedPeriod.kind === 'all'
                                                ? 'page'
                                                : undefined
                                        }
                                        className={`${levelSemesterRailItemClassName(dues.selectedPeriod.kind === 'all')} before:hidden`}
                                    >
                                        All Dues
                                    </Link>
                                    {dues.sessions.map((session) => (
                                        <LevelSemesterRailGroup
                                            key={session.id}
                                            label={
                                                <span className="grid gap-0.5">
                                                    <span>{session.level}</span>
                                                    <span className="text-xs font-normal text-muted-foreground">
                                                        {session.name} Session
                                                    </span>
                                                </span>
                                            }
                                        >
                                            <Link
                                                preserveScroll
                                                href={dueHref({
                                                    kind: 'session',
                                                    id: session.id,
                                                })}
                                                aria-current={
                                                    dues.selectedPeriod.kind ===
                                                        'session' &&
                                                    dues.selectedPeriod.id ===
                                                        session.id
                                                        ? 'page'
                                                        : undefined
                                                }
                                                className={levelSemesterRailItemClassName(
                                                    dues.selectedPeriod.kind ===
                                                        'session' &&
                                                        dues.selectedPeriod
                                                            .id === session.id,
                                                )}
                                            >
                                                Whole Session
                                            </Link>
                                            {session.semesters.map(
                                                (semester) => (
                                                    <Link
                                                        preserveScroll
                                                        key={semester.id}
                                                        href={dueHref({
                                                            kind: 'semester',
                                                            id: semester.id,
                                                        })}
                                                        aria-current={
                                                            dues.selectedPeriod
                                                                .kind ===
                                                                'semester' &&
                                                            dues.selectedPeriod
                                                                .id ===
                                                                semester.id
                                                                ? 'page'
                                                                : undefined
                                                        }
                                                        className={levelSemesterRailItemClassName(
                                                            dues.selectedPeriod
                                                                .kind ===
                                                                'semester' &&
                                                                dues
                                                                    .selectedPeriod
                                                                    .id ===
                                                                    semester.id,
                                                        )}
                                                    >
                                                        {semester.name}
                                                    </Link>
                                                ),
                                            )}
                                        </LevelSemesterRailGroup>
                                    ))}
                                </LevelSemesterRail>
                            </ScrollArea>
                        </nav>
                        <div className="mx-auto flex min-h-0 w-full max-w-5xl min-w-0 flex-col">
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
                            <CardFooter className="shrink-0 justify-end border-t border-border px-0 py-3 pb-0">
                                {dues.sessions.length > 0 ? (
                                    <Button size="sm" asChild>
                                        <Link href={createDueHref}>
                                            <RiAddLine /> Add Due
                                        </Link>
                                    </Button>
                                ) : null}
                            </CardFooter>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}
