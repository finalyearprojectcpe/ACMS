<?php

namespace App\Actions;

use App\Models\ResultSheet;
use App\Models\ResultSheetCourse;
use App\Models\ResultSheetRevision;
use App\Models\StudentCourseResult;
use App\Models\StudentSemesterResult;
use App\Models\User;

class RecordResultSheetRevisionAction
{
    /**
     * @param  array{disk?: string|null, path?: string|null, name?: string|null, checksum?: string|null}  $sourceFile
     * @param  array<string, mixed>  $columnMappings
     */
    public function execute(
        ResultSheet $resultSheet,
        ?User $actor,
        string $action,
        array $sourceFile = [],
        array $columnMappings = [],
        ?string $note = null,
    ): ResultSheetRevision {
        $resultSheet->load([
            'courses',
            'studentResults.studentProfile.user',
            'studentResults.courseResults.resultSheetCourse',
        ]);
        $revisionNumber = max(
            (int) $resultSheet->current_revision_number,
            (int) $resultSheet->revisions()->max('revision_number'),
        ) + 1;

        $revision = $resultSheet->revisions()->create([
            'actor_user_id' => $actor?->id,
            'revision_number' => $revisionNumber,
            'action' => $action,
            'source_file_disk' => $sourceFile['disk'] ?? null,
            'source_file_path' => $sourceFile['path'] ?? null,
            'source_file_name' => $sourceFile['name'] ?? null,
            'source_file_checksum' => $sourceFile['checksum'] ?? null,
            'column_mappings' => $columnMappings,
            'snapshot' => $this->snapshot($resultSheet),
            'note' => $note,
        ]);

        $resultSheet->forceFill(['current_revision_number' => $revisionNumber])->save();

        return $revision;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ResultSheet $resultSheet): array
    {
        return [
            'status' => $resultSheet->status,
            'student_set_id' => $resultSheet->student_set_id,
            'academic_session_id' => $resultSheet->academic_session_id,
            'academic_year_semester_id' => $resultSheet->academic_year_semester_id,
            'courses' => $resultSheet->courses
                ->map(fn (ResultSheetCourse $course): array => [
                    'id' => (int) $course->id,
                    'course_offering_id' => (int) $course->course_offering_id,
                    'code' => $course->course_code,
                    'title' => $course->course_title,
                    'credit_units' => (int) $course->credit_units,
                    'sort_order' => (int) $course->sort_order,
                ])
                ->values()
                ->all(),
            'students' => $resultSheet->studentResults
                ->sortBy(fn (StudentSemesterResult $result): string => $result->studentProfile?->registration_number ?? '')
                ->map(fn (StudentSemesterResult $result): array => [
                    'student_profile_id' => (int) $result->student_profile_id,
                    'registration_number' => $result->studentProfile?->registration_number,
                    'name' => $result->studentProfile?->user?->name,
                    'total_units' => (int) $result->total_units,
                    'total_grade_points' => (int) $result->total_grade_points,
                    'gpa' => (string) $result->gpa,
                    'grades' => $result->courseResults
                        ->mapWithKeys(fn (StudentCourseResult $courseResult): array => [
                            $courseResult->resultSheetCourse?->course_code ?? (string) $courseResult->result_sheet_course_id => $courseResult->grade,
                        ])
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }
}
