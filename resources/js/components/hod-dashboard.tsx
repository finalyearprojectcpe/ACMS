import { Link, router } from '@inertiajs/react';
import {
    RiArrowLeftLine,
    RiFileExcel2Line,
    RiSearchLine,
} from '@remixicon/react';
import type { ReactNode } from 'react';
import { useMemo, useState } from 'react';
import {
    CartesianGrid,
    Cell,
    Line,
    LineChart,
    Pie,
    PieChart,
    XAxis,
    YAxis,
} from 'recharts';
import * as XLSX from 'xlsx';

import {
    importHodStaff,
    section as dashboardSection,
    storeImportedHodStaff,
} from '@/actions/App/Http/Controllers/DashboardController';
import { ActionLink } from '@/components/action-link';
import { CopyableValue } from '@/components/copyable-value';
import { CourseTypeBadge } from '@/components/course-type-badge';
import { PersonCell } from '@/components/person-cell';
import { QualifiedSemester } from '@/components/qualified-semester';
import { RoleBadge } from '@/components/role-badge';
import { SpreadsheetImportPreview } from '@/components/spreadsheet-import-preview';
import type {
    SpreadsheetImportBatch,
    SpreadsheetImportCallbacks,
} from '@/components/spreadsheet-import-preview';
import {
    StudentSetLabel,
    studentSetLevelText,
    studentSetText,
} from '@/components/student-set-label';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import { Input } from '@/components/ui/input';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
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
import type {
    DashboardHod,
    DashboardHodResultSubmission,
    DashboardHodSetAdvisor,
    DashboardHodStaff,
    DashboardHodStaffForm,
    DashboardLevel,
    DashboardStaffCourseOffering,
    DashboardStaffCourseScheduleSemester,
    DashboardStaffHomeCarryoverSummary,
    DashboardStaffStudent,
    DashboardStudentRecord,
    DashboardStudentRecordSemester,
} from '@/types';

type HodDashboardProps = {
    hod: DashboardHod;
    section: string;
};

type FilterOption = string | { value: string; label: string };

export function HodDashboard({ hod, section }: HodDashboardProps) {
    if (section === 'staff') {
        return <HodStaffPage hod={hod} />;
    }

    if (section === 'students') {
        return <HodStudentsPage hod={hod} />;
    }

    if (section === 'courses') {
        return <HodCourseOfferingsPage hod={hod} />;
    }

    if (section === 'student-results') {
        return <HodStudentResultsPage hod={hod} />;
    }

    if (section === 'settings') {
        return <HodSettingsPage hod={hod} />;
    }

    return <HodHomePage hod={hod} />;
}

function HodHomePage({ hod }: { hod: DashboardHod }) {
    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <MetricBlock
                    label="Department Students"
                    value={hod.metrics.students}
                    detail={hod.department.abbreviation}
                />
                <MetricBlock
                    label="Active Staff"
                    value={hod.metrics.activeStaff}
                    detail={`${hod.metrics.pendingSignups} pending signup`}
                />
                <MetricBlock
                    label="Results Awaiting Review"
                    value={hod.metrics.resultsAwaitingReview}
                    detail={`${hod.metrics.missingResults} missing submissions`}
                />
                <MetricBlock
                    label="Students With Carryovers"
                    value={hod.metrics.studentsWithCarryovers}
                    detail="Across department levels"
                />
            </div>

            <div className="grid gap-4 xl:grid-cols-[1.05fr_0.95fr]">
                <DashboardCard title="Set Adviser Coverage">
                    <SetAdvisorTable
                        advisors={hod.setAdvisors}
                        minimumYearsToDegree={hod.department.minimumYearsToDegree}
                    />
                </DashboardCard>

                <DashboardCard title="Results Needing Attention">
                    <ResultSubmissionTable
                        submissions={hod.resultSubmissions.filter(
                            (submission) => submission.status !== 'Approved',
                        )}
                        compact
                    />
                </DashboardCard>

                <DashboardCard title="Carryover Summary">
                    <CarryoverSummaryTable summaries={hod.carryoverSummaries} />
                </DashboardCard>

                <DashboardCard title="Recent Staff Onboarding">
                    <StaffTable staff={hod.staff.slice(0, 5)} compact />
                </DashboardCard>
            </div>
        </div>
    );
}

function HodStaffPage({ hod }: { hod: DashboardHod }) {
    const [query, setQuery] = useState('');
    const [setFilter, setSetFilter] = useState('all');
    const [statusFilter, setStatusFilter] = useState('all');
    const [roleFilter, setRoleFilter] = useState('all');
    const setOptions = useMemo(
        () =>
            uniqueFlat(
                hod.staff.map((member) =>
                    member.assignedSets.map((set) => studentSetText(set)),
                ),
            ),
        [hod.staff],
    );
    const roleOptions = useMemo(
        () => unique(hod.staff.map((member) => member.role)),
        [hod.staff],
    );
    const visibleStaff = useMemo(
        () =>
            hod.staff.filter((member) => {
                const searchText = [
                    member.name,
                    member.email ?? '',
                    member.role,
                    member.status,
                    member.assignedSets
                        .map((set) => studentSetText(set))
                        .join(' '),
                ]
                    .join(' ')
                    .toLowerCase();

                return (
                    searchText.includes(query.trim().toLowerCase()) &&
                    (setFilter === 'all' ||
                        member.assignedSets.some(
                            (set) => studentSetText(set) === setFilter,
                        )) &&
                    (statusFilter === 'all' ||
                        member.status === statusFilter) &&
                    (roleFilter === 'all' || member.role === roleFilter)
                );
            }),
        [hod.staff, query, roleFilter, setFilter, statusFilter],
    );

    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <PageSummary
                    title={`${hod.department.abbreviation} Staff`}
                    detail={`${hod.staff.length} staff members in ${hod.department.name}`}
                />
                <Button asChild>
                    <Link href={importHodStaff()}>
                        <RiFileExcel2Line />
                        Import Staffs From Spreadsheet
                    </Link>
                </Button>
            </div>
            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardHeader className="border-b border-border">
                    <CardTitle>Department Staff</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-4 p-4">
                    <div className="grid gap-3 lg:grid-cols-[minmax(0,1fr)_12rem_12rem_12rem]">
                        <SearchInput
                            value={query}
                            onChange={setQuery}
                            placeholder="Search staff, email, role"
                        />
                        <FilterSelect
                            value={setFilter}
                            onChange={setSetFilter}
                            label="Advised set"
                            allLabel="All sets"
                            options={setOptions}
                        />
                        <FilterSelect
                            value={roleFilter}
                            onChange={setRoleFilter}
                            label="Role"
                            allLabel="All roles"
                            options={roleOptions}
                        />
                        <FilterSelect
                            value={statusFilter}
                            onChange={setStatusFilter}
                            label="Status"
                            allLabel="All statuses"
                            options={['Active', 'Pending signup']}
                        />
                    </div>
                    <StaffTable staff={visibleStaff} />
                </CardContent>
            </Card>
        </div>
    );
}

function HodStudentsPage({ hod }: { hod: DashboardHod }) {
    const [query, setQuery] = useState('');
    const [levelFilter, setLevelFilter] = useState('all');
    const [setFilter, setSetFilter] = useState('all');
    const [carryoverFilter, setCarryoverFilter] = useState('all');
    const minimumYearsToDegree = hod.department.minimumYearsToDegree;
    const levelOptions = useMemo(
        () =>
            unique(
                hod.students
                    .map((student) =>
                        studentSetLevelText(
                            student.currentLevel,
                            minimumYearsToDegree,
                        ),
                    )
                    .filter(Boolean),
            ),
        [hod.students, minimumYearsToDegree],
    );
    const setOptions = useMemo(
        () =>
            hod.setAdvisors.map((setAdvisor) => ({
                value: setAdvisor.entrySession.name,
                label: setOptionLabel(setAdvisor, minimumYearsToDegree),
            })),
        [hod.setAdvisors, minimumYearsToDegree],
    );
    const selectedSetAdvisor = useMemo(
        () =>
            setFilter === 'all'
                ? null
                : hod.setAdvisors.find(
                      (setAdvisor) =>
                          setAdvisor.entrySession.name === setFilter,
                  ) ?? null,
        [hod.setAdvisors, setFilter],
    );
    const visibleStudents = useMemo(
        () =>
            filterStudents(hod.students, {
                query,
                level: levelFilter,
                set: setFilter,
                carryover: carryoverFilter,
                minimumYearsToDegree,
            }),
        [
            carryoverFilter,
            hod.students,
            levelFilter,
            minimumYearsToDegree,
            query,
            setFilter,
        ],
    );

    function exportClasslist() {
        const rows = visibleStudents.map((student, index) => ({
            'S/N': index + 1,
            'Full Name': student.fullName,
            'Registration Number': student.registrationNumber,
            Email: student.email,
            Set: studentSetText({
                entrySession: student.entrySession,
                currentLevel: student.currentLevel,
                minimumYearsToDegree,
            }),
            'Course Adviser': student.advisorName,
            CGPA: student.cgpa,
        }));
        const worksheet = XLSX.utils.json_to_sheet(rows);
        const workbook = XLSX.utils.book_new();
        const levelLabel =
            levelFilter === 'all'
                ? 'all-levels'
                : levelFilter.toLowerCase().replace(/\s+/g, '-');
        const filename = `${hod.department.abbreviation}-${levelLabel}-classlist.xlsx`;

        XLSX.utils.book_append_sheet(workbook, worksheet, 'Classlist');
        XLSX.writeFile(workbook, filename);
    }

    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <PageSummary
                    title={`${hod.department.abbreviation} Students`}
                    detail={`${visibleStudents.length} of ${hod.students.length} students shown`}
                />
                <Button
                    type="button"
                    variant="outline"
                    disabled={visibleStudents.length === 0}
                    onClick={exportClasslist}
                >
                    <RiFileExcel2Line />
                    Export Classlist
                </Button>
            </div>
            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardHeader className="border-b border-border">
                    <CardTitle>Department Students</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-4 p-4">
                    <div className="grid gap-3 xl:grid-cols-[minmax(0,1fr)_10rem_18rem_11rem]">
                        <SearchInput
                            value={query}
                            onChange={setQuery}
                            placeholder="Search student, reg no, email"
                        />
                        <FilterSelect
                            value={levelFilter}
                            onChange={setLevelFilter}
                            label="Level"
                            allLabel="All levels"
                            options={levelOptions}
                        />
                        <FilterSelect
                            value={setFilter}
                            onChange={setSetFilter}
                            label="Entry set"
                            allLabel="All sets"
                            options={setOptions}
                        />
                        <FilterSelect
                            value={carryoverFilter}
                            onChange={setCarryoverFilter}
                            label="Carryover"
                            allLabel="All students"
                            options={['Has carryover', 'No carryover']}
                        />
                    </div>
                    {selectedSetAdvisor ? (
                        <div className="text-sm text-muted-foreground">
                            Course adviser:{' '}
                            <span className="font-medium text-foreground">
                                {selectedSetAdvisor.advisor?.name ??
                                    'Not assigned'}
                            </span>
                        </div>
                    ) : null}
                    <StudentsTable
                        students={visibleStudents}
                        minimumYearsToDegree={minimumYearsToDegree}
                    />
                </CardContent>
            </Card>
        </div>
    );
}

function HodCourseOfferingsPage({ hod }: { hod: DashboardHod }) {
    const selectedSession = hod.courseOfferings.academicSession;
    const selectedLevel = hod.courseOfferings.level;

    function updateSelection(params: {
        academicSession?: string | number | null;
        level?: string | null;
    }) {
        router.get(
            dashboardSection.url(
                { section: 'courses' },
                {
                    query: {
                        academic_session:
                            params.academicSession ?? selectedSession?.id,
                        level: params.level ?? selectedLevel?.slug,
                    },
                },
            ),
            {},
            { preserveScroll: true, preserveState: true },
        );
    }

    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                <PageSummary
                    title="Course Offerings"
                    detail="View faculty, departmental, and elective courses by session, level, and semester."
                />
                <div className="lg:w-64">
                    <Select
                        value={selectedSession?.id.toString() ?? ''}
                        onValueChange={(value) =>
                            updateSelection({ academicSession: value })
                        }
                    >
                        <SelectTrigger aria-label="Academic session">
                            <SelectValue placeholder="Academic session" />
                        </SelectTrigger>
                        <SelectContent>
                            {hod.academicSessions.map((session) => (
                                <SelectItem
                                    key={session.id}
                                    value={session.id.toString()}
                                >
                                    {session.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>

            {selectedSession && selectedLevel ? (
                <div className="grid gap-4 lg:grid-cols-[14rem_minmax(0,1fr)]">
                    <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                        <CardHeader className="border-b border-border">
                            <CardTitle>Levels</CardTitle>
                        </CardHeader>
                        <CardContent className="p-3">
                            <ScrollArea className="max-h-[28rem]">
                                <div className="flex gap-2 lg:flex-col">
                                    {hod.levels.map((level) => (
                                        <Button
                                            key={level.slug}
                                            type="button"
                                            variant={
                                                level.slug ===
                                                selectedLevel.slug
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            className="h-11 justify-start text-base"
                                            onClick={() =>
                                                updateSelection({
                                                    level: level.slug,
                                                })
                                            }
                                        >
                                            {level.title}
                                        </Button>
                                    ))}
                                </div>
                                <ScrollBar orientation="horizontal" />
                            </ScrollArea>
                        </CardContent>
                    </Card>
                    <div className="grid gap-4 xl:grid-cols-2">
                        {hod.courseOfferings.semesters.map((semester) => (
                            <CourseOfferingSemesterCard
                                key={semester.id}
                                levelTitle={selectedLevel.title}
                                semester={semester}
                                sessionName={selectedSession.name}
                            />
                        ))}
                    </div>
                </div>
            ) : (
                <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                    <CardContent className="p-6 text-sm text-muted-foreground">
                        Add an academic session and level before viewing course
                        offerings.
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

function HodStudentResultsPage({ hod }: { hod: DashboardHod }) {
    const [levelFilter, setLevelFilter] = useState('all');
    const [setFilter, setSetFilter] = useState('all');
    const minimumYearsToDegree = hod.department.minimumYearsToDegree;
    const setOptions = useMemo(
        () =>
            hod.setAdvisors.map((setAdvisor) => ({
                value: setAdvisor.entrySession.name,
                label: setOptionLabel(setAdvisor, minimumYearsToDegree),
            })),
        [hod.setAdvisors, minimumYearsToDegree],
    );
    const levelOptions = useMemo(
        () =>
            unique(
                hod.students.map((student) =>
                    studentSetLevelText(
                        student.currentLevel,
                        minimumYearsToDegree,
                    ),
                ),
            ),
        [hod.students, minimumYearsToDegree],
    );
    const selectedSetAdvisor = useMemo(
        () =>
            setFilter === 'all'
                ? null
                : hod.setAdvisors.find(
                      (setAdvisor) =>
                          setAdvisor.entrySession.name === setFilter,
                  ) ?? null,
        [hod.setAdvisors, setFilter],
    );
    const visibleStudents = useMemo(
        () =>
            filterStudents(hod.students, {
                query: '',
                level: levelFilter,
                set: setFilter,
                carryover: 'all',
                minimumYearsToDegree,
            }),
        [hod.students, levelFilter, minimumYearsToDegree, setFilter],
    );
    const classifications = useMemo(
        () => classificationRowsFor(visibleStudents),
        [visibleStudents],
    );
    const trendRows = useMemo(
        () => gpaTrendRowsFor(visibleStudents, hod.levels),
        [hod.levels, visibleStudents],
    );
    const bestStudents = useMemo(
        () =>
            [...visibleStudents]
                .sort(
                    (first, second) => Number(second.cgpa) - Number(first.cgpa),
                )
                .slice(0, 5),
        [visibleStudents],
    );
    const lowestStudents = useMemo(
        () =>
            [...visibleStudents]
                .sort(
                    (first, second) => Number(first.cgpa) - Number(second.cgpa),
                )
                .slice(0, 5),
        [visibleStudents],
    );
    const averageCgpa =
        visibleStudents.length === 0
            ? '0.00'
            : averageGpa(visibleStudents.map((student) => student.cgpa));
    const studentsWithCarryovers = visibleStudents.filter(
        (student) => student.pendingCarryovers.length > 0,
    ).length;

    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <div className="flex flex-col gap-3 xl:flex-row xl:items-end xl:justify-between">
                <PageSummary
                    title="Student Results"
                    detail="Department performance, result submissions, and student standing."
                />
                <div className="grid gap-2 sm:grid-cols-2 xl:w-[30rem]">
                    <FilterSelect
                        value={setFilter}
                        onChange={setSetFilter}
                        label="Entry set"
                        allLabel="All sets"
                        options={setOptions}
                    />
                    <FilterSelect
                        value={levelFilter}
                        onChange={setLevelFilter}
                        label="Level"
                        allLabel="All levels"
                        options={levelOptions}
                    />
                </div>
            </div>
            {selectedSetAdvisor ? (
                <div className="text-sm text-muted-foreground">
                    Course adviser:{' '}
                    <span className="font-medium text-foreground">
                        {selectedSetAdvisor.advisor?.name ?? 'Not assigned'}
                    </span>
                </div>
            ) : null}

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <MetricBlock
                    label="Students In View"
                    value={visibleStudents.length}
                    detail={`${hod.studentResults.studentsCount} total students`}
                />
                <MetricBlock
                    label="Average CGPA"
                    value={averageCgpa}
                    detail="Filtered student set"
                />
                <MetricBlock
                    label="Students With Carryovers"
                    value={studentsWithCarryovers}
                    detail="Need academic attention"
                />
                <MetricBlock
                    label="Pending Submissions"
                    value={hod.studentResults.pendingSubmissions}
                    detail="Awaiting review or missing"
                />
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <DashboardCard title="Classification Distribution">
                    <ClassificationChart rows={classifications} />
                </DashboardCard>
                <DashboardCard title="Average GPA Trend">
                    <GpaTrendChart rows={trendRows} />
                </DashboardCard>
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <DashboardCard title="Best 5 Students">
                    <StudentStandingTable
                        students={bestStudents}
                        minimumYearsToDegree={minimumYearsToDegree}
                    />
                </DashboardCard>
                <DashboardCard title="Lowest 5 Students">
                    <StudentStandingTable
                        students={lowestStudents}
                        minimumYearsToDegree={minimumYearsToDegree}
                    />
                </DashboardCard>
            </div>

            <DashboardCard title="Result Submissions">
                <ResultSubmissionTable submissions={hod.resultSubmissions} />
            </DashboardCard>
        </div>
    );
}

function HodSettingsPage({ hod }: { hod: DashboardHod }) {
    return (
        <div className="mx-auto grid w-full max-w-5xl gap-4 lg:grid-cols-2">
            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardHeader className="border-b border-border">
                    <CardTitle>Profile</CardTitle>
                </CardHeader>
                <CardContent className="p-4">
                    <PersonCell
                        name={hod.settings.hod.name}
                        secondary={hod.settings.hod.email}
                        avatar={hod.settings.hod.avatar}
                        size="md"
                    />
                </CardContent>
            </Card>
            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardHeader className="border-b border-border">
                    <CardTitle>Department</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-3 p-4 text-sm">
                    <SummaryLine
                        label="Department"
                        value={hod.department.name}
                    />
                    <SummaryLine
                        label="Abbreviation"
                        value={hod.department.abbreviation}
                    />
                    <SummaryLine
                        label="Faculty"
                        value={hod.faculty?.name ?? 'Not set'}
                    />
                </CardContent>
            </Card>
        </div>
    );
}

export function HodStaffImport({ form }: { form: DashboardHodStaffForm }) {
    function submitRows(
        rows: Array<Record<string, string>>,
        fileName: string | null,
        batch: SpreadsheetImportBatch,
        callbacks: SpreadsheetImportCallbacks,
    ) {
        router.post(
            storeImportedHodStaff.url(),
            {
                file_name: fileName ?? '',
                import_key: batch.importKey,
                batch_number: batch.batchNumber,
                total_batches: batch.totalBatches,
                start_row: batch.startRow,
                total_rows: batch.totalRows,
                batch_size: batch.batchSize,
                rows,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onError: callbacks.onError,
                onSuccess: callbacks.onSuccess,
                onFinish: callbacks.onFinish,
            },
        );
    }

    return (
        <div className="mx-auto flex w-full max-w-5xl flex-col gap-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <PageSummary
                    title="Import Staffs From Spreadsheet"
                    detail={`New staff will be added to ${form.department.abbreviation}.`}
                />
                <Button variant="outline" asChild>
                    <Link href={dashboardSection({ section: 'staff' })}>
                        Cancel
                    </Link>
                </Button>
            </div>
            <SpreadsheetImportPreview
                title={`${form.department.abbreviation} Staff Import`}
                submitLabel="Import Staffs"
                fields={[
                    {
                        key: 'name',
                        label: 'Full Name',
                        aliases: ['name', 'staff name', 'full name'],
                        defaultColumnIndex: 0,
                        required: true,
                    },
                    {
                        key: 'email',
                        label: 'Email',
                        aliases: ['email address', 'staff email'],
                        defaultColumnIndex: 1,
                        required: true,
                    },
                    {
                        key: 'accessCode',
                        label: 'Access Code',
                        aliases: ['access code', 'code'],
                        defaultColumnIndex: 2,
                        description:
                            'Leave unmapped or blank to auto-generate one.',
                        emptyValueLabel: 'Auto-generated',
                    },
                ]}
                onSubmit={submitRows}
            />
        </div>
    );
}

export function StudentRecordDashboard({
    record,
}: {
    record: DashboardStudentRecord;
}) {
    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <PersonCell
                        name={record.student.fullName}
                        secondary={record.student.email}
                        avatar={record.student.avatar}
                        size="md"
                    />
                    <div className="mt-2 flex flex-wrap gap-2 text-sm text-muted-foreground">
                        <span className="font-medium text-foreground">
                            Reg. No.
                        </span>
                        <CopyableValue
                            value={record.student.registrationNumber}
                            className="h-7"
                            valueClassName="text-xs"
                        />
                    </div>
                </div>
                <ActionLink
                    href={dashboardSection({ section: 'students' })}
                    icon={<RiArrowLeftLine className="size-4" />}
                >
                    Back to Students
                </ActionLink>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <MetricBlock
                    label="CGPA"
                    value={record.cgpa}
                    detail="Cumulative performance"
                />
                <MetricBlock
                    label="Current Level"
                    value={studentSetLevelText(
                        record.student.currentLevel,
                        record.department?.minimumYearsToDegree,
                    )}
                    detail={record.entrySession?.name ?? 'No entry set'}
                />
                <MetricBlock
                    label="Course Adviser"
                    value={record.courseAdvisor}
                    detail={record.department?.abbreviation ?? 'Department'}
                />
                <MetricBlock
                    label="Carryovers"
                    value={record.student.pendingCarryovers.length}
                    detail="Pending courses"
                />
            </div>

            <div className="grid gap-4 xl:grid-cols-[0.7fr_1.3fr]">
                <DashboardCard title="Profile Summary">
                    <div className="grid gap-3 p-4 text-sm">
                        <SummaryLine
                            label="Faculty"
                            value={record.faculty?.name ?? 'Not set'}
                        />
                        <SummaryLine
                            label="Department"
                            value={record.department?.name ?? 'Not set'}
                        />
                        <SummaryLine
                            label="Entry Set"
                            value={record.entrySession?.name ?? 'Not set'}
                        />
                        <SummaryLine
                            label="Current Level"
                            value={
                                studentSetLevelText(
                                    record.student.currentLevel,
                                    record.department?.minimumYearsToDegree,
                                )
                            }
                        />
                    </div>
                </DashboardCard>
                <DashboardCard title="Level GPAs">
                    <LevelGpaTable rows={record.levelGpas} />
                </DashboardCard>
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                {record.semesters.map((semester) => (
                    <StudentRecordSemesterCard
                        key={semester.id}
                        semester={semester}
                    />
                ))}
            </div>
        </div>
    );
}

function CourseOfferingSemesterCard({
    levelTitle,
    semester,
    sessionName,
}: {
    levelTitle: string;
    semester: DashboardStaffCourseScheduleSemester;
    sessionName: string;
}) {
    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardHeader className="border-b border-border">
                <div>
                    <CardTitle>
                        <QualifiedSemester
                            level={levelTitle}
                            semester={semester.name}
                        />
                    </CardTitle>
                    <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                        <span>{sessionName}</span>
                        <span>{semester.totalUnits} units</span>
                        <span>{semester.facultyCourseCount} faculty</span>
                        <span>
                            {semester.departmentalCourseCount} departmental
                        </span>
                        <span>{semester.electiveCourseCount} elective</span>
                    </div>
                </div>
            </CardHeader>
            <CardContent className="p-0">
                <CourseOfferingsTable semester={semester} />
            </CardContent>
        </Card>
    );
}

function CourseOfferingsTable({
    semester,
}: {
    semester: DashboardStaffCourseScheduleSemester;
}) {
    return (
        <ScrollArea>
            <Table className="min-w-[580px] text-sm">
                <TableHeader>
                    <TableRow>
                        <TableHead className="px-4">Course</TableHead>
                        <TableHead className="w-20 text-center">
                            Units
                        </TableHead>
                        <TableHead className="w-32">Category</TableHead>
                        <TableHead>Prerequisites</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {semester.offerings.length > 0 ? (
                        semester.offerings.map((offering) => (
                            <CourseOfferingRow
                                key={offering.id}
                                offering={offering}
                            />
                        ))
                    ) : (
                        <EmptyTableRow colSpan={4}>
                            No course offerings for this semester.
                        </EmptyTableRow>
                    )}
                </TableBody>
            </Table>
            <ScrollBar orientation="horizontal" />
        </ScrollArea>
    );
}

function CourseOfferingRow({
    offering,
}: {
    offering: DashboardStaffCourseOffering;
}) {
    return (
        <TableRow className="odd:bg-background even:bg-muted/25">
            <TableCell className="px-4">
                <div className="font-semibold">{offering.code}</div>
                <div className="text-xs text-muted-foreground">
                    {offering.title}
                </div>
            </TableCell>
            <TableCell className="text-center font-medium tabular-nums">
                {offering.units}
            </TableCell>
            <TableCell>
                <CourseTypeBadge type={offering.courseType} />
            </TableCell>
            <TableCell>
                {offering.prerequisites.length > 0 ? (
                    <div className="flex flex-wrap gap-1">
                        {offering.prerequisites.map((course) => (
                            <Badge
                                key={course.id}
                                variant="outline"
                                className="border-border bg-muted/60"
                            >
                                {course.code}
                            </Badge>
                        ))}
                    </div>
                ) : (
                    <span className="text-muted-foreground">None</span>
                )}
            </TableCell>
        </TableRow>
    );
}

function StaffTable({
    staff,
    compact = false,
}: {
    staff: DashboardHodStaff[];
    compact?: boolean;
}) {
    return (
        <ScrollArea>
            <Table className="min-w-[640px] text-sm">
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-[34%] px-4">Staff</TableHead>
                        <TableHead className="w-[16%]">Role</TableHead>
                        <TableHead>Advised Sets</TableHead>
                        <TableHead className="w-[16%]">Status</TableHead>
                        {!compact ? (
                            <TableHead className="w-[18%] pr-4">
                                Access Code
                            </TableHead>
                        ) : null}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {staff.length > 0 ? (
                        staff.map((member) => (
                            <TableRow
                                key={member.staffProfileId}
                                className="odd:bg-background even:bg-muted/25"
                            >
                                <TableCell className="px-4">
                                    <PersonCell
                                        name={member.name}
                                        secondary={member.email}
                                        avatar={member.avatar}
                                    />
                                </TableCell>
                                <TableCell>
                                    <RoleBadge
                                        role={member.role}
                                        context={member.roleContext}
                                    />
                                </TableCell>
                                <TableCell className="text-muted-foreground">
                                    {member.assignedSets.length > 0
                                        ? member.assignedSets
                                              .map((set) =>
                                                  studentSetText(set),
                                              )
                                              .join(', ')
                                        : 'Not assigned'}
                                </TableCell>
                                <TableCell>
                                    <StatusBadge status={member.status} />
                                </TableCell>
                                {!compact ? (
                                    <TableCell className="pr-4">
                                        <CopyableValue
                                            value={member.accessCode}
                                            emptyLabel="Used"
                                        />
                                    </TableCell>
                                ) : null}
                            </TableRow>
                        ))
                    ) : (
                        <EmptyTableRow colSpan={compact ? 4 : 5}>
                            No staff match the current filters.
                        </EmptyTableRow>
                    )}
                </TableBody>
            </Table>
            <ScrollBar orientation="horizontal" />
        </ScrollArea>
    );
}

function StudentsTable({
    students,
    minimumYearsToDegree,
}: {
    students: DashboardStaffStudent[];
    minimumYearsToDegree?: number;
}) {
    return (
        <ScrollArea>
            <Table className="min-w-[900px] text-sm">
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-14 px-4 text-center">
                            S/N
                        </TableHead>
                        <TableHead className="min-w-52">Student</TableHead>
                        <TableHead className="min-w-32">Reg. No.</TableHead>
                        <TableHead className="min-w-44">Set</TableHead>
                        <TableHead className="min-w-44">Course Adviser</TableHead>
                        <TableHead className="min-w-44">Carryovers</TableHead>
                        <TableHead className="w-24 text-center">CGPA</TableHead>
                        <TableHead className="w-24 pr-4 text-right">
                            Action
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {students.length > 0 ? (
                        students.map((student, index) => (
                            <TableRow
                                key={student.id}
                                className="odd:bg-background even:bg-muted/25"
                            >
                                <TableCell className="px-4 text-center text-muted-foreground tabular-nums">
                                    {index + 1}
                                </TableCell>
                                <TableCell>
                                    <PersonCell
                                        name={student.fullName}
                                        secondary={student.email}
                                        avatar={student.avatar}
                                    />
                                </TableCell>
                                <TableCell>
                                    <CopyableValue
                                        value={student.registrationNumber}
                                    />
                                </TableCell>
                                <TableCell>
                                    <StudentSetLabel
                                        entrySession={student.entrySession}
                                        currentLevel={student.currentLevel}
                                        minimumYearsToDegree={
                                            minimumYearsToDegree
                                        }
                                    />
                                </TableCell>
                                <TableCell className="text-muted-foreground">
                                    {student.advisorName}
                                </TableCell>
                                <TableCell>
                                    <CarryoverBadges
                                        courses={student.pendingCarryovers}
                                    />
                                </TableCell>
                                <TableCell className="text-center font-semibold tabular-nums">
                                    {student.cgpa}
                                </TableCell>
                                <TableCell className="pr-4 text-right">
                                    <ActionLink href={student.profileUrl}>
                                        View
                                    </ActionLink>
                                </TableCell>
                            </TableRow>
                        ))
                    ) : (
                        <EmptyTableRow colSpan={8}>
                            No students match the current filters.
                        </EmptyTableRow>
                    )}
                </TableBody>
            </Table>
            <ScrollBar orientation="horizontal" />
        </ScrollArea>
    );
}

function SetAdvisorTable({
    advisors,
    minimumYearsToDegree,
}: {
    advisors: DashboardHodSetAdvisor[];
    minimumYearsToDegree?: number;
}) {
    return (
        <Table className="text-sm">
            <TableHeader>
                <TableRow>
                    <TableHead className="w-[30%] px-4">Entry Set</TableHead>
                    <TableHead>Course Adviser</TableHead>
                    <TableHead className="w-[18%] pr-4 text-center">
                        Students
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {advisors.map((advisor) => (
                    <TableRow
                        key={advisor.entrySession.id}
                        className="odd:bg-background even:bg-muted/25"
                    >
                        <TableCell className="px-4 font-semibold">
                            <StudentSetLabel
                                entrySession={advisor.entrySession}
                                currentLevel={advisor.currentLevel}
                                minimumYearsToDegree={minimumYearsToDegree}
                            />
                        </TableCell>
                        <TableCell>
                            {advisor.advisor ? (
                                <div className="flex min-w-0 flex-col gap-1.5">
                                    <PersonCell
                                        name={advisor.advisor.name}
                                        secondary={advisor.advisor.email}
                                        avatar={advisor.advisor.avatar}
                                    />
                                    <RoleBadge
                                        role="Course Adviser"
                                        context={studentSetLevelText(
                                            advisor.currentLevel,
                                            minimumYearsToDegree,
                                        )}
                                        className="w-fit"
                                    />
                                </div>
                            ) : (
                                <span className="text-muted-foreground">
                                    Not assigned
                                </span>
                            )}
                        </TableCell>
                        <TableCell className="pr-4 text-center font-medium tabular-nums">
                            {advisor.studentsCount}
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

function ResultSubmissionTable({
    submissions,
    compact = false,
}: {
    submissions: DashboardHodResultSubmission[];
    compact?: boolean;
}) {
    return (
        <ScrollArea>
            <Table className="min-w-[700px] text-sm">
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-[32%] px-4">Course</TableHead>
                        {!compact ? <TableHead>Level</TableHead> : null}
                        <TableHead className="w-[16%]">Status</TableHead>
                        <TableHead className="w-[16%] pr-4 text-right">
                            Submitted
                        </TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {submissions.length > 0 ? (
                        submissions.map((submission) => (
                            <TableRow
                                key={submission.id}
                                className="odd:bg-background even:bg-muted/25"
                            >
                                <TableCell className="px-4">
                                    <div className="font-semibold">
                                        {submission.course
                                            .split(' ')
                                            .slice(0, 2)
                                            .join(' ')}
                                    </div>
                                    <div className="truncate text-xs text-muted-foreground">
                                        {submission.course
                                            .split(' ')
                                            .slice(2)
                                            .join(' ')}
                                    </div>
                                </TableCell>
                                {!compact ? (
                                    <TableCell className="text-muted-foreground">
                                        {submission.level} {submission.semester}
                                    </TableCell>
                                ) : null}
                                <TableCell>
                                    <StatusBadge status={submission.status} />
                                </TableCell>
                                <TableCell className="pr-4 text-right text-muted-foreground tabular-nums">
                                    {submission.submittedAt}
                                </TableCell>
                            </TableRow>
                        ))
                    ) : (
                        <EmptyTableRow colSpan={compact ? 3 : 4}>
                            No result submissions.
                        </EmptyTableRow>
                    )}
                </TableBody>
            </Table>
            <ScrollBar orientation="horizontal" />
        </ScrollArea>
    );
}

function CarryoverSummaryTable({
    summaries,
}: {
    summaries: DashboardStaffHomeCarryoverSummary[];
}) {
    return (
        <Table className="text-sm">
            <TableHeader>
                <TableRow>
                    <TableHead className="w-[56%] px-4">Course</TableHead>
                    <TableHead className="w-[16%] text-center">Units</TableHead>
                    <TableHead className="w-[28%] pr-4 text-right">
                        Students
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {summaries.length > 0 ? (
                    summaries.map((summary) => (
                        <TableRow
                            key={summary.code}
                            className="odd:bg-background even:bg-muted/25"
                        >
                            <TableCell className="px-4">
                                <div className="font-semibold">
                                    {summary.code}
                                </div>
                                <div className="truncate text-xs text-muted-foreground">
                                    {summary.title}
                                </div>
                            </TableCell>
                            <TableCell className="text-center font-medium tabular-nums">
                                {summary.units}
                            </TableCell>
                            <TableCell className="pr-4 text-right font-semibold tabular-nums">
                                {summary.students.length}
                            </TableCell>
                        </TableRow>
                    ))
                ) : (
                    <EmptyTableRow colSpan={3}>
                        No carryover records.
                    </EmptyTableRow>
                )}
            </TableBody>
        </Table>
    );
}

function ClassificationChart({
    rows,
}: {
    rows: Array<{ classification: string; students: number; fill: string }>;
}) {
    const chartConfig = {
        students: { label: 'Students' },
        first: { label: 'First Class', color: 'var(--chart-1)' },
        secondUpper: { label: 'Second Class Upper', color: 'var(--chart-2)' },
        secondLower: { label: 'Second Class Lower', color: 'var(--chart-3)' },
        third: { label: 'Third Class', color: 'var(--chart-4)' },
        below: { label: 'Below Third Class', color: 'var(--chart-5)' },
    } satisfies ChartConfig;

    return (
        <div className="grid gap-4 p-4 lg:grid-cols-[minmax(0,1fr)_14rem]">
            <ChartContainer
                config={chartConfig}
                className="min-h-64 w-full"
                initialDimension={{ width: 420, height: 260 }}
            >
                <PieChart>
                    <ChartTooltip content={<ChartTooltipContent />} />
                    <Pie
                        data={rows}
                        dataKey="students"
                        nameKey="classification"
                        innerRadius={56}
                        outerRadius={92}
                        paddingAngle={2}
                    >
                        {rows.map((row) => (
                            <Cell key={row.classification} fill={row.fill} />
                        ))}
                    </Pie>
                </PieChart>
            </ChartContainer>
            <div className="grid content-center gap-2 text-sm">
                {rows.map((row) => (
                    <div
                        key={row.classification}
                        className="flex items-center justify-between gap-3"
                    >
                        <span className="text-muted-foreground">
                            {row.classification}
                        </span>
                        <span className="font-semibold tabular-nums">
                            {row.students}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

function GpaTrendChart({
    rows,
}: {
    rows: Array<{ level: string; average: number }>;
}) {
    const chartConfig = {
        average: { label: 'Average GPA', color: 'var(--chart-1)' },
    } satisfies ChartConfig;

    return (
        <div className="p-4">
            <ChartContainer
                config={chartConfig}
                className="min-h-64 w-full"
                initialDimension={{ width: 520, height: 260 }}
            >
                <LineChart data={rows} margin={{ left: 8, right: 12 }}>
                    <CartesianGrid vertical={false} />
                    <XAxis
                        dataKey="level"
                        tickLine={false}
                        axisLine={false}
                        tickMargin={8}
                    />
                    <YAxis
                        domain={[0, 5]}
                        tickLine={false}
                        axisLine={false}
                        tickMargin={8}
                    />
                    <ChartTooltip content={<ChartTooltipContent />} />
                    <Line
                        type="monotone"
                        dataKey="average"
                        stroke="var(--color-average)"
                        strokeWidth={2}
                        dot
                    />
                </LineChart>
            </ChartContainer>
        </div>
    );
}

function StudentStandingTable({
    students,
    minimumYearsToDegree,
}: {
    students: DashboardStaffStudent[];
    minimumYearsToDegree?: number;
}) {
    return (
        <Table className="text-sm">
            <TableHeader>
                <TableRow>
                    <TableHead className="px-4">Student</TableHead>
                    <TableHead>Set</TableHead>
                    <TableHead className="w-24 text-center">CGPA</TableHead>
                    <TableHead className="w-20 pr-4 text-right">
                        Action
                    </TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {students.length > 0 ? (
                    students.map((student) => (
                        <TableRow
                            key={student.id}
                            className="odd:bg-background even:bg-muted/25"
                        >
                            <TableCell className="px-4">
                                <PersonCell
                                    name={student.fullName}
                                    avatar={student.avatar}
                                />
                            </TableCell>
                            <TableCell>
                                <CompactStudentSet
                                    student={student}
                                    minimumYearsToDegree={minimumYearsToDegree}
                                />
                            </TableCell>
                            <TableCell className="text-center font-semibold tabular-nums">
                                {student.cgpa}
                            </TableCell>
                            <TableCell className="pr-4 text-right">
                                <ActionLink href={student.profileUrl}>
                                    View
                                </ActionLink>
                            </TableCell>
                        </TableRow>
                    ))
                ) : (
                    <EmptyTableRow colSpan={4}>
                        No students match the current filters.
                    </EmptyTableRow>
                )}
            </TableBody>
        </Table>
    );
}

function CompactStudentSet({
    student,
    minimumYearsToDegree,
}: {
    student: DashboardStaffStudent;
    minimumYearsToDegree?: number;
}) {
    const setName =
        student.entrySession === null
            ? 'No entry set'
            : `${student.entrySession.name} Set`;

    return (
        <div className="flex min-w-0 flex-wrap items-center gap-1.5">
            <span className="truncate font-medium">{setName}</span>
            <Badge
                variant="outline"
                className="border-border bg-muted/60 text-xs text-muted-foreground"
            >
                {studentSetLevelText(
                    student.currentLevel,
                    minimumYearsToDegree,
                )}
            </Badge>
        </div>
    );
}

function LevelGpaTable({
    rows,
}: {
    rows: Array<{ level: string; gpa: string }>;
}) {
    return (
        <Table className="text-sm">
            <TableHeader>
                <TableRow>
                    <TableHead className="px-4">Level</TableHead>
                    <TableHead className="pr-4 text-right">GPA</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.length > 0 ? (
                    rows.map((row) => (
                        <TableRow
                            key={row.level}
                            className="odd:bg-background even:bg-muted/25"
                        >
                            <TableCell className="px-4 font-medium">
                                {row.level}
                            </TableCell>
                            <TableCell className="pr-4 text-right font-semibold tabular-nums">
                                {row.gpa}
                            </TableCell>
                        </TableRow>
                    ))
                ) : (
                    <EmptyTableRow colSpan={2}>No GPA records.</EmptyTableRow>
                )}
            </TableBody>
        </Table>
    );
}

function StudentRecordSemesterCard({
    semester,
}: {
    semester: DashboardStudentRecordSemester;
}) {
    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardHeader className="border-b border-border">
                <CardTitle>
                    <QualifiedSemester
                        level={semester.level.title}
                        semester={semester.semester.name}
                    />
                </CardTitle>
                <p className="text-sm text-muted-foreground">
                    GPA {semester.gpa} · {semester.totalUnits} units
                </p>
            </CardHeader>
            <CardContent className="p-0">
                <Table className="text-sm">
                    <TableHeader>
                        <TableRow>
                            <TableHead className="px-4">Course</TableHead>
                            <TableHead className="w-20 text-center">
                                Units
                            </TableHead>
                            <TableHead className="w-20 text-center">
                                Score
                            </TableHead>
                            <TableHead className="w-20 pr-4 text-center">
                                Grade
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {semester.courses.map((course) => (
                            <TableRow
                                key={course.code}
                                className="odd:bg-background even:bg-muted/25"
                            >
                                <TableCell className="px-4">
                                    <div className="font-semibold">
                                        {course.code}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {course.title}
                                    </div>
                                </TableCell>
                                <TableCell className="text-center font-medium tabular-nums">
                                    {course.units}
                                </TableCell>
                                <TableCell className="text-center font-medium tabular-nums">
                                    {course.score}
                                </TableCell>
                                <TableCell className="pr-4 text-center">
                                    <GradeBadge grade={course.grade} />
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </CardContent>
        </Card>
    );
}

function MetricBlock({
    label,
    value,
    detail,
}: {
    label: string;
    value: ReactNode;
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
                <div className="truncate text-3xl font-semibold tracking-normal tabular-nums">
                    {value}
                </div>
                <div className="mt-1 truncate text-xs text-muted-foreground">
                    {detail}
                </div>
            </CardContent>
        </Card>
    );
}

function PageSummary({ title, detail }: { title: string; detail: string }) {
    return (
        <div className="flex flex-col gap-1">
            <h1 className="text-xl font-semibold tracking-normal">{title}</h1>
            <p className="text-sm text-muted-foreground">{detail}</p>
        </div>
    );
}

function DashboardCard({
    title,
    children,
}: {
    title: string;
    children: ReactNode;
}) {
    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardHeader className="border-b border-border">
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent className="p-0">{children}</CardContent>
        </Card>
    );
}

function SearchInput({
    value,
    onChange,
    placeholder,
}: {
    value: string;
    onChange: (value: string) => void;
    placeholder: string;
}) {
    return (
        <div className="relative">
            <RiSearchLine className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
            <Input
                value={value}
                placeholder={placeholder}
                className="pl-9"
                onChange={(event) => onChange(event.target.value)}
            />
        </div>
    );
}

function FilterSelect({
    value,
    onChange,
    label,
    allLabel,
    options,
}: {
    value: string;
    onChange: (value: string) => void;
    label: string;
    allLabel: string;
    options: FilterOption[];
}) {
    return (
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger aria-label={label}>
                <SelectValue placeholder={label} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="all">{allLabel}</SelectItem>
                {options.map((option) => (
                    <SelectItem
                        key={optionValue(option)}
                        value={optionValue(option)}
                    >
                        {optionLabel(option)}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function setOptionLabel(
    setAdvisor: DashboardHodSetAdvisor,
    minimumYearsToDegree?: number,
): string {
    const set = studentSetText({
        entrySession: setAdvisor.entrySession,
        currentLevel: setAdvisor.currentLevel,
        minimumYearsToDegree,
    });
    const adviser = setAdvisor.advisor?.name ?? 'No course adviser';

    return `${set} · ${adviser}`;
}

function optionValue(option: FilterOption): string {
    return typeof option === 'string' ? option : option.value;
}

function optionLabel(option: FilterOption): string {
    return typeof option === 'string' ? option : option.label;
}

function CarryoverBadges({ courses }: { courses: string[] }) {
    if (courses.length === 0) {
        return <span className="text-muted-foreground">None</span>;
    }

    return (
        <div className="flex flex-wrap gap-1">
            {courses.slice(0, 3).map((course) => (
                <Badge
                    key={course}
                    variant="outline"
                    className="border-destructive/30 bg-destructive/10 text-destructive"
                >
                    {course}
                </Badge>
            ))}
            {courses.length > 3 ? (
                <Badge variant="outline" className="border-border bg-muted/60">
                    +{courses.length - 3} more
                </Badge>
            ) : null}
        </div>
    );
}

function GradeBadge({ grade }: { grade: string }) {
    const className =
        grade === 'F'
            ? 'border-destructive/30 bg-destructive/10 text-destructive'
            : 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-300';

    return (
        <Badge variant="outline" className={className}>
            {grade}
        </Badge>
    );
}

function StatusBadge({ status }: { status: string }) {
    const className =
        status === 'Active' || status === 'Approved'
            ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-300'
            : status === 'Awaiting review'
              ? 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-400/30 dark:bg-blue-400/10 dark:text-blue-300'
              : status === 'Missing'
                ? 'border-destructive/30 bg-destructive/10 text-destructive'
                : 'border-border bg-muted/60 text-foreground';

    return (
        <Badge variant="outline" className={className}>
            {status}
        </Badge>
    );
}

function SummaryLine({ label, value }: { label: string; value: string }) {
    return (
        <div className="grid grid-cols-[8rem_minmax(0,1fr)] gap-3">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium">{value}</span>
        </div>
    );
}

function EmptyTableRow({
    children,
    colSpan,
}: {
    children: string;
    colSpan: number;
}) {
    return (
        <TableRow>
            <TableCell
                colSpan={colSpan}
                className="px-4 py-6 text-muted-foreground"
            >
                {children}
            </TableCell>
        </TableRow>
    );
}

function filterStudents(
    students: DashboardStaffStudent[],
    filters: {
        query: string;
        level: string;
        set: string;
        carryover: string;
        minimumYearsToDegree?: number;
    },
) {
    return students.filter((student) => {
        const searchText = [
            student.fullName,
            student.email,
            student.registrationNumber,
            student.currentLevel?.title ?? '',
            studentSetLevelText(
                student.currentLevel,
                filters.minimumYearsToDegree,
            ),
            student.entrySession?.name ?? '',
            student.advisorName,
        ]
            .join(' ')
            .toLowerCase();
        const hasCarryover = student.pendingCarryovers.length > 0;

        return (
            searchText.includes(filters.query.trim().toLowerCase()) &&
            (filters.level === 'all' ||
                studentSetLevelText(
                    student.currentLevel,
                    filters.minimumYearsToDegree,
                ) === filters.level) &&
            (filters.set === 'all' ||
                student.entrySession?.name === filters.set) &&
            (filters.carryover === 'all' ||
                (filters.carryover === 'Has carryover' && hasCarryover) ||
                (filters.carryover === 'No carryover' && !hasCarryover))
        );
    });
}

function classificationRowsFor(students: DashboardStaffStudent[]) {
    const labels = [
        ['First Class', 'var(--chart-1)'],
        ['Second Class Upper', 'var(--chart-2)'],
        ['Second Class Lower', 'var(--chart-3)'],
        ['Third Class', 'var(--chart-4)'],
        ['Below Third Class', 'var(--chart-5)'],
    ] as const;

    return labels.map(([classification, fill]) => ({
        classification,
        fill,
        students: students.filter(
            (student) =>
                classificationFor(Number(student.cgpa)) === classification,
        ).length,
    }));
}

function classificationFor(cgpa: number): string {
    if (cgpa >= 4.5) {
        return 'First Class';
    }

    if (cgpa >= 3.5) {
        return 'Second Class Upper';
    }

    if (cgpa >= 2.4) {
        return 'Second Class Lower';
    }

    if (cgpa >= 1.5) {
        return 'Third Class';
    }

    return 'Below Third Class';
}

function gpaTrendRowsFor(
    students: DashboardStaffStudent[],
    levels: DashboardLevel[],
) {
    return levels
        .map((academicLevel) => {
            const level = shortLevelTitle(academicLevel.title);
            const gpas = students
                .map((student) =>
                    student.levelGpas.find((row) => row.level === level),
                )
                .filter((row) => row !== undefined)
                .map((row) => Number(row.gpa));

            return {
                level,
                average:
                    gpas.length === 0
                        ? 0
                        : Number(
                              (
                                  gpas.reduce((total, gpa) => total + gpa, 0) /
                                  gpas.length
                              ).toFixed(2),
                          ),
            };
        })
        .filter((row) => row.average > 0);
}

function averageGpa(values: string[]): string {
    const validValues = values
        .map((value) => Number(value))
        .filter((value) => !Number.isNaN(value));

    if (validValues.length === 0) {
        return '0.00';
    }

    return (
        validValues.reduce((total, value) => total + value, 0) /
        validValues.length
    ).toFixed(2);
}

function shortLevelTitle(level: string): string {
    const match = level.match(/^(\d+)00 Level$/);

    if (match) {
        return `${match[1]}00L`;
    }

    return level.replace(' Level', 'L');
}

function unique(values: Array<string | undefined | null>): string[] {
    return Array.from(new Set(values.filter(Boolean) as string[])).sort();
}

function uniqueFlat(values: string[][]): string[] {
    return unique(values.flat());
}
