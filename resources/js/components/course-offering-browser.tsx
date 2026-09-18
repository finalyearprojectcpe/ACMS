import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';

import { CourseCategoryBadge } from '@/components/course-category-badge';
import {
    LevelSemesterRail,
    LevelSemesterRailGroup,
    levelSemesterRailItemClassName,
} from '@/components/level-semester-rail';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldLabel } from '@/components/ui/field';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    academicSemesterOrder,
    qualifiedSemesterLabel,
    semesterLabel,
    sessionDisplayName,
    shortLevelLabel,
} from '@/lib/academic';
import type {
    DashboardAcademicSession,
    DashboardLevel,
    DashboardAdviserCourseOffering,
    DashboardAdviserCoursePrerequisite,
    DashboardAdviserCourseScheduleSemester,
} from '@/types';

type CourseOfferingBrowserProps = {
    title: string;
    detail?: string;
    levels: DashboardLevel[];
    selectedLevel: DashboardLevel;
    semesters: DashboardAdviserCourseScheduleSemester[];
    session: Pick<DashboardAcademicSession, 'name'> | null;
    sessions?: DashboardAcademicSession[];
    selectedSessionId?: string;
    headerControl?: ReactNode;
    onSessionChange?: (sessionId: string) => void;
    onSelectionChange?: (
        level: DashboardLevel,
        semester: DashboardAdviserCourseScheduleSemester,
    ) => void;
    renderHeaderAction?: (
        semester: DashboardAdviserCourseScheduleSemester,
    ) => ReactNode;
    renderCourseAction?: (
        offering: DashboardAdviserCourseOffering,
        semester: DashboardAdviserCourseScheduleSemester,
    ) => ReactNode;
};

export function CourseOfferingBrowser({
    title,
    detail,
    levels,
    selectedLevel,
    semesters,
    session,
    sessions = [],
    selectedSessionId,
    headerControl,
    onSessionChange,
    onSelectionChange,
    renderHeaderAction,
    renderCourseAction,
}: CourseOfferingBrowserProps) {
    const orderedSemesters = useMemo(
        () => [...semesters].sort(compareSemesters),
        [semesters],
    );
    const [activeSemesterSlug, setActiveSemesterSlug] = useState(
        orderedSemesters[0]?.slug ?? '',
    );
    const activeSemesterSlugInView = orderedSemesters.some(
        (semester) => semester.slug === activeSemesterSlug,
    )
        ? activeSemesterSlug
        : (orderedSemesters[0]?.slug ?? '');
    const activeSemester =
        orderedSemesters.find(
            (semester) => semester.slug === activeSemesterSlugInView,
        ) ?? null;
    const effectiveLevels = levels.length > 0 ? levels : [selectedLevel];
    const hasCourseActions = renderCourseAction !== undefined;

    function selectLevelSemester(
        level: DashboardLevel,
        semester: DashboardAdviserCourseScheduleSemester,
    ) {
        setActiveSemesterSlug(semester.slug);

        if (level.slug !== selectedLevel.slug) {
            onSelectionChange?.(level, semester);
        }
    }

    function selectLevel(levelSlug: string) {
        const level = effectiveLevels.find(
            (option) => option.slug === levelSlug,
        );

        if (level && activeSemester) {
            selectLevelSemester(level, activeSemester);
        }
    }

    function selectSemester(semesterSlug: string) {
        const semester = orderedSemesters.find(
            (option) => option.slug === semesterSlug,
        );

        if (semester) {
            selectLevelSemester(selectedLevel, semester);
        }
    }

    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <div className="min-w-0">
                    <h1 className="text-xl font-semibold tracking-normal">
                        {title}
                    </h1>
                    {detail ? (
                        <p className="mt-1 max-w-3xl text-sm text-muted-foreground">
                            {detail}
                        </p>
                    ) : null}
                </div>

                {headerControl || (sessions.length > 0 && onSessionChange) ? (
                    <div className="grid gap-3 sm:grid-cols-2 lg:flex lg:items-end">
                        {headerControl}
                        {sessions.length > 0 && onSessionChange ? (
                            <Field className="lg:w-72">
                                <FieldLabel>Academic Session</FieldLabel>
                                <Select
                                    value={selectedSessionId ?? ''}
                                    onValueChange={onSessionChange}
                                >
                                    <SelectTrigger aria-label="Academic session">
                                        <SelectValue placeholder="Select session" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {sessions.map((sessionOption) => (
                                            <SelectItem
                                                key={sessionOption.id}
                                                value={sessionOption.id.toString()}
                                            >
                                                {sessionDisplayName(
                                                    sessionOption,
                                                )}{' '}
                                                · {sessionOption.status}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </Field>
                        ) : null}
                    </div>
                ) : null}
            </div>

            {activeSemester ? (
                <div className="grid gap-4 lg:grid-cols-[18rem_minmax(0,1fr)]">
                    <div className="grid gap-3 sm:grid-cols-2 lg:hidden">
                        <Select
                            value={selectedLevel.slug}
                            onValueChange={selectLevel}
                        >
                            <SelectTrigger aria-label="Level">
                                <SelectValue placeholder="Select level" />
                            </SelectTrigger>
                            <SelectContent>
                                {effectiveLevels.map((level) => (
                                    <SelectItem
                                        key={level.slug}
                                        value={level.slug}
                                    >
                                        {shortLevelLabel(level.title)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>

                        <Select
                            value={activeSemester.slug}
                            onValueChange={selectSemester}
                        >
                            <SelectTrigger aria-label="Semester">
                                <SelectValue placeholder="Select semester" />
                            </SelectTrigger>
                            <SelectContent>
                                {orderedSemesters.map((semester) => (
                                    <SelectItem
                                        key={semester.slug}
                                        value={semester.slug}
                                    >
                                        {semesterLabel(semester.name)}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <Card className="hidden border border-border bg-background shadow-sm ring-1 ring-border/70 lg:block">
                        <CardHeader className="border-b border-border py-4">
                            <CardTitle className="text-base">
                                Levels and Semesters
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <ScrollArea className="h-[34rem]">
                                <LevelSemesterRail>
                                    {effectiveLevels.map((level) => (
                                        <LevelSemesterRailGroup
                                            key={level.slug}
                                            label={shortLevelLabel(level.title)}
                                        >
                                            {orderedSemesters.map(
                                                (semester) => {
                                                    const isActive =
                                                        level.slug ===
                                                            selectedLevel.slug &&
                                                        semester.slug ===
                                                            activeSemester.slug;

                                                    return (
                                                        <button
                                                            key={`${level.slug}-${semester.slug}`}
                                                            type="button"
                                                            className={levelSemesterRailItemClassName(
                                                                isActive,
                                                            )}
                                                            onClick={() =>
                                                                selectLevelSemester(
                                                                    level,
                                                                    semester,
                                                                )
                                                            }
                                                        >
                                                            {semesterLabel(
                                                                semester.name,
                                                            )}
                                                        </button>
                                                    );
                                                },
                                            )}
                                        </LevelSemesterRailGroup>
                                    ))}
                                </LevelSemesterRail>
                            </ScrollArea>
                        </CardContent>
                    </Card>

                    <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                        <CardHeader className="gap-3 border-b border-border">
                            <div className="flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
                                <div className="min-w-0">
                                    <CardTitle className="text-lg">
                                        {qualifiedSemesterLabel(
                                            selectedLevel.title,
                                            activeSemester.name,
                                        )}{' '}
                                        Course Offerings
                                    </CardTitle>
                                    <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                                        <span>
                                            {sessionDisplayName(session)}
                                        </span>
                                        <span>
                                            {activeSemester.offerings.length}{' '}
                                            {plural(
                                                activeSemester.offerings.length,
                                                'course',
                                            )}
                                        </span>
                                        <span>
                                            {activeSemester.totalUnits} units
                                        </span>
                                        <span>
                                            {activeSemester.normalCourseCount}{' '}
                                            normal
                                        </span>
                                        <span>
                                            {activeSemester.electiveCourseCount}{' '}
                                            elective
                                        </span>
                                    </div>
                                </div>

                                {renderHeaderAction ? (
                                    <div className="shrink-0">
                                        {renderHeaderAction(activeSemester)}
                                    </div>
                                ) : null}
                            </div>
                        </CardHeader>
                        <CardContent className="p-0">
                            <CourseOfferingsTable
                                semester={activeSemester}
                                hasCourseActions={hasCourseActions}
                                renderCourseAction={renderCourseAction}
                                selectedLevel={selectedLevel}
                            />
                        </CardContent>
                    </Card>
                </div>
            ) : (
                <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                    <CardContent className="p-6 text-sm text-muted-foreground">
                        Add semesters before viewing course offerings.
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

function CourseOfferingsTable({
    semester,
    hasCourseActions,
    renderCourseAction,
    selectedLevel,
}: {
    semester: DashboardAdviserCourseScheduleSemester;
    hasCourseActions: boolean;
    renderCourseAction?: (
        offering: DashboardAdviserCourseOffering,
        semester: DashboardAdviserCourseScheduleSemester,
    ) => ReactNode;
    selectedLevel: DashboardLevel;
}) {
    const columnCount = hasCourseActions ? 5 : 4;

    return (
        <Table className="text-sm">
            <TableHeader>
                <TableRow>
                    <TableHead className="w-[42%] px-4">Course</TableHead>
                    <TableHead className="w-20 text-center">Units</TableHead>
                    <TableHead className="w-40">Category</TableHead>
                    <TableHead>Prerequisites</TableHead>
                    {hasCourseActions ? (
                        <TableHead className="w-24 pr-4 text-right">
                            Action
                        </TableHead>
                    ) : null}
                </TableRow>
            </TableHeader>
            <TableBody>
                {semester.offerings.length > 0 ? (
                    semester.offerings.map((offering) => (
                        <TableRow
                            key={offering.id}
                            className="odd:bg-background even:bg-muted/25"
                        >
                            <TableCell className="px-4">
                                <div className="min-w-0">
                                    <div className="font-semibold">
                                        {offering.code}
                                    </div>
                                    <div className="mt-0.5 text-xs text-muted-foreground">
                                        {offering.title}
                                    </div>
                                </div>
                            </TableCell>
                            <TableCell className="text-center font-semibold tabular-nums">
                                {offering.units}
                            </TableCell>
                            <TableCell>
                                <CourseCategoryBadge
                                    category={offering.category}
                                />
                            </TableCell>
                            <TableCell>
                                <PrerequisiteBadges
                                    prerequisites={offering.prerequisites}
                                />
                            </TableCell>
                            {hasCourseActions ? (
                                <TableCell className="pr-4 text-right">
                                    {renderCourseAction?.(offering, semester)}
                                </TableCell>
                            ) : null}
                        </TableRow>
                    ))
                ) : (
                    <TableRow>
                        <TableCell
                            colSpan={columnCount}
                            className="px-4 py-7 text-muted-foreground"
                        >
                            No courses have been added for{' '}
                            {qualifiedSemesterLabel(
                                selectedLevel.title,
                                semester.name,
                            )}
                            .
                        </TableCell>
                    </TableRow>
                )}
            </TableBody>
        </Table>
    );
}

function PrerequisiteBadges({
    prerequisites,
}: {
    prerequisites: DashboardAdviserCoursePrerequisite[];
}) {
    if (prerequisites.length === 0) {
        return <span className="text-xs text-muted-foreground">None</span>;
    }

    const visiblePrerequisites = prerequisites.slice(0, 4);
    const hiddenPrerequisites = prerequisites.slice(4);

    return (
        <div className="flex flex-wrap gap-1">
            {visiblePrerequisites.map((prerequisite) => (
                <Badge
                    key={prerequisite.id}
                    variant="outline"
                    className="border-border bg-background text-xs"
                >
                    {prerequisite.code}
                </Badge>
            ))}
            {hiddenPrerequisites.length > 0 ? (
                <Badge
                    variant="outline"
                    title={hiddenPrerequisites
                        .map((prerequisite) => prerequisite.code)
                        .join(', ')}
                    className="border-border bg-background text-xs"
                >
                    +{hiddenPrerequisites.length} more
                </Badge>
            ) : null}
        </div>
    );
}

function compareSemesters(
    first: DashboardAdviserCourseScheduleSemester,
    second: DashboardAdviserCourseScheduleSemester,
): number {
    return (
        academicSemesterOrder(first.slug) - academicSemesterOrder(second.slug)
    );
}

function plural(count: number, singular: string): string {
    return count === 1 ? singular : `${singular}s`;
}
