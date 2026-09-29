import { Checkbox } from '@/components/ui/checkbox';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import {
    Table,
    TableBody,
    TableCell,
    TableFooter,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { cn } from '@/lib/utils';
import type { DashboardCourseRegistrationCourse } from '@/types';

export function CourseRegistrationCourseTable({
    courses,
    emptyLabel = 'No courses in this registration.',
    onToggleCourse,
    selectionIssue,
    selectedKeys = new Set<string>(),
    selectable = false,
    totalUnits,
}: {
    courses: DashboardCourseRegistrationCourse[];
    emptyLabel?: string;
    onToggleCourse?: (course: DashboardCourseRegistrationCourse) => void;
    selectionIssue?: (
        course: DashboardCourseRegistrationCourse,
        selected: boolean,
    ) => string | null;
    selectedKeys?: Set<string>;
    selectable?: boolean;
    totalUnits?: number;
}) {
    const normalCourses = courses.filter((course) => !course.isElective);
    const electiveCourses = courses.filter((course) => course.isElective);
    const columnCount = selectable ? 4 : 3;

    return (
        <ScrollArea className="w-full">
            <Table className="min-w-[300px] table-fixed text-sm sm:min-w-[560px]">
                <TableHeader>
                    <TableRow>
                        {selectable ? (
                            <TableHead className="w-10 px-2 sm:w-14 sm:px-4">
                                Take
                            </TableHead>
                        ) : null}
                        <TableHead className="w-24 px-2 sm:w-32 sm:px-4">
                            Course
                        </TableHead>
                        <TableHead>Title</TableHead>
                        <TableHead className="w-14 pr-2 text-center sm:w-20 sm:pr-4">
                            Units
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {courses.length === 0 ? (
                        <TableRow>
                            <TableCell
                                colSpan={columnCount}
                                className="px-4 py-8 text-center text-muted-foreground"
                            >
                                {emptyLabel}
                            </TableCell>
                        </TableRow>
                    ) : (
                        <>
                            {normalCourses.length > 0 ? (
                                <CourseSectionLabel
                                    colSpan={columnCount}
                                    label="Normal Courses"
                                />
                            ) : null}
                            {normalCourses.map((course, index) => (
                                <CourseRow
                                    key={course.courseOfferingId}
                                    course={course}
                                    selected={selectedKeys.has(
                                        courseKey(course),
                                    )}
                                    selectable={selectable}
                                    alternate={index % 2 === 1}
                                    onToggleCourse={onToggleCourse}
                                    issue={selectionIssue?.(
                                        course,
                                        selectedKeys.has(courseKey(course)),
                                    )}
                                />
                            ))}
                            {electiveCourses.length > 0 ? (
                                <CourseSectionLabel
                                    colSpan={columnCount}
                                    label="Elective Courses"
                                    elective
                                />
                            ) : null}
                            {electiveCourses.map((course) => (
                                <CourseRow
                                    key={course.courseOfferingId}
                                    course={course}
                                    selected={selectedKeys.has(
                                        courseKey(course),
                                    )}
                                    selectable={selectable}
                                    elective
                                    onToggleCourse={onToggleCourse}
                                    issue={selectionIssue?.(
                                        course,
                                        selectedKeys.has(courseKey(course)),
                                    )}
                                />
                            ))}
                        </>
                    )}
                </TableBody>
                {totalUnits !== undefined && (
                    <TableFooter>
                        <TableRow className="bg-muted/40 font-semibold">
                            <TableCell
                                colSpan={columnCount - 1}
                                className="px-2 text-right sm:px-4"
                            >
                                Total Units
                            </TableCell>
                            <TableCell className="pr-2 text-center tabular-nums sm:pr-4">
                                {totalUnits}
                            </TableCell>
                        </TableRow>
                    </TableFooter>
                )}
            </Table>
            <ScrollBar orientation="horizontal" />
        </ScrollArea>
    );
}

function CourseSectionLabel({
    colSpan,
    elective = false,
    label,
}: {
    colSpan: number;
    elective?: boolean;
    label: string;
}) {
    return (
        <TableRow
            className={cn(
                'hover:bg-muted/40',
                elective &&
                    'bg-amber-50/80 hover:bg-amber-50 dark:bg-amber-400/10 dark:hover:bg-amber-400/10',
            )}
        >
            <TableCell
                colSpan={colSpan}
                className="px-2 py-2 text-xs font-semibold text-muted-foreground sm:px-4"
            >
                <span className="inline-flex items-center gap-2">
                    {elective ? (
                        <span
                            aria-hidden="true"
                            className="size-2.5 bg-amber-400 dark:bg-amber-300"
                        />
                    ) : null}
                    {label}
                </span>
            </TableCell>
        </TableRow>
    );
}

function CourseRow({
    alternate = false,
    course,
    elective = false,
    issue,
    onToggleCourse,
    selectable,
    selected,
}: {
    alternate?: boolean;
    course: DashboardCourseRegistrationCourse;
    elective?: boolean;
    issue?: string | null;
    onToggleCourse?: (course: DashboardCourseRegistrationCourse) => void;
    selectable: boolean;
    selected: boolean;
}) {
    return (
        <TableRow
            className={cn(
                alternate && 'bg-muted/25',
                elective &&
                    'bg-amber-50/35 hover:bg-amber-50/60 dark:bg-amber-400/5 dark:hover:bg-amber-400/10',
            )}
        >
            {selectable ? (
                <TableCell className="px-2 sm:px-4">
                    <Checkbox
                        checked={selected}
                        disabled={!selected && Boolean(issue)}
                        aria-label={
                            issue
                                ? `${course.code}: ${issue}`
                                : `Select ${course.code}`
                        }
                        title={issue ?? undefined}
                        onCheckedChange={() => onToggleCourse?.(course)}
                    />
                </TableCell>
            ) : null}
            <TableCell className="px-2 font-semibold break-words sm:px-4">
                {course.code}
            </TableCell>
            <TableCell className="break-words">
                <div className="font-medium">{course.title}</div>
                {course.prerequisites.length > 0 &&
                course.missingPrerequisites.length === 0 ? (
                    <div className="text-xs text-muted-foreground">
                        Requires {course.prerequisites.join(', ')}
                    </div>
                ) : null}
                {issue ? (
                    <div className="text-xs text-destructive">{issue}</div>
                ) : null}
            </TableCell>
            <TableCell className="pr-2 text-center font-semibold tabular-nums sm:pr-4">
                {course.units}
            </TableCell>
        </TableRow>
    );
}

function courseKey(course: DashboardCourseRegistrationCourse): string {
    return course.courseOfferingId.toString();
}
