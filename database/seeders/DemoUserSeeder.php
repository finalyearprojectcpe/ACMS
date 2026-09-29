<?php

namespace Database\Seeders;

use App\AccountStatus;
use App\AccountType;
use App\Honorific;
use App\Models\AcademicSession;
use App\Models\AcademicSessionSemester;
use App\Models\AcademicYear;
use App\Models\AdvisorProfile;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\Due;
use App\Models\Semester;
use App\Models\StudentDueAssignment;
use App\Models\StudentProfile;
use App\Models\StudentSet;
use App\Models\User;
use App\Services\DueAssignmentService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    private const string PASSWORD = 'password';

    private const array DEMO_COURSES = [
        ['code' => 'CPE 101', 'title' => 'Introduction to Computer Engineering', 'year' => 1, 'semester' => 'first', 'units' => 3],
        ['code' => 'CPE 103', 'title' => 'Computer Programming I', 'year' => 1, 'semester' => 'first', 'units' => 3],
        ['code' => 'MTH 101', 'title' => 'Engineering Mathematics I', 'year' => 1, 'semester' => 'first', 'units' => 3],
        ['code' => 'CPE 102', 'title' => 'Computer Programming II', 'year' => 1, 'semester' => 'second', 'units' => 3],
        ['code' => 'CPE 104', 'title' => 'Logic Circuits', 'year' => 1, 'semester' => 'second', 'units' => 3],
        ['code' => 'GSS 102', 'title' => 'Communication in English', 'year' => 1, 'semester' => 'second', 'units' => 2, 'elective' => true],
        ['code' => 'CPE 201', 'title' => 'Digital Systems', 'year' => 2, 'semester' => 'first', 'units' => 3],
        ['code' => 'CPE 203', 'title' => 'Data Structures', 'year' => 2, 'semester' => 'first', 'units' => 3],
        ['code' => 'MTH 201', 'title' => 'Engineering Mathematics II', 'year' => 2, 'semester' => 'first', 'units' => 3],
        ['code' => 'CPE 202', 'title' => 'Computer Architecture', 'year' => 2, 'semester' => 'second', 'units' => 3],
        ['code' => 'CPE 204', 'title' => 'Signals and Systems', 'year' => 2, 'semester' => 'second', 'units' => 3],
        ['code' => 'GSS 202', 'title' => 'Entrepreneurship Studies', 'year' => 2, 'semester' => 'second', 'units' => 2, 'elective' => true],
        ['code' => 'CPE 301', 'title' => 'Microprocessor Systems', 'year' => 3, 'semester' => 'first', 'units' => 3],
        ['code' => 'CPE 303', 'title' => 'Computer Networks', 'year' => 3, 'semester' => 'first', 'units' => 3],
        ['code' => 'CPE 305', 'title' => 'Embedded Systems', 'year' => 3, 'semester' => 'first', 'units' => 3],
        ['code' => 'CPE 307', 'title' => 'Digital Signal Processing', 'year' => 3, 'semester' => 'first', 'units' => 2, 'elective' => true],
        ['code' => 'CPE 302', 'title' => 'Operating Systems', 'year' => 3, 'semester' => 'second', 'units' => 3],
        ['code' => 'CPE 304', 'title' => 'Software Engineering', 'year' => 3, 'semester' => 'second', 'units' => 3],
        ['code' => 'CPE 306', 'title' => 'Industrial Electronics', 'year' => 3, 'semester' => 'second', 'units' => 2, 'elective' => true],
    ];

    public function run(): void
    {
        $adviser = User::query()->updateOrCreate(
            ['email' => 'staff1@futo.edu'],
            [
                'account_type' => AccountType::Adviser,
                'title' => Honorific::Dr,
                'name' => 'Okoro Chinedu',
                'password' => Hash::make(self::PASSWORD),
                'email_verified_at' => now(),
                'status' => AccountStatus::Active,
            ],
        );
        $advisorProfile = AdvisorProfile::query()->updateOrCreate(['user_id' => $adviser->id]);
        $set = StudentSet::query()->updateOrCreate(
            [
                'advisor_profile_id' => $advisorProfile->id,
                'department_slug' => 'computer-engineering',
                'entry_start_year' => 2024,
                'entry_end_year' => 2025,
            ],
            [
                'department_name' => 'Computer Engineering',
                'student_signup_enabled' => true,
                'current_academic_year_id' => AcademicYear::query()->where('year_number', 3)->firstOrFail()->id,
                'current_semester_id' => Semester::query()->where('slug', 'first')->firstOrFail()->id,
            ],
        );

        $students = collect([
            $this->student($set, 'student1@futo.edu', '2024000001', 'Adaeze Nwosu'),
            $this->student($set, 'student2@futo.edu', '2024000002', 'Ikenna Obi'),
            $this->student($set, 'student3@futo.edu', '2024000003', 'Chiamaka Eze'),
            $this->student($set, 'student4@futo.edu', '2024000004', 'Somto Okafor'),
            $this->student($set, 'student5@futo.edu', '2024000005', 'Nneka Umeh'),
        ]);
        $sessions = collect([
            1 => $this->session($set, 1, 2024),
            2 => $this->session($set, 2, 2025),
            3 => $this->session($set, 3, 2026),
        ]);
        $offerings = $this->seedCourses($set, $sessions->all());
        $openSemester = $sessions->get(3)?->semesterInstances()
            ->whereHas('semester', fn ($query) => $query->where('slug', 'first'))
            ->firstOrFail();
        $openSemester->update(['registration_enabled' => true]);

        foreach ([1, 2] as $year) {
            foreach (['first', 'second'] as $semesterSlug) {
                $period = $sessions->get($year)->semesterInstances()
                    ->whereHas('semester', fn ($query) => $query->where('slug', $semesterSlug))
                    ->firstOrFail();

                foreach ($students as $studentIndex => $student) {
                    $registration = $this->seedRegistration($student, $period, $offerings, CourseRegistration::StatusApproved);
                    foreach ($registration->items as $courseIndex => $item) {
                        $grades = ['A', 'B', 'C', 'B', 'A', 'D', 'C', 'F'];
                        $item->update([
                            'grade' => $grades[($studentIndex + $courseIndex + $year + ($semesterSlug === 'second' ? 1 : 0)) % count($grades)],
                            'graded_at' => now(),
                        ]);
                    }
                }

                $publishedCourses = $period->courseOfferings()->orderBy('id')->get();
                if ($year === 2 && $semesterSlug === 'second') {
                    $publishedCourses = $publishedCourses->take(1);
                }

                $publishedCourses->each(fn (CourseOffering $offering) => $offering->forceFill([
                    'results_published_at' => now(),
                    'results_published_by_user_id' => $adviser->id,
                ])->save());
            }
        }

        $this->seedRegistration($students->get(0), $openSemester, $offerings, CourseRegistration::StatusDraft);
        $this->seedRegistration($students->get(1), $openSemester, $offerings, CourseRegistration::StatusSubmitted);
        $currentRegistration = $this->seedRegistration($students->get(2), $openSemester, $offerings, CourseRegistration::StatusApproved);
        $currentRegistration->items->first()?->update(['grade' => 'B', 'graded_at' => now()]);
        $this->seedDues($advisorProfile, $set, $sessions->all(), $students->get(4));
    }

    private function session(StudentSet $set, int $yearNumber, int $startYear): AcademicSession
    {
        $session = AcademicSession::query()->updateOrCreate(
            [
                'student_set_id' => $set->id,
                'academic_year_id' => AcademicYear::query()->where('year_number', $yearNumber)->firstOrFail()->id,
            ],
            ['start_year' => $startYear, 'end_year' => $startYear + 1],
        );

        Semester::query()->orderBy('sort_order')->each(function (Semester $semester) use ($session): void {
            $session->semesterInstances()->updateOrCreate(
                ['semester_id' => $semester->id],
                ['maximum_course_units' => 24, 'registration_enabled' => false],
            );
        });

        return $session;
    }

    private function student(StudentSet $set, string $email, string $registrationNumber, string $name): StudentProfile
    {
        $student = StudentProfile::query()
            ->where('advisor_profile_id', $set->advisor_profile_id)
            ->where('registration_number', $registrationNumber)
            ->first();
        $user = $student?->user ?? new User;
        $user->forceFill([
            'account_type' => AccountType::Student,
            'name' => $name,
            'email' => $email,
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
            'status' => AccountStatus::Active,
        ])->save();

        return StudentProfile::query()->updateOrCreate(
            [
                'advisor_profile_id' => $set->advisor_profile_id,
                'registration_number' => $registrationNumber,
            ],
            ['user_id' => $user->id, 'student_set_id' => $set->id, 'status' => 'active'],
        );
    }

    /**
     * @param  array<int, AcademicSession>  $sessionsByYear
     * @return array<string, CourseOffering>
     */
    private function seedCourses(StudentSet $set, array $sessionsByYear): array
    {
        $courses = [];
        $offerings = [];

        foreach (self::DEMO_COURSES as $courseData) {
            $session = $sessionsByYear[$courseData['year']] ?? null;

            if (! $session instanceof AcademicSession) {
                continue;
            }

            $semester = $session->semesterInstances()
                ->whereHas('semester', fn ($query) => $query->where('slug', $courseData['semester']))
                ->firstOrFail();
            $course = Course::query()->updateOrCreate(
                ['student_set_id' => $set->id, 'code' => $courseData['code']],
                [
                    'title' => $courseData['title'],
                ],
            );
            $offering = CourseOffering::query()->updateOrCreate(
                ['course_id' => $course->id, 'academic_session_semester_id' => $semester->id],
                [
                    'course_title' => $course->title,
                    'credit_units' => $courseData['units'],
                    'is_elective' => (bool) ($courseData['elective'] ?? false),
                ],
            );
            $courses[$course->code] = $course;
            $offerings[$course->code] = $offering;
        }

        foreach ([
            'CPE 203' => ['CPE 103'],
            'CPE 301' => ['CPE 201'],
            'CPE 302' => ['CPE 202'],
        ] as $courseCode => $prerequisiteCodes) {
            $offerings[$courseCode]?->prerequisiteCourses()->sync(
                collect($prerequisiteCodes)->map(fn (string $code): int => $courses[$code]->id)->all(),
            );
        }

        return $offerings;
    }

    /** @param array<string, CourseOffering> $offerings */
    private function seedRegistration(
        StudentProfile $student,
        AcademicSessionSemester $semester,
        array $offerings,
        string $status,
    ): CourseRegistration {
        $registration = CourseRegistration::query()->updateOrCreate(
            [
                'student_profile_id' => $student->id,
                'academic_session_semester_id' => $semester->id,
            ],
            [
                'status' => $status,
                'submitted_at' => $status === CourseRegistration::StatusSubmitted ? now() : null,
                'reviewed_at' => $status === CourseRegistration::StatusApproved ? now() : null,
                'feedback_note' => null,
            ],
        );
        $registration->items()->delete();

        foreach ($offerings as $offering) {
            if ((int) $offering->academic_session_semester_id !== (int) $semester->id) {
                continue;
            }

            $registration->items()->create([
                'course_offering_id' => $offering->id,
                'is_elective' => $offering->is_elective,
                'course_code' => $offering->course->code,
                'course_title' => $offering->course_title,
                'credit_units' => $offering->credit_units,
            ]);
        }

        return $registration->load('items');
    }

    /** @param array<int, AcademicSession> $sessionsByYear */
    private function seedDues(
        AdvisorProfile $advisor,
        StudentSet $set,
        array $sessionsByYear,
        StudentProfile $waivedStudent,
    ): void {
        $examples = [
            [3, null, 'Student Association Levy', 50000, Due::StatusPublished],
            [3, 'first', 'Departmental Dues 300 Level', 1500000, Due::StatusPublished],
            [2, 'second', 'Laboratory Materials Levy 200 Level', 750000, Due::StatusDraft],
            [1, 'first', 'Orientation Dues 100 Level', 350000, Due::StatusPublished],
        ];

        foreach ($examples as [$year, $semesterSlug, $title, $amountKobo, $status]) {
            $session = $sessionsByYear[$year];
            $semester = $semesterSlug === null ? null : $session->semesterInstances()
                ->whereHas('semester', fn ($query) => $query->where('slug', $semesterSlug))
                ->firstOrFail();
            $due = Due::query()->firstOrCreate(
                ['advisor_profile_id' => $advisor->id, 'student_set_id' => $set->id, 'academic_session_id' => $session->id, 'title' => $title],
                [
                    'academic_session_semester_id' => $semester?->id,
                    'description' => 'Student set levy.',
                    'amount_kobo' => $amountKobo,
                    'currency' => 'NGN',
                    'status' => $status,
                    'published_at' => $status === Due::StatusPublished ? now() : null,
                ],
            );

            if ($due->status === Due::StatusPublished) {
                app(DueAssignmentService::class)->syncForDue($due);
            }

            if ($title === 'Departmental Dues 300 Level') {
                $assignment = StudentDueAssignment::query()
                    ->where('due_id', $due->id)
                    ->where('student_profile_id', $waivedStudent->id)
                    ->first();
                if ($assignment?->status === StudentDueAssignment::StatusPending) {
                    app(DueAssignmentService::class)->waive($assignment, $advisor->user, 'Department grant');
                }
            }
        }
    }
}
