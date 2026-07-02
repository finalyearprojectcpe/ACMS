import {
    RiAddLine,
    RiArrowDownSLine,
    RiCloseLine,
    RiDeleteBinLine,
    RiRefreshLine,
    RiSendPlaneLine,
} from '@remixicon/react';
import { useMemo, useState } from 'react';

import { QualifiedSemester } from '@/components/qualified-semester';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    DashboardStudentCourseOption,
    DashboardStudentCourseScheduleCourse,
    DashboardStudentCourseScheduleSemester,
    DashboardStudentCourseSchedules,
} from '@/types';

type ReviewStatus = 'Draft' | 'In review' | 'Approved';

type StudentCourseSchedulesDashboardProps = {
    courseSchedules: DashboardStudentCourseSchedules;
};

export function StudentCourseSchedulesDashboard({
    courseSchedules,
}: StudentCourseSchedulesDashboardProps) {
    const editableSemester = courseSchedules.semesters.find(
        (semester) => semester.isEditable,
    );
    const lockedSemesters = courseSchedules.semesters.filter(
        (semester) => !semester.isEditable,
    );
    const [editableCourses, setEditableCourses] = useState<
        DashboardStudentCourseScheduleCourse[]
    >(editableSemester?.courses ?? []);
    const [isAddingCourse, setIsAddingCourse] = useState(false);
    const [reviewStatus, setReviewStatus] = useState<ReviewStatus>(
        reviewStatusFor(courseSchedules.reviewStatus),
    );
    const [isHistoryOpen, setIsHistoryOpen] = useState(false);

    const availableCourses = useMemo(
        () =>
            courseSchedules.validCourses.filter(
                (course) =>
                    !editableCourses.some(
                        (selectedCourse) => selectedCourse.code === course.code,
                    ),
            ),
        [courseSchedules.validCourses, editableCourses],
    );
    const selectedUnits = editableCourses.reduce(
        (total, course) => total + course.units,
        0,
    );
    const isLocked = reviewStatus !== 'Draft';
    const defaultCourses = useMemo(
        () => editableSemester?.courses ?? [],
        [editableSemester],
    );
    const isDirty = !sameCourseCodes(editableCourses, defaultCourses);

    function addCourse(courseCode: string) {
        const course = availableCourses.find(
            (option) => option.code === courseCode,
        );

        if (!course) {
            return;
        }

        setEditableCourses((courses) => [
            ...courses,
            {
                ...course,
                status: 'Selected',
            },
        ]);
        setIsAddingCourse(false);
    }

    function removeCourse(courseCode: string) {
        setEditableCourses((courses) =>
            courses.filter((course) => course.code !== courseCode),
        );
    }

    function resetCourses() {
        setEditableCourses(defaultCourses);
        setIsAddingCourse(false);
    }

    return (
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-4">
            <div className="grid gap-3 sm:grid-cols-2">
                <SummaryBlock
                    label="Maximum Course Units"
                    value={courseSchedules.maximumCourseUnits}
                    detail={`${selectedUnits} units selected`}
                />
                <SummaryBlock
                    label="Course Adviser"
                    value={courseSchedules.advisorName}
                    detail="Reviews submitted lists"
                />
            </div>

            {editableSemester ? (
                <EditableSemesterCard
                    semester={editableSemester}
                    courses={editableCourses}
                    availableCourses={availableCourses}
                    isAddingCourse={isAddingCourse}
                    reviewStatus={reviewStatus}
                    isLocked={isLocked}
                    selectedUnits={selectedUnits}
                    onAddCourse={addCourse}
                    onRemoveCourse={removeCourse}
                    onStartAdding={() => setIsAddingCourse(true)}
                    onCancelAdding={() => setIsAddingCourse(false)}
                    onReset={resetCourses}
                    onSubmit={() => {
                        setIsAddingCourse(false);
                        setReviewStatus('In review');
                    }}
                    canReset={isDirty}
                />
            ) : null}

            {lockedSemesters.length > 0 ? (
                <Collapsible
                    open={isHistoryOpen}
                    onOpenChange={setIsHistoryOpen}
                    className="mt-2"
                >
                    <CollapsibleTrigger asChild>
                        <button
                            type="button"
                            className="flex w-full items-center justify-between gap-4 border border-border bg-background px-4 py-3 text-left shadow-sm ring-1 ring-border/70 transition-colors hover:bg-muted/50"
                        >
                            <span className="min-w-0">
                                <span className="block text-sm font-semibold tracking-normal">
                                    Previous Course Schedules
                                </span>
                                <span className="mt-0.5 block text-xs text-muted-foreground">
                                    {lockedSemesters.length} previous semesters
                                </span>
                            </span>
                            <RiArrowDownSLine
                                className={`size-5 shrink-0 text-muted-foreground transition-transform ${isHistoryOpen ? 'rotate-180' : ''}`}
                            />
                        </button>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                        <div className="mt-4 flex flex-col gap-4">
                            {lockedSemesters.map((semester) => (
                                <LockedSemesterCard
                                    key={semester.id}
                                    semester={semester}
                                />
                            ))}
                        </div>
                    </CollapsibleContent>
                </Collapsible>
            ) : null}
        </div>
    );
}

function SummaryBlock({
    label,
    value,
    detail,
}: {
    label: string;
    value: string | number;
    detail: string;
}) {
    return (
        <Card
            size="sm"
            className="border border-border bg-background shadow-sm ring-1 ring-border/70"
        >
            <CardHeader>
                <CardTitle className="text-xs text-muted-foreground">
                    {label}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="truncate text-2xl font-semibold tracking-normal tabular-nums">
                    {value}
                </div>
                <div className="mt-1 text-xs text-muted-foreground">
                    {detail}
                </div>
            </CardContent>
        </Card>
    );
}

function EditableSemesterCard({
    semester,
    courses,
    availableCourses,
    isAddingCourse,
    reviewStatus,
    isLocked,
    selectedUnits,
    onAddCourse,
    onRemoveCourse,
    onStartAdding,
    onCancelAdding,
    onReset,
    onSubmit,
    canReset,
}: {
    semester: DashboardStudentCourseScheduleSemester;
    courses: DashboardStudentCourseScheduleCourse[];
    availableCourses: DashboardStudentCourseOption[];
    isAddingCourse: boolean;
    reviewStatus: ReviewStatus;
    isLocked: boolean;
    selectedUnits: number;
    onAddCourse: (courseCode: string) => void;
    onRemoveCourse: (courseCode: string) => void;
    onStartAdding: () => void;
    onCancelAdding: () => void;
    onReset: () => void;
    onSubmit: () => void;
    canReset: boolean;
}) {
    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardHeader className="flex flex-row items-center justify-between gap-3 border-b border-border">
                <div>
                    <CardTitle>
                        <QualifiedSemester
                            level={semester.level}
                            semester={semester.name}
                        />
                    </CardTitle>
                    <div className="mt-1 text-xs text-muted-foreground">
                        {semester.session}
                    </div>
                </div>
                <StatusBadge status={reviewStatus} />
            </CardHeader>
            <CardContent className="p-0">
                <EditableCoursesTable
                    semester={semester}
                    courses={courses}
                    availableCourses={availableCourses}
                    isAddingCourse={isAddingCourse}
                    isLocked={isLocked}
                    onAddCourse={onAddCourse}
                    onRemoveCourse={onRemoveCourse}
                    onCancelAdding={onCancelAdding}
                />
            </CardContent>
            <CardFooter className="flex flex-col items-stretch justify-between gap-3 border-t border-border sm:flex-row sm:items-center">
                <div className="text-xs text-muted-foreground">
                    <span className="font-medium text-foreground tabular-nums">
                        {selectedUnits}
                    </span>{' '}
                    selected units
                </div>
                <div className="flex flex-col gap-2 sm:flex-row">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={isLocked || (!canReset && !isAddingCourse)}
                        onClick={onReset}
                    >
                        <RiRefreshLine />
                        Reset
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        disabled={
                            isLocked ||
                            isAddingCourse ||
                            availableCourses.length === 0
                        }
                        onClick={onStartAdding}
                    >
                        <RiAddLine />
                        Add course
                    </Button>
                    <Button
                        type="button"
                        disabled={isLocked || courses.length === 0}
                        onClick={onSubmit}
                    >
                        <RiSendPlaneLine />
                        Submit For Review
                    </Button>
                </div>
            </CardFooter>
        </Card>
    );
}

function EditableCoursesTable({
    semester,
    courses,
    availableCourses,
    isAddingCourse,
    isLocked,
    onAddCourse,
    onRemoveCourse,
    onCancelAdding,
}: {
    semester: DashboardStudentCourseScheduleSemester;
    courses: DashboardStudentCourseScheduleCourse[];
    availableCourses: DashboardStudentCourseOption[];
    isAddingCourse: boolean;
    isLocked: boolean;
    onAddCourse: (courseCode: string) => void;
    onRemoveCourse: (courseCode: string) => void;
    onCancelAdding: () => void;
}) {
    return (
        <Table className="text-sm">
            <TableHeader>
                <TableRow>
                    <TableHead className="w-[24%] px-4">Course</TableHead>
                    <TableHead>Title</TableHead>
                    <TableHead className="w-[12%] text-center">Units</TableHead>
                    <TableHead className="hidden w-[14%] text-center md:table-cell">
                        Type
                    </TableHead>
                    <TableHead className="w-[12%] pr-4 text-right">
                        Action
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {courses.map((course) => (
                    <TableRow key={course.code}>
                        <TableCell className="px-4 font-semibold">
                            {course.code}
                        </TableCell>
                        <TableCell>
                            <div className="min-w-0">
                                <div className="font-medium">
                                    {course.title}
                                </div>
                                <div className="text-xs text-muted-foreground sm:hidden">
                                    {course.type}
                                </div>
                            </div>
                        </TableCell>
                        <TableCell className="text-center font-semibold tabular-nums">
                            {course.units}
                        </TableCell>
                        <TableCell className="hidden text-center md:table-cell">
                            {course.type}
                        </TableCell>
                        <TableCell className="pr-4 text-right">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-xs"
                                disabled={isLocked}
                                aria-label={`Remove ${course.code}`}
                                onClick={() => onRemoveCourse(course.code)}
                            >
                                <RiDeleteBinLine />
                            </Button>
                        </TableCell>
                    </TableRow>
                ))}

                {isAddingCourse ? (
                    <TableRow className="bg-muted/30">
                        <TableCell className="px-4">
                            <CourseDropdown
                                courses={availableCourses}
                                onSelect={onAddCourse}
                            />
                        </TableCell>
                        <TableCell className="text-muted-foreground">
                            Choose a valid course for{' '}
                            <QualifiedSemester
                                level={semester.level}
                                semester={semester.name}
                            />
                        </TableCell>
                        <TableCell className="text-center text-muted-foreground">
                            -
                        </TableCell>
                        <TableCell className="hidden text-center text-muted-foreground md:table-cell">
                            -
                        </TableCell>
                        <TableCell className="pr-4 text-right">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-xs"
                                aria-label="Cancel course selection"
                                onClick={onCancelAdding}
                            >
                                <RiCloseLine />
                            </Button>
                        </TableCell>
                    </TableRow>
                ) : null}
            </TableBody>
        </Table>
    );
}

function CourseDropdown({
    courses,
    onSelect,
}: {
    courses: DashboardStudentCourseOption[];
    onSelect: (courseCode: string) => void;
}) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    className="h-10 w-full justify-between px-3 text-left"
                    disabled={courses.length === 0}
                >
                    <span className="truncate">Select course</span>
                    <RiArrowDownSLine className="size-4 shrink-0 text-muted-foreground" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="max-h-72">
                {courses.map((course) => (
                    <DropdownMenuItem
                        key={course.code}
                        className="items-start gap-3 py-2"
                        onSelect={() => onSelect(course.code)}
                    >
                        <span className="min-w-0 flex-1">
                            <span className="block font-semibold">
                                {course.code}
                            </span>
                            <span className="block truncate text-muted-foreground">
                                {course.title}
                            </span>
                        </span>
                        <span className="shrink-0 text-xs font-medium text-muted-foreground tabular-nums">
                            {course.units}u
                        </span>
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function LockedSemesterCard({
    semester,
}: {
    semester: DashboardStudentCourseScheduleSemester;
}) {
    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardHeader className="flex flex-row items-center justify-between gap-3 border-b border-border">
                <div>
                    <CardTitle>
                        <QualifiedSemester
                            level={semester.level}
                            semester={semester.name}
                        />
                    </CardTitle>
                    <div className="mt-1 text-xs text-muted-foreground">
                        {semester.session}
                    </div>
                </div>
                <div className="text-right">
                    <div className="text-xs text-muted-foreground">Units</div>
                    <div className="text-lg font-semibold tabular-nums">
                        {semester.totalUnits}
                    </div>
                </div>
            </CardHeader>
            <CardContent className="p-0">
                <LockedCoursesTable courses={semester.courses} />
            </CardContent>
        </Card>
    );
}

function LockedCoursesTable({
    courses,
}: {
    courses: DashboardStudentCourseScheduleCourse[];
}) {
    return (
        <Table className="text-sm">
            <TableHeader>
                <TableRow>
                    <TableHead className="w-[24%] px-4">Course</TableHead>
                    <TableHead>Title</TableHead>
                    <TableHead className="w-[14%] text-center">Units</TableHead>
                    <TableHead className="w-[18%] pr-4 text-right">
                        Status
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {courses.length > 0 ? (
                    courses.map((course) => (
                        <TableRow key={course.code}>
                            <TableCell className="px-4 font-semibold">
                                {course.code}
                            </TableCell>
                            <TableCell className="font-medium">
                                {course.title}
                            </TableCell>
                            <TableCell className="text-center font-semibold tabular-nums">
                                {course.units}
                            </TableCell>
                            <TableCell className="pr-4 text-right">
                                <StatusBadge status={course.status} />
                            </TableCell>
                        </TableRow>
                    ))
                ) : (
                    <TableRow>
                        <TableCell
                            colSpan={4}
                            className="px-4 py-6 text-muted-foreground"
                        >
                            No courses recorded.
                        </TableCell>
                    </TableRow>
                )}
            </TableBody>
        </Table>
    );
}

function StatusBadge({ status }: { status: string }) {
    return (
        <Badge variant="outline" className={statusBadgeClass(status)}>
            {status}
        </Badge>
    );
}

function statusBadgeClass(status: string): string {
    return (
        {
            Passed: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-300',
            Failed: 'border-red-200 bg-red-50 text-red-700 dark:border-red-500/30 dark:bg-red-500/15 dark:text-red-300',
            'In review':
                'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-500/30 dark:bg-blue-500/15 dark:text-blue-300',
            Approved:
                'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-300',
            Draft: 'border-border bg-secondary text-secondary-foreground',
        }[status] ?? 'border-border bg-secondary text-secondary-foreground'
    );
}

function reviewStatusFor(status: string): ReviewStatus {
    if (status === 'In review' || status === 'Approved') {
        return status;
    }

    return 'Draft';
}

function sameCourseCodes(
    courses: DashboardStudentCourseScheduleCourse[],
    defaultCourses: DashboardStudentCourseScheduleCourse[],
): boolean {
    if (courses.length !== defaultCourses.length) {
        return false;
    }

    return courses.every((course, index) => {
        return course.code === defaultCourses[index]?.code;
    });
}
