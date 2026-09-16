<?php

namespace App\Services;

use App\Actions\RecordResultSheetRevisionAction;
use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\AcademicYearSemester;
use App\Models\AdvisorProfile;
use App\Models\CourseOffering;
use App\Models\ResultSheet;
use App\Models\ResultSheetCourse;
use App\Models\StudentProfile;
use App\Models\StudentSet;
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
     * @param array{
     *   student_set_id: int,
     *   academic_year_semester_id: int,
     *   action: string,
     *   source_file?: UploadedFile|null,
     *   column_mappings?: array<string, mixed>,
     *   courses: array<int, array{course_offering_id: int, code: string, title: string, credit_units: int}>,
     *   rows: array<int, array{row_number?: int, registration_number: string, grades: array<string, string|null>}>
     * } $data
     */
    public function import(AdvisorProfile $advisorProfile, array $data): ResultSheet
    {
        $studentSet = StudentSet::query()
            ->whereBelongsTo($advisorProfile)
            ->findOrFail($data['student_set_id']);
        $academicSession = $this->academicSessionForImport($studentSet, $data);
        $slot = AcademicYearSemester::query()->find($data['academic_year_semester_id']);

        if (! $slot instanceof AcademicYearSemester) {
            throw ValidationException::withMessages(['context' => 'Choose a valid level and semester.']);
        }

        $courses = $this->normalizeCourses($data['courses']);
        $rows = $this->normalizeRows($data['rows']);
        $students = $this->studentsForRows($studentSet, $rows);
        $sourceFile = $this->storeSourceFile($advisorProfile, $data['source_file'] ?? null);

        try {
            return DB::transaction(function () use ($advisorProfile, $studentSet, $academicSession, $slot, $data, $courses, $rows, $students, $sourceFile): ResultSheet {
                $publish = in_array($data['action'], ['publish', 'submit'], true);
                $sheet = ResultSheet::query()->updateOrCreate(
                    [
                        'student_set_id' => $studentSet->id,
                        'academic_session_id' => $academicSession->id,
                        'academic_year_semester_id' => $slot->id,
                    ],
                    [
                        'advisor_profile_id' => $advisorProfile->id,
                        'created_by_user_id' => $advisorProfile->user_id,
                        'status' => $publish ? ResultSheet::StatusPublished : ResultSheet::StatusDraft,
                        'published_at' => $publish ? now() : null,
                    ],
                );

                $sheet->studentResults()->delete();
                $sheet->courses()->delete();
                $courseRows = $this->createSheetCourses($sheet, $studentSet, $courses);

                foreach ($rows as $row) {
                    /** @var StudentProfile $student */
                    $student = $students->get($row['registration_number']);
                    $gradedCourses = collect($courseRows)
                        ->map(function (ResultSheetCourse $sheetCourse, string $courseCode) use ($row): ?array {
                            $grade = $row['grades'][$courseCode] ?? null;

                            return is_string($grade) && $grade !== ''
                                ? ['credit_units' => (int) $sheetCourse->credit_units, 'grade' => $grade]
                                : null;
                        })
                        ->filter()
                        ->values()
                        ->all();

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
                    $advisorProfile->user,
                    $publish ? 'published' : 'draft_saved',
                    $sourceFile ?? [],
                    is_array($data['column_mappings'] ?? null) ? $data['column_mappings'] : [],
                );

                return $sheet->fresh($this->relations());
            });
        } catch (Throwable $throwable) {
            if ($sourceFile !== null) {
                Storage::disk($sourceFile['disk'])->delete($sourceFile['path']);
            }

            throw $throwable;
        }
    }

    /** @return array{disk: string, path: string, name: string, checksum: string}|null */
    private function storeSourceFile(AdvisorProfile $advisorProfile, mixed $sourceFile): ?array
    {
        if (! $sourceFile instanceof UploadedFile) {
            return null;
        }

        $disk = (string) config('filesystems.result_import_disk', 'local');
        $checksum = hash_file('sha256', $sourceFile->getRealPath());

        if (! is_string($checksum)) {
            throw ValidationException::withMessages(['source_file' => 'The spreadsheet could not be read. Choose the file again and retry.']);
        }

        $path = Storage::disk($disk)->putFileAs(
            "advisers/{$advisorProfile->id}/result-imports",
            $sourceFile,
            Str::uuid().'.'.($sourceFile->extension() ?: 'xlsx'),
        );

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages(['source_file' => 'The spreadsheet could not be stored. Try the upload again.']);
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'name' => $sourceFile->getClientOriginalName(),
            'checksum' => $checksum,
        ];
    }

    /** @param array<string, mixed> $data */
    private function academicSessionForImport(StudentSet $studentSet, array $data): AcademicSession
    {
        $slot = AcademicYearSemester::query()->with('academicYear')->find($data['academic_year_semester_id'] ?? null);
        $session = $slot?->academicYear instanceof AcademicYear
            ? $this->academicContext->sessionForSetYear($studentSet, $slot->academicYear)
            : null;

        if (! $session instanceof AcademicSession) {
            throw ValidationException::withMessages([
                'context' => 'Create the academic session for this set and level before importing results.',
            ]);
        }

        return $session;
    }

    /** @param array<int, array{course_offering_id: int, code: string, title: string, credit_units: int}> $courses
     * @return array<int, array{course_offering_id: int, code: string, title: string, credit_units: int}>
     */
    private function normalizeCourses(array $courses): array
    {
        $normalized = collect($courses)->map(fn (array $course): array => [
            'course_offering_id' => (int) $course['course_offering_id'],
            'code' => $this->normalizeCourseCode($course['code']),
            'title' => trim($course['title']),
            'credit_units' => (int) $course['credit_units'],
        ])->values();
        $duplicate = $normalized->groupBy('code')->first(fn (Collection $group): bool => $group->count() > 1);

        if ($duplicate instanceof Collection) {
            throw ValidationException::withMessages([
                'courses' => "The course {$duplicate->first()['code']} appears more than once. Keep one column for each course.",
            ]);
        }

        return $normalized->all();
    }

    /** @param array<int, array{row_number?: int, registration_number: string, grades: array<string, string|null>}> $rows
     * @return array<int, array{row_number: int, registration_number: string, grades: array<string, string>}>
     */
    private function normalizeRows(array $rows): array
    {
        $normalized = collect($rows)->map(fn (array $row, int $index): array => [
            'row_number' => (int) ($row['row_number'] ?? ($index + 1)),
            'registration_number' => trim($row['registration_number']),
            'grades' => collect($row['grades'] ?? [])->mapWithKeys(fn ($grade, string $code): array => [
                $this->normalizeCourseCode($code) => is_string($grade) ? strtoupper(trim($grade)) : '',
            ])->all(),
        ])->values();
        $duplicate = $normalized->groupBy('registration_number')->first(fn (Collection $group): bool => $group->count() > 1);

        if ($duplicate instanceof Collection) {
            throw ValidationException::withMessages([
                'rows' => "Registration number {$duplicate->first()['registration_number']} appears on more than one row.",
            ]);
        }

        return $normalized->all();
    }

    /** @param array<int, array{row_number: int, registration_number: string, grades: array<string, string>}> $rows
     * @return Collection<string, StudentProfile>
     */
    private function studentsForRows(StudentSet $studentSet, array $rows): Collection
    {
        $registrationNumbers = collect($rows)->pluck('registration_number')->filter()->values();
        $students = $studentSet->students()
            ->with('user')
            ->whereIn('registration_number', $registrationNumbers)
            ->get()
            ->keyBy('registration_number');
        $missing = $registrationNumbers->reject(fn (string $registrationNumber): bool => $students->has($registrationNumber))->values();

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'rows' => 'These registration numbers are not in the selected set: '.$missing->take(5)->implode(', ').'.',
            ]);
        }

        return $students;
    }

    /** @param array<int, array{course_offering_id: int, code: string, title: string, credit_units: int}> $courses
     * @return Collection<string, ResultSheetCourse>
     */
    private function createSheetCourses(ResultSheet $sheet, StudentSet $studentSet, array $courses): Collection
    {
        $offerings = $this->courseOfferingsForImport($studentSet, $sheet, $courses);

        return collect($courses)->mapWithKeys(function (array $course, int $index) use ($sheet, $offerings): array {
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

    /** @param array<int, array{course_offering_id: int, code: string, title: string, credit_units: int}> $courses
     * @return Collection<string, CourseOffering>
     */
    private function courseOfferingsForImport(StudentSet $studentSet, ResultSheet $sheet, array $courses): Collection
    {
        $ids = collect($courses)->pluck('course_offering_id')->unique()->values();
        $offerings = CourseOffering::query()
            ->with(['course', 'academicYearSemester'])
            ->whereBelongsTo($studentSet)
            ->where('academic_session_id', $sheet->academic_session_id)
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        if ($ids->reject(fn (int $id): bool => $offerings->has($id))->isNotEmpty()) {
            throw ValidationException::withMessages([
                'courses' => 'Every course column must match an offering for the selected set and academic session.',
            ]);
        }

        return collect($courses)->mapWithKeys(function (array $course) use ($offerings): array {
            /** @var CourseOffering $offering */
            $offering = $offerings->get($course['course_offering_id']);
            $offeringCode = $this->normalizeCourseCode((string) $offering->course?->code);

            if ($offeringCode !== $course['code']) {
                throw ValidationException::withMessages([
                    'courses' => "{$course['code']} is matched to {$offeringCode}. Match it to the correct course before saving.",
                ]);
            }

            return [$course['code'] => $offering];
        });
    }

    /** @return array<int, string> */
    private function relations(): array
    {
        return [
            'academicSession',
            'studentSet.entryAcademicSession',
            'academicYearSemester.academicYear',
            'academicYearSemester.semester',
            'courses.courseOffering.academicYearSemester',
            'studentResults.courseResults',
            'studentResults.studentProfile.user',
            'revisions',
        ];
    }

    private function normalizeCourseCode(string $courseCode): string
    {
        return Str::of($courseCode)->squish()->upper()->toString();
    }
}
