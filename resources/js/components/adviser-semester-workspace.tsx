import { Link, router } from '@inertiajs/react';
import { RiAddLine, RiSearchLine } from '@remixicon/react';
import { useMemo, useState } from 'react';

import { updateRegistration } from '@/actions/App/Http/Controllers/AcademicSessionController';
import {
    createCourseOffering,
    editStudentSet,
    showCourseSchedule,
    showStudentSetRegistrations,
    showStudentSetSemesterResults,
} from '@/actions/App/Http/Controllers/DashboardController';
import { ActionLink } from '@/components/action-link';
import { RegistrationsTable } from '@/components/adviser-course-registration-dashboard';
import { CourseTable } from '@/components/adviser-course-schedules-dashboard';
import { SetAcademicPeriodNavigation } from '@/components/set-academic-period-navigation';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { WorkspaceSectionTabs } from '@/components/workspace-section-tabs';
import {
    sessionRouteYears,
    studentSetRouteParameters,
} from '@/lib/student-sets';
import type {
    DashboardAdviserCourseRegistration,
    DashboardAdviserCourseSchedules,
    DashboardAdviserResults,
    DashboardCourseScheduleSession,
} from '@/types';

type View = 'courses' | 'registrations' | 'results';

export function AdviserSemesterWorkspace({
    view,
    schedules,
    registrations,
    results,
}: {
    view: View;
    schedules?: DashboardAdviserCourseSchedules | null;
    registrations?: DashboardAdviserCourseRegistration | null;
    results?: DashboardAdviserResults | null;
}) {
    const set =
        schedules?.selectedSet ?? registrations?.selectedSet ?? results?.set;
    const sessions = (schedules?.sessions ??
        registrations?.sessions ??
        results?.sessions ??
        []) as DashboardCourseScheduleSession[];
    const selectedSemesterId =
        schedules?.selectedSemester?.id ??
        registrations?.selectedSemesterId ??
        results?.selectedSemesterId ??
        null;
    const selectedSession = sessions.find((session) =>
        session.semesters.some(
            (semester) => semester.id === selectedSemesterId,
        ),
    );
    const selectedSemester = selectedSession?.semesters.find(
        (semester) => semester.id === selectedSemesterId,
    );
    const [switchBusy, setSwitchBusy] = useState(false);

    if (!set) {
        return (
            <div className="grid flex-1 place-items-center text-sm text-muted-foreground">
                Add a student set to manage courses and results.
            </div>
        );
    }

    const parameters = studentSetRouteParameters(set);
    const routeFor = (
        target: View,
        session = selectedSession,
        semester = selectedSemester,
        status = 'submitted',
    ) => {
        if (!session || !semester) {
            return '#';
        }

        const sessionYears = sessionRouteYears(session);

        if (target === 'courses') {
            return showCourseSchedule(parameters, {
                query: { session: sessionYears, semester: semester.slug },
            }).url;
        }

        if (target === 'registrations') {
            return showStudentSetRegistrations(parameters, {
                query: {
                    session: sessionYears,
                    semester: semester.slug,
                    ...(status === 'submitted' ? {} : { status }),
                },
            }).url;
        }

        return showStudentSetSemesterResults({
            ...parameters,
            sessionYears,
            semester: semester.slug,
        }).url;
    };

    function changeRegistration(enabled: boolean) {
        if (!selectedSession || !selectedSemester) {
            return;
        }

        setSwitchBusy(true);
        router.patch(
            updateRegistration({
                ...parameters,
                sessionYears: sessionRouteYears(selectedSession),
                semester: selectedSemester.slug,
            }),
            { registration_enabled: enabled },
            { preserveScroll: true, onFinish: () => setSwitchBusy(false) },
        );
    }

    return (
        <div className="flex min-h-0 min-w-0 flex-1 flex-col">
            <header className="shrink-0 border-b">
                <div>
                    <h1 className="text-xl font-semibold">
                        Courses &amp; Results
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {set.name}
                    </p>
                </div>
                {sessions.length > 0 && selectedSession && selectedSemester ? (
                    <WorkspaceSectionTabs
                        label="Courses and results sections"
                        activeTab={view}
                        className="mt-4 w-full"
                        tabs={[
                            {
                                key: 'courses',
                                label: 'Course Schedule',
                                mobileLabel: 'Courses',
                                href: routeFor('courses'),
                            },
                            {
                                key: 'registrations',
                                label: 'Registrations',
                                href: routeFor('registrations'),
                            },
                            {
                                key: 'results',
                                label: 'Results',
                                href: routeFor('results'),
                            },
                        ]}
                    />
                ) : null}
            </header>
            {sessions.length === 0 ? (
                <div className="grid flex-1 place-content-center gap-3 text-center">
                    <p className="text-sm text-muted-foreground">
                        Add an academic session to this set first.
                    </p>
                    <ActionLink href={editStudentSet(parameters)}>
                        Open Set Settings
                    </ActionLink>
                </div>
            ) : (
                <div className="grid min-h-0 flex-1 grid-cols-1 grid-rows-[auto_minmax(0,1fr)] md:grid-cols-[15rem_minmax(0,1fr)] md:grid-rows-1">
                    <div className="min-h-0 border-b md:border-r md:border-b-0">
                        <SetAcademicPeriodNavigation
                            sessions={sessions}
                            selectedSemesterId={selectedSemesterId}
                            ariaLabel="Academic semesters"
                            buildHref={(sessionId, semesterId) => {
                                const session = sessions.find(
                                    (item) => item.publicId === sessionId,
                                );
                                const semester = session?.semesters.find(
                                    (item) => item.publicId === semesterId,
                                );

                                return routeFor(
                                    view,
                                    session,
                                    semester,
                                    registrations?.status,
                                );
                            }}
                        />
                    </div>
                    <div className="mx-auto flex min-h-0 w-full max-w-5xl min-w-0 flex-col md:pl-5">
                        {selectedSession && selectedSemester ? (
                            <>
                                <div className="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b py-4">
                                    <div>
                                        <h2 className="font-semibold">
                                            {selectedSession.level.title} ·{' '}
                                            {selectedSemester.name}
                                        </h2>
                                        <p className="text-xs text-muted-foreground">
                                            {selectedSession.name} Session
                                        </p>
                                    </div>
                                    <label className="flex items-center gap-2 text-sm font-medium">
                                        <Switch
                                            checked={
                                                selectedSemester.registrationEnabled
                                            }
                                            disabled={
                                                switchBusy ||
                                                selectedSemester.courseCount ===
                                                    0 ||
                                                Boolean(
                                                    selectedSemester.resultsPublishedAt,
                                                )
                                            }
                                            onCheckedChange={changeRegistration}
                                        />
                                        Registration{' '}
                                        {selectedSemester.registrationEnabled
                                            ? 'open'
                                            : 'closed'}
                                    </label>
                                </div>
                                {view === 'courses' &&
                                schedules?.selectedSemester ? (
                                    <CoursesPane
                                        schedules={schedules}
                                        set={set}
                                        sessionYears={sessionRouteYears(
                                            selectedSession,
                                        )}
                                    />
                                ) : null}
                                {view === 'registrations' && registrations ? (
                                    <RegistrationsPane
                                        registration={registrations}
                                        routeFor={routeFor}
                                    />
                                ) : null}
                                {view === 'results' && results ? (
                                    <ResultsPane results={results} />
                                ) : null}
                            </>
                        ) : (
                            <div className="p-6 text-sm text-muted-foreground">
                                Choose a semester.
                            </div>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

function CoursesPane({
    schedules,
    set,
    sessionYears,
}: {
    schedules: DashboardAdviserCourseSchedules;
    set: NonNullable<DashboardAdviserCourseSchedules['selectedSet']>;
    sessionYears: string;
}) {
    const semester = schedules.selectedSemester;

    if (!semester) {
        return null;
    }

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <div className="flex shrink-0 flex-wrap gap-4 border-b py-3 text-xs text-muted-foreground">
                <span>
                    Maximum{' '}
                    <strong className="text-foreground tabular-nums">
                        {semester.maximumCourseUnits}
                    </strong>{' '}
                    units
                </span>
                <span>
                    <strong className="text-foreground tabular-nums">
                        {semester.totalUnits}
                    </strong>{' '}
                    offered units
                </span>
            </div>
            <div className="flex h-0 min-h-0 flex-1">
                <CourseTable
                    courses={semester.offerings}
                    studentSet={set}
                    sessionYears={sessionYears}
                    semester={semester.slug}
                />
            </div>
            <footer className="flex shrink-0 justify-end border-t py-4">
                <Button asChild size="sm">
                    <Link
                        href={createCourseOffering({
                            ...studentSetRouteParameters(set),
                            sessionYears,
                            semester: semester.slug,
                        })}
                    >
                        <RiAddLine />
                        Add Course
                    </Link>
                </Button>
            </footer>
        </div>
    );
}

function RegistrationsPane({
    registration,
    routeFor,
}: {
    registration: DashboardAdviserCourseRegistration;
    routeFor: (
        target: View,
        session?: DashboardCourseScheduleSession,
        semester?: DashboardCourseScheduleSession['semesters'][number],
        status?: string,
    ) => string;
}) {
    const [query, setQuery] = useState('');
    const filtered = useMemo(
        () =>
            registration.registrations.filter((item) =>
                `${item.student.name} ${item.student.registrationNumber}`
                    .toLowerCase()
                    .includes(query.trim().toLowerCase()),
            ),
        [registration.registrations, query],
    );

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <div className="flex shrink-0 flex-wrap items-center justify-end gap-2 border-b py-3">
                <Select
                    value={registration.status}
                    onValueChange={(status) =>
                        router.visit(
                            routeFor(
                                'registrations',
                                undefined,
                                undefined,
                                status,
                            ),
                        )
                    }
                >
                    <SelectTrigger
                        className="w-40"
                        aria-label="Registration status"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="submitted">In review</SelectItem>
                        <SelectItem value="approved">Approved</SelectItem>
                        <SelectItem value="returned">
                            Changes requested
                        </SelectItem>
                        <SelectItem value="all">All statuses</SelectItem>
                    </SelectContent>
                </Select>
                <div className="relative w-full sm:w-56">
                    <RiSearchLine className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                        placeholder="Search students"
                        aria-label="Search students"
                        className="pl-9"
                    />
                </div>
            </div>
            <RegistrationsTable
                registrations={filtered}
                showStatus={registration.status === 'all'}
            />
        </div>
    );
}

function ResultsPane({ results }: { results: DashboardAdviserResults }) {
    const period = results.period;

    if (!period) {
        return null;
    }

    return (
        <div className="flex min-h-0 flex-1 flex-col">
            <ScrollArea scrollRegion className="h-0 min-h-0 flex-1">
                <div className="mx-auto w-full max-w-2xl min-w-[440px]">
                    <Table className="min-w-[440px]">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Course</TableHead>
                                <TableHead>Title</TableHead>
                                <TableHead className="text-right">
                                    Results
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {results.courses.length ? (
                                results.courses.map((course) => (
                                    <TableRow key={course.publicId}>
                                        <TableCell className="font-semibold">
                                            <Link
                                                href={course.url}
                                                className="text-link hover:underline"
                                            >
                                                {course.code}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            {course.title}
                                            {course.isElective ? (
                                                <span className="ml-2 text-xs text-muted-foreground">
                                                    Elective
                                                </span>
                                            ) : null}
                                        </TableCell>
                                        <TableCell className="text-right tabular-nums">
                                            {course.publishedAt ? (
                                                <Badge
                                                    variant="outline"
                                                    className="border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-300"
                                                >
                                                    Published
                                                </Badge>
                                            ) : (
                                                `${course.enteredCount}/${course.registeredCount} entered`
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))
                            ) : (
                                <TableRow>
                                    <TableCell
                                        colSpan={3}
                                        className="py-8 text-center text-muted-foreground"
                                    >
                                        No courses in this semester.
                                    </TableCell>
                                </TableRow>
                            )}
                        </TableBody>
                    </Table>
                </div>
                <ScrollBar orientation="horizontal" />
            </ScrollArea>
        </div>
    );
}
