import { QualifiedSemester } from '@/components/qualified-semester';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type {
    DashboardStudentResultCourse,
    DashboardStudentResultSemester,
    DashboardStudentResults,
} from '@/types';

type StudentResultsDashboardProps = {
    results: DashboardStudentResults;
};

export function StudentResultsDashboard({
    results,
}: StudentResultsDashboardProps) {
    return (
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-4">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <SummaryBlock
                    label="Average GPA"
                    value={results.averageGpa}
                    detail={results.level.title}
                />
                <SummaryBlock
                    label="Total Units"
                    value={results.totalUnits}
                    detail="Registered units"
                />
                <SummaryBlock
                    label="Courses Failed"
                    value={results.failedCourses}
                    detail="Across both semesters"
                />
                <SummaryBlock
                    label="Total Courses"
                    value={results.totalCourses}
                    detail="Recorded results"
                />
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                {results.semesters.map((semester) => (
                    <SemesterResultsCard
                        key={semester.slug}
                        level={results.level.title}
                        semester={semester}
                    />
                ))}
            </div>
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
                <div className="text-3xl font-semibold tracking-normal tabular-nums">
                    {value}
                </div>
                <div className="mt-1 text-xs text-muted-foreground">
                    {detail}
                </div>
            </CardContent>
        </Card>
    );
}

function SemesterResultsCard({
    level,
    semester,
}: {
    level: string;
    semester: DashboardStudentResultSemester;
}) {
    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardHeader className="flex flex-row items-center justify-between gap-3 border-b border-border">
                <CardTitle>
                    <QualifiedSemester level={level} semester={semester.name} />
                </CardTitle>
                <div className="text-right">
                    <div className="text-xs text-muted-foreground">GPA</div>
                    <div className="text-lg font-semibold tabular-nums">
                        {semester.gpa}
                    </div>
                </div>
            </CardHeader>
            <CardContent className="p-0">
                <ResultsTable courses={semester.courses} />
            </CardContent>
        </Card>
    );
}

function ResultsTable({
    courses,
}: {
    courses: DashboardStudentResultCourse[];
}) {
    return (
        <Table className="text-sm">
            <TableHeader>
                <TableRow>
                    <TableHead className="w-[52%] px-4">Course</TableHead>
                    <TableHead className="w-[16%] text-center">Units</TableHead>
                    <TableHead className="w-[16%] text-center">Score</TableHead>
                    <TableHead className="w-[16%] pr-4 text-center">
                        Grade
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {courses.length > 0 ? (
                    courses.map((course) => (
                        <TableRow key={course.code}>
                            <TableCell className="px-4">
                                <div className="flex min-w-0 flex-col gap-0.5">
                                    <span className="font-semibold">
                                        {course.code}
                                    </span>
                                    <span className="truncate text-xs text-muted-foreground">
                                        {course.title}
                                    </span>
                                </div>
                            </TableCell>
                            <TableCell className="text-center font-medium tabular-nums">
                                {course.units}
                            </TableCell>
                            <TableCell className="text-center font-medium tabular-nums">
                                {course.score}
                            </TableCell>
                            <TableCell
                                className={`pr-4 text-center text-sm font-semibold ${gradeClass(course.grade)}`}
                            >
                                {course.grade}
                            </TableCell>
                        </TableRow>
                    ))
                ) : (
                    <TableRow>
                        <TableCell
                            colSpan={4}
                            className="px-4 py-6 text-muted-foreground"
                        >
                            No results recorded.
                        </TableCell>
                    </TableRow>
                )}
            </TableBody>
        </Table>
    );
}

function gradeClass(grade: string): string {
    if (grade === 'F') {
        return 'text-destructive';
    }

    return 'text-foreground';
}
