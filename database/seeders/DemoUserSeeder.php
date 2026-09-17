<?php

namespace Database\Seeders;

use App\AccountStatus;
use App\AccountType;
use App\CourseCategory;
use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\AcademicYearSemester;
use App\Models\AdvisorProfile;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Due;
use App\Models\StudentDueAssignment;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoUserSeeder extends Seeder
{
    private const string PASSWORD = 'password';

    private const array DEMO_COURSES = [
        ['code' => 'CPE 101', 'title' => 'Introduction to Computer Engineering', 'year' => 1, 'semester' => 'harmattan', 'units' => 3],
        ['code' => 'CPE 103', 'title' => 'Computer Programming I', 'year' => 1, 'semester' => 'harmattan', 'units' => 3],
        ['code' => 'MTH 101', 'title' => 'Engineering Mathematics I', 'year' => 1, 'semester' => 'harmattan', 'units' => 3],
        ['code' => 'CPE 102', 'title' => 'Computer Programming II', 'year' => 1, 'semester' => 'rain', 'units' => 3],
        ['code' => 'CPE 104', 'title' => 'Logic Circuits', 'year' => 1, 'semester' => 'rain', 'units' => 3],
        ['code' => 'GSS 102', 'title' => 'Communication in English', 'year' => 1, 'semester' => 'rain', 'units' => 2, 'elective' => true],
        ['code' => 'CPE 201', 'title' => 'Digital Systems', 'year' => 2, 'semester' => 'harmattan', 'units' => 3],
        ['code' => 'CPE 203', 'title' => 'Data Structures', 'year' => 2, 'semester' => 'harmattan', 'units' => 3],
        ['code' => 'MTH 201', 'title' => 'Engineering Mathematics II', 'year' => 2, 'semester' => 'harmattan', 'units' => 3],
        ['code' => 'CPE 202', 'title' => 'Computer Architecture', 'year' => 2, 'semester' => 'rain', 'units' => 3],
        ['code' => 'CPE 204', 'title' => 'Signals and Systems', 'year' => 2, 'semester' => 'rain', 'units' => 3],
        ['code' => 'GSS 202', 'title' => 'Entrepreneurship Studies', 'year' => 2, 'semester' => 'rain', 'units' => 2, 'elective' => true],
        ['code' => 'CPE 301', 'title' => 'Microprocessor Systems', 'year' => 3, 'semester' => 'harmattan', 'units' => 3],
        ['code' => 'CPE 303', 'title' => 'Computer Networks', 'year' => 3, 'semester' => 'harmattan', 'units' => 3],
        ['code' => 'CPE 305', 'title' => 'Embedded Systems', 'year' => 3, 'semester' => 'harmattan', 'units' => 3],
        ['code' => 'CPE 302', 'title' => 'Operating Systems', 'year' => 3, 'semester' => 'rain', 'units' => 3],
        ['code' => 'CPE 304', 'title' => 'Software Engineering', 'year' => 3, 'semester' => 'rain', 'units' => 3],
        ['code' => 'CPE 306', 'title' => 'Industrial Electronics', 'year' => 3, 'semester' => 'rain', 'units' => 2, 'elective' => true],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adviser = User::query()->updateOrCreate(
            ['email' => 'adviser@demo.edu'],
            [
                'account_type' => AccountType::Adviser,
                'name' => 'Okoro Chinedu',
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'activated_at' => now(),
                'status' => AccountStatus::Active,
            ],
        );
        $advisorProfile = AdvisorProfile::query()->updateOrCreate(
            ['user_id' => $adviser->id],
            [
                'institution_name' => 'Federal University of Technology',
                'programme_name' => 'Computer Engineering',
                'programme_abbreviation' => 'CPE',
                'minimum_years_to_degree' => 4,
                'maximum_course_units' => 24,
                'timezone' => 'Africa/Lagos',
            ],
        );

        $entrySession = $this->session($advisorProfile, '2024/2025', '2024-09-01', '2025-08-31');
        $secondSession = $this->session($advisorProfile, '2025/2026', '2025-09-01', '2026-08-31');
        $currentSession = $this->session($advisorProfile, '2026/2027', '2026-09-01', '2027-08-31');

        $set = StudentSet::query()->updateOrCreate(
            ['advisor_profile_id' => $advisorProfile->id, 'entry_academic_session_id' => $entrySession->id],
            [
                'name' => '2024/2025 Set',
                'minimum_years_to_degree' => 4,
                'maximum_course_units' => 24,
                'status' => 'active',
            ],
        );

        $activeStudent = $this->student($set, 'student1@demo.edu', '2024000001', 'Adaeze Nwosu');
        $this->student($set, null, '2024000002', 'Ikenna Obi', pendingAccessCode: 'STUDENT2');
        $this->student($set, null, '2024000003', 'Chiamaka Eze', pendingAccessCode: 'STUDENT3');

        $this->seedCourses($advisorProfile, $set, [
            1 => $entrySession,
            2 => $secondSession,
            3 => $currentSession,
        ]);
        $this->seedDue($advisorProfile, $set, $currentSession, $activeStudent);
    }

    private function session(
        AdvisorProfile $advisor,
        string $name,
        string $startsOn,
        string $endsOn,
    ): AcademicSession {
        return AcademicSession::query()->updateOrCreate(
            ['advisor_profile_id' => $advisor->id, 'name' => $name],
            [
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
            ],
        );
    }

    private function student(
        StudentSet $set,
        ?string $email,
        string $registrationNumber,
        string $name,
        ?string $pendingAccessCode = null,
    ): StudentProfile {
        $student = StudentProfile::query()
            ->where('advisor_profile_id', $set->advisor_profile_id)
            ->where('registration_number', $registrationNumber)
            ->first();
        $user = $student?->user ?? new User;
        $user->forceFill([
            'account_type' => AccountType::Student,
            'name' => $name,
            'email' => $pendingAccessCode === null ? $email : null,
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => $pendingAccessCode === null ? now() : null,
            'access_code' => $pendingAccessCode,
            'access_code_used_at' => null,
            'activated_at' => $pendingAccessCode === null ? now() : null,
            'status' => $pendingAccessCode === null ? AccountStatus::Active : AccountStatus::Pending,
        ])->save();
        $academicYear = AcademicYear::query()->where('year_number', 3)->firstOrFail();

        return StudentProfile::query()->updateOrCreate(
            [
                'advisor_profile_id' => $set->advisor_profile_id,
                'registration_number' => $registrationNumber,
            ],
            [
                'user_id' => $user->id,
                'advisor_profile_id' => $set->advisor_profile_id,
                'student_set_id' => $set->id,
                'current_academic_year_id' => $academicYear->id,
                'registration_number' => $registrationNumber,
                'status' => 'active',
            ],
        );
    }

    private function seedCourses(
        AdvisorProfile $advisor,
        StudentSet $set,
        array $sessionsByYear,
    ): void {
        $yearSemesters = AcademicYearSemester::query()
            ->with(['academicYear', 'semester'])
            ->get()
            ->keyBy(fn (AcademicYearSemester $slot): string => "{$slot->academicYear->year_number}/{$slot->semester->slug}");

        foreach (self::DEMO_COURSES as $courseData) {
            $slot = $yearSemesters->get("{$courseData['year']}/{$courseData['semester']}");
            if (! $slot instanceof AcademicYearSemester) {
                continue;
            }

            $academicSession = $sessionsByYear[$courseData['year']] ?? null;

            if (! $academicSession instanceof AcademicSession) {
                continue;
            }

            $course = Course::query()->updateOrCreate(
                ['advisor_profile_id' => $advisor->id, 'code' => $courseData['code']],
                [
                    'title' => $courseData['title'],
                    'slug' => Str::slug("{$courseData['code']} {$courseData['title']}"),
                    'credit_units' => $courseData['units'],
                ],
            );

            CourseOffering::query()->updateOrCreate(
                [
                    'student_set_id' => $set->id,
                    'course_id' => $course->id,
                    'academic_session_id' => $academicSession->id,
                    'academic_year_semester_id' => $slot->id,
                ],
                [
                    'course_title' => $course->title,
                    'credit_units' => $course->credit_units,
                    'category' => ($courseData['elective'] ?? false)
                        ? CourseCategory::Elective
                        : CourseCategory::Normal,
                    'archived_at' => null,
                ],
            );
        }
    }

    private function seedDue(
        AdvisorProfile $advisor,
        StudentSet $set,
        AcademicSession $currentSession,
        StudentProfile $activeStudent,
    ): void {
        $due = Due::query()->updateOrCreate(
            ['advisor_profile_id' => $advisor->id, 'student_set_id' => $set->id, 'academic_session_id' => $currentSession->id],
            [
                'title' => 'Student Association Levy',
                'description' => 'Association dues for the 2026/2027 session.',
                'amount_kobo' => 50000,
                'currency' => 'NGN',
                'due_on' => '2026-10-31',
                'status' => Due::StatusPublished,
                'published_at' => now(),
            ],
        );

        StudentDueAssignment::query()->updateOrCreate(
            ['due_id' => $due->id, 'student_profile_id' => $activeStudent->id],
            [
                'amount_kobo' => $due->amount_kobo,
                'status' => StudentDueAssignment::StatusPending,
                'paid_at' => null,
            ],
        );
    }
}
