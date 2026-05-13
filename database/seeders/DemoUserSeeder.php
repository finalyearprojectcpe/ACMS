<?php

namespace Database\Seeders;

use App\AccountType;
use App\Models\Course;
use App\Models\Department;
use App\Models\Faculty;
use App\Models\Level;
use App\Models\Semester;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoUserSeeder extends Seeder
{
    private const string PASSWORD = 'password';

    public function run(): void
    {
        $faculty = Faculty::query()->where('abbreviation', 'SESET')->firstOrFail();
        $department = Department::query()->where('abbreviation', 'CPE')->firstOrFail();

        $department->setMinimumYears(5);

        $facultyHead = $this->seedStaffProfile($department, [
            'name' => 'Nwankwo Chika',
            'email' => 'facultyhead.seset@futo.edu',
        ]);

        $faculty->assignHead($facultyHead);

        $staffProfiles = collect($this->staff())
            ->mapWithKeys(fn (array $staff): array => [
                $staff['email'] => $this->seedStaffProfile($department, $staff),
            ]);

        $department->assignHeadOfDepartment($staffProfiles->get('staff1@futo.edu'));

        foreach (range(2, 6) as $staffNumber) {
            $department->assignStaffToLevel(
                Level::query()->findOrFail($staffNumber - 1),
                $staffProfiles->get("staff{$staffNumber}@futo.edu"),
            );
        }

        foreach ($this->students() as $student) {
            $this->seedStudent($department, $student);
        }

        $faculty->academicSessions()->updateOrCreate(
            ['name' => '2025/2026'],
            [
                'starts_on' => '2025-09-01',
                'ends_on' => '2026-08-31',
                'is_current' => true,
            ],
        );

        $this->seedCourses($faculty, $department);
    }

    /**
     * @param  array{name: string, email: string}  $staff
     */
    private function seedStaffProfile(Department $department, array $staff): StaffProfile
    {
        $user = User::query()->updateOrCreate(
            ['email' => $staff['email']],
            [
                'account_type' => AccountType::Staff,
                'name' => $staff['name'],
                'password' => self::PASSWORD,
            ],
        );

        return $user->staffProfile()->updateOrCreate(
            ['user_id' => $user->id],
            ['department_id' => $department->id],
        );
    }

    /**
     * @param  array{name: string, email: string, registration_number: string, level: string}  $student
     */
    private function seedStudent(Department $department, array $student): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => $student['email']],
            [
                'account_type' => AccountType::Student,
                'name' => $student['name'],
                'password' => self::PASSWORD,
            ],
        );

        $user->studentProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'department_id' => $department->id,
                'current_level_id' => Level::query()->where('slug', $student['level'])->value('id'),
                'registration_number' => $student['registration_number'],
            ],
        );
    }

    private function seedCourses(Faculty $faculty, Department $department): void
    {
        $courses = collect($this->courses())
            ->mapWithKeys(fn (array $course): array => [
                $course['code'] => Course::query()->updateOrCreate(
                    ['code' => $course['code']],
                    [
                        'title' => $course['title'],
                        'slug' => Str::slug($course['code'].' '.$course['title']),
                        'credit_units' => $course['credit_units'],
                    ],
                ),
            ]);

        $slots = $this->courseTimetableSlots();
        $slotIndex = 0;

        foreach ($this->courseOfferings() as $offering) {
            $owner = $offering['owner'] === 'faculty' ? $faculty : $department;

            $courseOffering = $owner->courseOfferings()->updateOrCreate(
                [
                    'course_id' => $courses->get($offering['code'])->id,
                    'level_id' => Level::query()->where('slug', $offering['level'])->value('id'),
                    'semester_id' => Semester::query()->where('slug', $offering['semester'])->value('id'),
                ],
                ['is_mandatory' => $offering['is_mandatory'] ?? true],
            );

            if ($offering['level'] === '300-level' && $offering['semester'] === 'harmattan') {
                $slot = $slots[$slotIndex % count($slots)];

                $courseOffering->timetableSlots()->updateOrCreate(
                    [
                        'day_of_week' => $slot['day_of_week'],
                        'starts_at' => $slot['starts_at'],
                    ],
                    [
                        'ends_at' => $slot['ends_at'],
                        'venue' => $slot['venue'],
                    ],
                );

                $slotIndex++;
            }
        }

        foreach ($this->coursePrerequisites() as $courseCode => $prerequisiteCode) {
            $courses->get($courseCode)->addPrerequisite($courses->get($prerequisiteCode));
        }
    }

    /**
     * @return array<int, array{name: string, email: string}>
     */
    private function staff(): array
    {
        return [
            ['name' => 'Okafor Chinedu', 'email' => 'staff1@futo.edu'],
            ['name' => 'Umeh Emeka', 'email' => 'staff2@futo.edu'],
            ['name' => 'Eze Ifeanyi', 'email' => 'staff3@futo.edu'],
            ['name' => 'Obi Chiamaka', 'email' => 'staff4@futo.edu'],
            ['name' => 'Nnamani Kelechi', 'email' => 'staff5@futo.edu'],
            ['name' => 'Okonkwo Ngozi', 'email' => 'staff6@futo.edu'],
        ];
    }

    /**
     * @return array<int, array{name: string, email: string, registration_number: string, level: string}>
     */
    private function students(): array
    {
        return [
            ['name' => 'Nwosu Adaeze', 'email' => 'student1@futo.edu', 'registration_number' => '2025000001', 'level' => '100-level'],
            ['name' => 'Eze Chidera', 'email' => 'student2@futo.edu', 'registration_number' => '2024000002', 'level' => '200-level'],
            ['name' => 'Obi Ifeoma', 'email' => 'student3@futo.edu', 'registration_number' => '2023000003', 'level' => '300-level'],
            ['name' => 'Uzochukwu Kamsiyochukwu', 'email' => 'student4@futo.edu', 'registration_number' => '2022000004', 'level' => '400-level'],
            ['name' => 'Ibekwe Chukwuemeka', 'email' => 'student5@futo.edu', 'registration_number' => '2021000005', 'level' => '500-level'],
            ['name' => 'Okeke Ngozi', 'email' => 'student6@futo.edu', 'registration_number' => '2025000006', 'level' => '100-level'],
        ];
    }

    /**
     * @return array<int, array{code: string, title: string, credit_units: int}>
     */
    private function courses(): array
    {
        return [
            ['code' => 'GST 101', 'title' => 'Use of English I', 'credit_units' => 2],
            ['code' => 'MTH 101', 'title' => 'Engineering Mathematics I', 'credit_units' => 3],
            ['code' => 'PHY 101', 'title' => 'General Physics I', 'credit_units' => 3],
            ['code' => 'CHM 101', 'title' => 'General Chemistry I', 'credit_units' => 3],
            ['code' => 'CPE 101', 'title' => 'Introduction to Computer Engineering', 'credit_units' => 2],
            ['code' => 'GST 102', 'title' => 'Use of English II', 'credit_units' => 2],
            ['code' => 'MTH 102', 'title' => 'Engineering Mathematics II', 'credit_units' => 3],
            ['code' => 'PHY 102', 'title' => 'General Physics II', 'credit_units' => 3],
            ['code' => 'CHM 102', 'title' => 'General Chemistry II', 'credit_units' => 3],
            ['code' => 'CPE 102', 'title' => 'Computer Workshop Practice', 'credit_units' => 2],
            ['code' => 'MTH 207', 'title' => 'Engineering Mathematics III', 'credit_units' => 3],
            ['code' => 'CPE 201', 'title' => 'Digital Logic Design', 'credit_units' => 3],
            ['code' => 'CPE 203', 'title' => 'Circuit Theory', 'credit_units' => 3],
            ['code' => 'EEE 201', 'title' => 'Electrical Machines I', 'credit_units' => 2],
            ['code' => 'CSC 201', 'title' => 'Computer Programming I', 'credit_units' => 3],
            ['code' => 'MTH 208', 'title' => 'Engineering Mathematics IV', 'credit_units' => 3],
            ['code' => 'CPE 202', 'title' => 'Computer Architecture Fundamentals', 'credit_units' => 3],
            ['code' => 'CPE 204', 'title' => 'Signals and Systems', 'credit_units' => 3],
            ['code' => 'EEE 202', 'title' => 'Electronic Devices', 'credit_units' => 3],
            ['code' => 'CSC 202', 'title' => 'Data Structures', 'credit_units' => 3],
            ['code' => 'CPE 301', 'title' => 'Microprocessor Systems', 'credit_units' => 3],
            ['code' => 'CPE 303', 'title' => 'Operating Systems', 'credit_units' => 3],
            ['code' => 'CPE 305', 'title' => 'Data Structures and Algorithms', 'credit_units' => 3],
            ['code' => 'EEE 301', 'title' => 'Electronic Circuit Design', 'credit_units' => 3],
            ['code' => 'MTH 305', 'title' => 'Numerical Methods', 'credit_units' => 2],
            ['code' => 'CPE 302', 'title' => 'Embedded Systems Design', 'credit_units' => 3],
            ['code' => 'CPE 304', 'title' => 'Database Systems', 'credit_units' => 3],
            ['code' => 'CPE 306', 'title' => 'Software Engineering', 'credit_units' => 3],
            ['code' => 'EEE 302', 'title' => 'Control Systems Engineering', 'credit_units' => 3],
            ['code' => 'CPE 308', 'title' => 'Computer Networks', 'credit_units' => 2],
        ];
    }

    /**
     * @return array<int, array{owner: string, code: string, level: string, semester: string, is_mandatory?: bool}>
     */
    private function courseOfferings(): array
    {
        return [
            ['owner' => 'faculty', 'code' => 'GST 101', 'level' => '100-level', 'semester' => 'harmattan'],
            ['owner' => 'faculty', 'code' => 'MTH 101', 'level' => '100-level', 'semester' => 'harmattan'],
            ['owner' => 'faculty', 'code' => 'PHY 101', 'level' => '100-level', 'semester' => 'harmattan'],
            ['owner' => 'faculty', 'code' => 'CHM 101', 'level' => '100-level', 'semester' => 'harmattan'],
            ['owner' => 'department', 'code' => 'CPE 101', 'level' => '100-level', 'semester' => 'harmattan'],
            ['owner' => 'faculty', 'code' => 'GST 102', 'level' => '100-level', 'semester' => 'rain'],
            ['owner' => 'faculty', 'code' => 'MTH 102', 'level' => '100-level', 'semester' => 'rain'],
            ['owner' => 'faculty', 'code' => 'PHY 102', 'level' => '100-level', 'semester' => 'rain'],
            ['owner' => 'faculty', 'code' => 'CHM 102', 'level' => '100-level', 'semester' => 'rain'],
            ['owner' => 'department', 'code' => 'CPE 102', 'level' => '100-level', 'semester' => 'rain'],
            ['owner' => 'faculty', 'code' => 'MTH 207', 'level' => '200-level', 'semester' => 'harmattan'],
            ['owner' => 'department', 'code' => 'CPE 201', 'level' => '200-level', 'semester' => 'harmattan'],
            ['owner' => 'department', 'code' => 'CPE 203', 'level' => '200-level', 'semester' => 'harmattan'],
            ['owner' => 'faculty', 'code' => 'EEE 201', 'level' => '200-level', 'semester' => 'harmattan'],
            ['owner' => 'faculty', 'code' => 'CSC 201', 'level' => '200-level', 'semester' => 'harmattan'],
            ['owner' => 'faculty', 'code' => 'MTH 208', 'level' => '200-level', 'semester' => 'rain'],
            ['owner' => 'department', 'code' => 'CPE 202', 'level' => '200-level', 'semester' => 'rain'],
            ['owner' => 'department', 'code' => 'CPE 204', 'level' => '200-level', 'semester' => 'rain'],
            ['owner' => 'faculty', 'code' => 'EEE 202', 'level' => '200-level', 'semester' => 'rain'],
            ['owner' => 'faculty', 'code' => 'CSC 202', 'level' => '200-level', 'semester' => 'rain'],
            ['owner' => 'department', 'code' => 'CPE 301', 'level' => '300-level', 'semester' => 'harmattan'],
            ['owner' => 'department', 'code' => 'CPE 303', 'level' => '300-level', 'semester' => 'harmattan'],
            ['owner' => 'department', 'code' => 'CPE 305', 'level' => '300-level', 'semester' => 'harmattan'],
            ['owner' => 'faculty', 'code' => 'EEE 301', 'level' => '300-level', 'semester' => 'harmattan'],
            ['owner' => 'faculty', 'code' => 'MTH 305', 'level' => '300-level', 'semester' => 'harmattan', 'is_mandatory' => false],
            ['owner' => 'department', 'code' => 'CPE 302', 'level' => '300-level', 'semester' => 'rain'],
            ['owner' => 'department', 'code' => 'CPE 304', 'level' => '300-level', 'semester' => 'rain'],
            ['owner' => 'department', 'code' => 'CPE 306', 'level' => '300-level', 'semester' => 'rain'],
            ['owner' => 'faculty', 'code' => 'EEE 302', 'level' => '300-level', 'semester' => 'rain'],
            ['owner' => 'department', 'code' => 'CPE 308', 'level' => '300-level', 'semester' => 'rain', 'is_mandatory' => false],
        ];
    }

    /**
     * @return array<int, array{day_of_week: string, starts_at: string, ends_at: string, venue: string}>
     */
    private function courseTimetableSlots(): array
    {
        return [
            ['day_of_week' => 'Monday', 'starts_at' => '08:00', 'ends_at' => '10:00', 'venue' => 'ETF 204'],
            ['day_of_week' => 'Tuesday', 'starts_at' => '10:00', 'ends_at' => '12:00', 'venue' => 'CPE Lab 1'],
            ['day_of_week' => 'Wednesday', 'starts_at' => '12:00', 'ends_at' => '14:00', 'venue' => 'SESET 102'],
            ['day_of_week' => 'Thursday', 'starts_at' => '09:00', 'ends_at' => '11:00', 'venue' => 'ETF 301'],
            ['day_of_week' => 'Friday', 'starts_at' => '11:00', 'ends_at' => '13:00', 'venue' => 'CPE Studio'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function coursePrerequisites(): array
    {
        return [
            'CPE 102' => 'CPE 101',
            'CPE 201' => 'CPE 101',
            'CPE 202' => 'CPE 201',
            'CPE 301' => 'CPE 202',
            'CPE 302' => 'CPE 301',
            'CPE 304' => 'CPE 202',
            'CPE 306' => 'CPE 305',
            'CPE 308' => 'CPE 204',
        ];
    }
}
