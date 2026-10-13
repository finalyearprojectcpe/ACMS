<?php

namespace Database\Seeders;

use App\AccountStatus;
use App\AccountType;
use App\Grade;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    private const string PASSWORD = 'password';

    private const array DEMO_COURSES = [
        1 => [
            'first' => [
                ['GST 111', 'Communications in English', 2],
                ['MTH 101', 'Elementary Mathematics I: Algebra and Trigonometry', 2],
                ['MTH 103', 'Elementary Mathematics III', 2],
                ['PHY 101', 'General Physics I: Mechanics', 2],
                ['PHY 107', 'General Practical Physics I', 1],
                ['GET 101', 'Engineer in Society', 1],
                ['CPE 111', 'Introduction to Computer Engineering', 2],
                ['CHM 101', 'General Chemistry I', 2],
                ['CHM 107', 'General Practical Chemistry I', 1],
                ['BIO 101', 'General Biology', 2],
                ['BIO 107', 'General Practical Biology', 1],
            ],
            'second' => [
                ['GST 112', 'Nigerian Peoples and Culture', 2],
                ['MTH 102', 'Elementary Mathematics II: Calculus', 2],
                ['PHY 102', 'General Physics II: Behaviour of Matter', 2],
                ['PHY 108', 'General Practical Physics II', 1],
                ['GET 102', 'Engineering Graphics & Solid Modeling I', 2],
                ['CHM 102', 'General Chemistry II', 2],
                ['CHM 108', 'General Practical Chemistry II', 1],
                ['PHY 104', 'General Physics IV', 2],
                ['STA 112', 'Probability I', 3],
                ['FUTO-CPE 102', 'Introduction to Computer Engineering Softwares', 2],
            ],
        ],
        2 => [
            'first' => [
                ['ENT 211', 'Entrepreneurship and Innovation', 2],
                ['GET 201', 'Applied Electricity I', 3],
                ['GET 203', 'Engineering Graphics and Solid Modeling II', 2],
                ['GET 205', 'Fundamentals of Fluid Mechanics', 3],
                ['GET 207', 'Applied Mechanics', 3],
                ['GET 209', 'Engineering Mathematics I', 3],
                ['GET 211', 'Computing and Software Engineering', 3],
                ['FUTO-EPE 201', 'Electrical and Electronic Engineering Studio', 1],
                ['FUTO-CPE 201', 'Introduction to Programming Languages', 1],
            ],
            'second' => [
                ['GST 212', 'Philosophy, Logic and Human Existence', 2],
                ['GET 202', 'Engineering Materials', 3],
                ['GET 204', 'Students Workshop Practice', 2],
                ['GET 206', 'Fundamentals of Thermodynamics', 3],
                ['GET 208', 'Strength of Materials', 3],
                ['GET 210', 'Engineering Mathematics II', 3],
                ['CET 202', 'Basic Electronics', 3],
                ['FUTO-MCE 202', 'Modeling and Simulation of Physical Systems', 1],
                ['FUTO-CPE 204', 'Digital Logic Technique and Systems', 1],
            ],
        ],
        3 => [
            'first' => [
                ['GET 301', 'Engineering Mathematics III', 3],
                ['GET 305', 'Engineering Statistics and Data Analytics', 3],
                ['GET 307', 'Introduction to Artificial Intelligence, Machine Learning and Convergent Technologies', 3],
                ['CPE 301', 'Computer Organization and Architecture', 2],
                ['EEE 321', 'Analogue Electronic Circuits', 2],
                ['TEL 303', 'Electric Circuit Theory', 2],
                ['CPE 307', 'Assembly Language Programming', 2],
                ['TEE 301', 'Communication Principles', 1],
                ['FUTO-CPE 301', 'Computer Engineering Studio I', 1],
                ['FUT-GET 317', 'Engineering Economics', 2],
            ],
            'second' => [
                ['GST 312', 'Peace and Conflict Resolution', 2],
                ['ENT 312', 'Venture Creation', 2],
                ['GET 302', 'Engineering Mathematics IV', 3],
                ['GET 304', 'Engineering Communications, Technical Writing and Presentation', 3],
                ['GET 306', 'Renewable Energy Systems and Technologies', 3],
                ['CPE 302', 'Measurement and Instrumentation', 3],
                ['EEE 322', 'Digital Electronic Circuits', 2],
                ['FUTO-MCE 302', 'Design and Development of Physical Products Studio', 1],
                ['FUTO-ENT 316', 'Human Skills for the 21st Century', 1],
                ['FUTO-CPE 310', 'Introduction to Operating System and Database Development', 1],
            ],
        ],
        4 => [
            'first' => [
                ['CPE 401', 'Microprocessor and Embedded Systems', 3],
                ['CPE 403', 'Control Systems', 2],
                ['CPE 405', 'Fundamentals of Software Engineering', 2],
                ['CPE 411', 'Hardware Design Techniques & Verification', 2],
                ['CPE 413', 'Research Methods', 2],
                ['ELE 405', 'Engineering Systems Modeling & Simulations', 2],
                ['ELE 407', 'Data Communications and Networking', 2],
                ['FUTO-CPE 417', 'Emerging Technologies', 1],
                ['FUTO-CPE 409', 'Introduction to Robotics', 1],
                ['FUTO-CPE 415', 'Computer Engineering Studio II', 2],
                ['FUTO-CPE 407', 'Cybersecurity Fundamentals', 1],
                ['FUTO-EMV 421', 'Health, Safety & Environment', 1],
            ],
            'second' => [
                ['GET 299', 'SIWES I: SWEP', 3],
                ['GET 399', 'SIWES II', 4],
                ['GET 499', 'SIWES III', 8],
                ['GET 402', 'Engineering Project', 2],
                ['GET 404', 'Engineering Evaluation and Costing', 2],
            ],
        ],
        5 => [
            'first' => [
                ['GET 501', 'Engineering Project Management', 3],
                ['CPE 501', 'Testing, Reliability and Maintainability', 2],
                ['CPE 505', 'Digital Systems Design with VHDL', 2],
                ['CPE 511', 'Machine Learning and Applications', 3],
                ['FUTO-CPE 503', 'Cybersecurity & Law', 2],
                ['FUTO-CPE 507', 'Multimedia & Operating Systems', 2],
                ['FUTO-CPE 515', 'Computer Engineering Studio II', 1],
                ['FUTO-CPE 513', 'Advanced Embedded System and Programming', 2],
                ['GET 599', 'B.Eng. Project I', 3],
            ],
            'second' => [
                ['GET 502', 'Engineering Law', 2],
                ['CPE 502', 'Digital Signal Processing and Applications', 3],
                ['CPE 514', 'Professional Practice and Ethics', 3],
                ['FUTO-CPE 504', 'Big Data and AI', 2],
                ['FUTO-CPE 506', 'Data Structures & Algorithms', 2],
                ['FUTO-CPE 508', 'Cryptography Principles & Applications', 1],
                ['FUTO-CPE 510', 'Embedded System Miniaturization and Applications', 2],
                ['FUTO-CPE 502', 'Recruitment Strategies and Interview Methods', 2],
                ['GET 599', 'B.Eng. Project II', 3],
            ],
        ],
    ];

    private const array DEMO_ELECTIVES = [
        1 => [
            'first' => [
                ['FUTO-FRN 101', 'Introduction to French Language I', 1],
                ['FUTO-IGB 101', 'Igbo Grammar, Composition and Comprehension', 1],
                ['FUTO-GST 103', 'Humanities', 1],
            ],
            'second' => [
                ['FUTO-EGL 102', 'Advanced Communication in English', 1],
                ['FUTO-IGB 102', 'Introduction to Igbo History, Culture and Literature', 1],
                ['FUTO-FRN 102', 'French Language II', 1],
            ],
        ],
        5 => ['second' => [
            ['FUTO-GET 502', 'Engineering Management', 2],
        ]],
    ];

    public function run(): void
    {
        DB::transaction(fn () => $this->seedDemo());
    }

    private function seedDemo(): void
    {
        $adviser = User::query()->updateOrCreate(
            ['email' => 'staff1@futo.edu.ng'],
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
            $this->student($set, 'student1@futo.edu.ng', '2024000001', 'Adaeze Nwosu'),
            $this->student($set, 'student2@futo.edu.ng', '2024000002', 'Ikenna Obi'),
            $this->student($set, 'student3@futo.edu.ng', '2024000003', 'Chiamaka Eze'),
            $this->student($set, 'student4@futo.edu.ng', '2024000004', 'Somto Okafor'),
            $this->student($set, 'student5@futo.edu.ng', '2024000005', 'Nneka Umeh'),
        ]);
        $givenNames = ['Amaka', 'Emeka', 'Chidera', 'Tobi', 'Kamsi', 'Ifeanyi', 'Zara', 'Uche', 'Chisom'];
        $surnames = ['Adebayo', 'Okafor', 'Nwankwo', 'Eze', 'Bello', 'Obi', 'Akinola', 'Ibrahim', 'Umeh', 'Nwosu'];
        for ($number = 7; $number <= 90; $number++) {
            $offset = $number - 7;
            $students->push($this->student(
                $set,
                "student{$number}@futo.edu.ng",
                sprintf('2024%06d', $number),
                $givenNames[$offset % count($givenNames)].' '.$surnames[intdiv($offset, count($givenNames)) % count($surnames)],
            ));
        }
        $set->students()->updateOrCreate(
            ['registration_number_key' => StudentProfile::registrationKey('2024000006')],
            [
                'advisor_profile_id' => $advisorProfile->id,
                'official_name' => 'Kelechi Okeke',
                'registration_number' => '2024000006',
                'user_id' => null,
            ],
        );
        $sessions = collect([
            1 => $this->session($set, 1, 2024),
            2 => $this->session($set, 2, 2025),
            3 => $this->session($set, 3, 2026),
            4 => $this->session($set, 4, 2027),
            5 => $this->session($set, 5, 2028),
        ]);
        $this->seedCourses($set, $sessions->all());
        $openSemester = $sessions->get(3)?->semesterInstances()
            ->whereHas('semester', fn ($query) => $query->where('slug', 'first'))
            ->firstOrFail();

        foreach ([1, 2] as $year) {
            foreach (['first', 'second'] as $semesterSlug) {
                $period = $sessions->get($year)->semesterInstances()
                    ->whereHas('semester', fn ($query) => $query->where('slug', $semesterSlug))
                    ->firstOrFail();

                foreach ($students as $studentIndex => $student) {
                    $registration = $this->seedRegistration($student, $period, CourseRegistration::StatusAssigned, $adviser->id, $studentIndex);
                    foreach ($registration->items as $item) {
                        $item->update([
                            'grade' => $year === 2 && $semesterSlug === 'first' && $item->course_code === 'GET 201' && $studentIndex % 4 === 1
                                ? Grade::F->value : $this->gradeFor($studentIndex, $item->course_code, $year, $semesterSlug),
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

        $retakeSources = CourseOffering::query()->with('course')
            ->whereHas('academicSessionSemester.academicSession', fn ($query) => $query->where('student_set_id', $set->id))
            ->whereHas('course', fn ($query) => $query->whereIn('code', ['GET 201', 'GET 203']))
            ->whereHas('academicSessionSemester.academicSession', fn ($query) => $query->where('start_year', 2025))
            ->get()->keyBy('course.code');
        foreach ($students as $studentIndex => $student) {
            if ($studentIndex % 4 === 3) {
                continue;
            }
            $status = $studentIndex % 4 === 0 ? CourseRegistration::StatusDraft : CourseRegistration::StatusAssigned;
            $currentRegistration = $this->seedRegistration($student, $openSemester, $status, $adviser->id, $studentIndex);
            $sourceCode = $studentIndex % 4 === 1 ? 'GET 201' : ($studentIndex % 5 === 0 ? 'GET 203' : null);
            $source = $sourceCode === null ? null : $retakeSources->get($sourceCode);
            if ($source instanceof CourseOffering) {
                $target = $openSemester->courseOfferings()->firstOrCreate(
                    ['course_id' => $source->course_id],
                    ['course_title' => $source->course_title, 'credit_units' => $source->credit_units, 'is_elective' => false, 'is_retake_only' => true],
                );
                $currentRegistration->items()->create([
                    'course_offering_id' => $target->id,
                    'course_code' => $source->course->code,
                    'course_title' => $source->course_title,
                    'credit_units' => $source->credit_units,
                    'is_elective' => false,
                    'assignment_origin' => $sourceCode === 'GET 201' ? 'carryover' : 'deferred',
                ]);
            }
            if ($studentIndex === 2) {
                $currentRegistration->items->first()?->update(['grade' => 'B', 'graded_at' => now()]);
            }
        }
        $this->seedDues($advisorProfile, $set, $sessions->all(), $students->get(4));
    }

    /**
     * Letter grade for a seeded course item, derived from the student's
     * ability tier with deterministic per-course variation so reseeding
     * always reproduces the same transcripts.
     */
    private function gradeFor(int $studentIndex, string $courseCode, int $year, string $semesterSlug): string
    {
        $ability = $this->studentAbility($studentIndex);
        $spread = $ability['spread'];
        $noise = (crc32($studentIndex.'|'.$courseCode.'|'.$year.'|'.$semesterSlug) % ($spread * 2 + 1)) - $spread;
        $score = max(31, min(99, $ability['mean'] + $noise));

        return match (true) {
            $score >= 70 => Grade::A->value,
            $score >= 60 => Grade::B->value,
            $score >= 50 => Grade::C->value,
            $score >= 45 => Grade::D->value,
            $score >= 40 => Grade::E->value,
            default => Grade::F->value,
        };
    }

    /**
     * Ability tier for a seeded student. Most students are average, a
     * minority are strong, and the weak students earn the failed grades
     * and carryovers. The carryover demo students (index % 4 === 1) are
     * always weak so their forced GET 201 failure fits their transcript.
     *
     * @return array{mean: int, spread: int}
     */
    private function studentAbility(int $studentIndex): array
    {
        $tier = match (true) {
            $studentIndex % 4 === 1 => 'weak',
            in_array($studentIndex, [2, 4], true) => 'weak',
            $studentIndex === 0 => 'strong',
            ((($studentIndex + 1) * 73) % 100) < 20 => 'strong',
            default => 'average',
        };

        return match ($tier) {
            'strong' => ['mean' => 76, 'spread' => 9],
            'weak' => ['mean' => 45, 'spread' => 16],
            default => ['mean' => 57, 'spread' => 14],
        };
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
                ['maximum_course_units' => 24],
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
            ['user_id' => $user->id, 'student_set_id' => $set->id, 'official_name' => $name, 'status' => 'active'],
        );
    }

    /**
     * @param  array<int, AcademicSession>  $sessionsByYear
     * @return array<int, CourseOffering>
     */
    private function seedCourses(StudentSet $set, array $sessionsByYear): array
    {
        $offerings = [];

        foreach (self::DEMO_COURSES as $year => $semesters) {
            $session = $sessionsByYear[$year];
            foreach ($semesters as $semesterSlug => $normalRows) {
                $semester = $session->semesterInstances()
                    ->whereHas('semester', fn ($query) => $query->where('slug', $semesterSlug))
                    ->firstOrFail();
                foreach ([...$normalRows, ...(self::DEMO_ELECTIVES[$year][$semesterSlug] ?? [])] as [$code, $title, $units]) {
                    $code = Course::normalizeCode($code);
                    $isElective = in_array($code, array_map(fn (array $row): string => Course::normalizeCode($row[0]), self::DEMO_ELECTIVES[$year][$semesterSlug] ?? []), true);
                    $course = Course::query()->firstOrCreate(
                        ['student_set_id' => $set->id, 'code' => $code],
                        ['title' => $title, 'credit_units' => $units, 'is_elective' => $isElective],
                    );
                    $offerings[] = CourseOffering::query()->updateOrCreate(
                        ['course_id' => $course->id, 'academic_session_semester_id' => $semester->id],
                        ['course_title' => $title, 'credit_units' => $units, 'is_elective' => $isElective],
                    );
                }
            }
        }

        foreach (['MTH 102' => 'MTH 101', 'GET 210' => 'GET 209', 'GET 302' => 'GET 301', 'FUTO-FRN 102' => 'FUTO-FRN 101'] as $code => $prerequisite) {
            $target = collect($offerings)->first(fn (CourseOffering $offering): bool => $offering->course->code === $code);
            $source = $set->courses()->where('code', $prerequisite)->first();
            if ($target instanceof CourseOffering && $source instanceof Course) {
                $target->prerequisiteCourses()->syncWithoutDetaching([$source->id]);
            }
        }

        return $offerings;
    }

    private function seedRegistration(
        StudentProfile $student,
        AcademicSessionSemester $semester,
        string $status,
        int $adviserId,
        int $studentIndex,
    ): CourseRegistration {
        $registration = CourseRegistration::query()->updateOrCreate(
            [
                'student_profile_id' => $student->id,
                'academic_session_semester_id' => $semester->id,
            ],
            [
                'status' => $status,
                'assigned_by_user_id' => $status === CourseRegistration::StatusAssigned ? $adviserId : null,
                'assigned_at' => $status === CourseRegistration::StatusAssigned ? now() : null,
            ],
        );
        $registration->items()->delete();

        foreach ($semester->courseOfferings()->with('course')->where('is_retake_only', false)->get() as $offering) {
            if ($offering->is_elective && ($studentIndex + (int) $semester->semester_id) % 3 !== (int) $offering->id % 3) {
                continue;
            }
            if ($semester->academicSession->start_year === 2025 && $semester->semester->slug === 'first'
                && $offering->course->code === 'GET 203' && $studentIndex % 5 === 0) {
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
