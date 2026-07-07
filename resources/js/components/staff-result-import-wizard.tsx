import { Link, router } from '@inertiajs/react';
import {
    RiArrowLeftLine,
    RiArrowRightLine,
    RiFileExcel2Line,
    RiSave3Line,
    RiSendPlaneLine,
} from '@remixicon/react';
import { useMemo, useState } from 'react';
import * as XLSX from 'xlsx';

import {
    createCourseOffering,
    storeResultSheetImport,
} from '@/actions/App/Http/Controllers/DashboardController';
import { ResultMatrixTable } from '@/components/result-matrix-table';
import { StudentSetChoiceLabel } from '@/components/student-set-label';
import { StudentSetPicker } from '@/components/student-set-picker';
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
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
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
import type {
    DashboardResultSheet,
    DashboardStaffResultImportForm,
} from '@/types';

type StaffResultImportWizardProps = {
    form: DashboardStaffResultImportForm;
};

type ParsedCourse = {
    code: string;
    title: string;
    creditUnits: number;
    courseOfferingId: number | null;
    offeringLabel: string | null;
    createInAcademicYearSemesterId: string;
    isCarryover: boolean;
};

type CourseOfferingOption =
    DashboardStaffResultImportForm['courseOfferings'][number];
type AcademicSessionOption =
    DashboardStaffResultImportForm['academicSessions'][number];

type ParsedRow = {
    row_number: number;
    registration_number: string;
    student_name: string;
    grades: Record<string, string>;
};

type WizardStep = 'context' | 'upload' | 'courses' | 'review';

const VALID_GRADES = new Set(['A', 'B', 'C', 'D', 'E', 'F']);

const WIZARD_STEPS: Array<{ id: WizardStep; label: string }> = [
    { id: 'context', label: 'Context' },
    { id: 'upload', label: 'Upload' },
    { id: 'courses', label: 'Course Offerings' },
    { id: 'review', label: 'Review' },
];

export function StaffResultImportWizard({
    form,
}: StaffResultImportWizardProps) {
    const knownCourses = useMemo(
        () =>
            new Map(
                form.knownCourses.map((course) => [
                    normalizeCourseCode(course.code),
                    course,
                ]),
            ),
        [form.knownCourses],
    );
    const [step, setStep] = useState<WizardStep>('context');
    const [entryAcademicSessionId, setEntryAcademicSessionId] = useState(
        form.defaults.entryAcademicSessionId?.toString() ?? '',
    );
    const [academicYearSemesterId, setAcademicYearSemesterId] = useState(
        form.defaults.academicYearSemesterId?.toString() ?? '',
    );
    const [fileName, setFileName] = useState<string | null>(null);
    const [courses, setCourses] = useState<ParsedCourse[]>([]);
    const [rows, setRows] = useState<ParsedRow[]>([]);
    const [parseErrors, setParseErrors] = useState<string[]>([]);
    const [processing, setProcessing] = useState(false);
    const selectedSet = form.assignedSets.find(
        (set) => set.entrySession.id.toString() === entryAcademicSessionId,
    );
    const selectedYearSemester = form.yearSemesters.find(
        (yearSemester) => yearSemester.id.toString() === academicYearSemesterId,
    );
    const derivedAcademicSession = useMemo(
        () =>
            academicSessionForSetLevel(
                form.academicSessions,
                entryAcademicSessionId,
                selectedYearSemester?.level.id,
            ),
        [
            entryAcademicSessionId,
            form.academicSessions,
            selectedYearSemester?.level.id,
        ],
    );
    const academicSessionId = derivedAcademicSession?.id.toString() ?? '';
    const courseErrors = useMemo(
        () =>
            courses.flatMap((course) => {
                const errors: string[] = [];

                if (course.courseOfferingId === null) {
                    errors.push(
                        `${course.code} is not listed as a course offering for ${selectedAcademicSessionName(form, academicSessionId)}. Create the offering before continuing.`,
                    );
                }

                return errors;
            }),
        [academicSessionId, courses, form],
    );
    const errors = useMemo(
        () => [...parseErrors, ...courseErrors],
        [courseErrors, parseErrors],
    );
    const contextComplete =
        entryAcademicSessionId !== '' &&
        academicYearSemesterId !== '' &&
        derivedAcademicSession !== null;
    const contextMissingAcademicSession =
        entryAcademicSessionId !== '' &&
        academicYearSemesterId !== '' &&
        derivedAcademicSession === null;
    const uploadComplete = courses.length > 0 && rows.length > 0;
    const coursesComplete = uploadComplete && courseErrors.length === 0;
    const previewSheet = useMemo(
        () =>
            previewResultSheet(
                courses,
                rows,
                form,
                academicSessionId,
                entryAcademicSessionId,
                academicYearSemesterId,
            ),
        [
            academicSessionId,
            academicYearSemesterId,
            courses,
            entryAcademicSessionId,
            form,
            rows,
        ],
    );
    const canSubmit =
        contextComplete &&
        uploadComplete &&
        coursesComplete &&
        errors.length === 0 &&
        !processing;

    async function parseFile(file: File) {
        const workbook = XLSX.read(await file.arrayBuffer(), {
            type: 'array',
        });
        const sheetName = workbook.SheetNames[0];
        const sheet = workbook.Sheets[sheetName];
        const sheetRows = XLSX.utils.sheet_to_json<unknown[]>(sheet, {
            header: 1,
            defval: '',
        });

        setFileName(file.name);
        parseRows(sheetRows, knownCourses);
    }

    function changeEntryAcademicSession(value: string) {
        setEntryAcademicSessionId(value);
        const nextAcademicSessionId =
            academicSessionForSetLevel(
                form.academicSessions,
                value,
                selectedYearSemester?.level.id,
            )?.id.toString() ?? '';

        setCourses((current) =>
            current.map((course) =>
                resolveParsedCourse(
                    course,
                    form,
                    nextAcademicSessionId,
                    academicYearSemesterId,
                ),
            ),
        );
    }

    function changeAcademicYearSemester(value: string) {
        setAcademicYearSemesterId(value);
        const nextYearSemester = form.yearSemesters.find(
            (yearSemester) => yearSemester.id.toString() === value,
        );
        const nextAcademicSessionId =
            academicSessionForSetLevel(
                form.academicSessions,
                entryAcademicSessionId,
                nextYearSemester?.level.id,
            )?.id.toString() ?? '';

        setCourses((current) =>
            current.map((course) =>
                resolveParsedCourse(course, form, nextAcademicSessionId, value),
            ),
        );
    }

    function parseRows(sheetRows: unknown[][], courseMap: typeof knownCourses) {
        const [headerRow, ...dataRows] = sheetRows;
        const headers = (headerRow ?? []).map((header) =>
            String(header).trim(),
        );
        const regIndex = headers.findIndex(isRegistrationHeader);
        const nameIndex = headers.findIndex(isNameHeader);
        const nextErrors: string[] = [];

        if (regIndex === -1) {
            nextErrors.push(
                'The spreadsheet needs a registration number column.',
            );
        }

        const courseIndexes = headers
            .map((header, index) => ({ header, index }))
            .filter(
                ({ header, index }) =>
                    header !== '' &&
                    index !== regIndex &&
                    index !== nameIndex &&
                    isCourseHeader(header, courseMap),
            );
        const parsedCourses = courseIndexes.map(({ header }) => {
            const code = normalizeCourseCode(header);
            const knownCourse = courseMap.get(code);

            return resolveParsedCourse(
                {
                    code,
                    title: knownCourse?.title ?? '',
                    creditUnits: knownCourse?.creditUnits ?? 3,
                },
                form,
                academicSessionId,
                academicYearSemesterId,
            );
        });
        const parsedRows = dataRows
            .map((row, index) => {
                const grades = Object.fromEntries(
                    courseIndexes.map(({ header, index: columnIndex }) => [
                        normalizeCourseCode(header),
                        normalizeGrade(String(row[columnIndex] ?? '')),
                    ]),
                );

                return {
                    row_number: index + 2,
                    registration_number:
                        regIndex === -1 ? '' : String(row[regIndex]).trim(),
                    student_name:
                        nameIndex === -1
                            ? ''
                            : String(row[nameIndex] ?? '').trim(),
                    grades,
                };
            })
            .filter(
                (row) =>
                    row.registration_number !== '' ||
                    Object.values(row.grades).some((grade) => grade !== ''),
            );

        if (parsedCourses.length === 0) {
            nextErrors.push(
                'No course columns were found. Course columns should be named with course codes like CPE 301.',
            );
        }

        parsedRows.forEach((row) => {
            if (row.registration_number === '') {
                nextErrors.push(`Row ${row.row_number} has no reg no.`);
            }

            Object.entries(row.grades).forEach(([courseCode, grade]) => {
                if (grade !== '' && !VALID_GRADES.has(grade)) {
                    nextErrors.push(
                        `Row ${row.row_number}, ${courseCode}: grade must be A, B, C, D, E, or F.`,
                    );
                }
            });
        });

        setCourses(parsedCourses);
        setRows(parsedRows);
        setParseErrors(nextErrors);

        if (parsedCourses.length > 0 && parsedRows.length > 0) {
            setStep('courses');
        }
    }

    function updateCourse(index: number, update: Partial<ParsedCourse>) {
        setCourses((current) =>
            current.map((course, courseIndex) =>
                courseIndex === index ? { ...course, ...update } : course,
            ),
        );
    }

    function submit(action: 'draft' | 'submit') {
        if (!canSubmit) {
            return;
        }

        setProcessing(true);

        router.post(
            storeResultSheetImport.url(),
            {
                entry_academic_session_id: Number(entryAcademicSessionId),
                academic_year_semester_id: Number(academicYearSemesterId),
                action,
                courses: courses.map((course) => ({
                    course_offering_id: course.courseOfferingId,
                    code: course.code,
                    title: course.title,
                    credit_units: course.creditUnits,
                })),
                rows: rows.map((row) => ({
                    row_number: row.row_number,
                    registration_number: row.registration_number,
                    grades: row.grades,
                })),
            },
            {
                onError: (serverErrors) =>
                    setParseErrors(Object.values(serverErrors).map(String)),
                onFinish: () => setProcessing(false),
            },
        );
    }

    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <div className="min-w-0">
                <h1 className="text-xl font-semibold tracking-normal">
                    Import Results
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Upload a spreadsheet, confirm the course columns, then save
                    it as a draft or submit it to the HOD.
                </p>
            </div>

            <WizardTrail currentStep={step} />

            {step === 'context' ? (
                <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                    <CardHeader className="border-b border-border">
                        <CardTitle>Result Context</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 p-4 lg:grid-cols-2">
                        <Field>
                            <FieldLabel>Student Set</FieldLabel>
                            <StudentSetPicker
                                value={entryAcademicSessionId}
                                onValueChange={changeEntryAcademicSession}
                                options={form.assignedSets.map((set) => ({
                                    value: set.entrySession.id.toString(),
                                    entrySession: set.entrySession,
                                    currentLevel: set.currentLevel,
                                    minimumYearsToDegree:
                                        form.department?.minimumYearsToDegree,
                                }))}
                                ariaLabel="Student set"
                            />
                        </Field>
                        <Field>
                            <FieldLabel>Level and Semester</FieldLabel>
                            <Select
                                value={academicYearSemesterId}
                                onValueChange={changeAcademicYearSemester}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select semester" />
                                </SelectTrigger>
                                <SelectContent>
                                    {form.yearSemesters.map((yearSemester) => (
                                        <SelectItem
                                            key={yearSemester.id}
                                            value={yearSemester.id.toString()}
                                        >
                                            {yearSemester.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </Field>
                    </CardContent>
                    <CardFooter className="flex-col items-start gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <p className="text-sm text-muted-foreground">
                            {derivedAcademicSession ? (
                                <>
                                    Results will be saved under{' '}
                                    {derivedAcademicSession.name} Session.
                                </>
                            ) : contextMissingAcademicSession ? (
                                'No academic session exists for this set and level yet. Ask the school admin to create it before importing results.'
                            ) : (
                                'The academic session will be derived from the selected set and level.'
                            )}
                        </p>
                        <Button
                            type="button"
                            disabled={!contextComplete}
                            onClick={() => setStep('upload')}
                        >
                            Continue
                            <RiArrowRightLine />
                        </Button>
                    </CardFooter>
                </Card>
            ) : null}

            {step === 'upload' ? (
                <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                    <CardHeader className="border-b border-border">
                        <CardTitle>Spreadsheet</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 p-4">
                        <FieldGroup>
                            <Field>
                                <FieldLabel>Upload XLSX or CSV</FieldLabel>
                                <Input
                                    type="file"
                                    accept=".xlsx,.xls,.csv"
                                    onChange={(event) => {
                                        const file =
                                            event.target.files?.[0] ?? null;

                                        if (file) {
                                            void parseFile(file);
                                        }
                                    }}
                                />
                                {fileName ? (
                                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                        <RiFileExcel2Line className="size-4" />
                                        {fileName}
                                    </div>
                                ) : null}
                            </Field>
                            <ErrorList errors={parseErrors} />
                        </FieldGroup>
                    </CardContent>
                    <CardFooter className="justify-between">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setStep('context')}
                        >
                            <RiArrowLeftLine />
                            Back
                        </Button>
                        <Button
                            type="button"
                            disabled={!uploadComplete}
                            onClick={() => setStep('courses')}
                        >
                            Continue
                            <RiArrowRightLine />
                        </Button>
                    </CardFooter>
                </Card>
            ) : null}

            {step === 'courses' ? (
                <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                    <CardHeader className="flex flex-col gap-2 border-b border-border sm:flex-row sm:items-center sm:justify-between">
                        <CardTitle>Course Offerings</CardTitle>
                        <Badge variant="outline">
                            {courses.length} courses
                        </Badge>
                    </CardHeader>
                    <CardContent className="p-0">
                        <ScrollArea className="max-h-[32rem]">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-40 px-4">
                                            Code
                                        </TableHead>
                                        <TableHead>Course Offering</TableHead>
                                        <TableHead className="w-24 text-center">
                                            Units
                                        </TableHead>
                                        <TableHead className="w-64 pr-4">
                                            Action
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {courses.map((course, index) => (
                                        <TableRow key={course.code}>
                                            <TableCell className="px-4 font-semibold">
                                                {course.code}
                                            </TableCell>
                                            <TableCell className="min-w-72">
                                                <div className="flex min-w-0 flex-col gap-1">
                                                    <div className="flex flex-wrap items-center gap-2">
                                                        <span className="font-medium text-foreground">
                                                            {course.title ||
                                                                'Course title needed'}
                                                        </span>
                                                        {course.isCarryover ? (
                                                            <Badge
                                                                variant="outline"
                                                                className="border-red-200 bg-red-50 text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300"
                                                            >
                                                                Carryover
                                                            </Badge>
                                                        ) : null}
                                                    </div>
                                                    {course.courseOfferingId ===
                                                    null ? (
                                                        <span className="text-xs font-medium text-destructive">
                                                            Not listed for{' '}
                                                            {selectedAcademicSessionName(
                                                                form,
                                                                academicSessionId,
                                                            )}
                                                        </span>
                                                    ) : (
                                                        <span className="text-xs text-muted-foreground">
                                                            {
                                                                course.offeringLabel
                                                            }
                                                        </span>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-center font-medium tabular-nums">
                                                {course.creditUnits}
                                            </TableCell>
                                            <TableCell className="pr-4">
                                                {course.courseOfferingId ===
                                                null ? (
                                                    <div className="flex flex-col gap-2">
                                                        <Select
                                                            value={
                                                                course.createInAcademicYearSemesterId
                                                            }
                                                            onValueChange={(
                                                                value,
                                                            ) =>
                                                                updateCourse(
                                                                    index,
                                                                    {
                                                                        createInAcademicYearSemesterId:
                                                                            value,
                                                                    },
                                                                )
                                                            }
                                                        >
                                                            <SelectTrigger>
                                                                <SelectValue placeholder="Choose level and semester" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {form.yearSemesters.map(
                                                                    (
                                                                        yearSemester,
                                                                    ) => (
                                                                        <SelectItem
                                                                            key={
                                                                                yearSemester.id
                                                                            }
                                                                            value={yearSemester.id.toString()}
                                                                        >
                                                                            {
                                                                                yearSemester.label
                                                                            }
                                                                        </SelectItem>
                                                                    ),
                                                                )}
                                                            </SelectContent>
                                                        </Select>
                                                        <Button
                                                            asChild
                                                            variant="outline"
                                                            size="sm"
                                                        >
                                                            <Link
                                                                href={createCourseOfferingUrl(
                                                                    course,
                                                                    form,
                                                                    academicSessionId,
                                                                )}
                                                            >
                                                                Create Offering
                                                            </Link>
                                                        </Button>
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">
                                                        Ready
                                                    </span>
                                                )}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </ScrollArea>
                        <div className="px-4 pb-4">
                            <ErrorList errors={courseErrors} />
                        </div>
                    </CardContent>
                    <CardFooter className="justify-between">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setStep('upload')}
                        >
                            <RiArrowLeftLine />
                            Back
                        </Button>
                        <Button
                            type="button"
                            disabled={!coursesComplete}
                            onClick={() => setStep('review')}
                        >
                            Review Results
                            <RiArrowRightLine />
                        </Button>
                    </CardFooter>
                </Card>
            ) : null}

            {step === 'review' && previewSheet ? (
                <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                    <CardHeader className="flex flex-col gap-3 border-b border-border lg:flex-row lg:items-start lg:justify-between">
                        <div className="min-w-0">
                            <CardTitle>Review Results</CardTitle>
                            <div className="mt-1 text-sm text-muted-foreground">
                                {selectedSet ? (
                                    <StudentSetChoiceLabel
                                        entrySession={selectedSet.entrySession}
                                        currentLevel={selectedSet.currentLevel}
                                        minimumYearsToDegree={
                                            form.department
                                                ?.minimumYearsToDegree
                                        }
                                    />
                                ) : (
                                    'Selected set'
                                )}{' '}
                                · {selectedYearSemester?.label ?? 'Semester'}
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Badge variant="outline">
                                {rows.length} students
                            </Badge>
                            <Badge variant="outline">
                                {courses.length} courses
                            </Badge>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        {errors.length > 0 ? (
                            <div className="p-4">
                                <ErrorList errors={errors} />
                            </div>
                        ) : null}
                        <ResultMatrixTable sheet={previewSheet} />
                    </CardContent>
                    <CardFooter className="flex-col gap-2 sm:flex-row sm:justify-between">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => setStep('courses')}
                        >
                            <RiArrowLeftLine />
                            Back
                        </Button>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={!canSubmit}
                                onClick={() => submit('draft')}
                            >
                                <RiSave3Line />
                                Save Draft
                            </Button>
                            <Button
                                type="button"
                                disabled={!canSubmit}
                                onClick={() => submit('submit')}
                            >
                                <RiSendPlaneLine />
                                Submit to HOD
                            </Button>
                        </div>
                    </CardFooter>
                </Card>
            ) : null}
        </div>
    );
}

function WizardTrail({ currentStep }: { currentStep: WizardStep }) {
    return (
        <div className="flex flex-wrap items-center gap-2 text-sm">
            {WIZARD_STEPS.map((step, index) => (
                <div key={step.id} className="flex items-center gap-2">
                    <span
                        className={
                            step.id === currentStep
                                ? 'font-semibold text-foreground'
                                : 'text-muted-foreground'
                        }
                    >
                        {step.label}
                    </span>
                    {index < WIZARD_STEPS.length - 1 ? (
                        <span className="text-muted-foreground/70">&gt;</span>
                    ) : null}
                </div>
            ))}
        </div>
    );
}

function ErrorList({ errors }: { errors: string[] }) {
    if (errors.length === 0) {
        return null;
    }

    return (
        <FieldError>
            {errors.slice(0, 6).map((error) => (
                <div key={error}>{error}</div>
            ))}
            {errors.length > 6 ? (
                <div>Fix these first {errors.length} issues and try again.</div>
            ) : null}
        </FieldError>
    );
}

function academicSessionForSetLevel(
    academicSessions: AcademicSessionOption[],
    entryAcademicSessionId: string,
    levelId: number | null | undefined,
): AcademicSessionOption | null {
    if (
        entryAcademicSessionId === '' ||
        levelId === null ||
        levelId === undefined
    ) {
        return null;
    }

    const sessions = [...academicSessions].sort((first, second) =>
        first.startsOn.localeCompare(second.startsOn),
    );
    const entryIndex = sessions.findIndex(
        (session) => session.id.toString() === entryAcademicSessionId,
    );

    if (entryIndex === -1) {
        return null;
    }

    return sessions[entryIndex + Math.max(levelId - 1, 0)] ?? null;
}

function previewResultSheet(
    courses: ParsedCourse[],
    rows: ParsedRow[],
    form: DashboardStaffResultImportForm,
    academicSessionId: string,
    entryAcademicSessionId: string,
    academicYearSemesterId: string,
): DashboardResultSheet | null {
    const academicSession = form.academicSessions.find(
        (session) => session.id.toString() === academicSessionId,
    );
    const entrySet = form.assignedSets.find(
        (set) => set.entrySession.id.toString() === entryAcademicSessionId,
    );
    const yearSemester = form.yearSemesters.find(
        (semester) => semester.id.toString() === academicYearSemesterId,
    );

    if (
        !academicSession ||
        !entrySet ||
        !yearSemester ||
        courses.length === 0
    ) {
        return null;
    }

    return {
        id: 0,
        status: 'draft',
        statusLabel: 'Draft',
        correctionNote: null,
        academicSession,
        entrySession: entrySet.entrySession,
        level: yearSemester.level,
        semester: yearSemester.semester,
        submittedBy: null,
        submittedAt: null,
        approvedAt: null,
        studentCount: rows.length,
        courseCount: courses.length,
        averageGpa: '0.00',
        courses: courses.map((course, index) => ({
            id: index + 1,
            code: course.code,
            title: course.title || 'Course title needed',
            creditUnits: course.creditUnits,
            isCarryover: course.isCarryover,
        })),
        rows: rows.map((row, index) => {
            const gradeRows = courses.map((course, courseIndex) => {
                const grade = row.grades[course.code] || null;
                const gradePoint = grade === null ? null : gradePointFor(grade);

                return {
                    courseId: courseIndex + 1,
                    grade,
                    gradePoint,
                    qualityPoints:
                        gradePoint === null
                            ? null
                            : gradePoint * course.creditUnits,
                };
            });
            const totalUnits = gradeRows.reduce((total, grade, courseIndex) => {
                if (grade.grade === null) {
                    return total;
                }

                return total + courses[courseIndex].creditUnits;
            }, 0);
            const totalPoints = gradeRows.reduce(
                (total, grade) => total + (grade.qualityPoints ?? 0),
                0,
            );

            return {
                id: index + 1,
                serialNumber: index + 1,
                student: {
                    name: row.student_name || 'Student Name',
                    avatar: null,
                    registrationNumber: row.registration_number,
                },
                grades: gradeRows,
                totalUnits,
                gpa:
                    totalUnits === 0
                        ? '0.00'
                        : (totalPoints / totalUnits).toFixed(2),
            };
        }),
    };
}

function resolveParsedCourse(
    course: Pick<ParsedCourse, 'code' | 'title' | 'creditUnits'>,
    form: DashboardStaffResultImportForm,
    academicSessionId: string,
    academicYearSemesterId: string,
): ParsedCourse {
    const code = normalizeCourseCode(course.code);
    const offering = matchingCourseOffering(
        form.courseOfferings,
        code,
        academicSessionId,
        academicYearSemesterId,
    );
    const knownCourse = form.knownCourses.find(
        (known) => normalizeCourseCode(known.code) === code,
    );
    const createInAcademicYearSemesterId =
        offering?.academicYearSemesterId.toString() ??
        inferAcademicYearSemesterId(code, form, academicYearSemesterId);

    return {
        code,
        title: offering?.title ?? knownCourse?.title ?? course.title,
        creditUnits:
            offering?.creditUnits ??
            knownCourse?.creditUnits ??
            course.creditUnits,
        courseOfferingId: offering?.id ?? null,
        offeringLabel: offering?.label ?? null,
        createInAcademicYearSemesterId,
        isCarryover:
            offering !== undefined &&
            offering.academicYearSemesterId !== Number(academicYearSemesterId),
    };
}

function matchingCourseOffering(
    offerings: CourseOfferingOption[],
    code: string,
    academicSessionId: string,
    academicYearSemesterId: string,
): CourseOfferingOption | undefined {
    const sessionId = Number(academicSessionId);
    const yearSemesterId = Number(academicYearSemesterId);
    const matches = offerings.filter(
        (offering) =>
            offering.academicSessionId === sessionId &&
            normalizeCourseCode(offering.code) === code,
    );

    return (
        matches.find(
            (offering) => offering.academicYearSemesterId === yearSemesterId,
        ) ?? matches[0]
    );
}

function inferAcademicYearSemesterId(
    code: string,
    form: DashboardStaffResultImportForm,
    fallbackAcademicYearSemesterId: string,
): string {
    const courseNumber = code.match(/\d{3}/)?.[0];
    const levelDigit = courseNumber?.[0];
    const inferredLevelSlug =
        levelDigit === undefined ? null : `${levelDigit}00-level`;
    const inferredSemesterSlug =
        courseNumber === undefined
            ? null
            : Number(courseNumber) % 2 === 0
              ? 'rain'
              : 'harmattan';
    const inferred = form.yearSemesters.find(
        (yearSemester) =>
            yearSemester.level.slug === inferredLevelSlug &&
            yearSemester.semester.slug === inferredSemesterSlug,
    );

    return (
        inferred?.id.toString() ??
        fallbackAcademicYearSemesterId ??
        form.yearSemesters[0]?.id.toString() ??
        ''
    );
}

function selectedAcademicSessionName(
    form: DashboardStaffResultImportForm,
    academicSessionId: string,
): string {
    const session = form.academicSessions.find(
        (option) => option.id.toString() === academicSessionId,
    );

    return session ? `${session.name} Session` : 'the derived academic session';
}

function createCourseOfferingUrl(
    course: ParsedCourse,
    form: DashboardStaffResultImportForm,
    academicSessionId: string,
): string {
    const yearSemester =
        form.yearSemesters.find(
            (option) =>
                option.id.toString() === course.createInAcademicYearSemesterId,
        ) ?? form.yearSemesters[0];

    if (!yearSemester || academicSessionId === '') {
        return '#';
    }

    return createCourseOffering.url(
        {
            level: yearSemester.level.slug,
            semester: yearSemester.semester.slug,
        },
        {
            query: {
                academic_session: Number(academicSessionId),
                code: course.code,
                title: course.title,
                credit_units: course.creditUnits,
                return_to: 'results-import',
            },
        },
    );
}

function normalizeCourseCode(value: string): string {
    return value.trim().replace(/\s+/g, ' ').toUpperCase();
}

function normalizeGrade(value: string): string {
    return value.trim().toUpperCase();
}

function isRegistrationHeader(header: string): boolean {
    return /reg|registration|matric/i.test(header);
}

function isNameHeader(header: string): boolean {
    return /^name$|full.?name|student.?name/i.test(header);
}

function isCourseHeader(
    header: string,
    knownCourses: Map<string, { code: string }>,
): boolean {
    const normalized = normalizeCourseCode(header);

    return (
        knownCourses.has(normalized) ||
        /^[A-Z]{2,4}\s?\d{3}[A-Z]?$/.test(normalized)
    );
}

function gradePointFor(grade: string): number {
    return (
        {
            A: 5,
            B: 4,
            C: 3,
            D: 2,
            E: 1,
            F: 0,
        }[grade] ?? 0
    );
}
