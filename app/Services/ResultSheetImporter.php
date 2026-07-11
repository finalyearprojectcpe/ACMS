<?php

namespace App\Services;

use App\Actions\RecordResultSheetRevisionAction;
use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\AcademicYearSemester;
use App\Models\CourseOffering;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\ResultSheet;
use App\Models\ResultSheetCourse;
use App\Models\StaffProfile;
use App\Models\StudentProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ResultSheetImporter
{
    public function __construct(
        private readonly GpaCalculator $gpaCalculator,
        private readonly AcademicContextService $academicContext,
        private readonly RecordResultSheetRevisionAction $recordRevision,
    ) {}

    /**
     * @param  array{
     *     entry_academic_session_id: int,
     *     academic_year_semester_id: int,
     *     action: string,
     *     source_file?: UploadedFile|null,
     *     column_mappings?: array<string, mixed>,
     *     courses: array<int, array{course_offering_id: int, code: string, title: string, credit_units: int}>,
     *     rows: array<int, array{row_number?: int, registration_number: string, grades: array<string, string|null>}>
     * }  $data
     */
    public function import(Department $department, StaffProfile $staffProfile, array $data): ResultSheet
    {
        $data['academic_session_id'] = $this->academicSessionForImport($department, $data)->id;
        $this->ensureCanImportForDepartment($department, $staffProfile, $data);
        $courses = $this->normalizeCourses($data['courses']);
        $rows = $this->normalizeRows($data['rows']);
        $students = $this->studentsForRows($department, $rows);
        $sourceFile = $this->storeSourceFile($department, $data['source_file'] ?? null);

        try {
            return DB::transaction(function () use ($department, $staffProfile, $data, $courses, $rows, $students, $sourceFile): ResultSheet {
                $sheet = ResultSheet::query()
                    ->where('department_id', $department->id)
                    ->where('entry_academic_session_id', $data['entry_academic_session_id'])
                    ->where('academic_session_id', $data['academic_session_id'])
                    ->where('academic_year_semester_id', $data['academic_year_semester_id'])
                    ->lockForUpdate()
                    ->first();

                if ($sheet instanceof ResultSheet && ! $sheet->canBeEditedByAdvisor()) {
                    throw ValidationException::withMessages([
                        'sheet' => 'This result sheet has already been submitted for review. The HOD has to return it before you can change it.',
                    ]);
                }

                $status = $data['action'] === 'submit'
                    ? ResultSheet::StatusSubmitted
                    : ResultSheet::StatusDraft;

                $sheet = ResultSheet::query()->updateOrCreate(
                    [
                        'department_id' => $department->id,
                        'entry_academic_session_id' => $data['entry_academic_session_id'],
                        'academic_session_id' => $data['academic_session_id'],
                        'academic_year_semester_id' => $data['academic_year_semester_id'],
                    ],
                    [
                        'school_id' => $department->faculty?->school_id,
                        'created_by_user_id' => $sheet?->created_by_user_id ?? $staffProfile->user_id,
                        'submitted_by_user_id' => $status === ResultSheet::StatusSubmitted ? $staffProfile->user_id : null,
                        'reviewed_by_user_id' => null,
                        'status' => $status,
                        'correction_note' => null,
                        'submitted_at' => $status === ResultSheet::StatusSubmitted ? now() : null,
                        'approved_at' => null,
                        'returned_at' => null,
                    ],
                );

                $sheet->courses()->delete();
                $sheet->studentResults()->delete();

                $courseRows = $this->createSheetCourses($sheet, $department, $courses);

                foreach ($rows as $row) {
                    /** @var StudentProfile $student */
                    $student = $students->get($row['registration_number']);
                    $gradedCourses = [];

                    foreach ($courseRows as $courseCode => $sheetCourse) {
                        $grade = $row['grades'][$courseCode] ?? null;

                        if (! is_string($grade) || $grade === '') {
                            continue;
                        }

                        $gradedCourses[] = [
                            'credit_units' => (int) $sheetCourse->credit_units,
                            'grade' => $grade,
                        ];
                    }

                    if ($gradedCourses === []) {
                        throw ValidationException::withMessages([
                            'rows' => "Row {$row['row_number']} has no grades. Add at least one grade or remove the row.",
                        ]);
                    }

                    $summary = $this->gpaCalculator->semesterSummary($gradedCourses);
                    $studentResult = $sheet->studentResults()->create([
                        'student_profile_id' => $student->id,
                        'total_units' => $summary['total_units'],
                        'total_grade_points' => $summary['total_grade_points'],
                        'gpa' => $summary['gpa'],
                    ]);

                    foreach ($courseRows as $courseCode => $sheetCourse) {
                        $grade = $row['grades'][$courseCode] ?? null;

                        if (! is_string($grade) || $grade === '') {
                            continue;
                        }

                        $gradePoint = $this->gpaCalculator->gradePoint($grade);

                        $studentResult->courseResults()->create([
                            'result_sheet_course_id' => $sheetCourse->id,
                            'grade' => $grade,
                            'grade_point' => $gradePoint,
                            'quality_points' => (int) $sheetCourse->credit_units * $gradePoint,
                        ]);
                    }
                }

                $this->recordRevision->execute(
                    $sheet,
                    $staffProfile->user,
                    $status === ResultSheet::StatusSubmitted ? 'submitted' : 'draft_saved',
                    $sourceFile ?? [],
                    is_array($data['column_mappings'] ?? null) ? $data['column_mappings'] : [],
                );

                return $sheet->fresh([
                    'academicSession',
                    'entryAcademicSession',
                    'academicYearSemester.academicYear',
                    'academicYearSemester.semester',
                    'courses.courseOffering.academicYearSemester',
                    'studentResults.courseResults',
                    'studentResults.studentProfile.user',
                    'revisions',
                ]);
            });
        } catch (Throwable $throwable) {
            if ($sourceFile !== null) {
                Storage::disk($sourceFile['disk'])->delete($sourceFile['path']);
            }

            throw $throwable;
        }
    }

    /**
     * @return array{disk: string, path: string, name: string, checksum: string}|null
     */
    private function storeSourceFile(Department $department, mixed $sourceFile): ?array
    {
        if (! $sourceFile instanceof UploadedFile) {
            return null;
        }

        $disk = config('filesystems.result_import_disk', 'local');

        if (! is_string($disk) || $disk === '') {
            $disk = 'local';
        }

        $checksum = hash_file('sha256', $sourceFile->getRealPath());

        if (! is_string($checksum)) {
            throw ValidationException::withMessages([
                'source_file' => 'The spreadsheet could not be read. Choose the file again and retry.',
            ]);
        }
        $extension = $sourceFile->extension() ?: 'xlsx';
        $directory = 'schools/'.$department->faculty?->school_id.'/result-imports';
        $path = Storage::disk($disk)->putFileAs(
            $directory,
            $sourceFile,
            Str::uuid().'.'.$extension,
        );

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'source_file' => 'The spreadsheet could not be stored. Try the upload again.',
            ]);
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'name' => $sourceFile->getClientOriginalName(),
            'checksum' => $checksum,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ensureCanImportForDepartment(Department $department, StaffProfile $staffProfile, array $data): void
    {
        if ((int) $staffProfile->department_id !== (int) $department->id) {
            throw ValidationException::withMessages([
                'department' => 'Only course advisers in this department can upload these results.',
            ]);
        }

        $isAssignedToSet = $staffProfile->studentSetAdvisorAssignments()
            ->where('department_id', $department->id)
            ->where('entry_academic_session_id', $data['entry_academic_session_id'])
            ->where('active_marker', 1)
            ->exists();

        if (! $isAssignedToSet) {
            throw ValidationException::withMessages([
                'entry_academic_session_id' => 'You can only upload results for a student set assigned to you.',
            ]);
        }

        $schoolId = (int) $department->faculty()->value('school_id');

        $academicSessionBelongsToSchool = AcademicSession::query()
            ->whereKey($data['academic_session_id'])
            ->where('school_id', $schoolId)
            ->exists();
        $entrySessionBelongsToSchool = AcademicSession::query()
            ->whereKey($data['entry_academic_session_id'])
            ->where('school_id', $schoolId)
            ->exists();
        $yearSemesterExists = AcademicYearSemester::query()
            ->whereKey($data['academic_year_semester_id'])
            ->exists();

        if (! $academicSessionBelongsToSchool || ! $entrySessionBelongsToSchool || ! $yearSemesterExists) {
            throw ValidationException::withMessages([
                'context' => 'Select a valid student set, level, and semester for this department.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function academicSessionForImport(Department $department, array $data): AcademicSession
    {
        $academicYearSemester = AcademicYearSemester::query()
            ->with('academicYear')
            ->find($data['academic_year_semester_id'] ?? null);
        $entryAcademicSession = AcademicSession::query()
            ->find($data['entry_academic_session_id'] ?? null);
        $academicYear = $academicYearSemester?->academicYear;
        $academicSession = $entryAcademicSession instanceof AcademicSession && $academicYear instanceof AcademicYear
            ? $this->academicContext->sessionForSetYear(
                $department,
                $entryAcademicSession,
                $academicYear,
            )
            : null;

        if (! $academicSession instanceof AcademicSession) {
            throw ValidationException::withMessages([
                'context' => 'The school has no academic session for this set and level yet. Ask the school admin to create the session before importing results.',
            ]);
        }

        return $academicSession;
    }

    /**
     * @param  array<int, array{course_offering_id: int, code: string, title: string, credit_units: int}>  $courses
     * @return array<int, array{course_offering_id: int, code: string, title: string, credit_units: int}>
     */
    private function normalizeCourses(array $courses): array
    {
        $normalized = collect($courses)
            ->map(fn (array $course): array => [
                'course_offering_id' => (int) $course['course_offering_id'],
                'code' => $this->normalizeCourseCode($course['code']),
                'title' => trim($course['title']),
                'credit_units' => (int) $course['credit_units'],
            ])
            ->values();
        $duplicate = $normalized
            ->groupBy('code')
            ->first(fn (Collection $group): bool => $group->count() > 1);

        if ($duplicate instanceof Collection) {
            throw ValidationException::withMessages([
                'courses' => "The course {$duplicate->first()['code']} appears more than once. Keep one column for each course.",
            ]);
        }

        return $normalized->all();
    }

    /**
     * @param  array<int, array{row_number?: int, registration_number: string, grades: array<string, string|null>}>  $rows
     * @return array<int, array{row_number: int, registration_number: string, grades: array<string, string>}>
     */
    private function normalizeRows(array $rows): array
    {
        $normalized = collect($rows)
            ->map(function (array $row, int $index): array {
                $grades = collect($row['grades'] ?? [])
                    ->mapWithKeys(function ($grade, string $courseCode): array {
                        $grade = is_string($grade) ? strtoupper(trim($grade)) : '';

                        return [$this->normalizeCourseCode($courseCode) => $grade];
                    })
                    ->all();

                return [
                    'row_number' => (int) ($row['row_number'] ?? ($index + 1)),
                    'registration_number' => trim($row['registration_number']),
                    'grades' => $grades,
                ];
            })
            ->values();
        $duplicate = $normalized
            ->groupBy('registration_number')
            ->first(fn (Collection $group): bool => $group->count() > 1);

        if ($duplicate instanceof Collection) {
            $registrationNumber = $duplicate->first()['registration_number'];
            $rowNumbers = $duplicate->pluck('row_number')->implode(', ');

            throw ValidationException::withMessages([
                'rows' => "Registration number {$registrationNumber} appears more than once on rows {$rowNumbers}. Keep one row per student.",
            ]);
        }

        return $normalized->all();
    }

    /**
     * @param  array<int, array{row_number: int, registration_number: string, grades: array<string, string>}>  $rows
     * @return Collection<string, StudentProfile>
     */
    private function studentsForRows(Department $department, array $rows): Collection
    {
        $registrationNumbers = collect($rows)
            ->pluck('registration_number')
            ->filter()
            ->values();
        $students = StudentProfile::query()
            ->with('user')
            ->where('department_id', $department->id)
            ->whereIn('registration_number', $registrationNumbers)
            ->get()
            ->keyBy('registration_number');
        $missing = $registrationNumbers
            ->reject(fn (string $registrationNumber): bool => $students->has($registrationNumber))
            ->values();

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'rows' => 'These registration numbers are not students in this department: '.$missing->take(5)->implode(', ').'.',
            ]);
        }

        return $students;
    }

    /**
     * @param  array<int, array{course_offering_id: int, code: string, title: string, credit_units: int}>  $courses
     * @return Collection<string, ResultSheetCourse>
     */
    private function createSheetCourses(ResultSheet $sheet, Department $department, array $courses): Collection
    {
        $offerings = $this->courseOfferingsForImport($department, $sheet, $courses);

        return collect($courses)
            ->mapWithKeys(function (array $course, int $index) use ($sheet, $offerings): array {
                /** @var CourseOffering $offering */
                $offering = $offerings->get($course['code']);
                $sheetCourse = $sheet->courses()->create([
                    'course_offering_id' => $offering->id,
                    'course_code' => $offering->course->code,
                    'course_title' => $offering->course_title,
                    'credit_units' => (int) $offering->credit_units,
                    'sort_order' => $index + 1,
                ]);

                return [$course['code'] => $sheetCourse];
            });
    }

    /**
     * @param  array<int, array{course_offering_id: int, code: string, title: string, credit_units: int}>  $courses
     * @return Collection<string, CourseOffering>
     */
    private function courseOfferingsForImport(Department $department, ResultSheet $sheet, array $courses): Collection
    {
        $faculty = $department->faculty;
        $courseOfferingIds = collect($courses)
            ->pluck('course_offering_id')
            ->unique()
            ->values();

        $offerings = CourseOffering::query()
            ->with(['course', 'academicYearSemester'])
            ->whereIn('id', $courseOfferingIds)
            ->where('academic_session_id', $sheet->academic_session_id)
            ->where(function ($query) use ($department, $faculty): void {
                $query->where(function ($query) use ($department): void {
                    $query
                        ->where('owner_type', Department::class)
                        ->where('owner_id', $department->id);
                });

                if ($faculty instanceof Faculty) {
                    $query->orWhere(function ($query) use ($faculty): void {
                        $query
                            ->where('owner_type', Faculty::class)
                            ->where('owner_id', $faculty->id);
                    });
                }
            })
            ->get()
            ->keyBy('id');

        $missingIds = $courseOfferingIds
            ->reject(fn (int $id): bool => $offerings->has($id))
            ->values();

        if ($missingIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'courses' => 'Every course column must match a course offering for this academic session before the results can be saved.',
            ]);
        }

        return collect($courses)
            ->mapWithKeys(function (array $course) use ($offerings): array {
                /** @var CourseOffering $offering */
                $offering = $offerings->get($course['course_offering_id']);
                $offeringCode = $this->normalizeCourseCode((string) $offering->course?->code);

                if ($offeringCode !== $course['code']) {
                    throw ValidationException::withMessages([
                        'courses' => "{$course['code']} is matched to {$offeringCode}. Match it to the correct course offering before saving.",
                    ]);
                }

                return [$course['code'] => $offering];
            });
    }

    private function normalizeCourseCode(string $courseCode): string
    {
        return Str::of($courseCode)
            ->squish()
            ->upper()
            ->toString();
    }
}
