<?php

namespace Database\Seeders;

use App\AccountType;
use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\AcademicYearSemester;
use App\Models\ApprovalRequest;
use App\Models\Course;
use App\Models\Department;
use App\Models\EmailDelivery;
use App\Models\Faculty;
use App\Models\ImportBatch;
use App\Models\School;
use App\Models\Semester;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DemoUserSeeder extends Seeder
{
    private const string PASSWORD = 'password';

    public function run(): void
    {
        $school = School::query()->where('abbreviation', 'FUTO')->firstOrFail();
        $faculty = Faculty::query()->where('abbreviation', 'SESET')->firstOrFail();
        $department = Department::query()->where('abbreviation', 'CPE')->firstOrFail();

        $department->setMinimumYearsToDegree(5);

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

        $currentAcademicSession = $this->academicSessionByName($school, '2025/2026');

        foreach ([
            'staff2@futo.edu' => '2025/2026',
            'staff3@futo.edu' => '2024/2025',
            'staff4@futo.edu' => '2023/2024',
            'staff5@futo.edu' => '2022/2023',
            'staff6@futo.edu' => '2021/2022',
        ] as $staffEmail => $entrySessionName) {
            $department->assignAdvisorToStudentSet(
                $this->academicSessionByName($school, $entrySessionName),
                $staffProfiles->get($staffEmail),
                $currentAcademicSession,
                $staffProfiles->get('staff1@futo.edu'),
            );
        }

        foreach ($this->students() as $student) {
            $this->seedStudent($department, $student);
        }

        $academicSessions = $school->academicSessions()
            ->oldest('starts_on')
            ->get();

        $academicSessions->each(
            fn (AcademicSession $academicSession) => $this->seedCourses($faculty, $department, $academicSession),
        );
        $this->seedFacultyHeadDemoData($faculty, $facultyHead);
    }

    /**
     * @param  array{name: string, email: string, activated?: bool, access_code?: string}  $staff
     */
    private function seedStaffProfile(Department $department, array $staff): StaffProfile
    {
        $isActivated = $staff['activated'] ?? true;

        $user = User::query()->updateOrCreate(
            [
                'school_id' => $department->faculty->school_id,
                'email' => $staff['email'],
            ],
            [
                'account_type' => AccountType::Staff,
                'name' => $staff['name'],
                'password' => $isActivated ? self::PASSWORD : Str::random(40),
                'access_code' => $isActivated ? null : ($staff['access_code'] ?? Str::upper(Str::random(8))),
                'access_code_used_at' => null,
                'activated_at' => $isActivated ? now() : null,
            ],
        );

        return $user->staffProfile()->updateOrCreate(
            ['user_id' => $user->id],
            ['department_id' => $department->id],
        );
    }

    private function seedFacultyHeadDemoData(Faculty $faculty, StaffProfile $facultyHead): void
    {
        $departments = Department::query()
            ->where('faculty_id', $faculty->id)
            ->whereIn('abbreviation', ['EEE', 'ECE', 'MCE', 'TCE', 'EPE'])
            ->get()
            ->keyBy('abbreviation');
        $departmentStaff = collect();

        foreach ($this->facultyDepartmentDemoData() as $departmentCode => $departmentData) {
            $department = $departments->get($departmentCode);

            if (! $department instanceof Department) {
                continue;
            }

            $department->setMinimumYearsToDegree($departmentData['minimum_years_to_degree']);

            if (isset($departmentData['hod'])) {
                $hodProfile = $this->seedStaffProfile($department, $departmentData['hod']);

                $department->assignHeadOfDepartment($hodProfile);
                $departmentStaff->push($hodProfile);
            }

            foreach ($departmentData['staff'] as $staff) {
                $departmentStaff->push($this->seedStaffProfile($department, $staff));
            }

            foreach ($departmentData['students'] as $student) {
                $this->seedStudent($department, $student);
            }
        }

        $this->seedFacultyHeadEmailDeliveries($faculty);
        $this->seedFacultyHeadImports($faculty, $facultyHead);
        $this->seedFacultyHeadApprovals($faculty, $facultyHead, $departmentStaff);
    }

    private function seedFacultyHeadEmailDeliveries(Faculty $faculty): void
    {
        foreach ($this->facultyHeadEmailDeliveries() as $delivery) {
            $user = User::query()
                ->where('school_id', $faculty->school_id)
                ->where('email', $delivery['recipient_email'])
                ->first();

            EmailDelivery::query()->updateOrCreate(
                [
                    'school_id' => $faculty->school_id,
                    'recipient_email' => $delivery['recipient_email'],
                    'subject' => $delivery['subject'],
                ],
                [
                    'user_id' => $user?->id,
                    'mailer' => 'cloudflare',
                    'status' => $delivery['status'],
                    'provider_message_id' => $delivery['provider_message_id'] ?? null,
                    'error' => $delivery['error'] ?? null,
                    'metadata' => $delivery['metadata'] ?? [],
                    'sent_at' => $delivery['status'] === 'sent' ? now()->subDays($delivery['days_ago']) : null,
                    'failed_at' => $delivery['status'] === 'failed' ? now()->subDays($delivery['days_ago']) : null,
                ],
            );
        }
    }

    private function seedFacultyHeadImports(Faculty $faculty, StaffProfile $facultyHead): void
    {
        foreach ($this->facultyHeadImportBatches() as $importData) {
            $batch = ImportBatch::query()->updateOrCreate(
                [
                    'school_id' => $faculty->school_id,
                    'type' => $importData['type'],
                    'file_name' => $importData['file_name'],
                ],
                [
                    'uploaded_by_user_id' => $facultyHead->user_id,
                    'scope_type' => Faculty::class,
                    'scope_id' => $faculty->id,
                    'status' => $importData['status'],
                    'mappings' => $importData['mappings'],
                    'summary' => $importData['summary'],
                    'submitted_at' => now()->subDays($importData['submitted_days_ago']),
                    'approved_at' => $importData['status'] === 'approved'
                        ? now()->subDays($importData['approved_days_ago'])
                        : null,
                ],
            );

            foreach ($importData['rows'] as $row) {
                $batch->rows()->updateOrCreate(
                    ['row_number' => $row['row_number']],
                    [
                        'raw_data' => $row['raw_data'],
                        'normalized_data' => $row['normalized_data'],
                        'errors' => $row['errors'] ?? [],
                        'status' => $row['status'],
                    ],
                );
            }
        }
    }

    /**
     * @param  Collection<int, StaffProfile>  $departmentStaff
     */
    private function seedFacultyHeadApprovals(Faculty $faculty, StaffProfile $facultyHead, Collection $departmentStaff): void
    {
        $requesterIds = $departmentStaff
            ->pluck('user_id')
            ->filter()
            ->values();

        foreach ($this->facultyHeadApprovalRequests() as $index => $approval) {
            ApprovalRequest::query()->updateOrCreate(
                [
                    'school_id' => $faculty->school_id,
                    'type' => $approval['type'],
                    'notes' => $approval['notes'],
                ],
                [
                    'requester_user_id' => $requesterIds->get($index),
                    'reviewer_user_id' => $facultyHead->user_id,
                    'status' => $approval['status'],
                    'submitted_at' => now()->subDays($approval['submitted_days_ago']),
                    'reviewed_at' => isset($approval['reviewed_days_ago'])
                        ? now()->subDays($approval['reviewed_days_ago'])
                        : null,
                ],
            );
        }
    }

    /**
     * @return array<string, array{
     *     minimum_years_to_degree: int,
     *     hod?: array{name: string, email: string, activated?: bool, access_code?: string},
     *     staff: array<int, array{name: string, email: string, activated?: bool, access_code?: string}>,
     *     students: array<int, array{name: string, email: string, registration_number: string, level: string}>
     * }>
     */
    private function facultyDepartmentDemoData(): array
    {
        return [
            'EEE' => [
                'minimum_years_to_degree' => 5,
                'hod' => ['name' => 'Nwachukwu Amarachi', 'email' => 'hod.eee@futo.edu'],
                'staff' => [
                    ['name' => 'Ezeani Chisom', 'email' => 'eee.staff1@futo.edu'],
                    ['name' => 'Okoro Tochukwu', 'email' => 'eee.staff2@futo.edu'],
                    ['name' => 'Ifeanyi Nneka', 'email' => 'eee.staff3@futo.edu'],
                ],
                'students' => [
                    ['name' => 'Onyeka Chiamaka', 'email' => 'eee.student1@futo.edu', 'registration_number' => '2025000101', 'level' => '100-level'],
                    ['name' => 'Agu Chinedu', 'email' => 'eee.student2@futo.edu', 'registration_number' => '2024000102', 'level' => '200-level'],
                    ['name' => 'Nworie Ifeoma', 'email' => 'eee.student3@futo.edu', 'registration_number' => '2023000103', 'level' => '300-level'],
                    ['name' => 'Ekwueme Somto', 'email' => 'eee.student4@futo.edu', 'registration_number' => '2022000104', 'level' => '400-level'],
                    ['name' => 'Ugochukwu Ada', 'email' => 'eee.student5@futo.edu', 'registration_number' => '2021000105', 'level' => '500-level'],
                    ['name' => 'Okafor Kenechukwu', 'email' => 'eee.student6@futo.edu', 'registration_number' => '2023000106', 'level' => '300-level'],
                ],
            ],
            'ECE' => [
                'minimum_years_to_degree' => 5,
                'hod' => ['name' => 'Iroegbu Somtochukwu', 'email' => 'hod.ece@futo.edu'],
                'staff' => [
                    ['name' => 'Opara Chioma', 'email' => 'ece.staff1@futo.edu'],
                    ['name' => 'Udeh Kamsi', 'email' => 'ece.staff2@futo.edu', 'activated' => false, 'access_code' => 'SESET-ECE-STAFF2'],
                ],
                'students' => [
                    ['name' => 'Nwankwo Oluebube', 'email' => 'ece.student1@futo.edu', 'registration_number' => '2025000201', 'level' => '100-level'],
                    ['name' => 'Chukwuemeka Ezinne', 'email' => 'ece.student2@futo.edu', 'registration_number' => '2024000202', 'level' => '200-level'],
                    ['name' => 'Okonkwo Chidera', 'email' => 'ece.student3@futo.edu', 'registration_number' => '2023000203', 'level' => '300-level'],
                    ['name' => 'Nnamdi Ifunanya', 'email' => 'ece.student4@futo.edu', 'registration_number' => '2022000204', 'level' => '400-level'],
                    ['name' => 'Eze Ebuka', 'email' => 'ece.student5@futo.edu', 'registration_number' => '2021000205', 'level' => '500-level'],
                ],
            ],
            'MCE' => [
                'minimum_years_to_degree' => 5,
                'hod' => ['name' => 'Okechukwu Nkiru', 'email' => 'hod.mce@futo.edu', 'activated' => false, 'access_code' => 'SESET-MCE-HOD'],
                'staff' => [
                    ['name' => 'Umeh Chidiebere', 'email' => 'mce.staff1@futo.edu'],
                    ['name' => 'Nwosu Obinna', 'email' => 'mce.staff2@futo.edu'],
                ],
                'students' => [
                    ['name' => 'Okorie Kosisochukwu', 'email' => 'mce.student1@futo.edu', 'registration_number' => '2025000301', 'level' => '100-level'],
                    ['name' => 'Ezeani Munachi', 'email' => 'mce.student2@futo.edu', 'registration_number' => '2024000302', 'level' => '200-level'],
                    ['name' => 'Uzoho Chizoba', 'email' => 'mce.student3@futo.edu', 'registration_number' => '2023000303', 'level' => '300-level'],
                    ['name' => 'Nwokedi Chukwu', 'email' => 'mce.student4@futo.edu', 'registration_number' => '2022000304', 'level' => '400-level'],
                ],
            ],
            'TCE' => [
                'minimum_years_to_degree' => 5,
                'staff' => [
                    ['name' => 'Ezeala Chukwudi', 'email' => 'tce.staff1@futo.edu'],
                    ['name' => 'Ibekwe Amarachi', 'email' => 'tce.staff2@futo.edu'],
                ],
                'students' => [
                    ['name' => 'Udechukwu Nmesoma', 'email' => 'tce.student1@futo.edu', 'registration_number' => '2025000401', 'level' => '100-level'],
                    ['name' => 'Nwafor Ifeanyi', 'email' => 'tce.student2@futo.edu', 'registration_number' => '2024000402', 'level' => '200-level'],
                    ['name' => 'Akabueze Adaora', 'email' => 'tce.student3@futo.edu', 'registration_number' => '2023000403', 'level' => '300-level'],
                ],
            ],
            'EPE' => [
                'minimum_years_to_degree' => 5,
                'staff' => [
                    ['name' => 'Okeke Chinonso', 'email' => 'epe.staff1@futo.edu'],
                    ['name' => 'Anyanwu Uchenna', 'email' => 'epe.staff2@futo.edu', 'activated' => false, 'access_code' => 'SESET-EPE-STAFF2'],
                ],
                'students' => [
                    ['name' => 'Onuorah Kelechi', 'email' => 'epe.student1@futo.edu', 'registration_number' => '2025000501', 'level' => '100-level'],
                    ['name' => 'Nweke Favour', 'email' => 'epe.student2@futo.edu', 'registration_number' => '2024000502', 'level' => '200-level'],
                    ['name' => 'Maduka Chisom', 'email' => 'epe.student3@futo.edu', 'registration_number' => '2023000503', 'level' => '300-level'],
                    ['name' => 'Ezugwu Ebube', 'email' => 'epe.student4@futo.edu', 'registration_number' => '2022000504', 'level' => '400-level'],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{recipient_email: string, subject: string, status: string, days_ago: int, provider_message_id?: string, error?: string, metadata?: array<string, string>}>
     */
    private function facultyHeadEmailDeliveries(): array
    {
        return [
            [
                'recipient_email' => 'hod.eee@futo.edu',
                'subject' => 'GradeManager HOD access code',
                'status' => 'sent',
                'days_ago' => 9,
                'provider_message_id' => 'cf-demo-hod-eee',
                'metadata' => ['department' => 'EEE', 'provisioned_role' => 'hod'],
            ],
            [
                'recipient_email' => 'hod.mce@futo.edu',
                'subject' => 'GradeManager HOD access code',
                'status' => 'sent',
                'days_ago' => 4,
                'provider_message_id' => 'cf-demo-hod-mce',
                'metadata' => ['department' => 'MCE', 'access_code' => 'SESET-MCE-HOD'],
            ],
            [
                'recipient_email' => 'epe.staff2@futo.edu',
                'subject' => 'GradeManager staff access code',
                'status' => 'failed',
                'days_ago' => 2,
                'error' => 'Mailbox unavailable.',
                'metadata' => ['department' => 'EPE', 'access_code' => 'SESET-EPE-STAFF2'],
            ],
            [
                'recipient_email' => 'ece.staff2@futo.edu',
                'subject' => 'GradeManager staff access code',
                'status' => 'skipped',
                'days_ago' => 1,
                'error' => 'Access code shared directly by faculty office.',
                'metadata' => ['department' => 'ECE', 'access_code' => 'SESET-ECE-STAFF2'],
            ],
        ];
    }

    /**
     * @return array<int, array{
     *     type: string,
     *     file_name: string,
     *     status: string,
     *     submitted_days_ago: int,
     *     approved_days_ago?: int,
     *     mappings: array<string, string>,
     *     summary: array<string, int>,
     *     rows: array<int, array{row_number: int, raw_data: array<string, string>, normalized_data: array<string, string>, status: string, errors?: array<int, string>}>
     * }>
     */
    private function facultyHeadImportBatches(): array
    {
        return [
            [
                'type' => 'hod_provisioning',
                'file_name' => 'seset-hod-access.xlsx',
                'status' => 'approved',
                'submitted_days_ago' => 10,
                'approved_days_ago' => 9,
                'mappings' => ['department' => 'Department', 'name' => 'Full Name', 'email' => 'Email', 'access_code' => 'Access Code'],
                'summary' => ['rows' => 3, 'valid_rows' => 3, 'invalid_rows' => 0],
                'rows' => [
                    ['row_number' => 2, 'raw_data' => ['Department' => 'EEE', 'Full Name' => 'Nwachukwu Amarachi', 'Email' => 'hod.eee@futo.edu'], 'normalized_data' => ['department' => 'EEE', 'email' => 'hod.eee@futo.edu'], 'status' => 'approved'],
                    ['row_number' => 3, 'raw_data' => ['Department' => 'ECE', 'Full Name' => 'Iroegbu Somtochukwu', 'Email' => 'hod.ece@futo.edu'], 'normalized_data' => ['department' => 'ECE', 'email' => 'hod.ece@futo.edu'], 'status' => 'approved'],
                    ['row_number' => 4, 'raw_data' => ['Department' => 'MCE', 'Full Name' => 'Okechukwu Nkiru', 'Email' => 'hod.mce@futo.edu'], 'normalized_data' => ['department' => 'MCE', 'email' => 'hod.mce@futo.edu'], 'status' => 'approved'],
                ],
            ],
            [
                'type' => 'staff_access_codes',
                'file_name' => 'seset-new-staff.xlsx',
                'status' => 'in_review',
                'submitted_days_ago' => 3,
                'mappings' => ['department' => 'Department', 'name' => 'Full Name', 'email' => 'Email'],
                'summary' => ['rows' => 3, 'valid_rows' => 2, 'invalid_rows' => 1],
                'rows' => [
                    ['row_number' => 2, 'raw_data' => ['Department' => 'EPE', 'Full Name' => 'Anyanwu Uchenna', 'Email' => 'epe.staff2@futo.edu'], 'normalized_data' => ['department' => 'EPE', 'email' => 'epe.staff2@futo.edu'], 'status' => 'valid'],
                    ['row_number' => 3, 'raw_data' => ['Department' => 'ECE', 'Full Name' => 'Udeh Kamsi', 'Email' => 'ece.staff2@futo.edu'], 'normalized_data' => ['department' => 'ECE', 'email' => 'ece.staff2@futo.edu'], 'status' => 'valid'],
                    ['row_number' => 4, 'raw_data' => ['Department' => 'TCE', 'Full Name' => '', 'Email' => ''], 'normalized_data' => ['department' => 'TCE'], 'status' => 'invalid', 'errors' => ['Missing name and email.']],
                ],
            ],
            [
                'type' => 'student_registration_numbers',
                'file_name' => 'seset-300l-students.xlsx',
                'status' => 'draft',
                'submitted_days_ago' => 1,
                'mappings' => ['department' => 'Department', 'registration_number' => 'Reg No'],
                'summary' => ['rows' => 4, 'valid_rows' => 4, 'invalid_rows' => 0],
                'rows' => [
                    ['row_number' => 2, 'raw_data' => ['Department' => 'CPE', 'Reg No' => '2023000003'], 'normalized_data' => ['department' => 'CPE', 'registration_number' => '2023000003'], 'status' => 'valid'],
                    ['row_number' => 3, 'raw_data' => ['Department' => 'EEE', 'Reg No' => '2023000103'], 'normalized_data' => ['department' => 'EEE', 'registration_number' => '2023000103'], 'status' => 'valid'],
                    ['row_number' => 4, 'raw_data' => ['Department' => 'ECE', 'Reg No' => '2023000203'], 'normalized_data' => ['department' => 'ECE', 'registration_number' => '2023000203'], 'status' => 'valid'],
                    ['row_number' => 5, 'raw_data' => ['Department' => 'MCE', 'Reg No' => '2023000303'], 'normalized_data' => ['department' => 'MCE', 'registration_number' => '2023000303'], 'status' => 'valid'],
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{type: string, status: string, notes: string, submitted_days_ago: int, reviewed_days_ago?: int}>
     */
    private function facultyHeadApprovalRequests(): array
    {
        return [
            [
                'type' => 'hod_import_review',
                'status' => 'approved',
                'notes' => 'Reviewed SESET HOD provisioning sheet.',
                'submitted_days_ago' => 10,
                'reviewed_days_ago' => 9,
            ],
            [
                'type' => 'staff_access_code_import',
                'status' => 'pending',
                'notes' => 'New staff access codes for EPE and ECE.',
                'submitted_days_ago' => 3,
            ],
            [
                'type' => 'course_catalog_update',
                'status' => 'pending',
                'notes' => 'EEE requested course catalog changes for 300L.',
                'submitted_days_ago' => 2,
            ],
            [
                'type' => 'student_profile_corrections',
                'status' => 'rejected',
                'notes' => 'TCE spreadsheet missing required email values.',
                'submitted_days_ago' => 1,
                'reviewed_days_ago' => 1,
            ],
        ];
    }

    /**
     * @param  array{name: string, email: string, registration_number: string, level: string}  $student
     */
    private function seedStudent(Department $department, array $student): void
    {
        $entryAcademicSession = $this->entryAcademicSessionForStudent($department, $student['registration_number']);

        $user = User::query()->updateOrCreate(
            [
                'school_id' => $department->faculty->school_id,
                'email' => $student['email'],
            ],
            [
                'account_type' => AccountType::Student,
                'name' => $student['name'],
                'password' => self::PASSWORD,
                'access_code' => null,
                'access_code_used_at' => null,
                'activated_at' => now(),
            ],
        );

        $user->studentProfile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'school_id' => $department->faculty->school_id,
                'department_id' => $department->id,
                'current_academic_year_id' => AcademicYear::query()->where('slug', $student['level'])->value('id'),
                'entry_academic_session_id' => $entryAcademicSession?->id,
                'registration_number' => $student['registration_number'],
            ],
        );
    }

    private function entryAcademicSessionForStudent(Department $department, string $registrationNumber): ?AcademicSession
    {
        $entryYear = substr($registrationNumber, 0, 4);

        if (! ctype_digit($entryYear)) {
            return null;
        }

        return AcademicSession::query()
            ->where('school_id', $department->faculty->school_id)
            ->where('name', 'like', $entryYear.'/%')
            ->first();
    }

    private function academicSessionByName(School $school, string $name): AcademicSession
    {
        return $school->academicSessions()
            ->where('name', $name)
            ->firstOrFail();
    }

    private function seedCourses(Faculty $faculty, Department $department, AcademicSession $academicSession): void
    {
        $courses = collect($this->courses())
            ->mapWithKeys(fn (array $course): array => [
                $course['code'] => Course::query()->updateOrCreate(
                    [
                        'school_id' => $faculty->school_id,
                        'code' => $course['code'],
                    ],
                    [
                        'title' => $course['title'],
                        'slug' => Str::slug($course['code'].' '.$course['title']),
                        'credit_units' => $course['credit_units'],
                    ],
                ),
            ]);

        foreach ($this->courseOfferings() as $offering) {
            $owner = $offering['owner'] === 'faculty' ? $faculty : $department;
            $academicYearSemester = $this->academicYearSemester($offering['level'], $offering['semester']);

            $owner->courseOfferings()->updateOrCreate(
                [
                    'course_id' => $courses->get($offering['code'])->id,
                    'academic_session_id' => $academicSession->id,
                    'academic_year_semester_id' => $academicYearSemester->id,
                ],
                ['is_mandatory' => $offering['is_mandatory'] ?? true],
            );
        }

        foreach ($this->coursePrerequisites() as $courseCode => $prerequisiteCode) {
            $courses->get($courseCode)->addPrerequisite($courses->get($prerequisiteCode));
        }
    }

    private function academicYearSemester(string $academicYearSlug, string $semesterSlug): AcademicYearSemester
    {
        return AcademicYearSemester::query()
            ->where('academic_year_id', AcademicYear::query()->where('slug', $academicYearSlug)->value('id'))
            ->where('semester_id', Semester::query()->where('slug', $semesterSlug)->value('id'))
            ->firstOrFail();
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
