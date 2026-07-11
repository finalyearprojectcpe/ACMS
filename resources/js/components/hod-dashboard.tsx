import { Link, router, useForm } from '@inertiajs/react';
import {
    RiArrowLeftLine,
    RiFileExcel2Line,
    RiLockPasswordLine,
    RiSave3Line,
    RiSearchLine,
} from '@remixicon/react';
import type { FormEvent, ReactNode } from 'react';
import { useMemo, useState } from 'react';
import * as XLSX from 'xlsx';

import {
    editStudentSetAdvisor,
    importHodStaff,
    section as dashboardSection,
    showResultSheet,
    showResultSheetApproval,
    storeImportedHodStaff,
    updateHodPassword,
    updateStudentSetAdvisor,
} from '@/actions/App/Http/Controllers/DashboardController';
import { ActionLink } from '@/components/action-link';
import { CopyableValue } from '@/components/copyable-value';
import { CourseOfferingBrowser } from '@/components/course-offering-browser';
import {
    AverageGpaByLevelBarChart,
    ChartCard,
    ResultClassificationPieChart,
    averageGpaByLevelRows,
    classificationRowsForStudents,
    studentsForEntrySession,
} from '@/components/dashboard-charts';
import { GradeDisplay } from '@/components/grade-display';
import { PersonCell } from '@/components/person-cell';
import { PersonPicker } from '@/components/person-picker';
import { ProfilePhotoSettingsCard } from '@/components/profile-photo-settings-card';
import {
    QualifiedSemester,
    qualifiedSemesterLabel,
} from '@/components/qualified-semester';
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
import { StudentSetPicker } from '@/components/student-set-picker';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardForm,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyTitle,
} from '@/components/ui/empty';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { PasswordInput } from '@/components/ui/password-input';
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
    DashboardResultSheet,
    DashboardHodSetAdvisor,
    DashboardHodStaff,
    DashboardHodStaffForm,
    DashboardHodSetAdvisorForm,
    DashboardStaffStudent,
    DashboardStudentRecord,
    DashboardStudentRecordSemester,
} from '@/types';

type HodDashboardProps = {
    hod: DashboardHod;
    section: string;
};

type FilterOption = string | { value: string; label: string };

type PasswordFormData = {
    current_password: string;
    password: string;
    password_confirmation: string;
};

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

    if (section === 'result-approvals') {
        return <HodResultApprovalsPage hod={hod} />;
    }

    if (section === 'settings') {
        return <HodSettingsPage hod={hod} />;
    }

    return <HodHomePage hod={hod} />;
}

function HodHomePage({ hod }: { hod: DashboardHod }) {
    const [gpaScope, setGpaScope] = useState('department');
    const [classificationScope, setClassificationScope] =
        useState('department');
    const gpaStudents = useMemo(
        () =>
            gpaScope === 'department'
                ? hod.students
                : studentsForEntrySession(hod.students, gpaScope),
        [gpaScope, hod.students],
    );
    const classificationStudents = useMemo(
        () =>
            classificationScope === 'department'
                ? hod.students
                : studentsForEntrySession(hod.students, classificationScope),
        [classificationScope, hod.students],
    );

    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
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
                    label="Students With Carryovers"
                    value={hod.metrics.studentsWithCarryovers}
                    detail="Across department levels"
                />
            </div>

            <div className="grid gap-4 xl:grid-cols-2">
                <ChartCard
                    title="GPA by Level"
                    detail="Academic performance for the selected student scope."
                    control={
                        <DepartmentSetScopePicker
                            value={gpaScope}
                            onValueChange={setGpaScope}
                            sets={hod.setAdvisors}
                            departmentLabel={`${hod.department.abbreviation} Department`}
                            minimumYearsToDegree={
                                hod.department.minimumYearsToDegree
                            }
                            ariaLabel="Select GPA scope"
                        />
                    }
                >
                    <AverageGpaByLevelBarChart
                        rows={averageGpaByLevelRows(gpaStudents, hod.levels)}
                    />
                </ChartCard>

                <ChartCard
                    title="Result Class Distribution"
                    detail="Student standing by CGPA classification."
                    control={
                        <DepartmentSetScopePicker
                            value={classificationScope}
                            onValueChange={setClassificationScope}
                            sets={hod.setAdvisors}
                            departmentLabel={`${hod.department.abbreviation} Department`}
                            minimumYearsToDegree={
                                hod.department.minimumYearsToDegree
                            }
                            ariaLabel="Select result scope"
                        />
                    }
                >
                    <ResultClassificationPieChart
                        rows={classificationRowsForStudents(
                            classificationStudents,
                        )}
                    />
                </ChartCard>
            </div>

            <div className="grid gap-4 xl:grid-cols-[1.05fr_0.95fr]">
                <DashboardCard title="Set Adviser Coverage">
                    <SetAdvisorTable
                        advisors={hod.setAdvisors}
                        minimumYearsToDegree={
                            hod.department.minimumYearsToDegree
                        }
                    />
                </DashboardCard>

                <DashboardCard title="Recent Staff Onboarding">
                    <StaffTable staff={hod.staff.slice(0, 5)} compact />
                </DashboardCard>
            </div>
        </div>
    );
}

function DepartmentSetScopePicker({
    value,
    onValueChange,
    sets,
    departmentLabel,
    minimumYearsToDegree,
    ariaLabel,
}: {
    value: string;
    onValueChange: (value: string) => void;
    sets: DashboardHodSetAdvisor[];
    departmentLabel: string;
    minimumYearsToDegree?: number | null;
    ariaLabel: string;
}) {
    return (
        <StudentSetPicker
            value={value}
            onValueChange={onValueChange}
            options={sets.map((set) => ({
                value: set.entrySession.name,
                entrySession: set.entrySession,
                currentLevel: set.currentLevel,
                minimumYearsToDegree,
            }))}
            allOption={{ value: 'department', label: departmentLabel }}
            ariaLabel={ariaLabel}
        />
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
                : (hod.setAdvisors.find(
                      (setAdvisor) =>
                          setAdvisor.entrySession.name === setFilter,
                  ) ?? null),
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

    if (selectedSession && selectedLevel) {
        return (
            <CourseOfferingBrowser
                title="Course Offerings"
                detail="View faculty, departmental, and elective courses by session, level, and semester."
                levels={hod.levels}
                selectedLevel={selectedLevel}
                semesters={hod.courseOfferings.semesters}
                session={selectedSession}
                sessions={hod.academicSessions}
                selectedSessionId={selectedSession.id.toString()}
                onSessionChange={(academicSession) =>
                    updateSelection({ academicSession })
                }
                onSelectionChange={(level) =>
                    updateSelection({ level: level.slug })
                }
            />
        );
    }

    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <PageSummary
                title="Course Offerings"
                detail="View faculty, departmental, and elective courses by session, level, and semester."
            />
            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardContent className="p-6 text-sm text-muted-foreground">
                    Add an academic session and level before viewing course
                    offerings.
                </CardContent>
            </Card>
        </div>
    );
}

function HodStudentResultsPage({ hod }: { hod: DashboardHod }) {
    const minimumYearsToDegree = hod.department.minimumYearsToDegree;
    const setGroups = useMemo(
        () =>
            resultSheetSetGroups(
                hod.studentResults.resultSheets,
                hod.setAdvisors,
                minimumYearsToDegree,
            ),
        [
            hod.setAdvisors,
            hod.studentResults.resultSheets,
            minimumYearsToDegree,
        ],
    );
    const [selectedSetId, setSelectedSetId] = useState(
        setGroups[0]?.value ?? '',
    );
    const selectedGroup =
        setGroups.find((group) => group.value === selectedSetId) ??
        setGroups[0] ??
        null;

    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <PageSummary
                title="Student Results"
                detail="Browse published results by student set."
            />

            <DashboardCard
                title="Published Results"
                detail={
                    selectedGroup
                        ? `${selectedGroup.label} · ${selectedGroup.sheets.length} ${
                              selectedGroup.sheets.length === 1
                                  ? 'published result sheet'
                                  : 'published result sheets'
                          }`
                        : 'HODs see only results that are already public.'
                }
                flush
                action={
                    setGroups.length > 0 ? (
                        <ResultSetCombobox
                            groups={setGroups}
                            value={selectedGroup?.value ?? ''}
                            onChange={setSelectedSetId}
                            minimumYearsToDegree={minimumYearsToDegree}
                        />
                    ) : null
                }
            >
                {selectedGroup ? (
                    <ResultArchiveTable sheets={selectedGroup.sheets} />
                ) : (
                    <Empty className="border-0 px-4 py-10">
                        <EmptyHeader>
                            <EmptyTitle>No published results</EmptyTitle>
                            <EmptyDescription>
                                Published results will appear here after the HOD
                                approves submitted result sheets.
                            </EmptyDescription>
                        </EmptyHeader>
                    </Empty>
                )}
            </DashboardCard>
        </div>
    );
}

function HodResultApprovalsPage({ hod }: { hod: DashboardHod }) {
    const minimumYearsToDegree = hod.department.minimumYearsToDegree;

    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <PageSummary
                title="Result Approvals"
                detail="Review result sheets submitted by course advisers."
            />

            <HodApprovalTable
                sheets={hod.studentResults.approvalSheets}
                setAdvisors={hod.setAdvisors}
                minimumYearsToDegree={minimumYearsToDegree}
            />
        </div>
    );
}

type ResultSheetSetGroup = {
    value: string;
    label: string;
    entrySession: DashboardResultSheet['entrySession'];
    currentLevel: DashboardResultSheet['level'] | null;
    sheets: DashboardResultSheet[];
};

function ResultSetCombobox({
    groups,
    value,
    onChange,
    minimumYearsToDegree,
}: {
    groups: ResultSheetSetGroup[];
    value: string;
    onChange: (value: string) => void;
    minimumYearsToDegree?: number | null;
}) {
    return (
        <StudentSetPicker
            value={value}
            onValueChange={onChange}
            options={groups.map((group) => ({
                value: group.value,
                entrySession: group.entrySession,
                currentLevel: group.currentLevel,
                minimumYearsToDegree,
                meta: `${group.sheets.length} ${
                    group.sheets.length === 1 ? 'sheet' : 'sheets'
                }`,
                searchText: `${group.label} ${group.entrySession.name}`,
            }))}
            placeholder="Select student set"
            ariaLabel="Select student set"
            className="w-full sm:w-96"
        />
    );
}

function resultSheetSetGroups(
    sheets: DashboardResultSheet[],
    setAdvisors: DashboardHodSetAdvisor[],
    minimumYearsToDegree?: number | null,
): ResultSheetSetGroup[] {
    const currentLevelBySet = new Map(
        setAdvisors.map((setAdvisor) => [
            setAdvisor.entrySession.id,
            setAdvisor.currentLevel,
        ]),
    );
    const map = new Map<number, ResultSheetSetGroup>();

    for (const sheet of sheets) {
        const currentLevel = currentLevelBySet.has(sheet.entrySession.id)
            ? (currentLevelBySet.get(sheet.entrySession.id) ?? null)
            : sheet.level;

        if (!map.has(sheet.entrySession.id)) {
            map.set(sheet.entrySession.id, {
                value: sheet.entrySession.id.toString(),
                label: studentSetText({
                    entrySession: sheet.entrySession,
                    currentLevel,
                    minimumYearsToDegree,
                }),
                entrySession: sheet.entrySession,
                currentLevel,
                sheets: [],
            });
        }

        map.get(sheet.entrySession.id)?.sheets.push(sheet);
    }

    return [...map.values()]
        .map((group) => ({
            ...group,
            sheets: resultSheetSort(group.sheets),
        }))
        .sort((first, second) =>
            second.entrySession.name.localeCompare(first.entrySession.name),
        );
}

function ResultArchiveTable({ sheets }: { sheets: DashboardResultSheet[] }) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead className="px-4">Result Sheet</TableHead>
                    <TableHead>Academic Session</TableHead>
                    <TableHead>Submitted By</TableHead>
                    <TableHead className="text-center">Students</TableHead>
                    <TableHead className="text-center">Courses</TableHead>
                    <TableHead className="text-center">Avg GPA</TableHead>
                    <TableHead className="pr-4 text-right">Action</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {sheets.map((sheet) => (
                    <TableRow key={sheet.id}>
                        <TableCell className="px-4">
                            <div className="font-semibold">
                                {qualifiedSemesterLabel(
                                    sheet.level.title,
                                    sheet.semester.name,
                                )}
                            </div>
                        </TableCell>
                        <TableCell>
                            {sheet.academicSession.name} Session
                        </TableCell>
                        <TableCell>
                            <SubmittedByCell person={sheet.submittedBy} />
                        </TableCell>
                        <TableCell className="text-center font-medium tabular-nums">
                            {sheet.studentCount}
                        </TableCell>
                        <TableCell className="text-center font-medium tabular-nums">
                            {sheet.courseCount}
                        </TableCell>
                        <TableCell className="text-center font-medium tabular-nums">
                            {sheet.averageGpa}
                        </TableCell>
                        <TableCell className="pr-4 text-right">
                            <ActionLink
                                href={showResultSheet({
                                    resultSheet: sheet.id,
                                })}
                            >
                                View Sheet
                            </ActionLink>
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

function HodApprovalTable({
    sheets,
    setAdvisors,
    minimumYearsToDegree,
}: {
    sheets: DashboardResultSheet[];
    setAdvisors: DashboardHodSetAdvisor[];
    minimumYearsToDegree?: number | null;
}) {
    const [search, setSearch] = useState('');
    const [setFilter, setSetFilter] = useState('all');
    const currentLevelBySet = useMemo(
        () =>
            new Map(
                setAdvisors.map((setAdvisor) => [
                    setAdvisor.entrySession.id,
                    setAdvisor.currentLevel,
                ]),
            ),
        [setAdvisors],
    );
    const setOptions = useMemo(
        () => resultSheetSetGroups(sheets, setAdvisors, minimumYearsToDegree),
        [minimumYearsToDegree, setAdvisors, sheets],
    );
    const visibleSheets = useMemo(
        () =>
            resultSheetSort(sheets).filter((sheet) => {
                const setLabel = resultSheetSetLabel(
                    sheet,
                    currentLevelBySet,
                    minimumYearsToDegree,
                );

                return (
                    resultSheetSearchText(sheet, setLabel).includes(
                        search.trim().toLowerCase(),
                    ) &&
                    (setFilter === 'all' ||
                        sheet.entrySession.id.toString() === setFilter)
                );
            }),
        [currentLevelBySet, minimumYearsToDegree, search, setFilter, sheets],
    );

    return (
        <DashboardCard
            title="Submitted Results"
            detail="Result sheets waiting for HOD review."
            flush
            toolbar={
                <div className="grid gap-3 lg:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">
                    <SearchInput
                        value={search}
                        onChange={setSearch}
                        placeholder="Search submitted results"
                    />
                    <SearchableSetFilter
                        groups={setOptions}
                        value={setFilter}
                        onChange={setSetFilter}
                        minimumYearsToDegree={minimumYearsToDegree}
                    />
                </div>
            }
        >
            {visibleSheets.length > 0 ? (
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="px-4">Result Sheet</TableHead>
                            <TableHead>Set</TableHead>
                            <TableHead>Submitted By</TableHead>
                            <TableHead>Submitted On</TableHead>
                            <TableHead className="text-center">
                                Students
                            </TableHead>
                            <TableHead className="text-center">
                                Avg GPA
                            </TableHead>
                            <TableHead className="pr-4 text-right">
                                Action
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {visibleSheets.map((sheet) => (
                            <TableRow key={sheet.id}>
                                <TableCell className="px-4">
                                    <div className="font-semibold">
                                        {qualifiedSemesterLabel(
                                            sheet.level.title,
                                            sheet.semester.name,
                                        )}
                                    </div>
                                    <div className="text-xs text-muted-foreground">
                                        {sheet.academicSession.name} Session
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <StudentSetLabel
                                        entrySession={sheet.entrySession}
                                        currentLevel={resultSheetCurrentLevel(
                                            sheet,
                                            currentLevelBySet,
                                        )}
                                        minimumYearsToDegree={
                                            minimumYearsToDegree
                                        }
                                    />
                                </TableCell>
                                <TableCell>
                                    <SubmittedByCell
                                        person={sheet.submittedBy}
                                    />
                                </TableCell>
                                <TableCell>
                                    {sheet.submittedAt ?? (
                                        <span className="text-muted-foreground">
                                            Not recorded
                                        </span>
                                    )}
                                </TableCell>
                                <TableCell className="text-center font-medium tabular-nums">
                                    {sheet.studentCount}
                                </TableCell>
                                <TableCell className="text-center font-medium tabular-nums">
                                    {sheet.averageGpa}
                                </TableCell>
                                <TableCell className="pr-4 text-right">
                                    <ActionLink
                                        href={showResultSheetApproval({
                                            resultSheet: sheet.id,
                                        })}
                                    >
                                        Review
                                    </ActionLink>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            ) : (
                <Empty className="border-0 px-4 py-10">
                    <EmptyHeader>
                        <EmptyTitle>No submitted results found</EmptyTitle>
                        <EmptyDescription>
                            Adjust the search or set filter to find a submitted
                            result sheet.
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            )}
        </DashboardCard>
    );
}

function SearchableSetFilter({
    groups,
    value,
    onChange,
    minimumYearsToDegree,
}: {
    groups: ResultSheetSetGroup[];
    value: string;
    onChange: (value: string) => void;
    minimumYearsToDegree?: number | null;
}) {
    return (
        <StudentSetPicker
            value={value}
            onValueChange={onChange}
            options={groups.map((group) => ({
                value: group.value,
                entrySession: group.entrySession,
                currentLevel: group.currentLevel,
                minimumYearsToDegree,
                meta: `${group.sheets.length} ${
                    group.sheets.length === 1 ? 'sheet' : 'sheets'
                }`,
                searchText: `${group.label} ${group.entrySession.name}`,
            }))}
            allOption={{ value: 'all', label: 'All sets' }}
            ariaLabel="Filter by student set"
        />
    );
}

function SubmittedByCell({
    person,
}: {
    person: DashboardResultSheet['submittedBy'];
}) {
    if (person === null) {
        return <span className="text-muted-foreground">Not submitted</span>;
    }

    const cell = (
        <PersonCell
            name={person.name}
            secondary={person.email}
            avatar={person.avatar}
        />
    );

    if (person.profileUrl === null) {
        return cell;
    }

    return (
        <Link
            href={person.profileUrl}
            className="block rounded-sm transition-colors hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-hidden"
        >
            {cell}
        </Link>
    );
}

function resultSheetSort(
    sheets: DashboardResultSheet[],
): DashboardResultSheet[] {
    return [...sheets].sort((first, second) => {
        const levelDiff = first.level.id - second.level.id;

        if (levelDiff !== 0) {
            return levelDiff;
        }

        const semesterDiff =
            semesterRank(first.semester.slug) -
            semesterRank(second.semester.slug);

        if (semesterDiff !== 0) {
            return semesterDiff;
        }

        return first.academicSession.name.localeCompare(
            second.academicSession.name,
        );
    });
}

function resultSheetSetLabel(
    sheet: DashboardResultSheet,
    currentLevelBySet: Map<number, DashboardResultSheet['level'] | null>,
    minimumYearsToDegree?: number | null,
): string {
    return studentSetText({
        entrySession: sheet.entrySession,
        currentLevel: resultSheetCurrentLevel(sheet, currentLevelBySet),
        minimumYearsToDegree,
    });
}

function resultSheetCurrentLevel(
    sheet: DashboardResultSheet,
    currentLevelBySet: Map<number, DashboardResultSheet['level'] | null>,
): DashboardResultSheet['level'] | null {
    return currentLevelBySet.has(sheet.entrySession.id)
        ? (currentLevelBySet.get(sheet.entrySession.id) ?? null)
        : sheet.level;
}

function semesterRank(semester: string): number {
    return semester === 'harmattan' ? 1 : 2;
}

function resultSheetSearchText(
    sheet: DashboardResultSheet,
    setLabel?: string,
): string {
    return [
        qualifiedSemesterLabel(sheet.level.title, sheet.semester.name),
        sheet.academicSession.name,
        sheet.entrySession.name,
        setLabel,
        sheet.statusLabel,
        sheet.submittedBy?.name,
        sheet.submittedBy?.email,
    ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();
}

function HodSettingsPage({ hod }: { hod: DashboardHod }) {
    return (
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-4">
            <PageSummary
                title="Profile Settings"
                detail="Manage your account profile and HOD access."
            />
            <div className="grid gap-4 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
                <div className="grid content-start gap-4">
                    <ProfilePhotoSettingsCard
                        inputId="hod-profile-photo"
                        user={hod.settings.hod}
                    />
                    <HodPasswordSettingsCard />
                </div>

                <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                    <CardHeader className="border-b border-border">
                        <CardTitle>Profile Summary</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 p-4 text-sm">
                        <div className="flex flex-wrap items-center gap-2">
                            <RoleBadge
                                role="HOD"
                                context={hod.department.abbreviation}
                            />
                        </div>
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
        </div>
    );
}

function HodPasswordSettingsCard() {
    const form = useForm<PasswordFormData>({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.submit(updateHodPassword(), {
            onSuccess: () => form.reset(),
        });
    }

    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardForm onSubmit={submit}>
                <CardHeader className="gap-2">
                    <CardTitle className="flex items-center gap-2 text-base">
                        <RiLockPasswordLine className="size-4 text-muted-foreground" />
                        Change Password
                    </CardTitle>
                </CardHeader>
                <CardContent className="px-5 pb-6">
                    <FieldGroup className="gap-6">
                        <Field
                            data-invalid={Boolean(form.errors.current_password)}
                        >
                            <FieldLabel htmlFor="hod-current-password">
                                Current Password
                            </FieldLabel>
                            <PasswordInput
                                id="hod-current-password"
                                value={form.data.current_password}
                                autoComplete="current-password"
                                aria-invalid={Boolean(
                                    form.errors.current_password,
                                )}
                                onChange={(event) =>
                                    form.setData(
                                        'current_password',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                            <FieldError>
                                {form.errors.current_password}
                            </FieldError>
                        </Field>
                        <Field data-invalid={Boolean(form.errors.password)}>
                            <FieldLabel htmlFor="hod-new-password">
                                New Password
                            </FieldLabel>
                            <PasswordInput
                                id="hod-new-password"
                                value={form.data.password}
                                autoComplete="new-password"
                                aria-invalid={Boolean(form.errors.password)}
                                onChange={(event) =>
                                    form.setData('password', event.target.value)
                                }
                                required
                            />
                            <FieldError>{form.errors.password}</FieldError>
                        </Field>
                        <Field
                            data-invalid={Boolean(
                                form.errors.password_confirmation,
                            )}
                        >
                            <FieldLabel htmlFor="hod-confirm-password">
                                Confirm Password
                            </FieldLabel>
                            <PasswordInput
                                id="hod-confirm-password"
                                value={form.data.password_confirmation}
                                autoComplete="new-password"
                                aria-invalid={Boolean(
                                    form.errors.password_confirmation,
                                )}
                                onChange={(event) =>
                                    form.setData(
                                        'password_confirmation',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                            <FieldError>
                                {form.errors.password_confirmation}
                            </FieldError>
                        </Field>
                    </FieldGroup>
                </CardContent>
                <CardFooter className="justify-end border-t bg-muted/20 px-5 py-4">
                    <Button type="submit" disabled={form.processing}>
                        <RiSave3Line />
                        {form.processing ? 'Saving...' : 'Save Password'}
                    </Button>
                </CardFooter>
            </CardForm>
        </Card>
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
                mappings: batch.mappings,
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

export function HodSetAdvisorForm({
    form: advisorForm,
}: {
    form: DashboardHodSetAdvisorForm;
}) {
    const form = useForm({
        staff_profile_id: advisorForm.currentStaffProfileId?.toString() ?? '',
    });
    const backRoute = dashboardSection({ section: 'staff' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.submit(
            updateStudentSetAdvisor({
                entryAcademicSession: advisorForm.set.entrySession.id,
            }),
        );
    }

    return (
        <div className="mx-auto flex w-full max-w-2xl flex-col gap-4">
            <div className="flex items-start justify-between gap-4">
                <PageSummary
                    title="Assign Course Adviser"
                    detail="Choose the staff member responsible for this student set."
                />
                <ActionLink href={backRoute}>Back to Staff</ActionLink>
            </div>
            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardForm onSubmit={submit}>
                    <CardHeader className="border-b border-border">
                        <CardTitle>
                            <StudentSetLabel
                                entrySession={advisorForm.set.entrySession}
                                currentLevel={advisorForm.set.currentLevel}
                                minimumYearsToDegree={
                                    advisorForm.set.minimumYearsToDegree
                                }
                                isGraduated={advisorForm.set.isGraduated}
                            />
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-5">
                        <Field
                            data-invalid={Boolean(form.errors.staff_profile_id)}
                        >
                            <FieldLabel>Course Adviser</FieldLabel>
                            <PersonPicker
                                value={form.data.staff_profile_id}
                                options={advisorForm.staff.map((staff) => ({
                                    value: staff.id.toString(),
                                    name: staff.name,
                                    secondary: staff.email,
                                    avatar: staff.avatar,
                                    unavailableReason: staff.unavailableReason,
                                }))}
                                onValueChange={(value) =>
                                    form.setData('staff_profile_id', value)
                                }
                                placeholder="Search department staff"
                            />
                            <FieldError>
                                {form.errors.staff_profile_id}
                            </FieldError>
                        </Field>
                    </CardContent>
                    <CardFooter className="flex justify-end gap-2 border-t border-border">
                        <Button asChild type="button" variant="outline">
                            <Link href={backRoute}>Cancel</Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={
                                form.processing || !form.data.staff_profile_id
                            }
                        >
                            Save Course Adviser
                        </Button>
                    </CardFooter>
                </CardForm>
            </Card>
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
            <div className="flex flex-col gap-4">
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

                    <Button variant="outline" asChild className="w-fit">
                        <a href={record.statementUrl}>
                            <RiFileExcel2Line className="size-4" />
                            Download Statement
                        </a>
                    </Button>
                </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <MetricBlock
                    label="CGPA"
                    value={record.cgpa ?? 'Not available'}
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
                            value={studentSetLevelText(
                                record.student.currentLevel,
                                record.department?.minimumYearsToDegree,
                            )}
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
                        <TableHead className="w-20 pr-4 text-right">
                            Action
                        </TableHead>
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
                                              .map((set) => studentSetText(set))
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
                                <TableCell className="pr-4 text-right">
                                    {member.profileUrl ? (
                                        <ActionLink href={member.profileUrl}>
                                            View
                                        </ActionLink>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            -
                                        </span>
                                    )}
                                </TableCell>
                            </TableRow>
                        ))
                    ) : (
                        <EmptyTableRow colSpan={compact ? 5 : 6}>
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
                        <TableHead className="min-w-44">
                            Course Adviser
                        </TableHead>
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
                                    {student.cgpa ?? '-'}
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
                    <TableHead className="w-[14%] text-center">
                        Students
                    </TableHead>
                    <TableHead className="w-24 pr-4 text-right">
                        Action
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
                        <TableCell className="pr-4 text-right">
                            <ActionLink
                                href={editStudentSetAdvisor({
                                    entryAcademicSession:
                                        advisor.entrySession.id,
                                })}
                            >
                                {advisor.advisor ? 'Replace' : 'Assign'}
                            </ActionLink>
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

function LevelGpaTable({
    rows,
}: {
    rows: Array<{ level: string; gpa: string | null }>;
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
                                {row.gpa ?? '-'}
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
                                <TableCell className="pr-4 text-center">
                                    <GradeDisplay grade={course.grade} />
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
    detail,
    action,
    toolbar,
    flush = false,
    children,
}: {
    title: string;
    detail?: string;
    action?: ReactNode;
    toolbar?: ReactNode;
    flush?: boolean;
    children: ReactNode;
}) {
    const titleBlock = (
        <div className="min-w-0">
            <CardTitle>{title}</CardTitle>
            {detail ? (
                <div className="mt-1 text-sm text-muted-foreground">
                    {detail}
                </div>
            ) : null}
        </div>
    );

    if (flush) {
        return (
            <section className="overflow-hidden border border-border bg-background shadow-sm ring-1 ring-border/70">
                <div className="grid gap-3 border-b border-border px-4 py-3">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        {titleBlock}
                        {action}
                    </div>
                    {toolbar}
                </div>
                <div>{children}</div>
            </section>
        );
    }

    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardHeader className="flex-col items-start justify-between gap-3 border-b border-border sm:flex-row sm:items-center">
                {titleBlock}
                {action}
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

function StatusBadge({ status }: { status: string }) {
    const className =
        status === 'Active' || status === 'Approved'
            ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-300'
            : status === 'Awaiting review' || status === 'Awaiting HOD Review'
              ? 'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-400/30 dark:bg-blue-400/10 dark:text-blue-300'
              : status === 'Returned for Correction'
                ? 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-300'
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

function unique(values: Array<string | undefined | null>): string[] {
    return Array.from(new Set(values.filter(Boolean) as string[])).sort();
}

function uniqueFlat(values: string[][]): string[] {
    return unique(values.flat());
}
