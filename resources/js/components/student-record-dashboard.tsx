import { RiArrowLeftLine, RiFileExcel2Line } from '@remixicon/react';

import { section as dashboardSection } from '@/actions/App/Http/Controllers/DashboardController';
import { ActionLink } from '@/components/action-link';
import { CopyableValue } from '@/components/copyable-value';
import { GradeDisplay } from '@/components/grade-display';
import { PersonCell } from '@/components/person-cell';
import { QualifiedSemester } from '@/components/qualified-semester';
import { studentSetLevelText } from '@/components/student-set-label';
import { Button } from '@/components/ui/button';
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
    DashboardStudentRecord,
    DashboardStudentRecordSemester,
} from '@/types';

export function StudentRecordDashboard({
    record,
}: {
    record: DashboardStudentRecord;
}) {
    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-5">
            <ActionLink
                href={dashboardSection({ section: 'students' })}
                icon={<RiArrowLeftLine className="size-4" />}
                className="w-fit"
            >
                Back to Students
            </ActionLink>

            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <PersonCell
                        name={record.student.fullName}
                        secondary={record.student.email}
                        avatar={record.student.avatar}
                        size="md"
                    />
                    <div className="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                        <span>Reg. No.</span>
                        <CopyableValue
                            value={record.student.registrationNumber}
                            className="h-7"
                            valueClassName="text-xs"
                        />
                    </div>
                </div>

                <Button variant="outline" asChild className="w-fit">
                    <a href={record.statementUrl} download>
                        <RiFileExcel2Line className="size-4" />
                        Download Statement
                    </a>
                </Button>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <Summary label="CGPA" value={record.cgpa ?? 'Not available'} />
                <Summary
                    label="Current Level"
                    value={studentSetLevelText(
                        record.student.currentLevel,
                        record.programme.minimumYearsToDegree,
                    )}
                />
                <Summary label="Student Set" value={record.student.studentSet.name} />
                <Summary label="Course Adviser" value={record.courseAdvisor} />
            </div>

            <div className="grid gap-4 lg:grid-cols-[0.8fr_1.2fr]">
                <Card>
                    <CardHeader>
                        <CardTitle>Profile Summary</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-3 text-sm">
                        <SummaryLine label="Institution" value={record.institution ?? 'Not set'} />
                        <SummaryLine label="Programme" value={record.programme.name} />
                        <SummaryLine label="Entry Session" value={`${record.entrySession.name} Session`} />
                        <SummaryLine label="Registration Number" value={record.student.registrationNumber} />
                    </CardContent>
                </Card>

                <Card className="gap-0 pb-0">
                    <CardHeader className="border-b">
                        <CardTitle>Level GPAs</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="px-4">Level</TableHead>
                                    <TableHead className="pr-4 text-right">GPA</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {record.levelGpas.map((row) => (
                                    <TableRow key={row.level}>
                                        <TableCell className="px-4 font-medium">{row.level}</TableCell>
                                        <TableCell className="pr-4 text-right font-semibold tabular-nums">{row.gpa ?? '-'}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                {record.semesters.map((semester) => (
                    <SemesterResults key={semester.id} semester={semester} />
                ))}
            </div>
        </div>
    );
}

function Summary({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="border bg-background p-4 shadow-sm">
            <div className="text-xs font-medium text-muted-foreground">{label}</div>
            <div className="mt-1 text-lg font-semibold">{value}</div>
        </div>
    );
}

function SummaryLine({ label, value }: { label: string; value: string }) {
    return (
        <div className="grid grid-cols-[8rem_minmax(0,1fr)] gap-3 border-b py-2 last:border-b-0">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium">{value}</span>
        </div>
    );
}

function SemesterResults({ semester }: { semester: DashboardStudentRecordSemester }) {
    return (
        <Card className="gap-0 pb-0">
            <CardHeader className="border-b">
                <CardTitle>
                    <QualifiedSemester level={semester.level.title} semester={semester.semester.name} />
                </CardTitle>
                <p className="text-sm text-muted-foreground">GPA {semester.gpa} · {semester.totalUnits} units</p>
            </CardHeader>
            <CardContent className="p-0">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="px-4">Course</TableHead>
                            <TableHead className="w-20 text-center">Units</TableHead>
                            <TableHead className="w-20 pr-4 text-center">Grade</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {semester.courses.map((course) => (
                            <TableRow key={course.code}>
                                <TableCell className="px-4">
                                    <div className="font-semibold">{course.code}</div>
                                    <div className="text-xs text-muted-foreground">{course.title}</div>
                                </TableCell>
                                <TableCell className="text-center tabular-nums">{course.units}</TableCell>
                                <TableCell className="pr-4 text-center"><GradeDisplay grade={course.grade} /></TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    );
}
