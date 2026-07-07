<?php

namespace App\Http\Controllers;

use App\AccountType;
use App\Actions\CreateFacultyHeadTransferAction;
use App\Actions\CreateSchoolAdminTransferAction;
use App\Http\Requests\AssignFacultyHodRequest;
use App\Http\Requests\FacultyCourseOfferingRequest;
use App\Http\Requests\ImportFacultyHodsRequest;
use App\Http\Requests\ImportFacultyStaffRequest;
use App\Http\Requests\ImportHodStaffRequest;
use App\Http\Requests\ResultSheetImportRequest;
use App\Http\Requests\ResultSheetReviewRequest;
use App\Http\Requests\ReturnCourseRegistrationRequest;
use App\Http\Requests\SchoolAcademicSessionRequest;
use App\Http\Requests\SchoolDepartmentRequest;
use App\Http\Requests\SchoolFacultyHeadRequest;
use App\Http\Requests\SchoolFacultyRequest;
use App\Http\Requests\StaffCourseOfferingRequest;
use App\Http\Requests\StoreCourseRegistrationRequest;
use App\Http\Requests\StoreFacultyHeadTransferRequest;
use App\Http\Requests\StoreFacultyStaffRequest;
use App\Http\Requests\StoreSchoolAdminTransferRequest;
use App\Http\Requests\StoreStaffDueRequest;
use App\Http\Requests\StoreStaffPaymentAccountRequest;
use App\Http\Requests\UpdateFacultyHeadPasswordRequest;
use App\Http\Requests\UpdateHodPasswordRequest;
use App\Http\Requests\UpdateProfilePhotoRequest;
use App\Http\Requests\UpdateSchoolAdminPasswordRequest;
use App\Http\Requests\UpdateStaffPasswordRequest;
use App\Http\Requests\UpdateStudentProfileRequest;
use App\Models\AcademicSession;
use App\Models\AcademicYear;
use App\Models\AcademicYearSemester;
use App\Models\ApprovalRequest;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CourseRegistrationItem;
use App\Models\Department;
use App\Models\Due;
use App\Models\DuePayment;
use App\Models\EmailDelivery;
use App\Models\Faculty;
use App\Models\FacultyHeadTransfer;
use App\Models\ImportBatch;
use App\Models\PaymentAccount;
use App\Models\PaystackWebhookEvent;
use App\Models\ResultSheet;
use App\Models\ResultSheetCourse;
use App\Models\School;
use App\Models\SchoolAdminTransfer;
use App\Models\Semester;
use App\Models\StaffProfile;
use App\Models\StudentDueAssignment;
use App\Models\StudentProfile;
use App\Models\StudentSemesterResult;
use App\Models\User;
use App\Services\CloudflareEmailService;
use App\Services\CourseRegistrationPlanner;
use App\Services\DuePaymentService;
use App\Services\GpaCalculator;
use App\Services\PaystackService;
use App\Services\ResultSheetImporter;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    private const array ROLE_SECTIONS = [
        'school_admin' => ['home', 'academic-sessions', 'structure', 'faculty-heads', 'settings'],
        'faculty_head' => ['home', 'departments', 'staff', 'sessions-courses', 'settings'],
        'staff' => ['home', 'students', 'courses', 'course-registration', 'result-management', 'dues', 'payment-settings', 'settings'],
        'student' => ['home', 'results', 'dues', 'course-registration', 'profile-settings'],
        'hod' => ['home', 'staff', 'students', 'courses', 'student-results', 'result-approvals', 'settings'],
    ];

    private const array SECTION_TITLES = [
        'home' => 'Home',
        'students' => 'Students',
        'courses' => 'Course Offerings',
        'result-management' => 'Result Management',
        'dues' => 'Dues',
        'payment-settings' => 'Paystack Settings',
        'profile-settings' => 'Profile Settings',
        'result-approvals' => 'Result Approvals',
        'settings' => 'Settings',
        'results' => 'Results',
        'student-results' => 'Student Results',
        'course-registration' => 'Course Registration',
        'departments' => 'Departments',
        'sessions-courses' => 'Sessions & Courses',
        'staff' => 'Staff',
        'academic-sessions' => 'Academic Sessions',
        'structure' => 'School Structure',
        'faculty-heads' => 'Faculty Heads',
    ];

    private const array RESULT_COURSES = [
        '100-level' => [
            'harmattan' => [
                ['GST 101', 'Use of English I', 2],
                ['MTH 101', 'Engineering Mathematics I', 3],
                ['PHY 101', 'General Physics I', 3],
                ['CHM 101', 'General Chemistry I', 3],
                ['CPE 101', 'Introduction to Computer Engineering', 2],
            ],
            'rain' => [
                ['GST 102', 'Use of English II', 2],
                ['MTH 102', 'Engineering Mathematics II', 3],
                ['PHY 102', 'General Physics II', 3],
                ['CHM 102', 'General Chemistry II', 3],
                ['CPE 102', 'Computer Workshop Practice', 2],
            ],
        ],
        '200-level' => [
            'harmattan' => [
                ['MTH 207', 'Engineering Mathematics III', 3],
                ['CPE 201', 'Digital Logic Design', 3],
                ['CPE 203', 'Circuit Theory', 3],
                ['EEE 201', 'Electrical Machines I', 2],
                ['CSC 201', 'Computer Programming I', 3],
            ],
            'rain' => [
                ['MTH 208', 'Engineering Mathematics IV', 3],
                ['CPE 202', 'Computer Architecture Fundamentals', 3],
                ['CPE 204', 'Signals and Systems', 3],
                ['EEE 202', 'Electronic Devices', 3],
                ['CSC 202', 'Data Structures', 3],
            ],
        ],
        '300-level' => [
            'harmattan' => [
                ['CPE 301', 'Microprocessor Systems', 3],
                ['CPE 303', 'Operating Systems', 3],
                ['CPE 305', 'Data Structures and Algorithms', 3],
                ['EEE 301', 'Electronic Circuit Design', 3],
                ['MTH 305', 'Numerical Methods', 2],
            ],
            'rain' => [
                ['CPE 302', 'Embedded Systems Design', 3],
                ['CPE 304', 'Database Systems', 3],
                ['CPE 306', 'Software Engineering', 3],
                ['EEE 302', 'Control Systems Engineering', 3],
                ['CPE 308', 'Computer Networks', 2],
            ],
        ],
        '400-level' => [
            'harmattan' => [
                ['CPE 401', 'Embedded Systems Design', 3],
                ['CPE 403', 'Computer Networks', 3],
                ['CPE 405', 'Control Systems Engineering', 2],
                ['CPE 407', 'Digital Signal Processing', 3],
                ['CPE 409', 'Engineering Economics', 2],
            ],
            'rain' => [
                ['CPE 402', 'Computer Communications', 3],
                ['CPE 404', 'Database Systems', 3],
                ['CPE 406', 'Software Project Management', 2],
                ['CPE 408', 'Artificial Intelligence', 3],
                ['EEE 402', 'Power Electronics', 2],
            ],
        ],
        '500-level' => [
            'harmattan' => [
                ['CPE 501', 'Digital Systems Project', 3],
                ['CPE 503', 'VLSI Design', 3],
                ['CPE 505', 'Engineering Management', 2],
                ['CPE 507', 'Advanced Computer Architecture', 3],
                ['CPE 509', 'Final Year Project Seminar', 2],
            ],
            'rain' => [
                ['CPE 502', 'Final Year Project', 6],
                ['CPE 504', 'Distributed Systems', 3],
                ['CPE 506', 'Network Security', 3],
                ['CPE 508', 'Robotics and Automation', 2],
            ],
        ],
    ];

    private const array RESULT_SCORES = [
        '100-level' => [
            'harmattan' => [
                'GST 101' => 76,
                'MTH 101' => 68,
                'PHY 101' => 63,
                'CHM 101' => 59,
                'CPE 101' => 82,
            ],
            'rain' => [
                'GST 102' => 74,
                'MTH 102' => 62,
                'PHY 102' => 58,
                'CHM 102' => 55,
                'CPE 102' => 79,
            ],
        ],
        '200-level' => [
            'harmattan' => [
                'MTH 207' => 61,
                'CPE 201' => 73,
                'CPE 203' => 57,
                'EEE 201' => 48,
                'CSC 201' => 69,
            ],
            'rain' => [
                'MTH 208' => 64,
                'CPE 202' => 71,
                'CPE 204' => 53,
                'EEE 202' => 46,
                'CSC 202' => 67,
            ],
        ],
        '300-level' => [
            'harmattan' => [
                'CPE 301' => 74,
                'CPE 303' => 67,
                'CPE 305' => 58,
                'EEE 301' => 62,
                'MTH 305' => 71,
            ],
            'rain' => [
                'CPE 302' => 69,
                'CPE 304' => 38,
                'CPE 306' => 64,
                'EEE 302' => 52,
                'CPE 308' => 73,
            ],
        ],
    ];

    public function home(Request $request): InertiaResponse
    {
        return $this->renderDashboard($request, 'home');
    }

    public function section(Request $request, string $section): InertiaResponse
    {
        return $this->renderDashboard($request, $section);
    }

    public function level(Request $request, string $section, string $level): InertiaResponse
    {
        return $this->renderDashboard($request, $section, $level);
    }

    public function createFacultyHod(Request $request): InertiaResponse
    {
        $user = $this->authorizedFacultyHeadUser($request);
        $faculty = $this->facultyForFacultyHead($user);
        $selectedStaffProfileId = $request->integer('staff_profile_id') ?: null;
        $selectedDepartmentId = $request->integer('department_id') ?: null;

        return $this->renderDashboard($request, 'departments', null, [
            'facultyHeadHodForm' => $this->facultyHeadHodFormPayload($faculty, $selectedStaffProfileId, $selectedDepartmentId),
        ]);
    }

    public function createDepartmentHod(Request $request, Department $department): InertiaResponse
    {
        $user = $this->authorizedFacultyHeadUser($request);
        $faculty = $this->facultyForFacultyHead($user);

        abort_unless((int) $department->faculty_id === (int) $faculty->id, 404);

        return $this->renderDashboard($request, 'departments', null, [
            'facultyHeadHodForm' => $this->facultyHeadHodFormPayload(
                faculty: $faculty,
                selectedDepartmentId: (int) $department->id,
                scopedDepartment: $department,
            ),
        ]);
    }

    public function importFacultyHods(Request $request): InertiaResponse
    {
        $user = $this->authorizedFacultyHeadUser($request);
        $faculty = $this->facultyForFacultyHead($user);

        return $this->renderDashboard($request, 'departments', null, [
            'facultyHeadHodForm' => $this->facultyHeadHodFormPayload($faculty, mode: 'import'),
        ]);
    }

    public function storeFacultyHod(
        AssignFacultyHodRequest $request,
        CloudflareEmailService $cloudflareEmailService,
    ): RedirectResponse {
        $user = $this->authorizedFacultyHeadUser($request);
        $faculty = $this->facultyForFacultyHead($user);
        $validated = $request->validated();
        $department = $faculty->departments()
            ->whereKey($validated['department_id'])
            ->firstOrFail();
        $staffProfile = $this->facultyStaffProfile($faculty, (int) $validated['staff_profile_id']);

        $this->ensureStaffProfileCanBecomeHod($staffProfile, $department);
        abort_unless((int) $staffProfile->department_id === (int) $department->id, 404);

        $emailDelivery = DB::transaction(function () use ($department, $faculty, $staffProfile): ?EmailDelivery {
            $staffProfile->forceFill(['department_id' => $department->id])->save();

            $department->assignHeadOfDepartment($staffProfile->refresh());

            return $this->queueHodAccessCodeIfNeeded($faculty, $department, $staffProfile->user);
        });

        if ($emailDelivery instanceof EmailDelivery) {
            $cloudflareEmailService->sendAccessCode(
                $emailDelivery,
                (string) ($emailDelivery->metadata['access_code'] ?? ''),
            );
        }

        return to_route('dashboard.section', [
            'section' => $validated['redirect_section'] ?? 'departments',
        ]);
    }

    public function storeImportedFacultyHods(
        ImportFacultyHodsRequest $request,
        CloudflareEmailService $cloudflareEmailService,
    ): RedirectResponse {
        $user = $this->authorizedFacultyHeadUser($request);
        $faculty = $this->facultyForFacultyHead($user);
        $validated = $request->validated();
        $rows = collect($validated['rows']);
        $metadata = $this->importRequestMetadata($validated, $rows->count());
        $departments = $faculty->departments()->get();

        $result = DB::transaction(function () use ($departments, $faculty, $metadata, $rows, $user, $validated): array {
            $importBatch = ImportBatch::query()->firstOrCreate([
                'school_id' => $faculty->school_id,
                'uploaded_by_user_id' => $user->id,
                'type' => 'hod_import',
                'import_key' => $metadata['import_key'],
            ], [
                'scope_type' => Faculty::class,
                'scope_id' => $faculty->id,
                'status' => 'approved',
                'file_name' => $validated['file_name'] ?: 'HODs spreadsheet',
                'mappings' => [],
                'summary' => [
                    'submitted_rows' => $metadata['total_rows'],
                    'total_batches' => $metadata['total_batches'],
                    'processed_batches' => 0,
                    'assigned_hods' => 0,
                ],
                'submitted_at' => now(),
                'approved_at' => now(),
            ]);
            $deliveries = [];
            $processedBatches = (int) data_get($importBatch->summary, 'processed_batches', 0);

            foreach ($rows as $index => $row) {
                $rowNumber = $metadata['start_row'] + $index;

                if ($this->alreadyImportedRow($importBatch, $rowNumber)) {
                    continue;
                }

                $department = $this->facultyDepartmentFromImportValue($departments, (string) $row['department'], $index);
                $staffProfile = $this->staffProfileFromHodImportRow($faculty, $department, $row, $index);

                $this->ensureStaffProfileCanBecomeHod($staffProfile, $department);

                $staffProfile->forceFill(['department_id' => $department->id])->save();
                $department->assignHeadOfDepartment($staffProfile->refresh());

                $emailDelivery = $this->queueHodAccessCodeIfNeeded($faculty, $department, $staffProfile->user, $row['accessCode'] ?: null);

                if ($emailDelivery instanceof EmailDelivery) {
                    $deliveries[] = $emailDelivery;
                }

                $importBatch->rows()->updateOrCreate(
                    ['row_number' => $rowNumber],
                    [
                        'raw_data' => $row,
                        'normalized_data' => [
                            'department_id' => $department->id,
                            'staff_profile_id' => $staffProfile->id,
                            'email' => $staffProfile->user->email,
                        ],
                        'errors' => [],
                        'status' => 'imported',
                    ],
                );
            }

            $assignedCount = $importBatch->rows()->where('status', 'imported')->count();

            $importBatch->forceFill([
                'summary' => [
                    'submitted_rows' => $metadata['total_rows'],
                    'total_batches' => $metadata['total_batches'],
                    'processed_batches' => max($processedBatches, $metadata['batch_number']),
                    'assigned_hods' => $assignedCount,
                ],
            ])->save();

            return [
                'deliveries' => $deliveries,
                'imported_count' => $assignedCount,
            ];
        });

        foreach ($result['deliveries'] as $emailDelivery) {
            $cloudflareEmailService->sendAccessCode(
                $emailDelivery,
                (string) ($emailDelivery->metadata['access_code'] ?? ''),
            );
        }

        return $this->importRedirect(
            importRouteName: 'dashboard.hod-management.import',
            section: 'departments',
            metadata: $metadata,
            recordLabel: 'HOD assignment',
            importedCount: $result['imported_count'],
        );
    }

    public function createFacultyStaff(Request $request): InertiaResponse
    {
        $user = $this->authorizedFacultyHeadUser($request);
        $faculty = $this->facultyForFacultyHead($user);

        return $this->renderDashboard($request, 'staff', null, [
            'facultyHeadStaffForm' => $this->facultyHeadStaffFormPayload($faculty),
        ]);
    }

    public function storeFacultyStaff(
        StoreFacultyStaffRequest $request,
        CloudflareEmailService $cloudflareEmailService,
    ): RedirectResponse {
        $user = $this->authorizedFacultyHeadUser($request);
        $faculty = $this->facultyForFacultyHead($user);
        $validated = $request->validated();
        $department = $faculty->departments()
            ->whereKey($validated['department_id'])
            ->firstOrFail();

        $emailDelivery = DB::transaction(function () use ($department, $faculty, $validated): ?EmailDelivery {
            $staffProfile = $this->staffProfileFromFacultyStaffData($faculty, $department, [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'accessCode' => $validated['access_code'] ?? '',
            ]);

            return $this->queueStaffAccessCodeIfNeeded(
                $faculty,
                $department,
                $staffProfile->user,
                $validated['access_code'] ?? null,
            );
        });

        if ($emailDelivery instanceof EmailDelivery) {
            $cloudflareEmailService->sendAccessCode(
                $emailDelivery,
                (string) ($emailDelivery->metadata['access_code'] ?? ''),
            );
        }

        return to_route('dashboard.section', [
            'section' => 'staff',
        ]);
    }

    public function importFacultyStaff(Request $request): InertiaResponse
    {
        $user = $this->authorizedFacultyHeadUser($request);
        $faculty = $this->facultyForFacultyHead($user);

        return $this->renderDashboard($request, 'staff', null, [
            'facultyHeadStaffForm' => $this->facultyHeadStaffFormPayload($faculty, 'import'),
        ]);
    }

    public function storeImportedFacultyStaff(
        ImportFacultyStaffRequest $request,
        CloudflareEmailService $cloudflareEmailService,
    ): RedirectResponse {
        $user = $this->authorizedFacultyHeadUser($request);
        $faculty = $this->facultyForFacultyHead($user);
        $validated = $request->validated();
        $rows = collect($validated['rows']);
        $metadata = $this->importRequestMetadata($validated, $rows->count());
        $departments = $faculty->departments()->get();

        $result = DB::transaction(function () use ($departments, $faculty, $metadata, $rows, $user, $validated): array {
            $importBatch = ImportBatch::query()->firstOrCreate([
                'school_id' => $faculty->school_id,
                'uploaded_by_user_id' => $user->id,
                'type' => 'staff_import',
                'import_key' => $metadata['import_key'],
            ], [
                'scope_type' => Faculty::class,
                'scope_id' => $faculty->id,
                'status' => 'approved',
                'file_name' => $validated['file_name'] ?: 'Staffs spreadsheet',
                'mappings' => [],
                'summary' => [
                    'submitted_rows' => $metadata['total_rows'],
                    'total_batches' => $metadata['total_batches'],
                    'processed_batches' => 0,
                    'imported_staff' => 0,
                ],
                'submitted_at' => now(),
                'approved_at' => now(),
            ]);
            $deliveries = [];
            $processedBatches = (int) data_get($importBatch->summary, 'processed_batches', 0);

            foreach ($rows as $index => $row) {
                $rowNumber = $metadata['start_row'] + $index;

                if ($this->alreadyImportedRow($importBatch, $rowNumber)) {
                    continue;
                }

                $department = $this->facultyDepartmentFromImportValue($departments, (string) $row['department'], $index);
                $staffProfile = $this->staffProfileFromFacultyStaffData($faculty, $department, $row, $index);
                $emailDelivery = $this->queueStaffAccessCodeIfNeeded($faculty, $department, $staffProfile->user, $row['accessCode'] ?: null);

                if ($emailDelivery instanceof EmailDelivery) {
                    $deliveries[] = $emailDelivery;
                }

                $importBatch->rows()->updateOrCreate(
                    ['row_number' => $rowNumber],
                    [
                        'raw_data' => $row,
                        'normalized_data' => [
                            'department_id' => $department->id,
                            'staff_profile_id' => $staffProfile->id,
                            'email' => $staffProfile->user->email,
                        ],
                        'errors' => [],
                        'status' => 'imported',
                    ],
                );
            }

            $importedCount = $importBatch->rows()->where('status', 'imported')->count();

            $importBatch->forceFill([
                'summary' => [
                    'submitted_rows' => $metadata['total_rows'],
                    'total_batches' => $metadata['total_batches'],
                    'processed_batches' => max($processedBatches, $metadata['batch_number']),
                    'imported_staff' => $importedCount,
                ],
            ])->save();

            return [
                'deliveries' => $deliveries,
                'imported_count' => $importedCount,
            ];
        });

        foreach ($result['deliveries'] as $emailDelivery) {
            $cloudflareEmailService->sendAccessCode(
                $emailDelivery,
                (string) ($emailDelivery->metadata['access_code'] ?? ''),
            );
        }

        return $this->importRedirect(
            importRouteName: 'dashboard.staff.import',
            section: 'staff',
            metadata: $metadata,
            recordLabel: 'staff record',
            importedCount: $result['imported_count'],
        );
    }

    public function importHodStaff(Request $request): InertiaResponse
    {
        $user = $this->authorizedHodUser($request);
        $department = $this->departmentForHod($user);

        return $this->renderDashboard($request, 'staff', null, [
            'hodStaffForm' => $this->hodStaffFormPayload($department),
        ]);
    }

    public function storeImportedHodStaff(
        ImportHodStaffRequest $request,
        CloudflareEmailService $cloudflareEmailService,
    ): RedirectResponse {
        $user = $this->authorizedHodUser($request);
        $department = $this->departmentForHod($user);
        $department->loadMissing('faculty');
        $faculty = $department->faculty;

        abort_unless($faculty instanceof Faculty, 404);

        $validated = $request->validated();
        $rows = collect($validated['rows']);
        $metadata = $this->importRequestMetadata($validated, $rows->count());

        $result = DB::transaction(function () use ($department, $faculty, $metadata, $rows, $user, $validated): array {
            $importBatch = ImportBatch::query()->firstOrCreate([
                'school_id' => $faculty->school_id,
                'uploaded_by_user_id' => $user->id,
                'type' => 'hod_staff_import',
                'import_key' => $metadata['import_key'],
            ], [
                'scope_type' => Department::class,
                'scope_id' => $department->id,
                'status' => 'approved',
                'file_name' => $validated['file_name'] ?: 'Staffs spreadsheet',
                'mappings' => [],
                'summary' => [
                    'submitted_rows' => $metadata['total_rows'],
                    'total_batches' => $metadata['total_batches'],
                    'processed_batches' => 0,
                    'imported_staff' => 0,
                ],
                'submitted_at' => now(),
                'approved_at' => now(),
            ]);
            $deliveries = [];
            $processedBatches = (int) data_get($importBatch->summary, 'processed_batches', 0);

            foreach ($rows as $index => $row) {
                $rowNumber = $metadata['start_row'] + $index;

                if ($this->alreadyImportedRow($importBatch, $rowNumber)) {
                    continue;
                }

                $staffProfile = $this->staffProfileFromDepartmentStaffData($faculty, $department, $row, $index);
                $emailDelivery = $this->queueStaffAccessCodeIfNeeded($faculty, $department, $staffProfile->user, $row['accessCode'] ?: null);

                if ($emailDelivery instanceof EmailDelivery) {
                    $deliveries[] = $emailDelivery;
                }

                $importBatch->rows()->updateOrCreate(
                    ['row_number' => $rowNumber],
                    [
                        'raw_data' => $row,
                        'normalized_data' => [
                            'department_id' => $department->id,
                            'staff_profile_id' => $staffProfile->id,
                            'email' => $staffProfile->user->email,
                        ],
                        'errors' => [],
                        'status' => 'imported',
                    ],
                );
            }

            $importedCount = $importBatch->rows()->where('status', 'imported')->count();

            $importBatch->forceFill([
                'summary' => [
                    'submitted_rows' => $metadata['total_rows'],
                    'total_batches' => $metadata['total_batches'],
                    'processed_batches' => max($processedBatches, $metadata['batch_number']),
                    'imported_staff' => $importedCount,
                ],
            ])->save();

            return [
                'deliveries' => $deliveries,
                'imported_count' => $importedCount,
            ];
        });

        foreach ($result['deliveries'] as $emailDelivery) {
            $cloudflareEmailService->sendAccessCode(
                $emailDelivery,
                (string) ($emailDelivery->metadata['access_code'] ?? ''),
            );
        }

        return $this->importRedirect(
            importRouteName: 'dashboard.department-staff.import',
            section: 'staff',
            metadata: $metadata,
            recordLabel: 'staff record',
            importedCount: $result['imported_count'],
        );
    }

    public function createAcademicSession(Request $request): InertiaResponse
    {
        $user = $this->authorizedSchoolAdminUser($request);

        return $this->renderDashboard($request, 'academic-sessions', null, [
            'schoolAdminAcademicSessionForm' => $this->schoolAcademicSessionFormPayload($user->school),
        ]);
    }

    public function storeAcademicSession(SchoolAcademicSessionRequest $request): RedirectResponse
    {
        $user = $this->authorizedSchoolAdminUser($request);
        $validated = $request->validated();

        $academicSession = $user->school->academicSessions()->create($validated);

        return to_route('dashboard.section', [
            'section' => 'academic-sessions',
            'academic_session' => $academicSession->id,
        ])->with('success', 'Academic session saved.');
    }

    public function editAcademicSession(Request $request, AcademicSession $academicSession): InertiaResponse
    {
        $user = $this->authorizedSchoolAdminUser($request);

        $this->ensureAcademicSessionBelongsToSchool($user->school, $academicSession);

        return $this->renderDashboard($request, 'academic-sessions', null, [
            'schoolAdminAcademicSessionForm' => $this->schoolAcademicSessionFormPayload($user->school, $academicSession),
        ]);
    }

    public function updateAcademicSession(SchoolAcademicSessionRequest $request, AcademicSession $academicSession): RedirectResponse
    {
        $user = $this->authorizedSchoolAdminUser($request);

        $this->ensureAcademicSessionBelongsToSchool($user->school, $academicSession);

        $academicSession->forceFill($request->validated())->save();

        return to_route('dashboard.section', [
            'section' => 'academic-sessions',
            'academic_session' => $academicSession->id,
        ])->with('success', 'Academic session saved.');
    }

    public function createSchoolFaculty(Request $request): InertiaResponse
    {
        $user = $this->authorizedSchoolAdminUser($request);

        return $this->renderDashboard($request, 'structure', null, [
            'schoolAdminFacultyForm' => $this->schoolFacultyFormPayload($user->school),
        ]);
    }

    public function storeSchoolFaculty(SchoolFacultyRequest $request): RedirectResponse
    {
        $user = $this->authorizedSchoolAdminUser($request);
        $validated = $request->validated();

        $user->school->faculties()->create([
            'name' => $validated['name'],
            'abbreviation' => $validated['abbreviation'],
            'slug' => Str::slug($validated['name']),
        ]);

        return to_route('dashboard.section', [
            'section' => 'structure',
        ])->with('success', 'Faculty saved.');
    }

    public function editSchoolFaculty(Request $request, Faculty $faculty): InertiaResponse
    {
        $user = $this->authorizedSchoolAdminUser($request);

        $this->ensureFacultyBelongsToSchool($user->school, $faculty);

        return $this->renderDashboard($request, 'structure', null, [
            'schoolAdminFacultyForm' => $this->schoolFacultyFormPayload($user->school, $faculty),
        ]);
    }

    public function updateSchoolFaculty(SchoolFacultyRequest $request, Faculty $faculty): RedirectResponse
    {
        $user = $this->authorizedSchoolAdminUser($request);

        $this->ensureFacultyBelongsToSchool($user->school, $faculty);

        $validated = $request->validated();

        $faculty->forceFill([
            'name' => $validated['name'],
            'abbreviation' => $validated['abbreviation'],
            'slug' => Str::slug($validated['name']),
        ])->save();

        return to_route('dashboard.section', [
            'section' => 'structure',
        ])->with('success', 'Faculty saved.');
    }

    public function createSchoolDepartment(Request $request): InertiaResponse
    {
        $user = $this->authorizedSchoolAdminUser($request);

        return $this->renderDashboard($request, 'structure', null, [
            'schoolAdminDepartmentForm' => $this->schoolDepartmentFormPayload($user->school, selectedFacultyId: $request->integer('faculty_id') ?: null),
        ]);
    }

    public function storeSchoolDepartment(SchoolDepartmentRequest $request): RedirectResponse
    {
        $user = $this->authorizedSchoolAdminUser($request);
        $validated = $request->validated();
        $faculty = $user->school->faculties()->whereKey($validated['faculty_id'])->firstOrFail();

        $faculty->departments()->create([
            'name' => $validated['name'],
            'abbreviation' => $validated['abbreviation'],
            'slug' => Str::slug($validated['name']),
            'minimum_years_to_degree' => (int) $validated['minimum_years_to_degree'],
        ]);

        return to_route('dashboard.section', [
            'section' => 'structure',
        ])->with('success', 'Department saved.');
    }

    public function editSchoolDepartment(Request $request, Department $department): InertiaResponse
    {
        $user = $this->authorizedSchoolAdminUser($request);

        $this->ensureDepartmentBelongsToSchool($user->school, $department);

        return $this->renderDashboard($request, 'structure', null, [
            'schoolAdminDepartmentForm' => $this->schoolDepartmentFormPayload($user->school, $department),
        ]);
    }

    public function updateSchoolDepartment(SchoolDepartmentRequest $request, Department $department): RedirectResponse
    {
        $user = $this->authorizedSchoolAdminUser($request);

        $this->ensureDepartmentBelongsToSchool($user->school, $department);

        $validated = $request->validated();
        $faculty = $user->school->faculties()->whereKey($validated['faculty_id'])->firstOrFail();

        $department->forceFill([
            'faculty_id' => $faculty->id,
            'name' => $validated['name'],
            'abbreviation' => $validated['abbreviation'],
            'slug' => Str::slug($validated['name']),
            'minimum_years_to_degree' => (int) $validated['minimum_years_to_degree'],
        ])->save();

        return to_route('dashboard.section', [
            'section' => 'structure',
        ])->with('success', 'Department saved.');
    }

    public function createSchoolFacultyHead(Request $request): InertiaResponse
    {
        $user = $this->authorizedSchoolAdminUser($request);

        return $this->renderDashboard($request, 'faculty-heads', null, [
            'schoolAdminFacultyHeadForm' => $this->schoolFacultyHeadFormPayload(
                $user->school,
                selectedFacultyId: $request->integer('faculty_id') ?: null,
            ),
        ]);
    }

    public function storeSchoolFacultyHead(
        SchoolFacultyHeadRequest $request,
        CloudflareEmailService $cloudflareEmailService,
    ): RedirectResponse {
        $user = $this->authorizedSchoolAdminUser($request);
        $validated = $request->validated();
        $faculty = $user->school->faculties()->whereKey($validated['faculty_id'])->firstOrFail();
        $department = $faculty->departments()->whereKey($validated['department_id'])->firstOrFail();

        $emailDelivery = DB::transaction(function () use ($department, $faculty, $user, $validated): ?EmailDelivery {
            $facultyHeadUser = User::query()
                ->where('school_id', $user->school_id)
                ->where('email', $validated['email'])
                ->first();

            if (! $facultyHeadUser instanceof User) {
                $facultyHeadUser = User::query()->create([
                    'school_id' => $user->school_id,
                    'account_type' => AccountType::Staff,
                    'name' => $validated['name'],
                    'password' => Str::random(40),
                    'access_code' => $validated['access_code'] ?: $this->suggestAccessCode($faculty->abbreviation, 'HEAD'),
                    'access_code_used_at' => null,
                    'activated_at' => null,
                    'email' => $validated['email'],
                ]);
            } else {
                $facultyHeadUser->forceFill([
                    'name' => $validated['name'],
                ])->save();
            }

            $staffProfile = $facultyHeadUser->staffProfile()->updateOrCreate(
                ['user_id' => $facultyHeadUser->id],
                ['department_id' => $department->id],
            );

            $faculty->assignHead($staffProfile->refresh());

            return $this->queueFacultyHeadAccessCodeIfNeeded($faculty, $facultyHeadUser, $validated['access_code'] ?: null);
        });

        if ($emailDelivery instanceof EmailDelivery) {
            $cloudflareEmailService->sendAccessCode(
                $emailDelivery,
                (string) ($emailDelivery->metadata['access_code'] ?? ''),
            );
        }

        return to_route('dashboard.section', [
            'section' => 'faculty-heads',
        ])->with('success', 'Faculty head saved.');
    }

    public function updateSchoolAdminPassword(UpdateSchoolAdminPasswordRequest $request): RedirectResponse
    {
        $user = $this->authorizedSchoolAdminUser($request);
        $validated = $request->validated();

        $user->forceFill([
            'password' => $validated['password'],
        ])->save();

        return to_route('dashboard.section', [
            'section' => 'settings',
        ])->with('success', 'Password changed.');
    }

    public function storeSchoolAdminTransfer(
        StoreSchoolAdminTransferRequest $request,
        CreateSchoolAdminTransferAction $createSchoolAdminTransfer,
        CloudflareEmailService $cloudflareEmailService,
    ): RedirectResponse {
        $user = $this->authorizedSchoolAdminUser($request);
        $validated = $request->validated();
        $result = $createSchoolAdminTransfer->execute(
            $user,
            $validated['email'],
            $validated['name'] ?? null,
        );

        $cloudflareEmailService->sendOwnershipTransferInvitation(
            $result['emailDelivery'],
            (string) ($result['emailDelivery']->metadata['access_code'] ?? ''),
            (string) ($result['emailDelivery']->metadata['invite_url'] ?? ''),
        );

        return to_route('dashboard.section', [
            'section' => 'settings',
        ])->with('success', 'Ownership invite sent.');
    }

    public function destroySchoolAdminTransfer(Request $request, SchoolAdminTransfer $schoolAdminTransfer): RedirectResponse
    {
        $user = $this->authorizedSchoolAdminUser($request);

        abort_unless((int) $schoolAdminTransfer->school_id === (int) $user->school_id, 404);

        if ($schoolAdminTransfer->isPending()) {
            $schoolAdminTransfer->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ])->save();
        }

        return to_route('dashboard.section', [
            'section' => 'settings',
        ])->with('success', 'Ownership invite cancelled.');
    }

    public function updateFacultyHeadPassword(UpdateFacultyHeadPasswordRequest $request): RedirectResponse
    {
        $user = $this->authorizedFacultyHeadUser($request);
        $validated = $request->validated();

        $user->forceFill([
            'password' => $validated['password'],
        ])->save();

        return to_route('dashboard.section', [
            'section' => 'settings',
        ])->with('success', 'Password changed.');
    }

    public function updateHodPassword(UpdateHodPasswordRequest $request): RedirectResponse
    {
        $user = $this->authorizedHodUser($request);
        $validated = $request->validated();

        $user->forceFill([
            'password' => $validated['password'],
        ])->save();

        return to_route('dashboard.section', [
            'section' => 'settings',
        ])->with('success', 'Password changed.');
    }

    public function updateStaffPassword(UpdateStaffPasswordRequest $request): RedirectResponse
    {
        $user = $this->authorizedStaffUser($request);
        $validated = $request->validated();

        $user->forceFill([
            'password' => $validated['password'],
        ])->save();

        return to_route('dashboard.section', [
            'section' => 'settings',
        ])->with('success', 'Password changed.');
    }

    public function updateStudentProfile(UpdateStudentProfileRequest $request): RedirectResponse
    {
        $user = $this->authorizedStudentUser($request);
        $validated = $request->validated();

        $user->forceFill([
            'name' => Str::squish($validated['surname'].' '.$validated['other_names']),
        ])->save();

        return to_route('dashboard.section', [
            'section' => 'profile-settings',
        ])->with('success', 'Profile saved.');
    }

    public function storeStaffPaymentAccount(
        StoreStaffPaymentAccountRequest $request,
        PaystackService $paystack,
    ): RedirectResponse {
        $user = $this->authorizedStaffUser($request);
        [$staffProfile, $department] = $this->staffProfileAndDepartment($user);
        $department->loadMissing('faculty');
        $validated = $request->validated();
        $entryAcademicSession = null;

        if (! empty($validated['entry_academic_session_id'])) {
            $entryAcademicSession = AcademicSession::query()->findOrFail($validated['entry_academic_session_id']);

            abort_unless($this->staffCanManageStudentSet($staffProfile, $department, $entryAcademicSession), 404);
        }

        $bank = $this->paystackBankFromCode($paystack, $validated['bank_code']);

        try {
            $resolvedAccount = $paystack->resolveAccount($validated['account_number'], $validated['bank_code']);
            $subaccount = $paystack->createSubaccount(
                $validated['business_name'],
                $validated['bank_code'],
                $validated['account_number'],
                sprintf('%s payout account for %s', $validated['label'], $user->name),
            );
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'account_number' => 'Paystack could not confirm this account. Check the bank and account number, then try again.',
            ]);
        }

        $accountName = Arr::get($resolvedAccount, 'account_name');
        $subaccountCode = Arr::get($subaccount, 'subaccount_code');

        if (! is_string($accountName) || $accountName === '' || ! is_string($subaccountCode) || $subaccountCode === '') {
            throw ValidationException::withMessages([
                'account_number' => 'Paystack did not return enough account details. Check the account and try again.',
            ]);
        }

        DB::transaction(function () use ($bank, $department, $staffProfile, $entryAcademicSession, $validated, $resolvedAccount, $subaccount, $accountName, $subaccountCode): void {
            $existingAccounts = PaymentAccount::query()
                ->where('department_id', $department->id)
                ->where('staff_profile_id', $staffProfile->id)
                ->where('status', PaymentAccount::StatusActive);

            if ($entryAcademicSession instanceof AcademicSession) {
                $existingAccounts->where('entry_academic_session_id', $entryAcademicSession->id);
            } else {
                $existingAccounts->whereNull('entry_academic_session_id');
            }

            $existingAccounts->update([
                'status' => PaymentAccount::StatusDisabled,
                'disabled_at' => now(),
            ]);

            PaymentAccount::query()->create([
                'school_id' => $department->faculty?->school_id,
                'department_id' => $department->id,
                'staff_profile_id' => $staffProfile->id,
                'entry_academic_session_id' => $entryAcademicSession?->id,
                'provider' => PaymentAccount::ProviderPaystack,
                'label' => $validated['label'],
                'business_name' => $validated['business_name'],
                'bank_code' => $validated['bank_code'],
                'bank_name' => $bank['name'],
                'account_number' => $validated['account_number'],
                'account_number_last4' => Str::of($validated['account_number'])->substr(-4)->toString(),
                'account_name' => $accountName,
                'paystack_subaccount_code' => $subaccountCode,
                'status' => PaymentAccount::StatusActive,
                'verified_at' => now(),
                'activated_at' => now(),
                'provider_payload' => [
                    'resolved_account' => $resolvedAccount,
                    'subaccount' => $subaccount,
                ],
            ]);
        });

        return to_route('dashboard.section', ['section' => 'payment-settings'])
            ->with('success', 'Payout account connected. Student dues for the selected set can now settle into that account.');
    }

    public function createStaffPaymentAccount(Request $request): InertiaResponse
    {
        $user = $this->authorizedStaffUser($request);
        [$staffProfile, $department] = $this->staffProfileAndDepartment($user);

        return $this->renderDashboard($request, 'payment-settings', null, [
            'staffPaymentAccountForm' => $this->staffPaymentAccountFormPayload($staffProfile, $department),
        ]);
    }

    public function updateProfilePhoto(UpdateProfilePhotoRequest $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $disk = User::profilePhotoDisk();
        $previousPath = $user->profile_photo_path;
        $path = $request->file('profile_photo')->store('profile-photos', $disk);

        $user->forceFill([
            'profile_photo_path' => $path,
        ])->save();

        if (is_string($previousPath) && $previousPath !== '') {
            Storage::disk($disk)->delete($previousPath);
        }

        return back()->with('success', 'Profile photo updated.');
    }

    public function storeFacultyHeadTransfer(
        StoreFacultyHeadTransferRequest $request,
        CreateFacultyHeadTransferAction $createFacultyHeadTransfer,
        CloudflareEmailService $cloudflareEmailService,
    ): RedirectResponse {
        $user = $this->authorizedFacultyHeadUser($request);
        $validated = $request->validated();
        $result = $createFacultyHeadTransfer->execute(
            $user,
            $validated['email'],
            $validated['name'] ?? null,
        );

        $cloudflareEmailService->sendFacultyHeadTransferInvitation(
            $result['emailDelivery'],
            (string) ($result['emailDelivery']->metadata['access_code'] ?? ''),
            (string) ($result['emailDelivery']->metadata['invite_url'] ?? ''),
        );

        return to_route('dashboard.section', [
            'section' => 'settings',
        ])->with('success', 'Faculty head invite sent.');
    }

    public function destroyFacultyHeadTransfer(Request $request, FacultyHeadTransfer $facultyHeadTransfer): RedirectResponse
    {
        $user = $this->authorizedFacultyHeadUser($request);
        $faculty = $this->facultyForFacultyHead($user);

        abort_unless((int) $facultyHeadTransfer->faculty_id === (int) $faculty->id, 404);

        if ($facultyHeadTransfer->isPending()) {
            $facultyHeadTransfer->forceFill([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ])->save();

            $faculty->forceFill([
                'pending_head_staff_profile_id' => null,
                'head_transfer_requested_at' => null,
            ])->save();
        }

        return to_route('dashboard.section', [
            'section' => 'settings',
        ])->with('success', 'Faculty head invite cancelled.');
    }

    public function copyFacultyCourseOfferings(Request $request, AcademicSession $academicSession): RedirectResponse
    {
        $user = $this->authorizedFacultyHeadUser($request);
        $faculty = $this->facultyForFacultyHead($user);

        $this->ensureAcademicSessionBelongsToFaculty($faculty, $academicSession);
        $this->ensureAcademicSessionCanBeManaged($academicSession);

        $copyResult = $this->copyFacultyCourseOfferingsFromPreviousSession($faculty, $academicSession);

        return to_route('dashboard.section', [
            'section' => 'sessions-courses',
            'academic_session' => $academicSession->id,
        ])->with('success', "{$copyResult['copied']} faculty courses copied from {$copyResult['sourceSession']->name}.");
    }

    public function copyFacultyCourseSemesterOfferings(
        Request $request,
        AcademicSession $academicSession,
        string $level,
        string $semester,
    ): RedirectResponse {
        $levelModel = $this->resolveLevel($level);
        $semesterModel = $this->resolveSemester($semester);
        $user = $this->authorizedFacultyHeadUser($request);
        $faculty = $this->facultyForFacultyHead($user);

        $this->ensureAcademicSessionBelongsToFaculty($faculty, $academicSession);
        $this->ensureAcademicSessionCanBeManaged($academicSession);
        $this->ensureFacultyCourseLevel($faculty, $levelModel);

        $academicYearSemester = $this->resolveAcademicYearSemester($levelModel, $semesterModel);
        $copyResult = $this->copyFacultyCourseOfferingsFromPreviousSession(
            $faculty,
            $academicSession,
            $academicYearSemester,
        );

        return to_route('dashboard.section', [
            'section' => 'sessions-courses',
            'academic_session' => $academicSession->id,
        ])->with('success', "{$copyResult['copied']} faculty courses copied into {$levelModel->name} {$semesterModel->name} from {$copyResult['sourceSession']->name}.");
    }

    public function createFacultyCourseOffering(
        Request $request,
        AcademicSession $academicSession,
        string $level,
        string $semester,
    ): InertiaResponse {
        $levelModel = $this->resolveLevel($level);
        $semesterModel = $this->resolveSemester($semester);
        $user = $this->authorizedFacultyHeadUser($request);
        $faculty = $this->facultyForFacultyHead($user);

        $this->ensureAcademicSessionBelongsToFaculty($faculty, $academicSession);
        $this->ensureAcademicSessionCanBeManaged($academicSession);
        $this->ensureFacultyCourseLevel($faculty, $levelModel);

        return $this->renderDashboard($request, 'sessions-courses', null, [
            'facultyHeadCourseOfferingForm' => $this->facultyCourseOfferingFormPayload($faculty, $academicSession, $levelModel, $semesterModel),
        ]);
    }

    public function storeFacultyCourseOffering(
        FacultyCourseOfferingRequest $request,
        AcademicSession $academicSession,
        string $level,
        string $semester,
    ): RedirectResponse {
        $levelModel = $this->resolveLevel($level);
        $semesterModel = $this->resolveSemester($semester);
        $user = $this->authorizedFacultyHeadUser($request);
        $faculty = $this->facultyForFacultyHead($user);

        $this->ensureAcademicSessionBelongsToFaculty($faculty, $academicSession);
        $this->ensureAcademicSessionCanBeManaged($academicSession);
        $this->ensureFacultyCourseLevel($faculty, $levelModel);

        $validated = $request->validated();
        $academicYearSemester = $this->resolveAcademicYearSemester($levelModel, $semesterModel);
        $prerequisiteIds = $this->validatedFacultyPrerequisiteIds(
            $faculty,
            $academicSession,
            $levelModel,
            $semesterModel,
            $validated['prerequisite_course_ids'] ?? [],
        );
        $course = Course::query()->updateOrCreate(
            [
                'school_id' => $faculty->school_id,
                'code' => $validated['code'],
            ],
            [
                'title' => $validated['title'],
                'slug' => Str::slug($validated['code'].' '.$validated['title']),
                'credit_units' => (int) $validated['credit_units'],
            ],
        );

        $faculty->courseOfferings()->updateOrCreate(
            [
                'course_id' => $course->id,
                'academic_session_id' => $academicSession->id,
                'academic_year_semester_id' => $academicYearSemester->id,
            ],
            ['is_mandatory' => true],
        );

        $course->prerequisites()->sync($prerequisiteIds);

        return to_route('dashboard.section', [
            'section' => 'sessions-courses',
            'academic_session' => $academicSession->id,
        ])->with('success', 'Faculty course saved.');
    }

    public function editFacultyCourseOffering(
        Request $request,
        AcademicSession $academicSession,
        string $level,
        string $semester,
        CourseOffering $offering,
    ): InertiaResponse {
        $levelModel = $this->resolveLevel($level);
        $semesterModel = $this->resolveSemester($semester);
        $user = $this->authorizedFacultyHeadUser($request);
        $faculty = $this->facultyForFacultyHead($user);

        $this->ensureAcademicSessionBelongsToFaculty($faculty, $academicSession);
        $this->ensureAcademicSessionCanBeManaged($academicSession);
        $this->ensureFacultyCourseLevel($faculty, $levelModel);

        $offering = $this->editableFacultyCourseOffering($faculty, $academicSession, $offering, $levelModel, $semesterModel);

        return $this->renderDashboard($request, 'sessions-courses', null, [
            'facultyHeadCourseOfferingForm' => $this->facultyCourseOfferingFormPayload(
                $faculty,
                $academicSession,
                $levelModel,
                $semesterModel,
                $offering,
            ),
        ]);
    }

    public function updateFacultyCourseOffering(
        FacultyCourseOfferingRequest $request,
        AcademicSession $academicSession,
        string $level,
        string $semester,
        CourseOffering $offering,
    ): RedirectResponse {
        $levelModel = $this->resolveLevel($level);
        $semesterModel = $this->resolveSemester($semester);
        $user = $this->authorizedFacultyHeadUser($request);
        $faculty = $this->facultyForFacultyHead($user);

        $this->ensureAcademicSessionBelongsToFaculty($faculty, $academicSession);
        $this->ensureAcademicSessionCanBeManaged($academicSession);
        $this->ensureFacultyCourseLevel($faculty, $levelModel);

        $offering = $this->editableFacultyCourseOffering($faculty, $academicSession, $offering, $levelModel, $semesterModel);
        $validated = $request->validated();
        $academicYearSemester = $this->resolveAcademicYearSemester($levelModel, $semesterModel);
        $prerequisiteIds = $this->validatedFacultyPrerequisiteIds(
            $faculty,
            $academicSession,
            $levelModel,
            $semesterModel,
            $validated['prerequisite_course_ids'] ?? [],
        );
        $course = Course::query()->updateOrCreate(
            [
                'school_id' => $faculty->school_id,
                'code' => $validated['code'],
            ],
            [
                'title' => $validated['title'],
                'slug' => Str::slug($validated['code'].' '.$validated['title']),
                'credit_units' => (int) $validated['credit_units'],
            ],
        );

        $duplicateOfferingExists = CourseOffering::query()
            ->where('owner_type', Faculty::class)
            ->where('owner_id', $faculty->id)
            ->where('academic_session_id', $academicSession->id)
            ->where('academic_year_semester_id', $academicYearSemester->id)
            ->where('course_id', $course->id)
            ->whereKeyNot($offering->id)
            ->exists();

        if ($duplicateOfferingExists) {
            throw ValidationException::withMessages([
                'code' => 'This course is already listed for the selected level and semester.',
            ]);
        }

        $offering->forceFill([
            'course_id' => $course->id,
            'is_mandatory' => true,
        ])->save();

        $course->prerequisites()->sync($prerequisiteIds);

        return to_route('dashboard.section', [
            'section' => 'sessions-courses',
            'academic_session' => $academicSession->id,
        ])->with('success', 'Faculty course saved.');
    }

    public function deleteFacultyCourseOffering(
        Request $request,
        AcademicSession $academicSession,
        string $level,
        string $semester,
        CourseOffering $offering,
    ): RedirectResponse {
        $levelModel = $this->resolveLevel($level);
        $semesterModel = $this->resolveSemester($semester);
        $user = $this->authorizedFacultyHeadUser($request);
        $faculty = $this->facultyForFacultyHead($user);

        $this->ensureAcademicSessionBelongsToFaculty($faculty, $academicSession);
        $this->ensureAcademicSessionCanBeManaged($academicSession);
        $this->ensureFacultyCourseLevel($faculty, $levelModel);

        $offering = $this->editableFacultyCourseOffering($faculty, $academicSession, $offering, $levelModel, $semesterModel);

        $offering->delete();

        return to_route('dashboard.section', [
            'section' => 'sessions-courses',
            'academic_session' => $academicSession->id,
        ])->with('success', 'Faculty course removed.');
    }

    public function createCourseOffering(Request $request, string $level, string $semester): InertiaResponse
    {
        $levelModel = $this->resolveLevel($level);
        $semesterModel = $this->resolveSemester($semester);
        $user = $this->authorizedCourseScheduleUser($request, $levelModel);
        $department = $this->departmentFor($user);

        abort_unless($department instanceof Department, 404);
        $academicSession = $this->courseOfferingAcademicSession($request, $department);

        return $this->renderDashboard($request, 'courses', null, [
            'staffCourseOfferingForm' => $this->courseOfferingFormPayload($department, $levelModel, $semesterModel, academicSession: $academicSession),
        ]);
    }

    public function storeCourseOffering(
        StaffCourseOfferingRequest $request,
        string $level,
        string $semester,
    ): RedirectResponse {
        $levelModel = $this->resolveLevel($level);
        $semesterModel = $this->resolveSemester($semester);
        $user = $this->authorizedCourseScheduleUser($request, $levelModel);
        $department = $this->departmentFor($user);

        abort_unless($department instanceof Department, 404);
        $department->loadMissing('faculty');
        abort_unless($department->faculty instanceof Faculty, 404);

        $validated = $request->validated();
        $academicSession = $this->courseOfferingAcademicSession($request, $department);
        $academicYearSemester = $this->resolveAcademicYearSemester($levelModel, $semesterModel);
        $prerequisiteIds = $this->validatedPrerequisiteIds(
            $department,
            $levelModel,
            $semesterModel,
            $validated['prerequisite_course_ids'] ?? [],
            $academicSession,
        );
        $course = Course::query()->updateOrCreate(
            [
                'school_id' => $department->faculty->school_id,
                'code' => $validated['code'],
            ],
            [
                'title' => $validated['title'],
                'slug' => Str::slug($validated['code'].' '.$validated['title']),
                'credit_units' => (int) $validated['credit_units'],
            ],
        );

        $department->courseOfferings()->updateOrCreate(
            [
                'course_id' => $course->id,
                'academic_session_id' => $academicSession->id,
                'academic_year_semester_id' => $academicYearSemester->id,
            ],
            ['is_mandatory' => (bool) $validated['is_mandatory']],
        );

        $course->prerequisites()->sync($prerequisiteIds);

        if ($request->query('return_to') === 'results-import') {
            return to_route('dashboard.results.import')
                ->with('success', 'Course offering saved. You can import the results again.');
        }

        return to_route('dashboard.section', [
            'section' => 'courses',
            'level' => $levelModel->slug,
            'academic_session' => $academicSession->id,
            ...($request->integer('entry_session') > 0 ? ['entry_session' => $request->integer('entry_session')] : []),
        ]);
    }

    public function editCourseOffering(
        Request $request,
        string $level,
        string $semester,
        CourseOffering $offering,
    ): InertiaResponse {
        $levelModel = $this->resolveLevel($level);
        $semesterModel = $this->resolveSemester($semester);
        $user = $this->authorizedCourseScheduleUser($request, $levelModel);
        $department = $this->departmentFor($user);

        abort_unless($department instanceof Department, 404);
        $academicSession = $this->courseOfferingAcademicSession($request, $department);

        $offering = $this->editableCourseOffering($department, $academicSession, $offering, $levelModel, $semesterModel);

        return $this->renderDashboard($request, 'courses', null, [
            'staffCourseOfferingForm' => $this->courseOfferingFormPayload(
                $department,
                $levelModel,
                $semesterModel,
                $offering,
                $academicSession,
            ),
        ]);
    }

    public function updateCourseOffering(
        StaffCourseOfferingRequest $request,
        string $level,
        string $semester,
        CourseOffering $offering,
    ): RedirectResponse {
        $levelModel = $this->resolveLevel($level);
        $semesterModel = $this->resolveSemester($semester);
        $user = $this->authorizedCourseScheduleUser($request, $levelModel);
        $department = $this->departmentFor($user);

        abort_unless($department instanceof Department, 404);
        $department->loadMissing('faculty');
        abort_unless($department->faculty instanceof Faculty, 404);

        $academicSession = $this->courseOfferingAcademicSession($request, $department);
        $offering = $this->editableCourseOffering($department, $academicSession, $offering, $levelModel, $semesterModel);
        $validated = $request->validated();
        $academicYearSemester = $this->resolveAcademicYearSemester($levelModel, $semesterModel);
        $prerequisiteIds = $this->validatedPrerequisiteIds(
            $department,
            $levelModel,
            $semesterModel,
            $validated['prerequisite_course_ids'] ?? [],
            $academicSession,
        );
        $course = Course::query()->updateOrCreate(
            [
                'school_id' => $department->faculty->school_id,
                'code' => $validated['code'],
            ],
            [
                'title' => $validated['title'],
                'slug' => Str::slug($validated['code'].' '.$validated['title']),
                'credit_units' => (int) $validated['credit_units'],
            ],
        );

        $duplicateOfferingExists = CourseOffering::query()
            ->where('owner_type', Department::class)
            ->where('owner_id', $department->id)
            ->where('academic_session_id', $academicSession->id)
            ->where('academic_year_semester_id', $academicYearSemester->id)
            ->where('course_id', $course->id)
            ->whereKeyNot($offering->id)
            ->exists();

        if ($duplicateOfferingExists) {
            throw ValidationException::withMessages([
                'code' => 'This course is already on the schedule for the selected level and semester.',
            ]);
        }

        $offering->forceFill([
            'course_id' => $course->id,
            'is_mandatory' => (bool) $validated['is_mandatory'],
        ])->save();

        $course->prerequisites()->sync($prerequisiteIds);

        return to_route('dashboard.section', [
            'section' => 'courses',
            'level' => $levelModel->slug,
            'academic_session' => $academicSession->id,
            ...($request->integer('entry_session') > 0 ? ['entry_session' => $request->integer('entry_session')] : []),
        ]);
    }

    public function showStudentProfile(Request $request, string $registrationNumber): InertiaResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        [$studentProfile, $department] = $this->authorizedStudentRecordForUser($user, $registrationNumber);

        return $this->renderDashboard($request, 'students', null, [
            'studentRecord' => $this->studentRecordPayload($studentProfile, $department),
        ]);
    }

    public function downloadStudentStatement(Request $request, string $registrationNumber): StreamedResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        [$studentProfile, $department] = $this->authorizedStudentRecordForUser($user, $registrationNumber);
        $rows = $this->studentStatementRows($studentProfile, $department);
        $safeRegistrationNumber = preg_replace('/[^A-Za-z0-9_-]+/', '-', $studentProfile->registration_number) ?: 'student';

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, "statement-of-results-{$safeRegistrationNumber}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{0: StudentProfile, 1: Department}
     */
    private function authorizedStudentRecordForUser(User $user, string $registrationNumber): array
    {
        $user->loadMissing(['staffProfile.studentSetAdvisorAssignments.entryAcademicSession', 'staffProfile.department']);
        $studentProfile = $this->studentProfileForRegistrationNumber($user, $registrationNumber);
        $role = $this->dashboardRole($user);
        $staffProfile = $user->staffProfile;
        $department = $staffProfile?->department;

        abort_unless($department instanceof Department, 404);
        abort_unless((int) $studentProfile->department_id === (int) $department->id, 404);

        if ($role === 'staff') {
            abort_unless($staffProfile instanceof StaffProfile, 404);

            $assignedEntrySessionIds = $this->assignedEntrySessionIdsForStaff($staffProfile, $department);

            abort_unless($assignedEntrySessionIds->contains($studentProfile->entry_academic_session_id), 404);
        } else {
            abort_unless($role === 'hod', 404);
        }

        return [$studentProfile, $department];
    }

    public function showStaffProfile(Request $request, string $staffEmail): InertiaResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $role = $this->dashboardRole($user);
        $staffProfile = $this->staffProfileForEmail($user, $staffEmail);

        abort_unless($this->userCanViewStaffProfile($user, $role, $staffProfile), 404);

        return $this->renderDashboard($request, 'staff', null, [
            'staffRecord' => $this->staffRecordPayload($staffProfile),
        ]);
    }

    private function studentProfileForRegistrationNumber(User $user, string $registrationNumber): StudentProfile
    {
        return StudentProfile::query()
            ->with([
                'currentLevel',
                'entryAcademicSession',
                'department.faculty.school',
                'user',
            ])
            ->where('school_id', $user->school_id)
            ->where('registration_number', rawurldecode($registrationNumber))
            ->firstOrFail();
    }

    private function staffProfileForEmail(User $user, string $staffEmail): StaffProfile
    {
        $email = Str::of(rawurldecode($staffEmail))->trim()->lower()->toString();

        return StaffProfile::query()
            ->with([
                'department.faculty.school',
                'headedDepartment',
                'headedFaculty',
                'studentSetAdvisorAssignments.entryAcademicSession',
                'user',
            ])
            ->whereHas('user', fn ($query) => $query
                ->where('school_id', $user->school_id)
                ->where('account_type', AccountType::Staff)
                ->where('email', $email))
            ->firstOrFail();
    }

    private function userCanViewStaffProfile(User $user, string $role, StaffProfile $staffProfile): bool
    {
        $staffProfile->loadMissing(['department.faculty', 'user']);

        if ((int) $staffProfile->user?->school_id !== (int) $user->school_id) {
            return false;
        }

        if ($role === 'faculty_head') {
            $faculty = $this->facultyForFacultyHead($user);

            return (int) $staffProfile->department?->faculty_id === (int) $faculty->id;
        }

        if ($role === 'hod') {
            $department = $this->departmentForHod($user);

            return (int) $staffProfile->department_id === (int) $department->id;
        }

        return false;
    }

    private function renderDashboard(
        Request $request,
        string $section,
        ?string $level = null,
        array $dashboardOverrides = [],
    ): InertiaResponse {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $user->loadMissing([
            'school',
            'staffProfile.department.faculty.school',
            'staffProfile.headedFaculty',
            'studentProfile.currentLevel',
            'studentProfile.entryAcademicSession',
            'studentProfile.department.faculty.school',
        ]);

        $role = $this->dashboardRole($user);
        $levelModel = $level === null ? null : AcademicYear::query()->where('slug', $level)->first();

        abort_if($level !== null && $levelModel === null, 404);
        abort_unless($this->isAllowedRoute($user, $role, $section, $levelModel), 404);

        $dashboard = [
            'role' => $role,
            'roleLabel' => $this->roleLabel($role),
            'section' => $section,
            'sectionTitle' => $this->sectionTitle($section, $levelModel, $role),
            'levelSlug' => $levelModel?->slug,
            'level' => $levelModel?->name,
            'levels' => $this->levelsFor($user, $role),
            'courseScheduleLevels' => $this->courseScheduleLevelsFor($user, $role),
            'advisedSets' => $this->advisedSetsFor($user, $role),
            'department' => $this->departmentPayload($this->departmentFor($user)),
            'faculty' => $this->facultyFor($user, $role)?->only(['name', 'abbreviation']),
            'schoolAdmin' => $this->schoolAdminPayload($user, $role),
            'schoolAdminAcademicSessionForm' => null,
            'schoolAdminFacultyForm' => null,
            'schoolAdminDepartmentForm' => null,
            'schoolAdminFacultyHeadForm' => null,
            'facultyHead' => $this->facultyHeadPayload($user, $role),
            'facultyHeadHodForm' => null,
            'facultyHeadStaffForm' => null,
            'facultyHeadCourses' => $this->facultyHeadCoursesPayload($request, $user, $role, $section),
            'facultyHeadCourseOfferingForm' => null,
            'hodStaffForm' => null,
            'staffHome' => $this->staffHomePayload($user, $role, $section),
            'staffStudents' => $this->staffStudentsPayload($user, $role, $section),
            'staffCourseSchedules' => $this->staffCourseSchedulesPayload($request, $user, $role, $section),
            'staffCourseOfferingForm' => null,
            'staffCourseRegistration' => $this->staffCourseRegistrationPayload($request, $user, $role, $section),
            'staffResults' => $this->staffResultsPayload($user, $role, $section),
            'staffResultImportForm' => null,
            'staffDues' => $this->staffDuesPayload($user, $role, $section),
            'staffDueForm' => null,
            'staffDueDetail' => null,
            'staffPaymentSettings' => $this->staffPaymentSettingsPayload($user, $role, $section),
            'staffPaymentAccountForm' => null,
            'staffSettings' => $this->staffSettingsPayload($user, $role, $section),
            'resultSheetDetail' => null,
            'hod' => $this->hodPayload($request, $user, $role),
            'studentRecord' => null,
            'staffRecord' => null,
            'student' => $this->studentPayload($user, $role),
            'studentProfile' => $this->studentProfilePayload($user, $role, $section),
            'studentHome' => $this->studentHomePayload($user, $role, $section),
            'studentResults' => $this->studentResultsPayload($user, $role, $section, $levelModel),
            'studentDues' => $this->studentDuesPayload($user, $role, $section),
            'studentCourseRegistration' => $this->studentCourseRegistrationPayload($user, $role, $section),
        ];

        return Inertia::render('dashboard', [
            'dashboard' => array_merge($dashboard, $dashboardOverrides),
        ]);
    }

    private function dashboardRole(User $user): string
    {
        if ($user->account_type === AccountType::SchoolAdmin) {
            return 'school_admin';
        }

        if ($user->account_type === AccountType::Student) {
            return 'student';
        }

        $staffProfile = $user->staffProfile;

        if ($staffProfile?->headedFaculty !== null) {
            return 'faculty_head';
        }

        if (
            $staffProfile !== null
            && $staffProfile->department !== null
            && (int) $staffProfile->department->hod_staff_profile_id === (int) $staffProfile->getKey()
        ) {
            return 'hod';
        }

        return 'staff';
    }

    private function roleLabel(string $role): string
    {
        return match ($role) {
            'school_admin' => 'School Admin',
            'faculty_head' => 'Faculty Head',
            'hod' => 'Head of Department',
            'staff' => 'Staff',
            'student' => 'Student',
        };
    }

    private function isAllowedRoute(User $user, string $role, string $section, ?AcademicYear $level): bool
    {
        if (! in_array($section, self::ROLE_SECTIONS[$role], true)) {
            return false;
        }

        if ($level !== null) {
            $allowedLevels = $section === 'courses'
                ? $this->courseScheduleLevelsFor($user, $role)
                : $this->levelsFor($user, $role);

            if (! collect($allowedLevels)->contains('slug', $level->slug)) {
                return false;
            }

            return match ($section) {
                'courses' => $role === 'staff',
                'result-management' => $role === 'staff',
                'results' => $role === 'student',
                'dues' => $role === 'staff',
                default => false,
            };
        }

        return true;
    }

    private function sectionTitle(string $section, ?AcademicYear $level, string $role): string
    {
        $title = self::SECTION_TITLES[$section];

        if ($section === 'settings' && $role !== 'school_admin') {
            $title = 'Profile Settings';
        }

        if ($section === 'course-registration' && $role === 'staff') {
            $title = 'Course Registrations';
        }

        if ($level !== null) {
            return $level->name.' '.$title;
        }

        return $title;
    }

    private function departmentFor(User $user): ?Department
    {
        if ($user->account_type === AccountType::Student) {
            return $user->studentProfile?->department;
        }

        return $user->staffProfile?->department;
    }

    /**
     * @return array{name: string, abbreviation: string, minimumYearsToDegree: int}|null
     */
    private function departmentPayload(?Department $department): ?array
    {
        if ($department === null) {
            return null;
        }

        return [
            'name' => $department->name,
            'abbreviation' => $department->abbreviation,
            'minimumYearsToDegree' => (int) $department->minimum_years_to_degree,
        ];
    }

    private function facultyFor(User $user, string $role): ?Faculty
    {
        if ($role !== 'faculty_head') {
            return null;
        }

        return $user->staffProfile?->headedFaculty;
    }

    private function authorizedSchoolAdminUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $user->loadMissing('school');

        abort_unless($user->account_type === AccountType::SchoolAdmin, 404);
        abort_unless($user->school instanceof School, 404);

        return $user;
    }

    private function authorizedFacultyHeadUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $user->loadMissing('staffProfile.headedFaculty.school');

        abort_unless($this->dashboardRole($user) === 'faculty_head', 404);

        return $user;
    }

    private function facultyForFacultyHead(User $user): Faculty
    {
        $faculty = $user->staffProfile?->headedFaculty;

        abort_unless($faculty instanceof Faculty, 404);

        return $faculty;
    }

    private function authorizedHodUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $user->loadMissing('staffProfile.headedDepartment.faculty.school');

        abort_unless($this->dashboardRole($user) === 'hod', 404);

        return $user;
    }

    private function departmentForHod(User $user): Department
    {
        $department = $user->staffProfile?->headedDepartment;

        abort_unless($department instanceof Department, 404);

        return $department;
    }

    private function authorizedStaffUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $user->loadMissing('staffProfile.department.faculty.school');

        abort_unless($this->dashboardRole($user) === 'staff', 404);

        return $user;
    }

    private function authorizedStudentUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $user->loadMissing('studentProfile.department.faculty.school', 'studentProfile.currentLevel', 'studentProfile.entryAcademicSession');

        abort_unless($this->dashboardRole($user) === 'student', 404);

        return $user;
    }

    /**
     * @return array{0: StaffProfile, 1: Department}
     */
    private function staffProfileAndDepartment(User $user): array
    {
        $staffProfile = $user->staffProfile;
        $department = $staffProfile?->department;

        abort_unless($staffProfile instanceof StaffProfile && $department instanceof Department, 404);

        return [$staffProfile, $department];
    }

    public function createResultSheetImport(Request $request): InertiaResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);
        abort_unless($this->dashboardRole($user) === 'staff', 404);

        $user->loadMissing('staffProfile.department.faculty.school');
        $staffProfile = $user->staffProfile;
        $department = $staffProfile?->department;

        abort_unless($staffProfile instanceof StaffProfile && $department instanceof Department, 404);

        return $this->renderDashboard($request, 'result-management', null, [
            'staffResultImportForm' => $this->staffResultImportFormPayload($department, $staffProfile),
        ]);
    }

    public function storeResultSheetImport(
        ResultSheetImportRequest $request,
        ResultSheetImporter $importer,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user instanceof User, 403);
        abort_unless($this->dashboardRole($user) === 'staff', 404);

        $user->loadMissing('staffProfile.department.faculty');
        $staffProfile = $user->staffProfile;
        $department = $staffProfile?->department;

        abort_unless($staffProfile instanceof StaffProfile && $department instanceof Department, 404);

        $sheet = $importer->import($department, $staffProfile, $request->validated());
        $message = $sheet->status === ResultSheet::StatusSubmitted
            ? 'Results submitted to the HOD for review.'
            : 'Result draft saved.';

        return redirect()
            ->route('dashboard.section', ['section' => 'result-management'])
            ->with('success', $message);
    }

    public function showResultSheet(Request $request, ResultSheet $resultSheet): InertiaResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $role = $this->dashboardRole($user);

        abort_unless(in_array($role, ['staff', 'hod'], true), 404);
        abort_unless($this->userCanViewResultSheet($user, $role, $resultSheet), 404);

        $section = $role === 'hod' ? 'student-results' : 'result-management';

        return $this->renderDashboard($request, $section, null, [
            'resultSheetDetail' => $this->resultSheetDetailPayload($resultSheet, $role, 'view'),
        ]);
    }

    public function showResultSheetApproval(Request $request, ResultSheet $resultSheet): InertiaResponse
    {
        $user = $this->authorizedHodUser($request);

        abort_unless($this->userCanViewResultSheet($user, 'hod', $resultSheet), 404);
        abort_unless($resultSheet->status === ResultSheet::StatusSubmitted, 404);

        return $this->renderDashboard($request, 'result-approvals', null, [
            'resultSheetDetail' => $this->resultSheetDetailPayload($resultSheet, 'hod', 'review'),
        ]);
    }

    public function reviewResultSheet(ResultSheetReviewRequest $request, ResultSheet $resultSheet): RedirectResponse
    {
        $user = $this->authorizedHodUser($request);
        $department = $this->departmentForHod($user);

        abort_unless((int) $resultSheet->department_id === (int) $department->id, 404);
        abort_unless($resultSheet->status === ResultSheet::StatusSubmitted, 404);

        if ($request->validated('action') === 'approve') {
            $resultSheet->forceFill([
                'status' => ResultSheet::StatusApproved,
                'reviewed_by_user_id' => $user->id,
                'approved_at' => now(),
                'returned_at' => null,
                'correction_note' => null,
            ])->save();

            return redirect()
                ->route('dashboard.section', ['section' => 'result-approvals'])
                ->with('success', 'Results approved. Students can now see them.');
        }

        $correctionNote = $this->resultSheetCorrectionNote(
            $request->string('correction_reason')->toString(),
            $request->string('correction_note')->trim()->toString(),
        );

        $resultSheet->forceFill([
            'status' => ResultSheet::StatusReturned,
            'reviewed_by_user_id' => $user->id,
            'returned_at' => now(),
            'approved_at' => null,
            'correction_note' => $correctionNote,
        ])->save();

        return redirect()
            ->route('dashboard.section', ['section' => 'result-approvals'])
            ->with('success', 'Results returned to the course adviser for correction.');
    }

    public function storeCourseRegistration(StoreCourseRegistrationRequest $request, CourseRegistrationPlanner $planner): RedirectResponse
    {
        $user = $this->authorizedStudentUser($request);
        $studentProfile = $user->studentProfile;

        abort_unless($studentProfile instanceof StudentProfile, 404);

        [$department, $academicSession, $academicYearSemester] = $this->currentCourseRegistrationContext($studentProfile);
        $validated = $request->validated();
        $selectedItems = collect($planner->selectedOptionsFromPayload(
            $planner->plan($studentProfile, $department, $academicSession, $academicYearSemester),
            $validated['items'],
            $validated['action'] === 'submit',
        ));
        $selectedUnits = (int) $selectedItems->sum('units');

        if ($selectedUnits > 24) {
            throw ValidationException::withMessages([
                'items' => 'Selected courses cannot be more than 24 units for this semester.',
            ]);
        }

        $registration = CourseRegistration::query()->firstOrNew([
            'student_profile_id' => $studentProfile->id,
            'academic_session_id' => $academicSession->id,
            'academic_year_semester_id' => $academicYearSemester->id,
        ]);

        if ($registration->exists && ! $registration->isEditableByStudent()) {
            throw ValidationException::withMessages([
                'items' => 'This registration has already been sent for review. Wait for your course adviser before changing it.',
            ]);
        }

        DB::transaction(function () use ($academicSession, $academicYearSemester, $department, $registration, $selectedItems, $studentProfile, $validated): void {
            $isSubmit = $validated['action'] === 'submit';

            $registration->forceFill([
                'school_id' => $studentProfile->school_id,
                'department_id' => $department->id,
                'student_profile_id' => $studentProfile->id,
                'academic_session_id' => $academicSession->id,
                'academic_year_semester_id' => $academicYearSemester->id,
                'reviewer_staff_profile_id' => $this->courseAdvisorForStudent($studentProfile, $department)?->id,
                'status' => $isSubmit ? CourseRegistration::StatusSubmitted : CourseRegistration::StatusDraft,
                'feedback_note' => $isSubmit ? null : $registration->feedback_note,
                'submitted_at' => $isSubmit ? now() : null,
                'reviewed_at' => null,
            ])->save();

            $registration->items()->delete();

            foreach ($selectedItems as $item) {
                $registration->items()->create([
                    'course_offering_id' => $item['courseOfferingId'],
                    'source' => $item['source'],
                    'category' => $item['category'],
                    'course_code' => $item['code'],
                    'course_title' => $item['title'],
                    'credit_units' => $item['units'],
                ]);
            }
        });

        return redirect()
            ->route('dashboard.section', ['section' => 'course-registration'])
            ->with('success', $validated['action'] === 'submit'
                ? 'Course registration sent to your course adviser.'
                : 'Course registration saved.');
    }

    public function showCourseRegistrationReview(Request $request, CourseRegistration $courseRegistration): InertiaResponse
    {
        $user = $this->authorizedStaffUser($request);
        [$staffProfile, $department] = $this->staffProfileAndDepartment($user);

        abort_unless($this->staffCanReviewCourseRegistration($staffProfile, $department, $courseRegistration), 404);

        return $this->renderDashboard($request, 'course-registration', null, [
            'staffCourseRegistration' => $this->staffCourseRegistrationPayload($request, $user, 'staff', 'course-registration', $courseRegistration),
        ]);
    }

    public function approveCourseRegistration(Request $request, CourseRegistration $courseRegistration): RedirectResponse
    {
        $user = $this->authorizedStaffUser($request);
        [$staffProfile, $department] = $this->staffProfileAndDepartment($user);

        abort_unless($this->staffCanReviewCourseRegistration($staffProfile, $department, $courseRegistration), 404);
        abort_unless($courseRegistration->status === CourseRegistration::StatusSubmitted, 404);

        $courseRegistration->forceFill([
            'status' => CourseRegistration::StatusApproved,
            'reviewer_staff_profile_id' => $staffProfile->id,
            'feedback_note' => null,
            'reviewed_at' => now(),
        ])->save();

        return redirect()
            ->route('dashboard.course-registration.show', ['courseRegistration' => $courseRegistration])
            ->with('success', 'Course registration approved.');
    }

    public function returnCourseRegistration(ReturnCourseRegistrationRequest $request, CourseRegistration $courseRegistration): RedirectResponse
    {
        $user = $this->authorizedStaffUser($request);
        [$staffProfile, $department] = $this->staffProfileAndDepartment($user);

        abort_unless($this->staffCanReviewCourseRegistration($staffProfile, $department, $courseRegistration), 404);
        abort_unless($courseRegistration->status === CourseRegistration::StatusSubmitted, 404);

        $courseRegistration->forceFill([
            'status' => CourseRegistration::StatusReturned,
            'reviewer_staff_profile_id' => $staffProfile->id,
            'feedback_note' => $request->string('feedback_note')->trim()->toString() ?: 'Review the highlighted courses and submit again.',
            'reviewed_at' => now(),
        ])->save();

        return redirect()
            ->route('dashboard.course-registration.show', ['courseRegistration' => $courseRegistration])
            ->with('success', 'Course registration returned to the student.');
    }

    public function createStaffDue(Request $request): InertiaResponse
    {
        $user = $this->authorizedStaffUser($request);
        [$staffProfile, $department] = $this->staffProfileAndDepartment($user);

        return $this->renderDashboard($request, 'dues', null, [
            'staffDueForm' => $this->staffDueFormPayload($staffProfile, $department),
        ]);
    }

    public function storeStaffDue(StoreStaffDueRequest $request): RedirectResponse
    {
        $user = $this->authorizedStaffUser($request);
        [$staffProfile, $department] = $this->staffProfileAndDepartment($user);
        $validated = $request->validated();
        $entryAcademicSession = AcademicSession::query()->findOrFail($validated['entry_academic_session_id']);

        abort_unless($this->staffCanManageStudentSet($staffProfile, $department, $entryAcademicSession), 404);

        $academicSession = $this->requiredCurrentAcademicSession($department);
        $isPublishing = $validated['action'] === 'publish';
        $paymentAccount = $this->activePaymentAccountForStaffSet($staffProfile, $department, $entryAcademicSession);

        if ($isPublishing && ! $paymentAccount instanceof PaymentAccount) {
            throw ValidationException::withMessages([
                'payment_account' => 'Set up a Paystack payout account before publishing dues for this student set.',
            ]);
        }

        $due = DB::transaction(function () use ($department, $staffProfile, $entryAcademicSession, $academicSession, $paymentAccount, $validated, $isPublishing): Due {
            $due = Due::query()->create([
                'school_id' => $department->faculty?->school_id,
                'department_id' => $department->id,
                'entry_academic_session_id' => $entryAcademicSession->id,
                'academic_session_id' => $academicSession->id,
                'created_by_staff_profile_id' => $staffProfile->id,
                'payment_account_id' => $paymentAccount?->id,
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'amount_kobo' => (int) $validated['amount_naira'] * 100,
                'currency' => 'NGN',
                'due_on' => $validated['due_on'] ?? null,
                'status' => $isPublishing ? Due::StatusPublished : Due::StatusDraft,
                'published_at' => $isPublishing ? now() : null,
            ]);

            if ($isPublishing) {
                $this->publishDueAssignments($due);
            }

            return $due;
        });

        return to_route('dashboard.dues.show', ['due' => $due])
            ->with('success', $isPublishing ? 'Due published for the selected set.' : 'Due draft saved.');
    }

    public function showStaffDue(Request $request, Due $due): InertiaResponse
    {
        $user = $this->authorizedStaffUser($request);
        [$staffProfile, $department] = $this->staffProfileAndDepartment($user);

        $this->ensureStaffCanManageDue($staffProfile, $department, $due);

        return $this->renderDashboard($request, 'dues', null, [
            'staffDueDetail' => $this->staffDueDetailPayload($due),
        ]);
    }

    public function startDuePayment(
        Request $request,
        StudentDueAssignment $studentDueAssignment,
        DuePaymentService $duePaymentService,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user instanceof User, 403);
        abort_unless($this->dashboardRole($user) === 'student', 404);
        abort_unless((int) $studentDueAssignment->student_profile_id === (int) $user->studentProfile?->id, 404);

        $studentDueAssignment->loadMissing('due.paymentAccount');
        abort_unless($studentDueAssignment->due?->status === Due::StatusPublished, 404);
        abort_unless($studentDueAssignment->due?->paymentAccount?->isActive(), 404);

        $payment = $duePaymentService->startPayment(
            $studentDueAssignment,
            fn (DuePayment $payment): string => route('dashboard.dues.payments.callback', [
                'duePayment' => $payment,
            ]),
        );

        if ($payment->status === DuePayment::StatusSuccess) {
            return to_route('dashboard.section', ['section' => 'dues'])
                ->with('success', 'This due has already been paid.');
        }

        return redirect()->away((string) $payment->authorization_url);
    }

    public function handleDuePaymentCallback(
        Request $request,
        DuePayment $duePayment,
        DuePaymentService $duePaymentService,
    ): RedirectResponse {
        $user = $request->user();

        abort_unless($user instanceof User, 403);
        abort_unless($this->dashboardRole($user) === 'student', 404);
        abort_unless((int) $duePayment->student_profile_id === (int) $user->studentProfile?->id, 404);

        $reference = $request->string('reference')->toString();

        abort_unless($reference === '' || $reference === $duePayment->reference, 404);

        try {
            $payment = $duePaymentService->verifyPayment($duePayment);
        } catch (\Throwable) {
            return to_route('dashboard.section', ['section' => 'dues'])
                ->with('error', 'Payment could not be confirmed yet. If money left your account, check again shortly.');
        }

        if ($payment->status !== DuePayment::StatusSuccess) {
            return to_route('dashboard.section', ['section' => 'dues'])
                ->with('error', 'Payment was not completed.');
        }

        return to_route('dashboard.section', ['section' => 'dues'])
            ->with('success', 'Payment received. Your receipt is ready.');
    }

    public function downloadDuePaymentReceipt(Request $request, DuePayment $duePayment): StreamedResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);
        abort_unless($this->userCanViewDuePayment($user, $duePayment), 404);
        abort_unless($duePayment->status === DuePayment::StatusSuccess, 404);

        $duePayment->loadMissing(['assignment.due', 'studentProfile.user']);
        $fileName = 'receipt-'.$duePayment->reference.'.txt';
        $lines = $this->duePaymentReceiptLines($duePayment);

        return response()->streamDownload(function () use ($lines): void {
            echo implode(PHP_EOL, $lines);
        }, $fileName, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function handlePaystackWebhook(
        Request $request,
        PaystackService $paystack,
        DuePaymentService $duePaymentService,
    ): Response {
        $rawPayload = $request->getContent();
        $signature = $request->header('x-paystack-signature');

        if (! $paystack->validSignature($rawPayload, $signature)) {
            return response('', 401);
        }

        $payload = json_decode($rawPayload, true);

        if (! is_array($payload)) {
            return response()->noContent();
        }

        $eventName = (string) Arr::get($payload, 'event', '');
        $reference = Arr::get($payload, 'data.reference');
        $webhookEvent = PaystackWebhookEvent::query()->firstOrCreate(
            ['payload_hash' => hash('sha256', $rawPayload)],
            [
                'event' => $eventName,
                'reference' => is_string($reference) ? $reference : null,
                'signature' => $signature,
                'payload' => $payload,
            ],
        );

        if ($webhookEvent->processed_at !== null) {
            return response()->noContent();
        }

        if (in_array($eventName, ['charge.success', 'transaction.successful'], true) && is_string($reference)) {
            $payment = DuePayment::query()->where('reference', $reference)->first();

            if ($payment instanceof DuePayment) {
                try {
                    $duePaymentService->verifyPayment($payment);
                } catch (\Throwable) {
                    return response()->noContent();
                }
            }
        }

        $webhookEvent->forceFill(['processed_at' => now()])->save();

        return response()->noContent();
    }

    private function schoolAdminPayload(User $user, string $role): ?array
    {
        if ($role !== 'school_admin') {
            return null;
        }

        $school = $user->school;

        abort_unless($school instanceof School, 404);

        $faculties = Faculty::query()
            ->with([
                'headStaffProfile.user',
                'headStaffProfile.department',
                'departments.hodStaffProfile.user',
            ])
            ->withCount('departments')
            ->where('school_id', $school->id)
            ->orderBy('name')
            ->get();
        $departments = Department::query()
            ->whereHas('faculty', fn ($query) => $query->where('school_id', $school->id))
            ->get();
        $academicSessions = $this->academicSessionsForSchool($school);
        $currentSession = $this->currentAcademicSessionForSchool($school);
        $nextSession = $school->academicSessions()
            ->whereDate('starts_on', '>', today())
            ->orderBy('starts_on')
            ->first();
        $pendingFacultyHeadAccessCodes = User::query()
            ->with('staffProfile.headedFaculty')
            ->where('school_id', $school->id)
            ->where('account_type', AccountType::Staff)
            ->whereNull('activated_at')
            ->whereNotNull('access_code')
            ->whereHas('staffProfile.headedFaculty')
            ->orderBy('name')
            ->get();
        $pendingOwnershipTransfer = SchoolAdminTransfer::query()
            ->where('school_id', $school->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        return [
            'school' => $school->only(['name', 'abbreviation', 'slug']),
            'metrics' => [
                'faculties' => $faculties->count(),
                'departments' => $departments->count(),
                'academicSessions' => $academicSessions->count(),
                'facultyHeadCoverage' => $faculties->whereNotNull('head_staff_profile_id')->count().'/'.$faculties->count(),
            ],
            'currentAcademicSession' => $currentSession === null ? null : $this->academicSessionPayload($currentSession),
            'nextAcademicSession' => $nextSession === null ? null : $this->academicSessionPayload($nextSession),
            'academicSessions' => $academicSessions
                ->map(fn (AcademicSession $academicSession): array => $this->academicSessionPayload($academicSession))
                ->values()
                ->all(),
            'faculties' => $faculties
                ->map(fn (Faculty $faculty): array => $this->schoolAdminFacultyPayload($faculty))
                ->values()
                ->all(),
            'pendingAccessCodes' => $pendingFacultyHeadAccessCodes
                ->map(fn (User $staffUser): array => $this->pendingAccessCodePayload($staffUser))
                ->values()
                ->all(),
            'settings' => [
                'admin' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->profilePhotoUrl(),
                ],
                'pendingOwnershipTransfer' => $pendingOwnershipTransfer === null
                    ? null
                    : $this->schoolAdminTransferPayload($pendingOwnershipTransfer),
            ],
        ];
    }

    private function schoolAdminTransferPayload(SchoolAdminTransfer $transfer): array
    {
        return [
            'id' => (int) $transfer->id,
            'recipientEmail' => $transfer->recipient_email,
            'recipientName' => $transfer->recipient_name,
            'accessCode' => $transfer->access_code,
            'expiresAt' => $transfer->expires_at->toDayDateTimeString(),
        ];
    }

    private function schoolAdminFacultyPayload(Faculty $faculty): array
    {
        $headProfile = $faculty->headStaffProfile;

        return [
            'id' => (int) $faculty->id,
            'name' => $faculty->name,
            'abbreviation' => $faculty->abbreviation,
            'slug' => $faculty->slug,
            'departmentsCount' => (int) ($faculty->departments_count ?? $faculty->departments->count()),
            'head' => $headProfile?->user === null ? null : [
                'name' => $headProfile->user->name,
                'email' => $headProfile->user->email,
                'avatar' => $headProfile->user->profilePhotoUrl(),
                'department' => $headProfile->department?->only(['name', 'abbreviation']),
                'accessCode' => $headProfile->user->access_code,
                'activatedAt' => $headProfile->user->activated_at?->toDateString(),
            ],
            'departments' => $faculty->departments
                ->sortBy('name')
                ->map(fn (Department $department): array => [
                    'id' => (int) $department->id,
                    'name' => $department->name,
                    'abbreviation' => $department->abbreviation,
                    'minimumYearsToDegree' => (int) $department->minimum_years_to_degree,
                    'hod' => $department->hodStaffProfile?->user === null ? null : [
                        'name' => $department->hodStaffProfile->user->name,
                        'email' => $department->hodStaffProfile->user->email,
                        'avatar' => $department->hodStaffProfile->user->profilePhotoUrl(),
                    ],
                ])
                ->values()
                ->all(),
        ];
    }

    private function schoolAcademicSessionFormPayload(School $school, ?AcademicSession $academicSession = null): array
    {
        if ($academicSession instanceof AcademicSession) {
            $this->ensureAcademicSessionBelongsToSchool($school, $academicSession);
        }

        return [
            'mode' => $academicSession === null ? 'create' : 'edit',
            'academicSession' => $academicSession === null ? null : $this->academicSessionPayload($academicSession),
        ];
    }

    private function schoolFacultyFormPayload(School $school, ?Faculty $faculty = null): array
    {
        if ($faculty instanceof Faculty) {
            $this->ensureFacultyBelongsToSchool($school, $faculty);
        }

        return [
            'mode' => $faculty === null ? 'create' : 'edit',
            'faculty' => $faculty === null ? null : [
                'id' => (int) $faculty->id,
                'name' => $faculty->name,
                'abbreviation' => $faculty->abbreviation,
            ],
        ];
    }

    private function schoolDepartmentFormPayload(
        School $school,
        ?Department $department = null,
        ?int $selectedFacultyId = null,
    ): array {
        if ($department instanceof Department) {
            $this->ensureDepartmentBelongsToSchool($school, $department);
        }

        return [
            'mode' => $department === null ? 'create' : 'edit',
            'selectedFacultyId' => $selectedFacultyId,
            'department' => $department === null ? null : [
                'id' => (int) $department->id,
                'facultyId' => (int) $department->faculty_id,
                'name' => $department->name,
                'abbreviation' => $department->abbreviation,
                'minimumYearsToDegree' => (int) $department->minimum_years_to_degree,
            ],
            'faculties' => $this->schoolFacultyOptions($school),
        ];
    }

    private function schoolFacultyHeadFormPayload(School $school, ?int $selectedFacultyId = null): array
    {
        $faculties = Faculty::query()
            ->with('departments')
            ->where('school_id', $school->id)
            ->orderBy('name')
            ->get();
        $selectedFaculty = $selectedFacultyId === null
            ? $faculties->first()
            : $faculties->firstWhere('id', $selectedFacultyId);
        $schoolAbbreviation = $school->abbreviation;
        $facultyAbbreviation = $selectedFaculty instanceof Faculty ? $selectedFaculty->abbreviation : 'FAC';

        return [
            'faculties' => $faculties
                ->map(fn (Faculty $faculty): array => [
                    'id' => (int) $faculty->id,
                    'name' => $faculty->name,
                    'abbreviation' => $faculty->abbreviation,
                    'departments' => $faculty->departments
                        ->sortBy('name')
                        ->map(fn (Department $department): array => [
                            'id' => (int) $department->id,
                            'name' => $department->name,
                            'abbreviation' => $department->abbreviation,
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
            'selectedFacultyId' => $selectedFaculty instanceof Faculty ? (int) $selectedFaculty->id : null,
            'suggestedAccessCode' => $this->suggestAccessCode($schoolAbbreviation, $facultyAbbreviation.'-HEAD'),
        ];
    }

    private function schoolFacultyOptions(School $school): array
    {
        return Faculty::query()
            ->where('school_id', $school->id)
            ->orderBy('name')
            ->get()
            ->map(fn (Faculty $faculty): array => [
                'id' => (int) $faculty->id,
                'name' => $faculty->name,
                'abbreviation' => $faculty->abbreviation,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     metrics: array<string, string|int>,
     *     departments: array<int, array{id: int, name: string, abbreviation: string, status: string, staffCount: int, studentCount: int, hod: array{name: string, email: string|null, avatar: string|null, accessCode: string|null, activatedAt: string|null}|null}>,
     *     pendingAccessCodes: array<int, array{id: int, name: string, email: string|null, avatar: string|null, accessCode: string|null, department: string|null, role: string, roleContext: string|null, status: string}>,
     *     staff: array<int, array{id: int, staffProfileId: int, name: string, email: string|null, avatar: string|null, department: array{name: string, abbreviation: string}|null, faculty: array{name: string, abbreviation: string}|null, role: string, status: string, accessCode: string|null, canAssignHod: bool, assignmentBlockReason: string|null}>,
     *     pendingTasks: array<int, array{id: string, title: string, detail: string, status: string}>
     * }|null
     */
    private function facultyHeadPayload(User $user, string $role): ?array
    {
        if ($role !== 'faculty_head') {
            return null;
        }

        $faculty = $this->facultyForFacultyHead($user);
        $departments = Department::query()
            ->with('hodStaffProfile.user')
            ->withCount(['staffProfiles', 'studentProfiles'])
            ->where('faculty_id', $faculty->id)
            ->orderBy('name')
            ->get();
        $pendingAccessCodeUsers = $this->pendingFacultyStaffAccessCodes($faculty);
        $staff = $this->facultyHeadStaffPayload($faculty);
        $facultyStudents = StudentProfile::query()
            ->with(['currentLevel', 'entryAcademicSession', 'user', 'department'])
            ->whereIn('department_id', $departments->pluck('id'))
            ->get()
            ->sortBy(fn (StudentProfile $studentProfile): string => sprintf(
                '%02d-%s',
                $studentProfile->current_academic_year_id,
                $studentProfile->user->name,
            ))
            ->values();
        $levels = AcademicYear::query()
            ->where('id', '<=', max(1, (int) $departments->max('minimum_years_to_degree')))
            ->orderBy('id')
            ->get();
        $currentAcademicSession = $this->currentAcademicSessionForFaculty($faculty);
        $departmentsWithoutHod = $departments->whereNull('hod_staff_profile_id')->count();
        $pendingFacultyHeadTransfer = FacultyHeadTransfer::query()
            ->where('faculty_id', $faculty->id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        return [
            'metrics' => [
                'departments' => $departments->count(),
                'staff' => (int) $departments->sum('staff_profiles_count'),
                'activeStaff' => collect($staff)->where('status', 'Active')->count(),
                'pendingSignups' => $pendingAccessCodeUsers->count(),
                'students' => (int) $departments->sum('student_profiles_count'),
                'departmentsWithoutHod' => $departmentsWithoutHod,
                'hodCoverage' => $departments->whereNotNull('hod_staff_profile_id')->count().'/'.$departments->count(),
            ],
            'departments' => $departments
                ->map(fn (Department $department): array => $this->facultyDepartmentPayload($department))
                ->values()
                ->all(),
            'pendingAccessCodes' => $pendingAccessCodeUsers
                ->map(fn (User $staffUser): array => $this->pendingAccessCodePayload($staffUser))
                ->values()
                ->all(),
            'staff' => $staff,
            'students' => $facultyStudents
                ->map(function (StudentProfile $studentProfile): array {
                    $department = $studentProfile->department;

                    return [
                        ...$this->staffStudentPayload($studentProfile, $department),
                        'department' => $this->departmentPayload($department),
                    ];
                })
                ->values()
                ->all(),
            'levels' => $levels
                ->map(fn (AcademicYear $level): array => $this->levelPayload($level))
                ->values()
                ->all(),
            'pendingTasks' => $this->facultyPendingTasks($faculty, $departments, $pendingAccessCodeUsers),
            'courseSetupSession' => $currentAcademicSession === null ? null : $this->academicSessionPayload($currentAcademicSession),
            'courseSetup' => $this->facultyCourseSetupSummary($faculty, $currentAcademicSession),
            'settings' => [
                'faculty' => [
                    'name' => $faculty->name,
                    'abbreviation' => $faculty->abbreviation,
                ],
                'head' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->profilePhotoUrl(),
                ],
                'pendingOwnershipTransfer' => $pendingFacultyHeadTransfer === null
                    ? null
                    : $this->facultyHeadTransferPayload($pendingFacultyHeadTransfer),
            ],
        ];
    }

    private function facultyHeadTransferPayload(FacultyHeadTransfer $transfer): array
    {
        return [
            'id' => (int) $transfer->id,
            'recipientEmail' => $transfer->recipient_email,
            'recipientName' => $transfer->recipient_name,
            'accessCode' => $transfer->access_code,
            'expiresAt' => $transfer->expires_at->toDayDateTimeString(),
        ];
    }

    /**
     * @return array{
     *     academicSession: array{id: int, name: string, startsOn: string, endsOn: string, status: string}|null,
     *     academicSessions: array<int, array{id: int, name: string, startsOn: string, endsOn: string, status: string}>,
     *     copySourceSession: array{id: int, name: string, startsOn: string, endsOn: string, status: string}|null,
     *     canManageSelectedSession: bool,
     *     metrics: array{departments: int, courses: int, compulsoryUnits: int, session: string},
     *     levels: array<int, array{id: int, title: string, slug: string}>,
     *     semesters: array<int, array{
     *         id: string,
     *         level: array{id: int, title: string, slug: string},
     *         semester: array{id: int, name: string, slug: string},
     *         totalUnits: int,
     *         courseCount: int,
     *         offerings: array<int, array{id: int, courseId: int, code: string, title: string, units: int, departmentsCount: int, prerequisites: array<int, array{id: int, code: string, title: string}>}>
     *     }>
     * }|null
     */
    private function facultyHeadCoursesPayload(Request $request, User $user, string $role, string $section): ?array
    {
        if ($role !== 'faculty_head' || $section !== 'sessions-courses') {
            return null;
        }

        $faculty = $this->facultyForFacultyHead($user);
        $academicSessions = $faculty->school->academicSessions()
            ->orderByDesc('starts_on')
            ->get();
        $selectedAcademicSession = $this->selectedFacultyAcademicSession($request, $faculty, $academicSessions);
        $levels = $this->facultyCourseLevelModels($faculty);
        $semesters = Semester::query()
            ->whereIn('slug', ['harmattan', 'rain'])
            ->orderBy('sort_order')
            ->get();
        $allOfferings = $selectedAcademicSession === null
            ? collect()
            : CourseOffering::query()
                ->with('course')
                ->where('owner_type', Faculty::class)
                ->where('owner_id', $faculty->id)
                ->where('academic_session_id', $selectedAcademicSession->id)
                ->get();
        $copySourceSession = $selectedAcademicSession === null
            ? null
            : $this->previousAcademicSessionFor($faculty, $selectedAcademicSession);

        return [
            'academicSession' => $selectedAcademicSession === null ? null : $this->academicSessionPayload($selectedAcademicSession),
            'academicSessions' => $academicSessions
                ->map(fn (AcademicSession $academicSession): array => $this->academicSessionPayload($academicSession))
                ->values()
                ->all(),
            'copySourceSession' => $copySourceSession === null ? null : $this->academicSessionPayload($copySourceSession),
            'canManageSelectedSession' => $selectedAcademicSession !== null,
            'metrics' => [
                'departments' => $faculty->departments()->count(),
                'courses' => $allOfferings->count(),
                'compulsoryUnits' => (int) $allOfferings->sum(fn (CourseOffering $offering): int => (int) $offering->course->credit_units),
                'session' => $selectedAcademicSession?->name ?? 'No academic session',
            ],
            'levels' => $levels
                ->map(fn (AcademicYear $level): array => $this->levelPayload($level))
                ->values()
                ->all(),
            'semesters' => $levels
                ->flatMap(fn (AcademicYear $level): array => $semesters
                    ->map(fn (Semester $semester): array => $this->facultyCourseSemesterPayload($faculty, $selectedAcademicSession, $level, $semester))
                    ->all())
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     level: array{id: int, title: string, slug: string},
     *     semester: array{id: int, name: string, slug: string},
     *     totalUnits: int,
     *     courseCount: int,
     *     offerings: array<int, array{id: int, courseId: int, code: string, title: string, units: int, departmentsCount: int, prerequisites: array<int, array{id: int, code: string, title: string}>}>
     * }
     */
    private function facultyCourseSemesterPayload(Faculty $faculty, ?AcademicSession $academicSession, AcademicYear $level, Semester $semester): array
    {
        $offerings = $this->courseOfferingsForFaculty($faculty, $academicSession, $level, $semester);

        return [
            'id' => $level->slug.'-'.$semester->slug,
            'level' => $this->levelPayload($level),
            'semester' => $this->semesterPayload($semester),
            'totalUnits' => (int) $offerings->sum(fn (CourseOffering $offering): int => (int) $offering->course->credit_units),
            'courseCount' => $offerings->count(),
            'offerings' => $offerings
                ->map(fn (CourseOffering $offering): array => $this->facultyCourseOfferingPayload($faculty, $offering))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return Collection<int, CourseOffering>
     */
    private function courseOfferingsForFaculty(Faculty $faculty, ?AcademicSession $academicSession, AcademicYear $level, Semester $semester): Collection
    {
        $academicYearSemester = $this->resolveAcademicYearSemester($level, $semester);

        if ($academicSession === null) {
            return collect();
        }

        return CourseOffering::query()
            ->with(['course.prerequisites'])
            ->where('owner_type', Faculty::class)
            ->where('owner_id', $faculty->id)
            ->where('academic_session_id', $academicSession->id)
            ->where('academic_year_semester_id', $academicYearSemester->id)
            ->get()
            ->sortBy(fn (CourseOffering $offering): string => $offering->course->code)
            ->values();
    }

    /**
     * @return array{id: int, courseId: int, code: string, title: string, units: int, departmentsCount: int, prerequisites: array<int, array{id: int, code: string, title: string}>}
     */
    private function facultyCourseOfferingPayload(Faculty $faculty, CourseOffering $offering): array
    {
        return [
            'id' => (int) $offering->id,
            'courseId' => (int) $offering->course_id,
            'code' => $offering->course->code,
            'title' => $offering->course->title,
            'units' => (int) $offering->course->credit_units,
            'departmentsCount' => $faculty->departments()->count(),
            'prerequisites' => $this->coursePrerequisitePayload($offering->course),
        ];
    }

    /**
     * @return array<int, array{id: string, label: string, courseCount: int, totalUnits: int, status: string}>
     */
    private function facultyCourseSetupSummary(Faculty $faculty, ?AcademicSession $academicSession): array
    {
        if (! $academicSession instanceof AcademicSession) {
            return [];
        }

        $semesters = Semester::query()
            ->whereIn('slug', ['harmattan', 'rain'])
            ->orderBy('sort_order')
            ->get();

        return $this->facultyCourseLevelModels($faculty)
            ->flatMap(fn (AcademicYear $level): array => $semesters
                ->map(function (Semester $semester) use ($academicSession, $faculty, $level): array {
                    $offerings = $this->courseOfferingsForFaculty($faculty, $academicSession, $level, $semester);

                    return [
                        'id' => $level->slug.'-'.$semester->slug,
                        'label' => $level->name.' '.$semester->name,
                        'courseCount' => $offerings->count(),
                        'totalUnits' => (int) $offerings->sum(fn (CourseOffering $offering): int => (int) $offering->course->credit_units),
                        'status' => $offerings->isEmpty() ? 'Not started' : 'Started',
                    ];
                })
                ->all())
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, staffProfileId: int, name: string, email: string|null, avatar: string|null, department: array{id: int, name: string, abbreviation: string}|null, faculty: array{name: string, abbreviation: string}|null, role: string, roleContext: string|null, status: string, accessCode: string|null, canAssignHod: bool, assignmentBlockReason: string|null}>
     */
    private function facultyHeadStaffPayload(Faculty $faculty, ?Department $department = null): array
    {
        $query = User::query()
            ->with([
                'staffProfile.department.faculty',
                'staffProfile.headedDepartment',
                'staffProfile.headedFaculty',
                'staffProfile.studentSetAdvisorAssignments.entryAcademicSession',
            ])
            ->where('school_id', $faculty->school_id)
            ->where('account_type', AccountType::Staff)
            ->whereHas('staffProfile.department', fn ($query) => $query->where('faculty_id', $faculty->id));

        if ($department instanceof Department) {
            $query->whereHas('staffProfile', fn ($query) => $query->where('department_id', $department->id));
        }

        return $query
            ->orderBy('name')
            ->get()
            ->map(fn (User $staffUser): array => $this->facultyHeadStaffMemberPayload($staffUser))
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, staffProfileId: int, name: string, email: string|null, avatar: string|null, department: array{id: int, name: string, abbreviation: string}|null, faculty: array{name: string, abbreviation: string}|null, role: string, roleContext: string|null, status: string, accessCode: string|null, canAssignHod: bool, assignmentBlockReason: string|null}
     */
    private function facultyHeadStaffMemberPayload(User $staffUser): array
    {
        $staffProfile = $staffUser->staffProfile;
        $department = $staffProfile?->department;
        $faculty = $department?->faculty;
        $role = $this->staffRolePayload($staffProfile, $department);
        $assignmentBlockReason = $this->hodAssignmentBlockReason($staffProfile);

        return [
            'id' => (int) $staffUser->id,
            'staffProfileId' => (int) $staffProfile?->id,
            'name' => $staffUser->name,
            'email' => $staffUser->email,
            'avatar' => $staffUser->profilePhotoUrl(),
            'department' => $department === null ? null : [
                'id' => (int) $department->id,
                'name' => $department->name,
                'abbreviation' => $department->abbreviation,
            ],
            'faculty' => $faculty === null ? null : [
                'name' => $faculty->name,
                'abbreviation' => $faculty->abbreviation,
            ],
            'role' => $role['role'],
            'roleContext' => $role['roleContext'],
            'status' => $staffUser->activated_at === null ? 'Pending signup' : 'Active',
            'accessCode' => $staffUser->access_code,
            'profileUrl' => $this->staffProfileUrl($staffUser),
            'canAssignHod' => $assignmentBlockReason === null,
            'assignmentBlockReason' => $assignmentBlockReason,
        ];
    }

    private function staffProfileUrl(?User $user): ?string
    {
        if (! $user instanceof User || $user->email === null) {
            return null;
        }

        return route('dashboard.staff.show', [
            'staffEmail' => $user->email,
        ]);
    }

    private function hodAssignmentBlockReason(?StaffProfile $staffProfile): ?string
    {
        if (! $staffProfile instanceof StaffProfile) {
            return 'No staff profile';
        }

        $staffProfile->loadMissing(['headedDepartment', 'headedFaculty']);

        if ($staffProfile->headedFaculty !== null) {
            return 'Already Faculty Head';
        }

        if ($staffProfile->headedDepartment !== null) {
            return 'Already HOD of '.$staffProfile->headedDepartment->abbreviation;
        }

        return null;
    }

    /**
     * @return EloquentCollection<int, User>
     */
    private function pendingFacultyStaffAccessCodes(Faculty $faculty): EloquentCollection
    {
        return User::query()
            ->with([
                'staffProfile.department',
                'staffProfile.headedDepartment',
                'staffProfile.headedFaculty',
                'staffProfile.studentSetAdvisorAssignments.entryAcademicSession',
            ])
            ->where('school_id', $faculty->school_id)
            ->where('account_type', AccountType::Staff)
            ->whereNotNull('access_code')
            ->whereNull('access_code_used_at')
            ->whereNull('activated_at')
            ->whereHas('staffProfile.department', fn ($query) => $query->where('faculty_id', $faculty->id))
            ->orderBy('name')
            ->get();
    }

    /**
     * @return array{id: int, name: string, abbreviation: string, status: string, staffCount: int, studentCount: int, hod: array{name: string, email: string|null, avatar: string|null, accessCode: string|null, activatedAt: string|null}|null}
     */
    private function facultyDepartmentPayload(Department $department): array
    {
        $hodUser = $department->hodStaffProfile?->user;

        return [
            'id' => (int) $department->id,
            'name' => $department->name,
            'abbreviation' => $department->abbreviation,
            'status' => $this->departmentHodStatus($hodUser),
            'staffCount' => (int) ($department->staff_profiles_count ?? 0),
            'studentCount' => (int) ($department->student_profiles_count ?? 0),
            'hod' => $hodUser === null ? null : [
                'name' => $hodUser->name,
                'email' => $hodUser->email,
                'avatar' => $hodUser->profilePhotoUrl(),
                'accessCode' => $hodUser->access_code,
                'activatedAt' => $this->displayDateTime($hodUser->activated_at),
            ],
        ];
    }

    private function departmentHodStatus(?User $hodUser): string
    {
        if ($hodUser === null) {
            return 'Vacant';
        }

        if ($hodUser->activated_at === null) {
            return 'Pending activation';
        }

        return 'Active';
    }

    /**
     * @return array{id: int, name: string, email: string|null, avatar: string|null, accessCode: string|null, department: string|null, role: string, roleContext: string|null, status: string}
     */
    private function pendingAccessCodePayload(User $staffUser): array
    {
        $staffProfile = $staffUser->staffProfile;
        $staffProfile?->loadMissing([
            'department',
            'headedDepartment',
            'headedFaculty',
            'studentSetAdvisorAssignments.entryAcademicSession',
        ]);
        $department = $staffProfile?->department;
        $role = $this->staffRolePayload($staffProfile, $department);

        return [
            'id' => (int) $staffUser->id,
            'name' => $staffUser->name,
            'email' => $staffUser->email,
            'avatar' => $staffUser->profilePhotoUrl(),
            'accessCode' => $staffUser->access_code,
            'department' => $department?->abbreviation,
            'role' => $role['role'],
            'roleContext' => $role['roleContext'],
            'status' => 'Pending activation',
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{import_key: string, batch_number: int, total_batches: int, start_row: int, total_rows: int, batch_size: int}
     */
    private function importRequestMetadata(array $validated, int $rowCount): array
    {
        $batchNumber = max(1, (int) ($validated['batch_number'] ?? 1));

        return [
            'import_key' => trim((string) ($validated['import_key'] ?? '')) ?: (string) Str::uuid(),
            'batch_number' => $batchNumber,
            'total_batches' => max($batchNumber, (int) ($validated['total_batches'] ?? 1)),
            'start_row' => max(1, (int) ($validated['start_row'] ?? 1)),
            'total_rows' => max($rowCount, (int) ($validated['total_rows'] ?? $rowCount)),
            'batch_size' => min(200, max(1, (int) ($validated['batch_size'] ?? 200))),
        ];
    }

    /**
     * @param  array{import_key: string, batch_number: int, total_batches: int, start_row: int, total_rows: int, batch_size: int}  $metadata
     */
    private function importRedirect(
        string $importRouteName,
        string $section,
        array $metadata,
        string $recordLabel,
        int $importedCount,
    ): RedirectResponse {
        if ($metadata['batch_number'] < $metadata['total_batches']) {
            return to_route($importRouteName)->with(
                'success',
                'Imported batch '.$metadata['batch_number'].' of '.$metadata['total_batches'].'. Continue with the next batch.',
            );
        }

        return to_route('dashboard.section', [
            'section' => $section,
        ])->with(
            'success',
            'Imported '.$importedCount.' '.Str::plural($recordLabel, $importedCount).' from spreadsheet.',
        );
    }

    private function alreadyImportedRow(ImportBatch $importBatch, int $rowNumber): bool
    {
        return $importBatch->rows()
            ->where('row_number', $rowNumber)
            ->where('status', 'imported')
            ->exists();
    }

    /**
     * @param  EloquentCollection<int, Department>  $departments
     * @param  EloquentCollection<int, User>  $pendingAccessCodeUsers
     * @return array<int, array{id: string, title: string, detail: string, status: string}>
     */
    private function facultyPendingTasks(
        Faculty $faculty,
        EloquentCollection $departments,
        EloquentCollection $pendingAccessCodeUsers,
    ): array {
        $tasks = [];

        $departments
            ->whereNull('hod_staff_profile_id')
            ->each(function (Department $department) use (&$tasks): void {
                $tasks[] = [
                    'id' => 'vacant-hod-'.$department->id,
                    'title' => 'Assign HOD for '.$department->abbreviation,
                    'detail' => $department->name.' has no current HOD.',
                    'status' => 'Needs action',
                ];
            });

        $pendingAccessCodeCount = $pendingAccessCodeUsers->count();

        if ($pendingAccessCodeCount > 0) {
            $tasks[] = [
                'id' => 'pending-access-codes',
                'title' => 'Follow up access codes',
                'detail' => $pendingAccessCodeCount === 1
                    ? '1 staff account has not completed signup.'
                    : $pendingAccessCodeCount.' staff accounts have not completed signup.',
                'status' => 'Pending',
            ];
        }

        EmailDelivery::query()
            ->where('school_id', $faculty->school_id)
            ->where('status', 'failed')
            ->latest()
            ->limit(2)
            ->get()
            ->each(function (EmailDelivery $delivery) use (&$tasks): void {
                $tasks[] = [
                    'id' => 'failed-email-'.$delivery->id,
                    'title' => 'Share access code manually',
                    'detail' => 'Email failed for '.$delivery->recipient_email.'.',
                    'status' => 'Email failed',
                ];
            });

        ApprovalRequest::query()
            ->with('requesterUser')
            ->where('school_id', $faculty->school_id)
            ->where('status', 'pending')
            ->latest()
            ->limit(3)
            ->get()
            ->each(function (ApprovalRequest $approvalRequest) use (&$tasks): void {
                $tasks[] = [
                    'id' => 'pending-request-'.$approvalRequest->id,
                    'title' => $this->approvalTaskTitle($approvalRequest->type),
                    'detail' => $approvalRequest->notes
                        ?? $approvalRequest->requesterUser?->name
                        ?? 'Pending review',
                    'status' => 'Pending',
                ];
            });

        return array_slice($tasks, 0, 6);
    }

    private function approvalTaskTitle(string $type): string
    {
        return match ($type) {
            'staff_access_code_import' => 'Review staff upload',
            'course_catalog_update' => 'Review course catalog update',
            'student_profile_corrections' => 'Review profile corrections',
            'hod_import_review' => 'Review HOD upload',
            default => 'Review pending request',
        };
    }

    private function facultyStaffProfile(Faculty $faculty, int $staffProfileId): StaffProfile
    {
        return StaffProfile::query()
            ->with(['user', 'department', 'headedDepartment', 'headedFaculty'])
            ->whereKey($staffProfileId)
            ->whereHas('department', fn ($query) => $query->where('faculty_id', $faculty->id))
            ->whereHas('user', fn ($query) => $query
                ->where('school_id', $faculty->school_id)
                ->where('account_type', AccountType::Staff))
            ->firstOrFail();
    }

    private function ensureStaffProfileCanBecomeHod(StaffProfile $staffProfile, Department $department, ?int $rowIndex = null): void
    {
        $staffProfile->loadMissing(['department', 'headedDepartment', 'headedFaculty']);
        $headedDepartment = $staffProfile->headedDepartment;

        if ((int) $staffProfile->department?->faculty_id !== (int) $department->faculty_id) {
            throw ValidationException::withMessages([
                $this->hodValidationKey($rowIndex, 'email') => 'This staff member is not in this faculty.',
            ]);
        }

        if ($staffProfile->headedFaculty !== null) {
            throw ValidationException::withMessages([
                $this->hodValidationKey($rowIndex, 'email') => 'This staff member is already a faculty head.',
            ]);
        }

        if ($headedDepartment !== null && (int) $headedDepartment->id !== (int) $department->id) {
            throw ValidationException::withMessages([
                $this->hodValidationKey($rowIndex, 'email') => 'This staff member is already HOD of '.$headedDepartment->abbreviation.'.',
            ]);
        }
    }

    private function hodValidationKey(?int $rowIndex, string $field): string
    {
        return $rowIndex === null ? 'staff_profile_id' : 'rows.'.$rowIndex.'.'.$field;
    }

    /**
     * @param  EloquentCollection<int, Department>  $departments
     */
    private function facultyDepartmentFromImportValue(EloquentCollection $departments, string $value, int $rowIndex): Department
    {
        $normalizedValue = $this->normalizedImportValue($value);
        $department = $departments->first(fn (Department $department): bool => in_array($normalizedValue, [
            $this->normalizedImportValue($department->abbreviation),
            $this->normalizedImportValue($department->name),
            $this->normalizedImportValue($department->slug),
        ], true));

        if (! $department instanceof Department) {
            throw ValidationException::withMessages([
                'rows.'.$rowIndex.'.department' => 'No department in this faculty matches "'.$value.'".',
            ]);
        }

        return $department;
    }

    /**
     * @param  array{department: string, name?: string|null, email: string, accessCode?: string|null}  $row
     */
    private function staffProfileFromHodImportRow(Faculty $faculty, Department $department, array $row, int $rowIndex): StaffProfile
    {
        $email = (string) $row['email'];
        $name = trim((string) ($row['name'] ?? ''));
        $accessCode = trim((string) ($row['accessCode'] ?? ''));
        $staffUser = User::query()
            ->where('school_id', $faculty->school_id)
            ->where('email', $email)
            ->first();

        if ($staffUser !== null && $staffUser->account_type !== AccountType::Staff) {
            throw ValidationException::withMessages([
                'rows.'.$rowIndex.'.email' => 'This email belongs to a student account in this school.',
            ]);
        }

        if ($staffUser === null) {
            if ($name === '') {
                throw ValidationException::withMessages([
                    'rows.'.$rowIndex.'.name' => 'Full name is required when importing a new staff account.',
                ]);
            }

            $staffUser = User::query()->create([
                'school_id' => $faculty->school_id,
                'account_type' => AccountType::Staff,
                'name' => $name,
                'email' => $email,
                'password' => Str::random(40),
                'access_code' => $accessCode !== '' ? $accessCode : $this->generateAccessCode($faculty, $department),
                'access_code_used_at' => null,
                'activated_at' => null,
            ]);
        } else {
            $updates = [];

            if ($name !== '') {
                $updates['name'] = $name;
            }

            if ($staffUser->activated_at === null && $accessCode !== '') {
                $updates['access_code'] = $accessCode;
                $updates['access_code_used_at'] = null;
            }

            if ($updates !== []) {
                $staffUser->forceFill($updates)->save();
            }
        }

        return $staffUser->staffProfile()->updateOrCreate(
            ['user_id' => $staffUser->id],
            ['department_id' => $department->id],
        )->load(['user', 'headedDepartment', 'headedFaculty']);
    }

    private function queueHodAccessCodeIfNeeded(
        Faculty $faculty,
        Department $department,
        ?User $staffUser,
        ?string $preferredAccessCode = null,
    ): ?EmailDelivery {
        if (! $staffUser instanceof User || $staffUser->activated_at !== null) {
            return null;
        }

        $accessCode = trim((string) $preferredAccessCode) !== ''
            ? trim((string) $preferredAccessCode)
            : ($staffUser->access_code ?: $this->generateAccessCode($faculty, $department));

        $staffUser->forceFill([
            'access_code' => $accessCode,
            'access_code_used_at' => null,
        ])->save();

        return EmailDelivery::query()->create([
            'school_id' => $faculty->school_id,
            'user_id' => $staffUser->id,
            'recipient_email' => (string) $staffUser->email,
            'subject' => 'GradeManager HOD access code',
            'status' => 'queued',
            'metadata' => [
                'department' => $department->abbreviation,
                'access_code' => $accessCode,
                'provisioned_role' => 'hod',
            ],
        ]);
    }

    private function queueStaffAccessCodeIfNeeded(
        Faculty $faculty,
        Department $department,
        ?User $staffUser,
        ?string $preferredAccessCode = null,
    ): ?EmailDelivery {
        if (! $staffUser instanceof User || $staffUser->activated_at !== null) {
            return null;
        }

        $accessCode = trim((string) $preferredAccessCode) !== ''
            ? trim((string) $preferredAccessCode)
            : ($staffUser->access_code ?: $this->generateAccessCode($faculty, $department));

        $staffUser->forceFill([
            'access_code' => $accessCode,
            'access_code_used_at' => null,
        ])->save();

        return EmailDelivery::query()->create([
            'school_id' => $faculty->school_id,
            'user_id' => $staffUser->id,
            'recipient_email' => (string) $staffUser->email,
            'subject' => 'GradeManager staff access code',
            'status' => 'queued',
            'metadata' => [
                'department' => $department->abbreviation,
                'access_code' => $accessCode,
                'provisioned_role' => 'staff',
            ],
        ]);
    }

    private function normalizedImportValue(string $value): string
    {
        return (string) Str::of($value)->lower()->replaceMatches('/[^a-z0-9]+/', '');
    }

    /**
     * @return array{mode: string, selectedStaffProfileId: int|null, selectedDepartmentId: int|null, department: array{id: int, name: string, abbreviation: string, currentHod: array{name: string, email: string|null, avatar: string|null}|null}|null, departments: array<int, array{id: int, name: string, abbreviation: string, currentHod: string|null}>, staff: array<int, array{id: int, staffProfileId: int, name: string, email: string|null, avatar: string|null, department: array{id: int, name: string, abbreviation: string}|null, faculty: array{name: string, abbreviation: string}|null, role: string, status: string, accessCode: string|null, canAssignHod: bool, assignmentBlockReason: string|null}>, suggestedAccessCode: string}
     */
    private function facultyHeadHodFormPayload(
        Faculty $faculty,
        ?int $selectedStaffProfileId = null,
        ?int $selectedDepartmentId = null,
        ?Department $scopedDepartment = null,
        string $mode = 'assign',
    ): array {
        $scopedDepartment?->loadMissing('hodStaffProfile.user');

        return [
            'mode' => $mode,
            'selectedStaffProfileId' => $selectedStaffProfileId,
            'selectedDepartmentId' => $selectedDepartmentId,
            'department' => $scopedDepartment === null ? null : $this->facultyHeadHodDepartmentPayload($scopedDepartment),
            'departments' => $faculty->departments()
                ->with('hodStaffProfile.user')
                ->orderBy('name')
                ->get()
                ->map(fn (Department $department): array => [
                    'id' => (int) $department->id,
                    'name' => $department->name,
                    'abbreviation' => $department->abbreviation,
                    'currentHod' => $department->hodStaffProfile?->user?->name,
                ])
                ->values()
                ->all(),
            'staff' => $this->facultyHeadStaffPayload($faculty, $scopedDepartment),
            'suggestedAccessCode' => $this->generateAccessCode($faculty),
        ];
    }

    /**
     * @return array{id: int, name: string, abbreviation: string, currentHod: array{name: string, email: string|null, avatar: string|null}|null}
     */
    private function facultyHeadHodDepartmentPayload(Department $department): array
    {
        $hodUser = $department->hodStaffProfile?->user;

        return [
            'id' => (int) $department->id,
            'name' => $department->name,
            'abbreviation' => $department->abbreviation,
            'currentHod' => $hodUser === null ? null : [
                'name' => $hodUser->name,
                'email' => $hodUser->email,
                'avatar' => $hodUser->profilePhotoUrl(),
            ],
        ];
    }

    /**
     * @return array{mode: string, departments: array<int, array{id: int, name: string, abbreviation: string}>, suggestedAccessCode: string}
     */
    private function facultyHeadStaffFormPayload(Faculty $faculty, string $mode = 'create'): array
    {
        return [
            'mode' => $mode,
            'departments' => $faculty->departments()
                ->orderBy('name')
                ->get()
                ->map(fn (Department $department): array => [
                    'id' => (int) $department->id,
                    'name' => $department->name,
                    'abbreviation' => $department->abbreviation,
                ])
                ->values()
                ->all(),
            'suggestedAccessCode' => $this->generateAccessCode($faculty),
        ];
    }

    /**
     * @return array{mode: string, department: array{id: int, name: string, abbreviation: string}, suggestedAccessCode: string}
     */
    private function hodStaffFormPayload(Department $department): array
    {
        $department->loadMissing('faculty');

        return [
            'mode' => 'import',
            'department' => [
                'id' => (int) $department->id,
                'name' => $department->name,
                'abbreviation' => $department->abbreviation,
            ],
            'suggestedAccessCode' => $department->faculty instanceof Faculty
                ? $this->generateAccessCode($department->faculty, $department)
                : $this->suggestAccessCode($department->abbreviation, 'STAFF'),
        ];
    }

    private function generateAccessCode(Faculty $faculty, ?Department $department = null): string
    {
        $parts = [
            $faculty->abbreviation,
            $department?->abbreviation ?? 'HOD',
            Str::upper(Str::random(6)),
        ];

        return implode('-', $parts);
    }

    private function suggestAccessCode(string $prefix, string $context): string
    {
        return implode('-', [
            Str::upper($prefix),
            Str::upper($context),
            Str::upper(Str::random(6)),
        ]);
    }

    private function queueFacultyHeadAccessCodeIfNeeded(
        Faculty $faculty,
        ?User $staffUser,
        ?string $preferredAccessCode = null,
    ): ?EmailDelivery {
        if (! $staffUser instanceof User || $staffUser->activated_at !== null) {
            return null;
        }

        $faculty->loadMissing('school');

        $accessCode = trim((string) $preferredAccessCode) !== ''
            ? trim((string) $preferredAccessCode)
            : ($staffUser->access_code ?: $this->suggestAccessCode($faculty->school?->abbreviation ?? 'SCHOOL', $faculty->abbreviation.'-HEAD'));

        $staffUser->forceFill([
            'access_code' => $accessCode,
            'access_code_used_at' => null,
        ])->save();

        return EmailDelivery::query()->create([
            'school_id' => $faculty->school_id,
            'user_id' => $staffUser->id,
            'recipient_email' => (string) $staffUser->email,
            'subject' => 'GradeManager faculty head access code',
            'status' => 'queued',
            'metadata' => [
                'faculty' => $faculty->abbreviation,
                'access_code' => $accessCode,
                'provisioned_role' => 'faculty_head',
            ],
        ]);
    }

    /**
     * @param  array{name: string, email: string, accessCode?: string|null}  $data
     */
    private function staffProfileFromFacultyStaffData(
        Faculty $faculty,
        Department $department,
        array $data,
        ?int $rowIndex = null,
    ): StaffProfile {
        $email = (string) $data['email'];
        $name = trim((string) $data['name']);
        $accessCode = trim((string) ($data['accessCode'] ?? ''));
        $staffUser = User::query()
            ->with('staffProfile.department')
            ->where('school_id', $faculty->school_id)
            ->where('email', $email)
            ->first();

        if ($staffUser !== null && $staffUser->account_type !== AccountType::Staff) {
            throw ValidationException::withMessages([
                $this->staffValidationKey($rowIndex, 'email') => 'This email belongs to a student account in this school.',
            ]);
        }

        if (
            $staffUser?->staffProfile?->department !== null
            && (int) $staffUser->staffProfile->department->faculty_id !== (int) $faculty->id
        ) {
            throw ValidationException::withMessages([
                $this->staffValidationKey($rowIndex, 'email') => 'This staff member belongs to another faculty.',
            ]);
        }

        if ($staffUser === null) {
            $staffUser = User::query()->create([
                'school_id' => $faculty->school_id,
                'account_type' => AccountType::Staff,
                'name' => $name,
                'email' => $email,
                'password' => Str::random(40),
                'access_code' => $accessCode !== '' ? $accessCode : $this->generateAccessCode($faculty, $department),
                'access_code_used_at' => null,
                'activated_at' => null,
            ]);
        } else {
            $updates = ['name' => $name];

            if ($staffUser->activated_at === null && $accessCode !== '') {
                $updates['access_code'] = $accessCode;
                $updates['access_code_used_at'] = null;
            }

            $staffUser->forceFill($updates)->save();
        }

        return $staffUser->staffProfile()->updateOrCreate(
            ['user_id' => $staffUser->id],
            ['department_id' => $department->id],
        )->load(['user', 'department', 'headedDepartment', 'headedFaculty']);
    }

    /**
     * @param  array{name: string, email: string, accessCode?: string|null}  $data
     */
    private function staffProfileFromDepartmentStaffData(
        Faculty $faculty,
        Department $department,
        array $data,
        ?int $rowIndex = null,
    ): StaffProfile {
        $email = (string) $data['email'];
        $name = trim((string) $data['name']);
        $accessCode = trim((string) ($data['accessCode'] ?? ''));
        $staffUser = User::query()
            ->with('staffProfile.department')
            ->where('school_id', $faculty->school_id)
            ->where('email', $email)
            ->first();

        if ($staffUser !== null && $staffUser->account_type !== AccountType::Staff) {
            throw ValidationException::withMessages([
                $this->staffValidationKey($rowIndex, 'email') => 'This email belongs to a student account in this school.',
            ]);
        }

        if (
            $staffUser?->staffProfile?->department !== null
            && (int) $staffUser->staffProfile->department_id !== (int) $department->id
        ) {
            throw ValidationException::withMessages([
                $this->staffValidationKey($rowIndex, 'email') => 'This staff member already belongs to '.$staffUser->staffProfile->department->abbreviation.'.',
            ]);
        }

        if ($staffUser === null) {
            $staffUser = User::query()->create([
                'school_id' => $faculty->school_id,
                'account_type' => AccountType::Staff,
                'name' => $name,
                'email' => $email,
                'password' => Str::random(40),
                'access_code' => $accessCode !== '' ? $accessCode : $this->generateAccessCode($faculty, $department),
                'access_code_used_at' => null,
                'activated_at' => null,
            ]);
        } else {
            $updates = ['name' => $name];

            if ($staffUser->activated_at === null && $accessCode !== '') {
                $updates['access_code'] = $accessCode;
                $updates['access_code_used_at'] = null;
            }

            $staffUser->forceFill($updates)->save();
        }

        return $staffUser->staffProfile()->updateOrCreate(
            ['user_id' => $staffUser->id],
            ['department_id' => $department->id],
        )->load(['user', 'department', 'headedDepartment', 'headedFaculty']);
    }

    private function staffValidationKey(?int $rowIndex, string $field): string
    {
        return $rowIndex === null ? $field : 'rows.'.$rowIndex.'.'.$field;
    }

    private function displayDateTime(mixed $dateTime): ?string
    {
        if (! is_object($dateTime) || ! method_exists($dateTime, 'format')) {
            return null;
        }

        return $dateTime->format('M j, Y H:i');
    }

    /**
     * @return array<int, array{id: int, title: string, slug: string}>
     */
    private function levelsFor(User $user, string $role): array
    {
        $department = $this->departmentFor($user);

        if ($department === null || $role === 'faculty_head') {
            return [];
        }

        if ($role === 'student') {
            return $this->studentLevelsFor($user);
        }

        if ($role === 'staff' && $user->staffProfile !== null) {
            $setLevels = $this->levelsForAssignedStudentSets($user->staffProfile, $department);

            if ($setLevels->isNotEmpty()) {
                return $setLevels
                    ->map(fn (AcademicYear $level): array => $this->levelPayload($level))
                    ->values()
                    ->all();
            }
        }

        return $department->visibleAcademicYears()
            ->map(fn (AcademicYear $level): array => $this->levelPayload($level))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, title: string, slug: string}>
     */
    private function courseScheduleLevelsFor(User $user, string $role): array
    {
        $department = $this->departmentFor($user);

        if ($department === null || in_array($role, ['student', 'faculty_head'], true)) {
            return [];
        }

        if ($role === 'staff' && $user->staffProfile !== null) {
            $setLevels = $this->levelsForAssignedStudentSets($user->staffProfile, $department);
            $highestLevelId = $setLevels->max('id');

            if ($highestLevelId !== null) {
                return AcademicYear::query()
                    ->where('id', '<=', $highestLevelId)
                    ->orderBy('id')
                    ->get()
                    ->map(fn (AcademicYear $level): array => $this->levelPayload($level))
                    ->values()
                    ->all();
            }
        }

        return $department->visibleAcademicYears()
            ->map(fn (AcademicYear $level): array => $this->levelPayload($level))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{entrySession: array{id: int, name: string, startsOn: string, endsOn: string, status: string}, currentLevel: array{id: int, title: string, slug: string}|null, advisorName: string|null}>
     */
    private function advisedSetsFor(User $user, string $role): array
    {
        $department = $this->departmentFor($user);

        if ($role !== 'staff' || $department === null || $user->staffProfile === null) {
            return [];
        }

        return $this->assignedEntrySessionsForStaff($user->staffProfile, $department)
            ->map(fn (AcademicSession $academicSession): array => $this->studentSetContextPayload($department, $academicSession))
            ->values()
            ->all();
    }

    /**
     * @return EloquentCollection<int, AcademicYear>
     */
    private function facultyCourseLevelModels(Faculty $faculty): EloquentCollection
    {
        $minimumYears = (int) $faculty->departments()->max('minimum_years_to_degree');
        $minimumAcademicYearIds = $minimumYears > 0 ? range(1, $minimumYears) : [];
        $offeringAcademicYearIds = CourseOffering::query()
            ->with('academicYearSemester')
            ->where('owner_type', Faculty::class)
            ->where('owner_id', $faculty->id)
            ->get()
            ->pluck('academicYearSemester.academic_year_id')
            ->filter();
        $academicYearIds = collect($minimumAcademicYearIds)
            ->merge($offeringAcademicYearIds)
            ->unique()
            ->values();

        if ($academicYearIds->isEmpty()) {
            return AcademicYear::query()->whereRaw('1 = 0')->get();
        }

        return AcademicYear::query()
            ->whereIn('id', $academicYearIds)
            ->orderBy('id')
            ->get();
    }

    private function ensureFacultyCourseLevel(Faculty $faculty, AcademicYear $level): void
    {
        abort_unless($this->facultyCourseLevelModels($faculty)->contains('id', $level->id), 404);
    }

    /**
     * @return array{
     *     mode: string,
     *     academicSession: array{id: int, name: string, startsOn: string, endsOn: string, status: string}|null
     * }
     */
    private function facultyAcademicSessionFormPayload(Faculty $faculty, ?AcademicSession $academicSession = null): array
    {
        if ($academicSession instanceof AcademicSession) {
            $this->ensureAcademicSessionBelongsToFaculty($faculty, $academicSession);
        }

        return [
            'mode' => $academicSession === null ? 'create' : 'edit',
            'academicSession' => $academicSession === null ? null : $this->academicSessionPayload($academicSession),
        ];
    }

    /**
     * @return array{
     *     mode: string,
     *     academicSession: array{id: int, name: string, startsOn: string, endsOn: string, status: string},
     *     level: array{id: int, title: string, slug: string},
     *     semester: array{id: int, name: string, slug: string},
     *     offering: array{id: int, code: string, title: string, creditUnits: int, prerequisiteCourseIds: array<int, int>}|null,
     *     prerequisiteOptions: array<int, array{id: int, code: string, title: string, units: int, level: string, semester: string}>
     * }
     */
    private function facultyCourseOfferingFormPayload(
        Faculty $faculty,
        AcademicSession $academicSession,
        AcademicYear $level,
        Semester $semester,
        ?CourseOffering $offering = null,
    ): array {
        $offering?->loadMissing('course.prerequisites');

        return [
            'mode' => $offering === null ? 'create' : 'edit',
            'academicSession' => $this->academicSessionPayload($academicSession),
            'level' => $this->levelPayload($level),
            'semester' => $this->semesterPayload($semester),
            'offering' => $offering === null ? null : [
                'id' => (int) $offering->id,
                'code' => $offering->course->code,
                'title' => $offering->course->title,
                'creditUnits' => (int) $offering->course->credit_units,
                'prerequisiteCourseIds' => $offering->course->prerequisites
                    ->pluck('id')
                    ->map(fn (int $id): int => $id)
                    ->values()
                    ->all(),
            ],
            'prerequisiteOptions' => $this->facultyPrerequisiteOptions($faculty, $academicSession, $level, $semester),
        ];
    }

    /**
     * @return array<int, array{id: int, code: string, title: string, units: int, level: string, semester: string}>
     */
    private function facultyPrerequisiteOptions(Faculty $faculty, AcademicSession $academicSession, AcademicYear $level, Semester $semester): array
    {
        return CourseOffering::query()
            ->with(['course', 'academicYearSemester.academicYear', 'academicYearSemester.semester'])
            ->where('owner_type', Faculty::class)
            ->where('owner_id', $faculty->id)
            ->where('academic_session_id', $academicSession->id)
            ->where(function ($query) use ($level, $semester): void {
                $query->whereHas('academicYearSemester', function ($query) use ($level, $semester): void {
                    $query
                        ->where('academic_year_id', '<', $level->id)
                        ->orWhere(function ($query) use ($level, $semester): void {
                            $query
                                ->where('academic_year_id', $level->id)
                                ->whereHas('semester', fn ($query) => $query->where('sort_order', '<', $semester->sort_order));
                        });
                });
            })
            ->get()
            ->sortBy(fn (CourseOffering $offering): string => sprintf(
                '%02d-%02d-%s',
                $offering->academicYearSemester?->academic_year_id ?? 0,
                $offering->academicYearSemester?->semester?->sort_order ?? 0,
                $offering->course->code,
            ))
            ->unique('course_id')
            ->map(fn (CourseOffering $offering): array => [
                'id' => (int) $offering->course->id,
                'code' => $offering->course->code,
                'title' => $offering->course->title,
                'units' => (int) $offering->course->credit_units,
                'level' => $offering->academicYearSemester?->academicYear?->name ?? 'Previous Level',
                'semester' => $offering->academicYearSemester?->semester?->name ?? 'Previous Semester',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $courseIds
     * @return array<int, int>
     */
    private function validatedFacultyPrerequisiteIds(Faculty $faculty, AcademicSession $academicSession, AcademicYear $level, Semester $semester, array $courseIds): array
    {
        $ids = collect($courseIds)
            ->map(fn ($courseId): int => (int) $courseId)
            ->filter()
            ->unique()
            ->values();
        $eligibleIds = collect($this->facultyPrerequisiteOptions($faculty, $academicSession, $level, $semester))->pluck('id');

        if ($ids->diff($eligibleIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'prerequisite_course_ids' => 'Prerequisites must come from earlier faculty courses.',
            ]);
        }

        return $ids->all();
    }

    /**
     * @return array<int, array{id: int, title: string, slug: string}>
     */
    private function studentLevelsFor(User $user): array
    {
        $currentLevel = $user->studentProfile?->currentLevel;

        if ($currentLevel === null) {
            return [];
        }

        return AcademicYear::query()
            ->where('id', '<=', $currentLevel->id)
            ->orderBy('id')
            ->get()
            ->map(fn (AcademicYear $level): array => $this->levelPayload($level))
            ->values()
            ->all();
    }

    /**
     * @return array{registrationNumber: string, currentLevel: array{id: int, title: string, slug: string}|null}|null
     */
    private function studentPayload(User $user, string $role): ?array
    {
        $studentProfile = $user->studentProfile;

        if ($role !== 'student' || $studentProfile === null) {
            return null;
        }

        return [
            'registrationNumber' => $studentProfile->registration_number,
            'currentLevel' => $studentProfile->currentLevel === null
                ? null
                : $this->levelPayload($studentProfile->currentLevel),
        ];
    }

    /**
     * @return array{
     *     name: array{full: string, surname: string, otherNames: string},
     *     email: string,
     *     avatar: string|null,
     *     registrationNumber: string,
     *     cgpa: string,
     *     faculty: array{name: string, abbreviation: string}|null,
     *     department: array{name: string, abbreviation: string}|null,
     *     currentLevel: array{id: int, title: string, slug: string}|null,
     *     courseAdvisor: string
     * }|null
     */
    private function studentProfilePayload(User $user, string $role, string $section): ?array
    {
        $studentProfile = $user->studentProfile;

        if ($role !== 'student' || $section !== 'profile-settings' || $studentProfile === null) {
            return null;
        }

        $name = $this->splitName($user->name);
        $department = $studentProfile->department;
        $currentLevel = $studentProfile->currentLevel;

        return [
            'name' => [
                'full' => $user->name,
                'surname' => $name['surname'],
                'otherNames' => $name['otherNames'],
            ],
            'email' => $user->email,
            'avatar' => $user->profilePhotoUrl(),
            'registrationNumber' => $studentProfile->registration_number,
            'cgpa' => '4.32',
            'faculty' => $department?->faculty?->only(['name', 'abbreviation']),
            'department' => $department?->only(['name', 'abbreviation']),
            'currentLevel' => $currentLevel === null ? null : $this->levelPayload($currentLevel),
            'courseAdvisor' => $this->courseAdvisorNameForStudent($studentProfile, $department),
        ];
    }

    private function studentRecordPayload(StudentProfile $studentProfile, Department $department): array
    {
        $studentProfile->loadMissing(['user', 'currentLevel', 'entryAcademicSession']);
        $currentLevel = $studentProfile->currentLevel;
        $levels = $currentLevel === null
            ? collect()
            : AcademicYear::query()
                ->where('id', '<=', $currentLevel->id)
                ->orderBy('id')
                ->get();
        $semesters = Semester::query()
            ->whereIn('slug', ['harmattan', 'rain'])
            ->orderBy('sort_order')
            ->get();
        $semesterResults = $levels
            ->flatMap(fn (AcademicYear $level): Collection => $semesters
                ->map(function (Semester $semester) use ($department, $level, $studentProfile): array {
                    $result = $this->studentResultSemesterForProfile($department, $level, $semester, $studentProfile);

                    return [
                        'id' => $level->slug.'-'.$semester->slug,
                        'level' => $this->levelPayload($level),
                        'semester' => $this->semesterPayload($semester),
                        'gpa' => $result['gpa'],
                        'totalUnits' => $result['totalUnits'],
                        'courses' => $result['courses'],
                    ];
                }))
            ->values();
        $levelGpas = $levels
            ->map(function (AcademicYear $level) use ($department, $studentProfile): array {
                $courses = $this->studentLevelResultCourses($department, $level, $studentProfile);

                return [
                    'level' => $this->shortLevelName($level),
                    'gpa' => $this->gpaFor($courses),
                ];
            })
            ->values();
        $allCourses = $semesterResults
            ->flatMap(fn (array $semesterResult): array => $semesterResult['courses'])
            ->values()
            ->all();

        return [
            'student' => $this->staffStudentPayload($studentProfile, $department),
            'statementUrl' => route('dashboard.students.statement', [
                'registrationNumber' => $studentProfile->registration_number,
            ]),
            'faculty' => $department->faculty?->only(['name', 'abbreviation']),
            'department' => $department->only(['name', 'abbreviation']),
            'entrySession' => $studentProfile->entryAcademicSession === null
                ? null
                : $this->academicSessionPayload($studentProfile->entryAcademicSession),
            'courseAdvisor' => $this->courseAdvisorNameForStudent($studentProfile, $department, 'Not assigned'),
            'cgpa' => $this->gpaFor($allCourses),
            'levelGpas' => $levelGpas->all(),
            'semesters' => $semesterResults->all(),
        ];
    }

    /**
     * @return array<int, array<int, string|int|float|null>>
     */
    private function studentStatementRows(StudentProfile $studentProfile, Department $department): array
    {
        $studentProfile->loadMissing(['user', 'currentLevel', 'entryAcademicSession', 'department.faculty']);
        $semesterResults = StudentSemesterResult::query()
            ->with([
                'resultSheet.academicSession',
                'resultSheet.entryAcademicSession',
                'resultSheet.academicYearSemester.academicYear',
                'resultSheet.academicYearSemester.semester',
                'courseResults.resultSheetCourse',
            ])
            ->where('student_profile_id', $studentProfile->id)
            ->whereHas('resultSheet', fn ($query) => $query
                ->where('department_id', $department->id)
                ->where('status', ResultSheet::StatusApproved))
            ->get()
            ->sortBy(fn (StudentSemesterResult $result): string => sprintf(
                '%s-%03d-%03d',
                $result->resultSheet?->academicSession?->starts_on?->toDateString() ?? '',
                $result->resultSheet?->academicYearSemester?->academic_year_id ?? 0,
                $result->resultSheet?->academicYearSemester?->semester?->sort_order ?? 0,
            ))
            ->values();

        $rows = [
            ['Statement of Results'],
            ['Student', $studentProfile->user->name],
            ['Registration Number', $studentProfile->registration_number],
            ['Faculty', $department->faculty?->name],
            ['Department', $department->name],
            ['Entry Set', $studentProfile->entryAcademicSession?->name],
            ['Generated', now()->format('M j, Y')],
            [],
            [
                'Academic Session',
                'Entry Set',
                'Level',
                'Semester',
                'Course Code',
                'Course Title',
                'Units',
                'Grade',
                'Grade Point',
                'Quality Points',
                'Semester GPA',
                'CGPA After Semester',
            ],
        ];

        if ($semesterResults->isEmpty()) {
            $rows[] = ['No approved results are available for this student yet.'];

            return $rows;
        }

        $cumulativeUnits = 0;
        $cumulativeGradePoints = 0;

        foreach ($semesterResults as $semesterResult) {
            $sheet = $semesterResult->resultSheet;
            $cumulativeUnits += (int) $semesterResult->total_units;
            $cumulativeGradePoints += (int) $semesterResult->total_grade_points;
            $cumulativeCgpa = $cumulativeUnits === 0
                ? '0.00'
                : number_format($cumulativeGradePoints / $cumulativeUnits, 2);

            foreach ($semesterResult->courseResults->sortBy(fn ($courseResult): int => (int) $courseResult->resultSheetCourse?->sort_order) as $courseResult) {
                $sheetCourse = $courseResult->resultSheetCourse;

                $rows[] = [
                    $sheet?->academicSession?->name,
                    $sheet?->entryAcademicSession?->name,
                    $sheet?->academicYearSemester?->academicYear?->name,
                    $sheet?->academicYearSemester?->semester?->name,
                    $sheetCourse?->course_code,
                    $sheetCourse?->course_title,
                    $sheetCourse?->credit_units,
                    $courseResult->grade,
                    $courseResult->grade_point,
                    $courseResult->quality_points,
                    number_format((float) $semesterResult->gpa, 2),
                    $cumulativeCgpa,
                ];
            }
        }

        return $rows;
    }

    /**
     * @return array{surname: string, otherNames: string}
     */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2) ?: [];

        return [
            'surname' => $parts[0] ?? '',
            'otherNames' => $parts[1] ?? '',
        ];
    }

    /**
     * @return array{
     *     cgpa: string,
     *     currentSemester: array{name: string, level: string, session: string},
     *     courseAdvisor: string,
     *     totalCourseUnits: int,
     *     levels: array<int, array{id: int, title: string, slug: string}>,
     *     gpaProgress: array<int, array{level: string, average: float}>,
     *     courses: array<int, array{code: string, title: string, units: int, type: string}>,
     *     pendingDues: array<int, array{title: string, context: string, amount: string, dueDate: string}>,
     *     resits: array<int, array{code: string, title: string, units: int, originalSemester: string, resitWindow: string, status: string}>
     * }|null
     */
    private function studentHomePayload(User $user, string $role, string $section): ?array
    {
        $studentProfile = $user->studentProfile;

        if ($role !== 'student' || $section !== 'home' || $studentProfile === null) {
            return null;
        }

        $department = $studentProfile->department;
        $currentLevel = $studentProfile->currentLevel;
        $semester = Semester::query()->where('slug', 'harmattan')->first();
        $session = $this->currentAcademicSessionFor($department)?->name ?? '2025/2026';
        $courses = $currentLevel === null || $semester === null
            ? []
            : collect($this->studentResultCourses($department, $currentLevel, $semester))
                ->map(fn (array $course): array => [
                    ...$course,
                    'type' => $this->courseType($course['code']),
                ])
                ->values()
                ->all();
        $gpaProgress = $department === null
            ? []
            : collect($this->staffStudentPayload($studentProfile, $department)['levelGpas'])
                ->map(fn (array $row): array => [
                    'level' => $row['level'],
                    'average' => (float) $row['gpa'],
                ])
                ->values()
                ->all();
        $levels = $department === null
            ? []
            : $department->visibleAcademicYears()
                ->map(fn (AcademicYear $level): array => $this->levelPayload($level))
                ->values()
                ->all();

        return [
            'cgpa' => '4.32',
            'currentSemester' => [
                'name' => $semester?->name ?? 'Harmattan',
                'level' => $currentLevel?->name ?? 'Current Level',
                'session' => $session,
            ],
            'courseAdvisor' => $this->courseAdvisorNameForStudent($studentProfile, $department, 'Not assigned'),
            'totalCourseUnits' => (int) collect($courses)->sum('units'),
            'levels' => $levels,
            'gpaProgress' => $gpaProgress,
            'courses' => $courses,
            'pendingDues' => $this->studentPendingDues($studentProfile),
            'resits' => $this->studentResits($currentLevel),
        ];
    }

    /**
     * @return array{
     *     pendingAmount: string,
     *     paidAmount: string,
     *     totalDues: int,
     *     pending: array<int, array{name: string, description: string, amount: string, paymentUrl: string}>,
     *     paid: array<int, array{name: string, description: string, amount: string, paidAt: string, receiptUrl: string}>
     * }|null
     */
    private function studentDuesPayload(User $user, string $role, string $section): ?array
    {
        $studentProfile = $user->studentProfile;

        if ($role !== 'student' || $section !== 'dues' || $studentProfile === null) {
            return null;
        }

        $assignments = StudentDueAssignment::query()
            ->with(['due', 'latestPayment'])
            ->where('student_profile_id', $studentProfile->id)
            ->whereHas('due', fn ($query) => $query->where('status', Due::StatusPublished))
            ->latest()
            ->get();
        $pendingAssignments = $assignments
            ->where('status', StudentDueAssignment::StatusPending)
            ->sortBy(fn (StudentDueAssignment $assignment): string => $assignment->due->title)
            ->values();
        $paidAssignments = $assignments
            ->where('status', StudentDueAssignment::StatusPaid)
            ->sortByDesc(fn (StudentDueAssignment $assignment): int => $assignment->paid_at?->getTimestamp() ?? 0)
            ->values();
        $pending = $pendingAssignments
            ->map(fn (StudentDueAssignment $assignment): array => $this->studentPendingDuePayload($assignment))
            ->all();
        $paid = $paidAssignments
            ->map(fn (StudentDueAssignment $assignment): array => $this->studentPaidDuePayload($assignment))
            ->all();

        return [
            'pendingAmount' => $this->formatKoboAmount((int) $pendingAssignments->sum('amount_kobo')),
            'paidAmount' => $this->formatKoboAmount((int) $paidAssignments->sum('amount_kobo')),
            'totalDues' => count($pending) + count($paid),
            'pending' => $pending,
            'paid' => $paid,
        ];
    }

    /**
     * @return array{id: int, name: string, description: string|null, amount: string, dueDate: string|null, paymentUrl: string}
     */
    private function studentPendingDuePayload(StudentDueAssignment $assignment): array
    {
        $due = $assignment->due;

        return [
            'id' => (int) $assignment->id,
            'name' => $due->title,
            'description' => $due->description,
            'amount' => $this->formatKoboAmount((int) $assignment->amount_kobo),
            'dueDate' => $due->due_on?->toFormattedDateString(),
            'paymentUrl' => route('dashboard.dues.payments.store', [
                'studentDueAssignment' => $assignment,
            ]),
        ];
    }

    /**
     * @return array{id: int, name: string, description: string|null, amount: string, paidAt: string, receiptUrl: string}
     */
    private function studentPaidDuePayload(StudentDueAssignment $assignment): array
    {
        $due = $assignment->due;
        $payment = $assignment->latestPayment;

        return [
            'id' => (int) $assignment->id,
            'name' => $due->title,
            'description' => $due->description,
            'amount' => $this->formatKoboAmount((int) $assignment->amount_kobo),
            'paidAt' => $assignment->paid_at?->toFormattedDateString() ?? 'Paid',
            'receiptUrl' => $payment instanceof DuePayment
                ? route('dashboard.dues.payments.receipt', ['duePayment' => $payment])
                : '#',
        ];
    }

    private function staffDuesPayload(User $user, string $role, string $section): ?array
    {
        if ($role !== 'staff' || $section !== 'dues') {
            return null;
        }

        $staffProfile = $user->staffProfile;
        $department = $staffProfile?->department;

        if (! $staffProfile instanceof StaffProfile || ! $department instanceof Department) {
            return [
                'assignedSets' => [],
                'metrics' => $this->staffDueMetricsPayload(collect()),
                'dues' => [],
                'createUrl' => route('dashboard.dues.create'),
                'paymentSettingsUrl' => route('dashboard.section', ['section' => 'payment-settings']),
                'hasActivePaymentAccount' => false,
            ];
        }

        $assignedSets = $this->assignedEntrySessionsForStaff($staffProfile, $department);
        $dues = Due::query()
            ->with(['entryAcademicSession', 'assignments.latestPayment'])
            ->where('department_id', $department->id)
            ->whereIn('entry_academic_session_id', $assignedSets->pluck('id'))
            ->latest()
            ->get();

        return [
            'assignedSets' => $assignedSets
                ->map(fn (AcademicSession $academicSession): array => $this->studentSetContextPayload($department, $academicSession))
                ->values()
                ->all(),
            'metrics' => $this->staffDueMetricsPayload($dues),
            'dues' => $dues
                ->map(fn (Due $due): array => $this->staffDueSummaryPayload($due))
                ->values()
                ->all(),
            'createUrl' => route('dashboard.dues.create'),
            'paymentSettingsUrl' => route('dashboard.section', ['section' => 'payment-settings']),
            'hasActivePaymentAccount' => $this->staffPaymentAccounts($staffProfile, $department)
                ->contains(fn (PaymentAccount $paymentAccount): bool => $paymentAccount->isActive()),
        ];
    }

    private function staffPaymentSettingsPayload(User $user, string $role, string $section): ?array
    {
        if ($role !== 'staff' || $section !== 'payment-settings') {
            return null;
        }

        $staffProfile = $user->staffProfile;
        $department = $staffProfile?->department;

        if (! $staffProfile instanceof StaffProfile || ! $department instanceof Department) {
            return [
                'accounts' => [],
                'dashboardUrl' => $this->paystackDashboardUrl(),
                'createUrl' => route('dashboard.payment-settings.create'),
            ];
        }

        return [
            'accounts' => $this->staffPaymentAccounts($staffProfile, $department)
                ->map(fn (PaymentAccount $paymentAccount): array => $this->paymentAccountPayload($paymentAccount, $department))
                ->values()
                ->all(),
            'dashboardUrl' => $this->paystackDashboardUrl(),
            'createUrl' => route('dashboard.payment-settings.create'),
        ];
    }

    private function staffPaymentAccountFormPayload(StaffProfile $staffProfile, Department $department): array
    {
        $bankOptions = $this->paystackBankOptionsPayload();

        return [
            'accounts' => $this->staffPaymentAccounts($staffProfile, $department)
                ->map(fn (PaymentAccount $paymentAccount): array => $this->paymentAccountPayload($paymentAccount, $department))
                ->values()
                ->all(),
            'assignedSets' => $this->assignedEntrySessionsForStaff($staffProfile, $department)
                ->map(fn (AcademicSession $academicSession): array => $this->studentSetContextPayload($department, $academicSession))
                ->values()
                ->all(),
            'bankOptions' => $bankOptions['options'],
            'bankOptionsError' => $bankOptions['error'],
            'dashboardUrl' => $this->paystackDashboardUrl(),
            'returnUrl' => route('dashboard.section', ['section' => 'payment-settings']),
        ];
    }

    private function staffSettingsPayload(User $user, string $role, string $section): ?array
    {
        if ($role !== 'staff' || $section !== 'settings') {
            return null;
        }

        $staffProfile = $user->staffProfile;
        $department = $staffProfile?->department;

        if (! $staffProfile instanceof StaffProfile || ! $department instanceof Department) {
            return null;
        }

        return [
            'staff' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->profilePhotoUrl(),
            ],
            'department' => $this->departmentPayload($department),
            'faculty' => $department->faculty?->only(['name', 'abbreviation']),
            'assignedSets' => $this->assignedEntrySessionsForStaff($staffProfile, $department)
                ->map(fn (AcademicSession $academicSession): array => $this->studentSetContextPayload($department, $academicSession))
                ->values()
                ->all(),
        ];
    }

    private function staffDueFormPayload(StaffProfile $staffProfile, Department $department): array
    {
        $assignedSets = $this->assignedEntrySessionsForStaff($staffProfile, $department);
        $paymentAccounts = $this->staffPaymentAccounts($staffProfile, $department);

        return [
            'assignedSets' => $assignedSets
                ->map(fn (AcademicSession $academicSession): array => $this->studentSetContextPayload($department, $academicSession))
                ->values()
                ->all(),
            'paymentAccounts' => $paymentAccounts
                ->map(fn (PaymentAccount $paymentAccount): array => $this->paymentAccountPayload($paymentAccount, $department))
                ->values()
                ->all(),
            'paymentSettingsUrl' => route('dashboard.section', ['section' => 'payment-settings']),
            'defaults' => [
                'entryAcademicSessionId' => $assignedSets->first()?->id,
                'dueOn' => now()->addMonth()->toDateString(),
            ],
        ];
    }

    private function staffDueDetailPayload(Due $due): array
    {
        $due->loadMissing([
            'entryAcademicSession',
            'paymentAccount',
            'assignments.studentProfile.user',
            'assignments.latestPayment',
        ]);
        $assignments = $due->assignments->sortBy(fn (StudentDueAssignment $assignment): string => $assignment->studentProfile->user->name)->values();

        return [
            'due' => $this->staffDueSummaryPayload($due),
            'students' => $assignments
                ->map(fn (StudentDueAssignment $assignment): array => $this->staffDueStudentPayload($assignment))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, Due>  $dues
     * @return array{expectedAmount: string, collectedAmount: string, outstandingAmount: string, studentsOwing: int}
     */
    private function staffDueMetricsPayload(Collection $dues): array
    {
        $assignments = $dues->flatMap(fn (Due $due): Collection => $due->assignments);
        $pendingAssignments = $assignments->where('status', StudentDueAssignment::StatusPending);
        $paidAssignments = $assignments->where('status', StudentDueAssignment::StatusPaid);

        return [
            'expectedAmount' => $this->formatKoboAmount((int) $assignments->sum('amount_kobo')),
            'collectedAmount' => $this->formatKoboAmount((int) $paidAssignments->sum('amount_kobo')),
            'outstandingAmount' => $this->formatKoboAmount((int) $pendingAssignments->sum('amount_kobo')),
            'studentsOwing' => $pendingAssignments->pluck('student_profile_id')->unique()->count(),
        ];
    }

    private function staffDueSummaryPayload(Due $due): array
    {
        $due->loadMissing(['department.faculty.school', 'entryAcademicSession', 'paymentAccount', 'assignments']);
        $assignments = $due->assignments;
        $paid = $assignments->where('status', StudentDueAssignment::StatusPaid);
        $pending = $assignments->where('status', StudentDueAssignment::StatusPending);
        $currentLevel = $this->academicYearForEntrySession($due->department, $due->entryAcademicSession);
        $setLabel = $due->entryAcademicSession->name.' Set';

        if ($currentLevel instanceof AcademicYear && (int) $currentLevel->id <= (int) $due->department->minimum_years_to_degree) {
            $setLabel .= ' · '.$this->shortLevelName($currentLevel);
        } elseif ($currentLevel instanceof AcademicYear) {
            $setLabel .= ' · Graduated';
        }

        return [
            'id' => (int) $due->id,
            'title' => $due->title,
            'description' => $due->description,
            'amount' => $this->formatKoboAmount((int) $due->amount_kobo),
            'status' => $this->dueStatusLabel($due->status),
            'set' => $setLabel,
            'dueDate' => $due->due_on?->toFormattedDateString(),
            'paidCount' => $paid->count(),
            'pendingCount' => $pending->count(),
            'collectedAmount' => $this->formatKoboAmount((int) $paid->sum('amount_kobo')),
            'outstandingAmount' => $this->formatKoboAmount((int) $pending->sum('amount_kobo')),
            'paymentAccount' => $due->paymentAccount instanceof PaymentAccount
                ? $this->paymentAccountPayload($due->paymentAccount, $due->department)
                : null,
            'detailUrl' => route('dashboard.dues.show', ['due' => $due]),
        ];
    }

    /**
     * @return Collection<int, PaymentAccount>
     */
    private function staffPaymentAccounts(StaffProfile $staffProfile, Department $department): Collection
    {
        return PaymentAccount::query()
            ->with('entryAcademicSession')
            ->where('department_id', $department->id)
            ->where('staff_profile_id', $staffProfile->id)
            ->latest()
            ->get();
    }

    private function paymentAccountPayload(PaymentAccount $paymentAccount, Department $department): array
    {
        $paymentAccount->loadMissing('entryAcademicSession');
        $setLabel = 'All assigned sets';

        if ($paymentAccount->entryAcademicSession instanceof AcademicSession) {
            $currentLevel = $this->academicYearForEntrySession($department, $paymentAccount->entryAcademicSession);
            $setLabel = $paymentAccount->entryAcademicSession->name.' Set';

            if ($currentLevel instanceof AcademicYear && (int) $currentLevel->id <= (int) $department->minimum_years_to_degree) {
                $setLabel .= ' · '.$this->shortLevelName($currentLevel);
            } elseif ($currentLevel instanceof AcademicYear) {
                $setLabel .= ' · Graduated';
            }
        }

        return [
            'id' => (int) $paymentAccount->id,
            'label' => $paymentAccount->label,
            'businessName' => $paymentAccount->business_name,
            'bankName' => $paymentAccount->bank_name,
            'accountName' => $paymentAccount->account_name,
            'accountNumber' => $paymentAccount->account_number,
            'status' => $this->paymentAccountStatusLabel($paymentAccount->status),
            'set' => $setLabel,
            'entryAcademicSessionId' => $paymentAccount->entry_academic_session_id,
            'verifiedAt' => $paymentAccount->verified_at?->toFormattedDateString(),
        ];
    }

    private function paymentAccountStatusLabel(string $status): string
    {
        return match ($status) {
            PaymentAccount::StatusActive => 'Active',
            PaymentAccount::StatusDisabled => 'Disabled',
            default => 'Draft',
        };
    }

    private function paystackBankFromCode(PaystackService $paystack, string $bankCode): array
    {
        try {
            $bank = collect($paystack->nigerianBankOptions())
                ->firstWhere('code', $bankCode);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'bank_code' => 'Paystack could not load the supported banks right now. Try again shortly.',
            ]);
        }

        if (! is_array($bank)) {
            throw ValidationException::withMessages([
                'bank_code' => 'Select a bank from the Paystack bank list.',
            ]);
        }

        return $bank;
    }

    /**
     * @return array{options: array<int, array{code: string, name: string}>, error: string|null}
     */
    private function paystackBankOptionsPayload(): array
    {
        try {
            $options = app(PaystackService::class)->nigerianBankOptions();
        } catch (\Throwable) {
            return [
                'options' => [],
                'error' => 'Paystack banks could not be loaded. Check the Paystack keys or try again shortly.',
            ];
        }

        if ($options === []) {
            return [
                'options' => [],
                'error' => 'Paystack did not return any Nigerian banks. Try again shortly.',
            ];
        }

        return [
            'options' => $options,
            'error' => null,
        ];
    }

    private function paystackDashboardUrl(): string
    {
        $dashboardUrl = config('services.paystack.dashboard_url');

        return is_string($dashboardUrl) && $dashboardUrl !== ''
            ? $dashboardUrl
            : 'https://dashboard.paystack.com';
    }

    private function staffDueStudentPayload(StudentDueAssignment $assignment): array
    {
        $studentProfile = $assignment->studentProfile;
        $payment = $assignment->latestPayment;

        return [
            'id' => (int) $assignment->id,
            'studentName' => $studentProfile->user->name,
            'studentAvatar' => $studentProfile->user->profilePhotoUrl(),
            'registrationNumber' => $studentProfile->registration_number,
            'amount' => $this->formatKoboAmount((int) $assignment->amount_kobo),
            'status' => $assignment->status === StudentDueAssignment::StatusPaid ? 'Paid' : 'Pending',
            'paidAt' => $assignment->paid_at?->toFormattedDateString(),
            'receiptUrl' => $payment instanceof DuePayment && $payment->status === DuePayment::StatusSuccess
                ? route('dashboard.dues.payments.receipt', ['duePayment' => $payment])
                : null,
        ];
    }

    private function dueStatusLabel(string $status): string
    {
        return match ($status) {
            Due::StatusPublished => 'Published',
            Due::StatusArchived => 'Archived',
            default => 'Draft',
        };
    }

    /**
     * @return array{
     *     level: array{id: int, title: string, slug: string},
     *     averageGpa: string,
     *     totalUnits: int,
     *     failedCourses: int,
     *     totalCourses: int,
     *     semesters: array<int, array{name: string, slug: string, gpa: string, totalUnits: int, courses: array<int, array{code: string, title: string, units: int, grade: string}>}>
     * }|null
     */
    private function studentResultsPayload(User $user, string $role, string $section, ?AcademicYear $level): ?array
    {
        $studentProfile = $user->studentProfile;

        if ($role !== 'student' || $section !== 'results' || $level === null || $studentProfile === null) {
            return null;
        }

        $department = $studentProfile->department;
        $academicYearSemesterIds = AcademicYearSemester::query()
            ->where('academic_year_id', $level->id)
            ->pluck('id');
        $studentResults = StudentSemesterResult::query()
            ->with([
                'resultSheet.academicYearSemester.semester',
                'resultSheet.courses',
                'courseResults.resultSheetCourse',
            ])
            ->where('student_profile_id', $studentProfile->id)
            ->whereHas('resultSheet', fn ($query) => $query
                ->where('department_id', $department?->id)
                ->where('status', ResultSheet::StatusApproved)
                ->whereIn('academic_year_semester_id', $academicYearSemesterIds))
            ->get()
            ->sortBy(fn (StudentSemesterResult $result): int => (int) $result->resultSheet?->academicYearSemester?->semester?->sort_order)
            ->values();
        $semesterResults = $studentResults
            ->map(function (StudentSemesterResult $result): array {
                $sheet = $result->resultSheet;
                $courseResults = $result->courseResults->keyBy('result_sheet_course_id');

                return [
                    'name' => $sheet?->academicYearSemester?->semester?->name ?? 'Semester',
                    'slug' => $sheet?->academicYearSemester?->semester?->slug ?? 'semester',
                    'gpa' => number_format((float) $result->gpa, 2),
                    'totalUnits' => (int) $result->total_units,
                    'courses' => ($sheet?->courses ?? collect())
                        ->map(function (ResultSheetCourse $course) use ($courseResults): array {
                            $courseResult = $courseResults->get($course->id);

                            return [
                                'code' => $course->course_code,
                                'title' => $course->course_title,
                                'units' => (int) $course->credit_units,
                                'grade' => $courseResult?->grade,
                            ];
                        })
                        ->filter(fn (array $course): bool => is_string($course['grade']))
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
        $summary = app(GpaCalculator::class)->cumulativeGpa(
            $studentResults
                ->map(fn (StudentSemesterResult $result): array => [
                    'total_units' => (int) $result->total_units,
                    'total_grade_points' => (int) $result->total_grade_points,
                ])
                ->all(),
        );
        $courses = collect($semesterResults)->flatMap(fn (array $semester): array => $semester['courses']);

        if ($semesterResults === []) {
            $semesters = Semester::query()
                ->whereIn('slug', ['harmattan', 'rain'])
                ->orderBy('sort_order')
                ->get();
            $semesterResults = $semesters
                ->map(fn (Semester $semester): array => [
                    'name' => $semester->name,
                    'slug' => $semester->slug,
                    'gpa' => '0.00',
                    'totalUnits' => 0,
                    'courses' => [],
                ])
                ->values()
                ->all();
        }

        return [
            'level' => $this->levelPayload($level),
            'averageGpa' => number_format($summary, 2),
            'totalUnits' => (int) $studentResults->sum('total_units'),
            'failedCourses' => $courses->where('grade', 'F')->count(),
            'totalCourses' => $courses->count(),
            'semesters' => $semesterResults,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function studentCourseRegistrationPayload(User $user, string $role, string $section): ?array
    {
        $studentProfile = $user->studentProfile;

        if ($role !== 'student' || $section !== 'course-registration' || $studentProfile === null) {
            return null;
        }

        [$department, $academicSession, $academicYearSemester] = $this->currentCourseRegistrationContext($studentProfile);
        $academicYearSemester->loadMissing(['academicYear', 'semester']);
        $registrationPlan = app(CourseRegistrationPlanner::class)->plan($studentProfile, $department, $academicSession, $academicYearSemester);
        $registration = CourseRegistration::query()
            ->with(['items' => fn ($query) => $query->orderBy('source')->orderBy('course_code')])
            ->where('student_profile_id', $studentProfile->id)
            ->where('academic_session_id', $academicSession->id)
            ->where('academic_year_semester_id', $academicYearSemester->id)
            ->first();
        $selectedItems = $registration instanceof CourseRegistration
            ? $registration->items->map(fn (CourseRegistrationItem $item): array => $this->courseRegistrationItemPayload($item, $registrationPlan['availableCourses']))->values()->all()
            : [];
        $previousRegistrations = CourseRegistration::query()
            ->with(['items' => fn ($query) => $query->orderBy('source')->orderBy('course_code'), 'academicSession', 'academicYearSemester.academicYear', 'academicYearSemester.semester'])
            ->where('student_profile_id', $studentProfile->id)
            ->where(function ($query) use ($academicSession, $academicYearSemester): void {
                $query->where('academic_session_id', '!=', $academicSession->id)
                    ->orWhere('academic_year_semester_id', '!=', $academicYearSemester->id);
            })
            ->latest()
            ->limit(8)
            ->get();

        return [
            'currentSemester' => [
                'name' => $academicYearSemester->semester->name,
                'level' => $academicYearSemester->academicYear->name,
                'session' => $academicSession->name,
            ],
            'maximumCourseUnits' => 24,
            'advisorName' => $this->courseAdvisorNameForStudent($studentProfile, $department),
            'reviewStatus' => $this->courseRegistrationStatusLabel($registration?->status ?? CourseRegistration::StatusDraft),
            'feedbackNote' => $registration?->feedback_note,
            'selectedUnits' => (int) collect($selectedItems)->sum('units'),
            'registrationId' => $registration?->id,
            'selectedCourses' => $selectedItems,
            'availableCourses' => $registrationPlan['availableCourses'],
            'selectableCourses' => $registrationPlan['selectableCourses'],
            'courseGroups' => $registrationPlan['courseGroups'],
            'previousRegistrations' => $previousRegistrations
                ->map(fn (CourseRegistration $previousRegistration): array => $this->courseRegistrationSummaryPayload($previousRegistration))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function staffCourseRegistrationPayload(
        Request $request,
        User $user,
        string $role,
        string $section,
        ?CourseRegistration $selectedRegistration = null,
    ): ?array {
        if ($role !== 'staff' || $section !== 'course-registration') {
            return null;
        }

        [$staffProfile, $department] = $this->staffProfileAndDepartment($user);
        $students = $this->assignedStudentsForStaff($staffProfile, $department);
        $studentIds = $students->pluck('id');
        $registrations = CourseRegistration::query()
            ->with(['studentProfile.user', 'studentProfile.entryAcademicSession', 'academicSession', 'academicYearSemester.academicYear', 'academicYearSemester.semester', 'items'])
            ->whereIn('student_profile_id', $studentIds)
            ->whereIn('status', [
                CourseRegistration::StatusSubmitted,
                CourseRegistration::StatusApproved,
                CourseRegistration::StatusReturned,
            ])
            ->latest('submitted_at')
            ->latest('updated_at')
            ->get();

        return [
            'assignedSets' => $this->assignedEntrySessionsForStaff($staffProfile, $department)
                ->map(fn (AcademicSession $academicSession): array => $this->studentSetContextPayload($department, $academicSession))
                ->values()
                ->all(),
            'registrations' => $registrations
                ->map(fn (CourseRegistration $registration): array => $this->courseRegistrationListPayload($registration))
                ->values()
                ->all(),
            'review' => $selectedRegistration instanceof CourseRegistration
                ? $this->courseRegistrationReviewPayload($selectedRegistration, $department)
                : null,
        ];
    }

    /**
     * @return array{0: Department, 1: AcademicSession, 2: AcademicYearSemester}
     */
    private function currentCourseRegistrationContext(StudentProfile $studentProfile): array
    {
        $department = $studentProfile->department;
        $currentLevel = $studentProfile->currentLevel;
        $currentSemester = Semester::query()->where('slug', 'harmattan')->first();

        abort_unless($department instanceof Department && $currentLevel instanceof AcademicYear && $currentSemester instanceof Semester, 404);

        $academicSession = $this->currentAcademicSessionFor($department);

        abort_unless($academicSession instanceof AcademicSession, 404);

        return [
            $department,
            $academicSession,
            $this->resolveAcademicYearSemester($currentLevel, $currentSemester),
        ];
    }

    /**
     * @return array{courseOfferingId: int, code: string, title: string, units: int, category: string, source: string, sourceLabel: string, isCarryover: bool}
     */
    private function courseRegistrationItemPayload(CourseRegistrationItem $item, array $availableCourses = []): array
    {
        $availableCourse = collect($availableCourses)->first(fn (array $course): bool => (int) $course['courseOfferingId'] === (int) $item->course_offering_id
            && $course['source'] === $item->source);

        return [
            'courseOfferingId' => (int) $item->course_offering_id,
            'code' => $item->course_code,
            'title' => $item->course_title,
            'units' => (int) $item->credit_units,
            'category' => $item->category,
            'source' => $item->source,
            'sourceLabel' => $this->courseRegistrationSourceLabel($item->source),
            'isCarryover' => $item->source === CourseRegistrationItem::SourceCarryover,
            'selectable' => $availableCourse['selectable'] ?? true,
            'blockReasons' => $availableCourse['blockReasons'] ?? [],
            'originSemester' => $availableCourse['originSemester'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function courseRegistrationSummaryPayload(CourseRegistration $registration): array
    {
        $registration->loadMissing(['academicSession', 'academicYearSemester.academicYear', 'academicYearSemester.semester', 'items']);

        return [
            'id' => (int) $registration->id,
            'status' => $this->courseRegistrationStatusLabel($registration->status),
            'semester' => [
                'level' => $registration->academicYearSemester->academicYear->name,
                'name' => $registration->academicYearSemester->semester->name,
                'session' => $registration->academicSession->name,
            ],
            'totalUnits' => (int) $registration->items->sum('credit_units'),
            'courses' => $registration->items
                ->map(fn (CourseRegistrationItem $item): array => $this->courseRegistrationItemPayload($item))
                ->values()
                ->all(),
            'reviewUrl' => route('dashboard.course-registration.show', ['courseRegistration' => $registration]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function courseRegistrationListPayload(CourseRegistration $registration): array
    {
        $registration->loadMissing(['studentProfile.user', 'studentProfile.entryAcademicSession', 'academicSession', 'academicYearSemester.academicYear', 'academicYearSemester.semester', 'items']);
        $studentProfile = $registration->studentProfile;

        return [
            ...$this->courseRegistrationSummaryPayload($registration),
            'student' => [
                'name' => $studentProfile->user->name,
                'avatar' => $studentProfile->user->profilePhotoUrl(),
                'registrationNumber' => $studentProfile->registration_number,
                'profileUrl' => route('dashboard.students.show', ['registrationNumber' => $studentProfile->registration_number]),
            ],
            'submittedAt' => $registration->submitted_at?->toFormattedDateString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function courseRegistrationReviewPayload(CourseRegistration $registration, Department $department): array
    {
        $registration->loadMissing(['studentProfile.user', 'studentProfile.currentLevel', 'studentProfile.entryAcademicSession', 'academicSession', 'academicYearSemester.academicYear', 'academicYearSemester.semester', 'items']);
        $studentProfile = $registration->studentProfile;
        $items = $registration->items
            ->sortBy(fn (CourseRegistrationItem $item): string => $item->source.'-'.$item->course_code)
            ->values();
        $regularUnits = (int) $items
            ->reject(fn (CourseRegistrationItem $item): bool => $item->source === CourseRegistrationItem::SourceCarryover)
            ->sum('credit_units');
        $carryoverUnits = (int) $items
            ->filter(fn (CourseRegistrationItem $item): bool => $item->source === CourseRegistrationItem::SourceCarryover)
            ->sum('credit_units');

        return [
            ...$this->courseRegistrationListPayload($registration),
            'student' => [
                'name' => $studentProfile->user->name,
                'avatar' => $studentProfile->user->profilePhotoUrl(),
                'registrationNumber' => $studentProfile->registration_number,
                'profileUrl' => route('dashboard.students.show', ['registrationNumber' => $studentProfile->registration_number]),
                'cgpa' => $this->staffStudentPayload($studentProfile, $department)['cgpa'],
            ],
            'set' => $studentProfile->entryAcademicSession instanceof AcademicSession
                ? $this->studentSetContextPayload($department, $studentProfile->entryAcademicSession)
                : null,
            'department' => $this->departmentPayload($department),
            'regularUnits' => $regularUnits,
            'carryoverUnits' => $carryoverUnits,
            'totalUnits' => $regularUnits + $carryoverUnits,
            'feedbackNote' => $registration->feedback_note,
            'canReview' => $registration->status === CourseRegistration::StatusSubmitted,
            'courses' => $items
                ->map(fn (CourseRegistrationItem $item): array => $this->courseRegistrationItemPayload($item))
                ->values()
                ->all(),
        ];
    }

    private function staffCanReviewCourseRegistration(
        StaffProfile $staffProfile,
        Department $department,
        CourseRegistration $registration,
    ): bool {
        $registration->loadMissing('studentProfile.entryAcademicSession');
        $studentProfile = $registration->studentProfile;

        return (int) $registration->department_id === (int) $department->id
            && $studentProfile instanceof StudentProfile
            && $studentProfile->entryAcademicSession instanceof AcademicSession
            && $this->staffCanManageStudentSet($staffProfile, $department, $studentProfile->entryAcademicSession);
    }

    private function courseAdvisorForStudent(StudentProfile $studentProfile, Department $department): ?StaffProfile
    {
        $entryAcademicSession = $studentProfile->entryAcademicSession;

        if (! $entryAcademicSession instanceof AcademicSession) {
            return null;
        }

        $assignment = $department->studentSetAdvisorAssignments()
            ->with('staffProfile')
            ->where('entry_academic_session_id', $entryAcademicSession->id)
            ->where('active_marker', 1)
            ->first();

        return $assignment?->staffProfile;
    }

    private function courseRegistrationStatusLabel(string $status): string
    {
        return match ($status) {
            CourseRegistration::StatusSubmitted => 'In review',
            CourseRegistration::StatusApproved => 'Approved',
            CourseRegistration::StatusReturned => 'Returned',
            default => 'Draft',
        };
    }

    private function courseRegistrationSourceLabel(string $source): string
    {
        return match ($source) {
            CourseRegistrationItem::SourceCurrentSet => 'Current Set',
            CourseRegistrationItem::SourceCarryover => 'Carryover',
            CourseRegistrationItem::SourceOutstandingRequirement => 'Outstanding Requirement',
            CourseRegistrationItem::SourceElective => 'Elective',
            default => 'Course',
        };
    }

    /**
     * @return array{
     *     level: array{id: int, title: string, slug: string},
     *     academicSession: array{name: string}|null,
     *     semesters: array<int, array{
     *         id: string,
     *         name: string,
     *         slug: string,
     *         totalUnits: int,
     *         facultyCourseCount: int,
     *         departmentalCourseCount: int,
     *         electiveCourseCount: int,
     *         offerings: array<int, array{
     *             id: int,
     *             courseId: int,
     *             code: string,
     *             title: string,
     *             units: int,
     *             isMandatory: bool,
     *             courseType: string,
     *             prerequisites: array<int, array{id: int, code: string, title: string}>,
     *             canEdit: bool
     *         }>
     *     }>
     * }|null
     */
    private function staffCourseSchedulesPayload(Request $request, User $user, string $role, string $section): ?array
    {
        if ($role !== 'staff' || $section !== 'courses') {
            return null;
        }

        $staffProfile = $user->staffProfile;
        $department = $this->departmentFor($user);

        if (! $staffProfile instanceof StaffProfile || $department === null) {
            return null;
        }

        $assignedSets = $this->assignedEntrySessionsForStaff($staffProfile, $department);
        $selectedEntrySession = $this->selectedStaffEntrySession($request, $assignedSets);
        $levels = $this->levelsForStaffCourseSchedule($department, $selectedEntrySession);
        $selectedLevel = $this->selectedStaffCourseScheduleLevel($request, $department, $levels, $selectedEntrySession);
        $selectedAcademicSession = $this->academicSessionForStudentSetLevel($department, $selectedEntrySession, $selectedLevel);
        $semesters = Semester::query()
            ->whereIn('slug', ['harmattan', 'rain'])
            ->orderBy('sort_order')
            ->get();

        return [
            'level' => $selectedLevel === null ? null : $this->levelPayload($selectedLevel),
            'levels' => $levels
                ->map(fn (AcademicYear $level): array => $this->levelPayload($level))
                ->values()
                ->all(),
            'academicSession' => $selectedAcademicSession === null ? null : $this->academicSessionPayload($selectedAcademicSession),
            'selectedSet' => $selectedEntrySession === null
                ? null
                : $this->studentSetContextPayload($department, $selectedEntrySession),
            'assignedSets' => $assignedSets
                ->map(fn (AcademicSession $academicSession): array => $this->studentSetContextPayload($department, $academicSession))
                ->values()
                ->all(),
            'semesters' => $selectedLevel === null
                ? []
                : $semesters
                    ->map(fn (Semester $semester): array => $this->staffCourseScheduleSemesterPayload($department, $selectedAcademicSession, $selectedLevel, $semester))
                    ->values()
                    ->all(),
        ];
    }

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     slug: string,
     *     totalUnits: int,
     *     facultyCourseCount: int,
     *     departmentalCourseCount: int,
     *     electiveCourseCount: int,
     *     offerings: array<int, array{
     *         id: int,
     *         courseId: int,
     *         code: string,
     *         title: string,
     *         units: int,
     *         isMandatory: bool,
     *         courseType: string,
     *         prerequisites: array<int, array{id: int, code: string, title: string}>,
     *         canEdit: bool
     *     }>
     * }
     */
    private function staffCourseScheduleSemesterPayload(
        Department $department,
        ?AcademicSession $academicSession,
        AcademicYear $level,
        Semester $semester,
    ): array {
        $offerings = $this->courseOfferingsForDepartment($department, $level, $semester, $academicSession);
        $courseTypeCounts = $this->courseTypeCounts($department, $offerings);

        return [
            'id' => $level->slug.'-'.$semester->slug,
            'name' => $semester->name,
            'slug' => $semester->slug,
            'totalUnits' => (int) $offerings->sum(fn (CourseOffering $offering): int => (int) $offering->course->credit_units),
            'facultyCourseCount' => $courseTypeCounts['Faculty Course'],
            'departmentalCourseCount' => $courseTypeCounts['Departmental Course'],
            'electiveCourseCount' => $courseTypeCounts['Elective Course'],
            'offerings' => $offerings
                ->map(fn (CourseOffering $offering): array => $this->staffCourseOfferingPayload($department, $offering))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return Collection<int, CourseOffering>
     */
    private function courseOfferingsForDepartment(
        Department $department,
        AcademicYear $level,
        Semester $semester,
        ?AcademicSession $academicSession = null,
    ): Collection {
        $academicSession ??= $this->currentAcademicSessionFor($department);
        $academicYearSemester = $this->resolveAcademicYearSemester($level, $semester);

        if ($academicSession === null) {
            return collect();
        }

        return CourseOffering::query()
            ->with(['course.prerequisites', 'academicYearSemester.academicYear', 'academicYearSemester.semester'])
            ->where('academic_session_id', $academicSession->id)
            ->where('academic_year_semester_id', $academicYearSemester->id)
            ->where(function ($query) use ($department): void {
                $this->whereOfferingOwner($query, $department);
            })
            ->get()
            ->sortBy(fn (CourseOffering $offering): string => $offering->course->code)
            ->values();
    }

    /**
     * @return array{
     *     id: int,
     *     courseId: int,
     *     code: string,
     *     title: string,
     *     units: int,
     *     isMandatory: bool,
     *     courseType: string,
     *     prerequisites: array<int, array{id: int, code: string, title: string}>,
     *     canEdit: bool
     * }
     */
    private function staffCourseOfferingPayload(Department $department, CourseOffering $offering): array
    {
        $isDepartmentOwned = $offering->owner_type === Department::class
            && (int) $offering->owner_id === (int) $department->id;

        return [
            'id' => (int) $offering->id,
            'courseId' => (int) $offering->course_id,
            'code' => $offering->course->code,
            'title' => $offering->course->title,
            'units' => (int) $offering->course->credit_units,
            'isMandatory' => (bool) $offering->is_mandatory,
            'courseType' => $this->courseTypeForOffering($department, $offering),
            'prerequisites' => $this->coursePrerequisitePayload($offering->course),
            'canEdit' => $isDepartmentOwned,
        ];
    }

    /**
     * @param  Collection<int, CourseOffering>  $offerings
     * @return array{'Faculty Course': int, 'Departmental Course': int, 'Elective Course': int}
     */
    private function courseTypeCounts(Department $department, Collection $offerings): array
    {
        $counts = $offerings
            ->map(fn (CourseOffering $offering): string => $this->courseTypeForOffering($department, $offering))
            ->countBy();

        return [
            'Faculty Course' => (int) ($counts['Faculty Course'] ?? 0),
            'Departmental Course' => (int) ($counts['Departmental Course'] ?? 0),
            'Elective Course' => (int) ($counts['Elective Course'] ?? 0),
        ];
    }

    private function courseTypeForOffering(Department $department, CourseOffering $offering): string
    {
        $isDepartmentOwned = $offering->owner_type === Department::class
            && (int) $offering->owner_id === (int) $department->id;

        if (! $isDepartmentOwned) {
            return 'Faculty Course';
        }

        return $offering->is_mandatory ? 'Departmental Course' : 'Elective Course';
    }

    /**
     * @return array{
     *     mode: string,
     *     academicSession: array{id: int, name: string, startsOn: string, endsOn: string, status: string},
     *     level: array{id: int, title: string, slug: string},
     *     semester: array{id: int, name: string, slug: string},
     *     offering: array{id: int, code: string, title: string, creditUnits: int, isMandatory: bool, prerequisiteCourseIds: array<int, int>}|null,
     *     prefill: array{code: string, title: string, creditUnits: int}|null,
     *     returnTo: string|null,
     *     prerequisiteOptions: array<int, array{id: int, code: string, title: string, units: int, level: string, semester: string}>
     * }
     */
    private function courseOfferingFormPayload(
        Department $department,
        AcademicYear $level,
        Semester $semester,
        ?CourseOffering $offering = null,
        ?AcademicSession $academicSession = null,
    ): array {
        $offering?->loadMissing('course.prerequisites');
        $academicSession ??= $this->requiredCurrentAcademicSession($department);
        $prefillCode = request()->string('code')->trim()->upper()->toString();
        $prefill = [
            'code' => $prefillCode,
            'title' => request()->string('title')->trim()->toString(),
            'creditUnits' => request()->integer('credit_units', 3),
        ];

        return [
            'mode' => $offering === null ? 'create' : 'edit',
            'academicSession' => $this->academicSessionPayload($academicSession),
            'level' => $this->levelPayload($level),
            'semester' => $this->semesterPayload($semester),
            'offering' => $offering === null ? null : [
                'id' => (int) $offering->id,
                'code' => $offering->course->code,
                'title' => $offering->course->title,
                'creditUnits' => (int) $offering->course->credit_units,
                'isMandatory' => (bool) $offering->is_mandatory,
                'prerequisiteCourseIds' => $offering->course->prerequisites
                    ->pluck('id')
                    ->map(fn (int $id): int => $id)
                    ->values()
                    ->all(),
            ],
            'prefill' => $offering === null && $prefillCode !== '' ? $prefill : null,
            'entrySessionId' => request()->integer('entry_session') ?: null,
            'returnTo' => request()->string('return_to')->trim()->toString() ?: null,
            'prerequisiteOptions' => $this->prerequisiteOptions($department, $level, $semester, $academicSession),
        ];
    }

    /**
     * @return array<int, array{id: int, code: string, title: string, units: int, level: string, semester: string}>
     */
    private function prerequisiteOptions(
        Department $department,
        AcademicYear $level,
        Semester $semester,
        ?AcademicSession $academicSession = null,
    ): array {
        $academicSession ??= $this->currentAcademicSessionFor($department);

        if ($academicSession === null) {
            return [];
        }

        return CourseOffering::query()
            ->with(['course', 'academicYearSemester.academicYear', 'academicYearSemester.semester'])
            ->where('academic_session_id', $academicSession->id)
            ->where(function ($query) use ($department): void {
                $this->whereOfferingOwner($query, $department);
            })
            ->where(function ($query) use ($level, $semester): void {
                $query->whereHas('academicYearSemester', function ($query) use ($level, $semester): void {
                    $query
                        ->where('academic_year_id', '<', $level->id)
                        ->orWhere(function ($query) use ($level, $semester): void {
                            $query
                                ->where('academic_year_id', $level->id)
                                ->whereHas('semester', fn ($query) => $query->where('sort_order', '<', $semester->sort_order));
                        });
                });
            })
            ->get()
            ->sortBy(fn (CourseOffering $offering): string => sprintf(
                '%02d-%02d-%s',
                $offering->academicYearSemester?->academic_year_id ?? 0,
                $offering->academicYearSemester?->semester?->sort_order ?? 0,
                $offering->course->code,
            ))
            ->unique('course_id')
            ->map(fn (CourseOffering $offering): array => [
                'id' => (int) $offering->course->id,
                'code' => $offering->course->code,
                'title' => $offering->course->title,
                'units' => (int) $offering->course->credit_units,
                'level' => $offering->academicYearSemester?->academicYear?->name ?? 'Previous Level',
                'semester' => $offering->academicYearSemester?->semester?->name ?? 'Previous Semester',
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $courseIds
     * @return array<int, int>
     */
    private function validatedPrerequisiteIds(
        Department $department,
        AcademicYear $level,
        Semester $semester,
        array $courseIds,
        ?AcademicSession $academicSession = null,
    ): array {
        $ids = collect($courseIds)
            ->map(fn ($courseId): int => (int) $courseId)
            ->filter()
            ->unique()
            ->values();
        $eligibleIds = collect($this->prerequisiteOptions($department, $level, $semester, $academicSession))->pluck('id');

        if ($ids->diff($eligibleIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'prerequisite_course_ids' => 'Prerequisites must come from courses in earlier semesters.',
            ]);
        }

        return $ids->all();
    }

    private function resolveLevel(string $level): AcademicYear
    {
        return AcademicYear::query()->where('slug', $level)->firstOrFail();
    }

    private function resolveSemester(string $semester): Semester
    {
        return Semester::query()->where('slug', $semester)->firstOrFail();
    }

    private function resolveAcademicYearSemester(AcademicYear $academicYear, Semester $semester): AcademicYearSemester
    {
        return AcademicYearSemester::query()
            ->where('academic_year_id', $academicYear->id)
            ->where('semester_id', $semester->id)
            ->firstOrFail();
    }

    /**
     * @param  EloquentCollection<int, AcademicSession>  $academicSessions
     */
    private function selectedFacultyAcademicSession(Request $request, Faculty $faculty, EloquentCollection $academicSessions): ?AcademicSession
    {
        $requestedAcademicSessionId = $request->integer('academic_session');

        if ($requestedAcademicSessionId > 0) {
            $requestedAcademicSession = $academicSessions->firstWhere('id', $requestedAcademicSessionId);

            if ($requestedAcademicSession instanceof AcademicSession) {
                return $requestedAcademicSession;
            }
        }

        return $this->currentAcademicSessionForFaculty($faculty)
            ?? $academicSessions->sortByDesc('starts_on')->first();
    }

    /**
     * @return array{id: int, name: string, startsOn: string, endsOn: string, status: string}
     */
    private function academicSessionPayload(AcademicSession $academicSession): array
    {
        return [
            'id' => (int) $academicSession->id,
            'name' => $academicSession->name,
            'startsOn' => $academicSession->starts_on->toDateString(),
            'endsOn' => $academicSession->ends_on->toDateString(),
            'status' => $this->academicSessionStatus($academicSession),
        ];
    }

    private function academicSessionStatus(AcademicSession $academicSession): string
    {
        if ($academicSession->starts_on->isAfter(today())) {
            return 'Upcoming';
        }

        if ($academicSession->ends_on->isBefore(today())) {
            return 'Past';
        }

        return 'Current';
    }

    /**
     * @return EloquentCollection<int, AcademicSession>
     */
    private function academicSessionsForSchool(School $school): EloquentCollection
    {
        return $school->academicSessions()
            ->orderByDesc('starts_on')
            ->get();
    }

    private function ensureAcademicSessionBelongsToSchool(School $school, AcademicSession $academicSession): void
    {
        abort_unless((int) $academicSession->school_id === (int) $school->id, 404);
    }

    private function ensureFacultyBelongsToSchool(School $school, Faculty $faculty): void
    {
        abort_unless((int) $faculty->school_id === (int) $school->id, 404);
    }

    private function ensureDepartmentBelongsToSchool(School $school, Department $department): void
    {
        $department->loadMissing('faculty');

        abort_unless((int) $department->faculty?->school_id === (int) $school->id, 404);
    }

    private function ensureAcademicSessionBelongsToFaculty(Faculty $faculty, AcademicSession $academicSession): void
    {
        abort_unless((int) $academicSession->school_id === (int) $faculty->school_id, 404);
    }

    private function ensureAcademicSessionCanBeManaged(AcademicSession $academicSession): void
    {
        abort_unless($academicSession->exists, 404);
    }

    private function previousAcademicSessionFor(Faculty $faculty, AcademicSession $academicSession): ?AcademicSession
    {
        return AcademicSession::query()
            ->where('school_id', $faculty->school_id)
            ->whereDate('ends_on', '<', $academicSession->starts_on)
            ->orderByDesc('ends_on')
            ->first();
    }

    /**
     * @return array{sourceSession: AcademicSession, copied: int}
     */
    private function copyFacultyCourseOfferingsFromPreviousSession(
        Faculty $faculty,
        AcademicSession $academicSession,
        ?AcademicYearSemester $academicYearSemester = null,
    ): array {
        $sourceSession = $this->previousAcademicSessionFor($faculty, $academicSession);

        if (! $sourceSession instanceof AcademicSession) {
            throw ValidationException::withMessages([
                'academic_session' => 'There is no previous academic session to copy from.',
            ]);
        }

        $sourceOfferings = CourseOffering::query()
            ->where('owner_type', Faculty::class)
            ->where('owner_id', $faculty->id)
            ->where('academic_session_id', $sourceSession->id)
            ->when(
                $academicYearSemester instanceof AcademicYearSemester,
                fn ($query) => $query->where('academic_year_semester_id', $academicYearSemester->id),
            )
            ->get();
        $copied = 0;

        foreach ($sourceOfferings as $sourceOffering) {
            $offering = $faculty->courseOfferings()->firstOrCreate(
                [
                    'course_id' => $sourceOffering->course_id,
                    'academic_session_id' => $academicSession->id,
                    'academic_year_semester_id' => $sourceOffering->academic_year_semester_id,
                ],
                ['is_mandatory' => $sourceOffering->is_mandatory],
            );

            if ($offering->wasRecentlyCreated) {
                $copied++;
            }
        }

        return [
            'sourceSession' => $sourceSession,
            'copied' => $copied,
        ];
    }

    private function currentAcademicSessionFor(?Department $department): ?AcademicSession
    {
        return $department?->faculty?->school?->academicSessions()
            ->whereDate('starts_on', '<=', today())
            ->whereDate('ends_on', '>=', today())
            ->latest('starts_on')
            ->first();
    }

    private function currentAcademicSessionForFaculty(Faculty $faculty): ?AcademicSession
    {
        return $faculty->school?->academicSessions()
            ->whereDate('starts_on', '<=', today())
            ->whereDate('ends_on', '>=', today())
            ->latest('starts_on')
            ->first();
    }

    private function currentAcademicSessionForSchool(School $school): ?AcademicSession
    {
        return $school->academicSessions()
            ->whereDate('starts_on', '<=', today())
            ->whereDate('ends_on', '>=', today())
            ->latest('starts_on')
            ->first();
    }

    private function requiredCurrentAcademicSession(Department $department): AcademicSession
    {
        $academicSession = $this->currentAcademicSessionFor($department);

        if ($academicSession === null) {
            throw ValidationException::withMessages([
                'code' => 'The school does not have an active academic session yet.',
            ]);
        }

        return $academicSession;
    }

    private function staffCanManageStudentSet(
        StaffProfile $staffProfile,
        Department $department,
        AcademicSession $entryAcademicSession,
    ): bool {
        return $staffProfile->studentSetAdvisorAssignments()
            ->where('department_id', $department->id)
            ->where('entry_academic_session_id', $entryAcademicSession->id)
            ->where('active_marker', 1)
            ->exists();
    }

    private function activePaymentAccountForStaffSet(
        StaffProfile $staffProfile,
        Department $department,
        AcademicSession $entryAcademicSession,
    ): ?PaymentAccount {
        return PaymentAccount::query()
            ->where('department_id', $department->id)
            ->where('staff_profile_id', $staffProfile->id)
            ->where('status', PaymentAccount::StatusActive)
            ->where(function ($query) use ($entryAcademicSession): void {
                $query
                    ->where('entry_academic_session_id', $entryAcademicSession->id)
                    ->orWhereNull('entry_academic_session_id');
            })
            ->get()
            ->sortByDesc(fn (PaymentAccount $paymentAccount): int => $paymentAccount->entry_academic_session_id === null ? 0 : 1)
            ->first();
    }

    private function ensureStaffCanManageDue(StaffProfile $staffProfile, Department $department, Due $due): void
    {
        abort_unless((int) $due->department_id === (int) $department->id, 404);
        abort_unless($this->staffCanManageStudentSet($staffProfile, $department, $due->entryAcademicSession), 404);
    }

    private function publishDueAssignments(Due $due): void
    {
        $students = StudentProfile::query()
            ->where('department_id', $due->department_id)
            ->where('entry_academic_session_id', $due->entry_academic_session_id)
            ->get(['id']);

        foreach ($students as $studentProfile) {
            StudentDueAssignment::query()->firstOrCreate(
                [
                    'due_id' => $due->id,
                    'student_profile_id' => $studentProfile->id,
                ],
                [
                    'amount_kobo' => $due->amount_kobo,
                    'status' => StudentDueAssignment::StatusPending,
                ],
            );
        }
    }

    private function userCanViewDuePayment(User $user, DuePayment $duePayment): bool
    {
        $role = $this->dashboardRole($user);
        $duePayment->loadMissing(['assignment.due', 'studentProfile']);

        if ($role === 'student') {
            return (int) $duePayment->student_profile_id === (int) $user->studentProfile?->id;
        }

        if ($role === 'staff') {
            $staffProfile = $user->staffProfile;
            $department = $staffProfile?->department;

            return $staffProfile instanceof StaffProfile
                && $department instanceof Department
                && (int) $duePayment->assignment->due->department_id === (int) $department->id
                && $this->staffCanManageStudentSet($staffProfile, $department, $duePayment->assignment->due->entryAcademicSession);
        }

        if ($role === 'hod') {
            return (int) $duePayment->assignment->due->department_id === (int) $this->departmentForHod($user)->id;
        }

        if ($role === 'faculty_head') {
            return (int) $duePayment->assignment->due->department?->faculty_id === (int) $this->facultyForFacultyHead($user)->id;
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function duePaymentReceiptLines(DuePayment $duePayment): array
    {
        $due = $duePayment->assignment->due;
        $studentProfile = $duePayment->studentProfile;

        return [
            'GradeManager Payment Receipt',
            'Receipt Number: '.($duePayment->receipt_number ?? 'Pending'),
            'Reference: '.$duePayment->reference,
            'Student: '.($studentProfile->user?->name ?? 'Student'),
            'Registration Number: '.$studentProfile->registration_number,
            'Due: '.$due->title,
            'Amount: '.$this->formatKoboAmount((int) $duePayment->amount_kobo),
            'Paid At: '.$duePayment->paid_at?->toDayDateTimeString(),
            'Gateway Response: '.($duePayment->gateway_response ?? 'Successful'),
        ];
    }

    private function courseOfferingAcademicSession(Request $request, Department $department): AcademicSession
    {
        $department->loadMissing('faculty');
        $schoolId = $department->faculty?->school_id;
        $requestedAcademicSessionId = $request->integer('academic_session');

        if ($requestedAcademicSessionId > 0 && $schoolId !== null) {
            $academicSession = AcademicSession::query()
                ->where('school_id', $schoolId)
                ->whereKey($requestedAcademicSessionId)
                ->first();

            abort_unless($academicSession instanceof AcademicSession, 404);

            return $academicSession;
        }

        return $this->requiredCurrentAcademicSession($department);
    }

    private function requiredCurrentAcademicSessionForFaculty(Faculty $faculty): AcademicSession
    {
        $academicSession = $this->currentAcademicSessionForFaculty($faculty);

        if ($academicSession === null) {
            throw ValidationException::withMessages([
                'code' => 'The school does not have an active academic session yet.',
            ]);
        }

        return $academicSession;
    }

    private function authorizedCourseScheduleUser(Request $request, AcademicYear $level): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $user->loadMissing([
            'staffProfile.department.faculty.school',
            'staffProfile.headedFaculty',
        ]);

        $role = $this->dashboardRole($user);

        abort_unless($role === 'staff', 404);
        abort_unless(collect($this->courseScheduleLevelsFor($user, $role))->contains('slug', $level->slug), 404);

        return $user;
    }

    private function editableCourseOffering(
        Department $department,
        AcademicSession $academicSession,
        CourseOffering $offering,
        AcademicYear $level,
        Semester $semester,
    ): CourseOffering {
        $offering->loadMissing('course.prerequisites');
        $academicYearSemester = $this->resolveAcademicYearSemester($level, $semester);

        abort_unless(
            (int) $offering->academic_session_id === (int) $academicSession->id
            && (int) $offering->academic_year_semester_id === (int) $academicYearSemester->id
            && $offering->owner_type === Department::class
            && (int) $offering->owner_id === (int) $department->id,
            404,
        );

        return $offering;
    }

    private function editableFacultyCourseOffering(
        Faculty $faculty,
        AcademicSession $academicSession,
        CourseOffering $offering,
        AcademicYear $level,
        Semester $semester,
    ): CourseOffering {
        $offering->loadMissing('course.prerequisites');
        $academicYearSemester = $this->resolveAcademicYearSemester($level, $semester);

        abort_unless(
            (int) $offering->academic_session_id === (int) $academicSession->id
            && (int) $offering->academic_year_semester_id === (int) $academicYearSemester->id
            && $offering->owner_type === Faculty::class
            && (int) $offering->owner_id === (int) $faculty->id,
            404,
        );

        return $offering;
    }

    private function manageableCourseOffering(
        Department $department,
        CourseOffering $offering,
        AcademicYear $level,
        Semester $semester,
    ): CourseOffering {
        $offering->loadMissing('course.prerequisites');
        $academicSession = $this->currentAcademicSessionFor($department);
        $academicYearSemester = $this->resolveAcademicYearSemester($level, $semester);

        abort_unless(
            $academicSession !== null
            && (int) $offering->academic_session_id === (int) $academicSession->id
            && (int) $offering->academic_year_semester_id === (int) $academicYearSemester->id
            && $this->offeringBelongsToDepartmentContext($department, $offering),
            404,
        );

        return $offering;
    }

    private function offeringBelongsToDepartmentContext(Department $department, CourseOffering $offering): bool
    {
        if ($offering->owner_type === Department::class && (int) $offering->owner_id === (int) $department->id) {
            return true;
        }

        return $department->faculty !== null
            && $offering->owner_type === Faculty::class
            && (int) $offering->owner_id === (int) $department->faculty->id;
    }

    private function whereOfferingOwner($query, Department $department): void
    {
        $faculty = $department->faculty;

        $query->where(function ($query) use ($department, $faculty): void {
            $query->where(function ($query) use ($department): void {
                $query
                    ->where('owner_type', Department::class)
                    ->where('owner_id', $department->id);
            });

            if ($faculty !== null) {
                $query->orWhere(function ($query) use ($faculty): void {
                    $query
                        ->where('owner_type', Faculty::class)
                        ->where('owner_id', $faculty->id);
                });
            }
        });
    }

    /**
     * @return array<int, array{id: int, code: string, title: string}>
     */
    private function coursePrerequisitePayload(Course $course): array
    {
        return $course->prerequisites
            ->sortBy('code')
            ->map(fn (Course $prerequisite): array => [
                'id' => (int) $prerequisite->id,
                'code' => $prerequisite->code,
                'title' => $prerequisite->title,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string, slug: string}
     */
    private function semesterPayload(Semester $semester): array
    {
        return [
            'id' => (int) $semester->id,
            'name' => $semester->name,
            'slug' => $semester->slug,
        ];
    }

    /**
     * @return array<int, array{id: string, name: string, level: string, session: string, isEditable: bool, totalUnits: int, courses: array<int, array{code: string, title: string, units: int, type: string, status: string}>}>
     */
    private function studentHistoricalCourseScheduleSemesters(
        ?Department $department,
        AcademicYear $currentLevel,
        string $currentSession,
    ): array {
        $semesters = Semester::query()
            ->whereIn('slug', ['harmattan', 'rain'])
            ->orderByDesc('sort_order')
            ->get()
            ->values();

        return AcademicYear::query()
            ->where('id', '<', $currentLevel->id)
            ->orderByDesc('id')
            ->get()
            ->flatMap(function (AcademicYear $level) use ($department, $semesters, $currentLevel, $currentSession): array {
                return $semesters
                    ->map(function (Semester $semester) use ($department, $level, $currentLevel, $currentSession): array {
                        $courses = $this->studentScheduleCourses($department, $level, $semester, 'Passed');
                        $courses = collect($courses)
                            ->map(function (array $course) use ($level, $semester): array {
                                $grade = $this->gradeForScore($this->scoreFor($level, $semester, $course['code']));

                                return [
                                    ...$course,
                                    'status' => $grade === 'F' ? 'Failed' : 'Passed',
                                ];
                            })
                            ->values()
                            ->all();

                        return [
                            'id' => $level->slug.'-'.$semester->slug,
                            'name' => $semester->name.' Semester',
                            'level' => $level->name,
                            'session' => $this->sessionForLevel($currentSession, (int) $currentLevel->id, (int) $level->id),
                            'isEditable' => false,
                            'totalUnits' => (int) collect($courses)->sum('units'),
                            'courses' => $courses,
                        ];
                    })
                    ->all();
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{code: string, title: string, units: int, type: string, status: string}>
     */
    private function studentScheduleCourses(?Department $department, AcademicYear $level, Semester $semester, string $status): array
    {
        return collect($this->studentResultCourses($department, $level, $semester))
            ->map(fn (array $course): array => [
                ...$course,
                'type' => $this->courseType($course['code']),
                'status' => $status,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{code: string, title: string, units: int, type: string, status: string}>  $currentCourses
     * @return array<int, array{code: string, title: string, units: int, type: string}>
     */
    private function studentCourseScheduleOptions(array $currentCourses, AcademicYear $currentLevel): array
    {
        return collect($currentCourses)
            ->map(fn (array $course): array => [
                'code' => $course['code'],
                'title' => $course['title'],
                'units' => $course['units'],
                'type' => $course['type'],
            ])
            ->merge($this->extraCourseOptionsFor($currentLevel))
            ->unique('code')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{code: string, title: string, units: int, type: string}>
     */
    private function extraCourseOptionsFor(AcademicYear $level): array
    {
        return match ($level->slug) {
            '300-level' => [
                ['code' => 'GST 301', 'title' => 'Entrepreneurship Studies I', 'units' => 2, 'type' => 'General'],
                ['code' => 'CPE 307', 'title' => 'Computer Hardware Laboratory', 'units' => 1, 'type' => 'Core'],
                ['code' => 'CPE 204', 'title' => 'Signals and Systems', 'units' => 3, 'type' => 'Carryover'],
            ],
            '400-level' => [
                ['code' => 'CPE 411', 'title' => 'Computer Engineering Design Studio', 'units' => 2, 'type' => 'Core'],
                ['code' => 'CPE 304', 'title' => 'Database Systems', 'units' => 3, 'type' => 'Carryover'],
            ],
            '500-level' => [
                ['code' => 'CPE 511', 'title' => 'Distributed Systems', 'units' => 2, 'type' => 'Elective'],
                ['code' => 'CPE 403', 'title' => 'Computer Networks', 'units' => 3, 'type' => 'Carryover'],
            ],
            default => [
                ['code' => 'GST 103', 'title' => 'Nigerian Peoples and Culture', 'units' => 2, 'type' => 'General'],
            ],
        };
    }

    private function courseType(string $courseCode): string
    {
        if (str_starts_with($courseCode, 'CPE')) {
            return 'Departmental Course';
        }

        if (str_starts_with($courseCode, 'GST')) {
            return 'Faculty Course';
        }

        return 'Faculty Course';
    }

    private function courseAdvisorNameForStudent(
        StudentProfile $studentProfile,
        ?Department $department = null,
        string $fallback = 'Department Course Adviser',
    ): string {
        $department ??= $studentProfile->department;
        $entryAcademicSession = $studentProfile->entryAcademicSession;

        if ($department === null || $entryAcademicSession === null) {
            return $fallback;
        }

        return $this->courseAdvisorNameForSet($department, $entryAcademicSession, $fallback);
    }

    private function courseAdvisorNameForSet(
        ?Department $department,
        ?AcademicSession $entryAcademicSession,
        string $fallback = 'Department Course Adviser',
    ): string {
        if ($department === null || $entryAcademicSession === null) {
            return $fallback;
        }

        $assignment = $department->studentSetAdvisorAssignments()
            ->with('staffProfile.user')
            ->where('entry_academic_session_id', $entryAcademicSession->id)
            ->where('active_marker', 1)
            ->first();

        return $assignment?->staffProfile?->user?->name ?? $fallback;
    }

    private function sessionForLevel(string $currentSession, int $currentLevelId, int $levelId): string
    {
        $offset = $currentLevelId - $levelId;

        if (preg_match('/^(?<start>\d{4})\/(?<end>\d{4})$/', $currentSession, $matches) !== 1) {
            $startYear = 2025 - $offset;

            return $startYear.'/'.($startYear + 1);
        }

        $startYear = (int) $matches['start'] - $offset;

        return $startYear.'/'.($startYear + 1);
    }

    /**
     * @return array{name: string, slug: string, gpa: string, totalUnits: int, courses: array<int, array{code: string, title: string, units: int, score: int, grade: string}>}
     */
    private function studentResultSemester(?Department $department, AcademicYear $level, Semester $semester): array
    {
        $courses = collect($this->studentResultCourses($department, $level, $semester))
            ->map(function (array $course) use ($level, $semester): array {
                $score = $this->scoreFor($level, $semester, $course['code']);

                return [
                    ...$course,
                    'score' => $score,
                    'grade' => $this->gradeForScore($score),
                ];
            })
            ->values()
            ->all();

        return [
            'name' => $semester->name,
            'slug' => $semester->slug,
            'gpa' => $this->gpaFor($courses),
            'totalUnits' => (int) collect($courses)->sum('units'),
            'courses' => $courses,
        ];
    }

    /**
     * @return array{name: string, slug: string, gpa: string, totalUnits: int, courses: array<int, array{code: string, title: string, units: int, score: int, grade: string}>}
     */
    private function studentResultSemesterForProfile(
        ?Department $department,
        AcademicYear $level,
        Semester $semester,
        ?StudentProfile $studentProfile,
    ): array {
        $semesterResult = $this->studentResultSemester($department, $level, $semester);

        if (! $studentProfile instanceof StudentProfile) {
            return $semesterResult;
        }

        $courses = collect($semesterResult['courses'])
            ->map(function (array $course) use ($studentProfile, $level, $semester): array {
                $score = $this->studentScoreFor($studentProfile, $level, $semester, $course['code'], $course['score']);

                return [
                    ...$course,
                    'score' => $score,
                    'grade' => $this->gradeForScore($score),
                ];
            })
            ->values()
            ->all();

        return [
            ...$semesterResult,
            'gpa' => $this->gpaFor($courses),
            'totalUnits' => (int) collect($courses)->sum('units'),
            'courses' => $courses,
        ];
    }

    /**
     * @return array<int, array{code: string, title: string, units: int}>
     */
    private function studentResultCourses(?Department $department, AcademicYear $level, Semester $semester): array
    {
        if ($department === null) {
            return $this->fallbackResultCourses($level, $semester);
        }

        $academicSession = $this->currentAcademicSessionFor($department);
        $academicYearSemester = $this->resolveAcademicYearSemester($level, $semester);
        $faculty = $department->faculty;

        if ($academicSession === null) {
            return $this->fallbackResultCourses($level, $semester);
        }

        $offerings = CourseOffering::query()
            ->with('course')
            ->where('academic_session_id', $academicSession->id)
            ->where('academic_year_semester_id', $academicYearSemester->id)
            ->where(function ($query) use ($department, $faculty): void {
                $query->where(function ($query) use ($department): void {
                    $query
                        ->where('owner_type', Department::class)
                        ->where('owner_id', $department->id);
                });

                if ($faculty !== null) {
                    $query->orWhere(function ($query) use ($faculty): void {
                        $query
                            ->where('owner_type', Faculty::class)
                            ->where('owner_id', $faculty->id);
                    });
                }
            })
            ->get()
            ->sortBy(fn (CourseOffering $offering): string => $offering->course->code)
            ->values();

        if ($offerings->isEmpty()) {
            return $this->fallbackResultCourses($level, $semester);
        }

        return $offerings
            ->map(fn (CourseOffering $offering): array => [
                'code' => $offering->course->code,
                'title' => $offering->course->title,
                'units' => (int) $offering->course->credit_units,
            ])
            ->all();
    }

    /**
     * @return array<int, array{code: string, title: string, units: int}>
     */
    private function fallbackResultCourses(AcademicYear $level, Semester $semester): array
    {
        return collect(self::RESULT_COURSES[$level->slug][$semester->slug] ?? [])
            ->map(fn (array $course): array => [
                'code' => $course[0],
                'title' => $course[1],
                'units' => $course[2],
            ])
            ->all();
    }

    private function scoreFor(AcademicYear $level, Semester $semester, string $courseCode): int
    {
        return self::RESULT_SCORES[$level->slug][$semester->slug][$courseCode]
            ?? 50 + (abs(crc32($level->slug.$semester->slug.$courseCode)) % 35);
    }

    private function studentScoreFor(
        StudentProfile $studentProfile,
        AcademicYear $level,
        Semester $semester,
        string $courseCode,
        int $baseScore,
    ): int {
        $variance = (abs(crc32($studentProfile->registration_number.$level->slug.$semester->slug.$courseCode)) % 21) - 10;

        return max(32, min(91, $baseScore + $variance));
    }

    private function gradeForScore(int $score): string
    {
        return match (true) {
            $score >= 70 => 'A',
            $score >= 60 => 'B',
            $score >= 50 => 'C',
            $score >= 45 => 'D',
            $score >= 40 => 'E',
            default => 'F',
        };
    }

    private function gradePoint(string $grade): int
    {
        return match ($grade) {
            'A' => 5,
            'B' => 4,
            'C' => 3,
            'D' => 2,
            'E' => 1,
            default => 0,
        };
    }

    /**
     * @param  array<int, array{units: int, grade: string}>  $courses
     */
    private function gpaFor(array $courses): string
    {
        $totalUnits = (int) collect($courses)->sum('units');

        if ($totalUnits === 0) {
            return '0.00';
        }

        $weightedPoints = collect($courses)->sum(
            fn (array $course): int => $course['units'] * $this->gradePoint($course['grade']),
        );

        return number_format($weightedPoints / $totalUnits, 2);
    }

    /**
     * @return array<int, array{title: string, context: string, amount: string, dueDate: string}>
     */
    private function studentPendingDues(StudentProfile $studentProfile): array
    {
        return StudentDueAssignment::query()
            ->with('due')
            ->where('student_profile_id', $studentProfile->id)
            ->where('status', StudentDueAssignment::StatusPending)
            ->whereHas('due', fn ($query) => $query->where('status', Due::StatusPublished))
            ->latest()
            ->get()
            ->sortBy(fn (StudentDueAssignment $assignment): string => $assignment->due->title)
            ->values()
            ->map(fn (StudentDueAssignment $assignment): array => [
                'title' => $assignment->due->title,
                'context' => $assignment->due->description ?? 'Pending due',
                'amount' => $this->formatKoboAmount((int) $assignment->amount_kobo),
                'dueDate' => $assignment->due->due_on?->toFormattedDateString() ?? 'No due date',
            ])
            ->all();
    }

    private function staffResultsPayload(User $user, string $role, string $section): ?array
    {
        if ($role !== 'staff' || $section !== 'result-management') {
            return null;
        }

        $staffProfile = $user->staffProfile;
        $department = $staffProfile?->department;

        if (! $staffProfile instanceof StaffProfile || ! $department instanceof Department) {
            return [
                'department' => null,
                'assignedSets' => [],
                'resultSheets' => [],
                'importUrl' => route('dashboard.results.import'),
            ];
        }

        $assignedSets = $this->assignedEntrySessionsForStaff($staffProfile, $department);
        $resultSheets = ResultSheet::query()
            ->with($this->resultSheetPayloadRelations())
            ->where('department_id', $department->id)
            ->whereIn('entry_academic_session_id', $assignedSets->pluck('id'))
            ->latest('updated_at')
            ->get();

        return [
            'department' => $this->departmentPayload($department),
            'assignedSets' => $assignedSets
                ->map(fn (AcademicSession $academicSession): array => $this->studentSetContextPayload($department, $academicSession))
                ->values()
                ->all(),
            'resultSheets' => $resultSheets
                ->map(fn (ResultSheet $sheet): array => $this->resultSheetPayload($sheet))
                ->values()
                ->all(),
            'importUrl' => route('dashboard.results.import'),
        ];
    }

    private function staffResultImportFormPayload(Department $department, StaffProfile $staffProfile): array
    {
        $assignedSets = $this->assignedEntrySessionsForStaff($staffProfile, $department);
        $academicSessions = $this->academicSessionsForDepartment($department);
        $yearSemesters = AcademicYearSemester::query()
            ->with(['academicYear', 'semester'])
            ->whereHas('academicYear', fn ($query) => $query->whereIn('id', $department->visibleAcademicYears()->pluck('id')))
            ->whereHas('semester', fn ($query) => $query->whereIn('slug', ['harmattan', 'rain']))
            ->orderBy('academic_year_id')
            ->orderBy('semester_id')
            ->get();
        $schoolId = (int) $department->faculty()->value('school_id');
        $knownCourses = Course::query()
            ->where('school_id', $schoolId)
            ->orderBy('code')
            ->get(['code', 'title', 'credit_units']);
        $sessionIds = $academicSessions->pluck('id');
        $courseOfferings = CourseOffering::query()
            ->with(['course', 'academicYearSemester.academicYear', 'academicYearSemester.semester'])
            ->whereIn('academic_session_id', $sessionIds)
            ->where(function ($query) use ($department): void {
                $this->whereOfferingOwner($query, $department);
            })
            ->get()
            ->sortBy(fn (CourseOffering $offering): string => sprintf(
                '%s-%02d-%02d-%s',
                $offering->academic_session_id,
                $offering->academicYearSemester?->academic_year_id ?? 0,
                $offering->academicYearSemester?->semester?->sort_order ?? 0,
                $offering->course->code,
            ));

        return [
            'department' => $this->departmentPayload($department),
            'academicSessions' => $academicSessions
                ->map(fn (AcademicSession $academicSession): array => $this->academicSessionPayload($academicSession))
                ->values()
                ->all(),
            'assignedSets' => $assignedSets
                ->map(fn (AcademicSession $academicSession): array => $this->studentSetContextPayload($department, $academicSession))
                ->values()
                ->all(),
            'yearSemesters' => $yearSemesters
                ->map(fn (AcademicYearSemester $yearSemester): array => [
                    'id' => (int) $yearSemester->id,
                    'level' => $this->levelPayload($yearSemester->academicYear),
                    'semester' => $this->semesterPayload($yearSemester->semester),
                    'label' => $this->shortLevelName($yearSemester->academicYear).' '.$yearSemester->semester->name.' Semester',
                ])
                ->values()
                ->all(),
            'knownCourses' => $knownCourses
                ->map(fn (Course $course): array => [
                    'code' => $course->code,
                    'title' => $course->title,
                    'creditUnits' => (int) $course->credit_units,
                ])
                ->values()
                ->all(),
            'courseOfferings' => $courseOfferings
                ->map(fn (CourseOffering $offering): array => [
                    'id' => (int) $offering->id,
                    'academicSessionId' => (int) $offering->academic_session_id,
                    'academicYearSemesterId' => (int) $offering->academic_year_semester_id,
                    'level' => $this->levelPayload($offering->academicYearSemester->academicYear),
                    'semester' => $this->semesterPayload($offering->academicYearSemester->semester),
                    'label' => $this->shortLevelName($offering->academicYearSemester->academicYear).' '.$offering->academicYearSemester->semester->name.' Semester',
                    'code' => $offering->course->code,
                    'title' => $offering->course->title,
                    'creditUnits' => (int) $offering->course->credit_units,
                ])
                ->values()
                ->all(),
            'defaults' => [
                'entryAcademicSessionId' => $assignedSets->first()?->id,
                'academicYearSemesterId' => $yearSemesters->first()?->id,
            ],
        ];
    }

    private function userCanViewResultSheet(User $user, string $role, ResultSheet $resultSheet): bool
    {
        if ($role === 'hod') {
            $department = $this->departmentForHod($user);

            return (int) $resultSheet->department_id === (int) $department->id;
        }

        if ($role !== 'staff') {
            return false;
        }

        $user->loadMissing('staffProfile.department');

        $staffProfile = $user->staffProfile;
        $department = $staffProfile?->department;

        if (! $staffProfile instanceof StaffProfile || ! $department instanceof Department) {
            return false;
        }

        if ((int) $resultSheet->department_id !== (int) $department->id) {
            return false;
        }

        return $this->assignedEntrySessionIdsForStaff($staffProfile, $department)
            ->contains((int) $resultSheet->entry_academic_session_id);
    }

    private function resultSheetDetailPayload(ResultSheet $sheet, string $role, string $mode): array
    {
        return [
            'sheet' => $this->resultSheetPayload($sheet),
            'mode' => $mode,
            'canReview' => $role === 'hod' && $mode === 'review' && $sheet->status === ResultSheet::StatusSubmitted,
            'canEdit' => $role === 'staff' && $sheet->canBeEditedByAdvisor(),
            'returnSection' => $role === 'hod'
                ? ($mode === 'review' ? 'result-approvals' : 'student-results')
                : 'result-management',
            'returnLabel' => $role === 'hod'
                ? ($mode === 'review' ? 'Back to Result Approvals' : 'Back to Student Results')
                : 'Back to Result Management',
        ];
    }

    private function resultSheetCorrectionNote(string $reason, string $note): ?string
    {
        $reasonLabel = match ($reason) {
            'incomplete_data' => 'Incomplete data',
            'incorrect_grades' => 'Incorrect grades',
            'missing_students_or_courses' => 'Missing students or courses',
            default => null,
        };

        if ($reasonLabel !== null && $note !== '') {
            return $reasonLabel.': '.$note;
        }

        if ($reasonLabel !== null) {
            return $reasonLabel;
        }

        return $note === '' ? null : $note;
    }

    /**
     * @return array<int, string>
     */
    private function resultSheetPayloadRelations(): array
    {
        return [
            'academicSession',
            'entryAcademicSession',
            'academicYearSemester.academicYear',
            'academicYearSemester.semester',
            'submittedBy',
            'reviewedBy',
            'courses.courseOffering.academicYearSemester',
            'studentResults.studentProfile.user',
            'studentResults.courseResults',
        ];
    }

    private function resultSheetPayload(ResultSheet $sheet): array
    {
        $sheet->loadMissing($this->resultSheetPayloadRelations());

        $courses = $sheet->courses->values();
        $rows = $sheet->studentResults
            ->sortBy(fn (StudentSemesterResult $result): string => $result->studentProfile?->user?->name ?? '')
            ->values();
        $averageGpa = $rows->isEmpty()
            ? '0.00'
            : number_format($rows->average(fn (StudentSemesterResult $result): float => (float) $result->gpa), 2);

        return [
            'id' => (int) $sheet->id,
            'status' => $sheet->status,
            'statusLabel' => $this->resultSheetStatusLabel($sheet->status),
            'correctionNote' => $sheet->correction_note,
            'academicSession' => $this->academicSessionPayload($sheet->academicSession),
            'entrySession' => $this->academicSessionPayload($sheet->entryAcademicSession),
            'level' => $this->levelPayload($sheet->academicYearSemester->academicYear),
            'semester' => $this->semesterPayload($sheet->academicYearSemester->semester),
            'submittedBy' => $sheet->submittedBy === null ? null : [
                'name' => $sheet->submittedBy->name,
                'email' => $sheet->submittedBy->email,
                'avatar' => $sheet->submittedBy->profilePhotoUrl(),
                'profileUrl' => $this->staffProfileUrl($sheet->submittedBy),
            ],
            'submittedAt' => $sheet->submitted_at?->format('M j, Y'),
            'approvedAt' => $sheet->approved_at?->format('M j, Y'),
            'studentCount' => $rows->count(),
            'courseCount' => $courses->count(),
            'averageGpa' => $averageGpa,
            'courses' => $courses
                ->map(fn (ResultSheetCourse $course): array => [
                    'id' => (int) $course->id,
                    'code' => $course->course_code,
                    'title' => $course->course_title,
                    'creditUnits' => (int) $course->credit_units,
                    'isCarryover' => $course->courseOffering?->academic_year_semester_id !== null
                        && (int) $course->courseOffering->academic_year_semester_id !== (int) $sheet->academic_year_semester_id,
                ])
                ->all(),
            'rows' => $rows
                ->map(function (StudentSemesterResult $result, int $index) use ($courses): array {
                    $courseResults = $result->courseResults->keyBy('result_sheet_course_id');

                    return [
                        'id' => (int) $result->id,
                        'serialNumber' => $index + 1,
                        'student' => [
                            'name' => $result->studentProfile?->user?->name ?? 'Student',
                            'avatar' => $result->studentProfile?->user?->profilePhotoUrl(),
                            'registrationNumber' => $result->studentProfile?->registration_number ?? '',
                        ],
                        'grades' => $courses
                            ->map(function (ResultSheetCourse $course) use ($courseResults): array {
                                $courseResult = $courseResults->get($course->id);

                                return [
                                    'courseId' => (int) $course->id,
                                    'grade' => $courseResult?->grade,
                                    'gradePoint' => $courseResult?->grade_point,
                                    'qualityPoints' => $courseResult?->quality_points,
                                ];
                            })
                            ->values()
                            ->all(),
                        'totalUnits' => (int) $result->total_units,
                        'gpa' => number_format((float) $result->gpa, 2),
                    ];
                })
                ->all(),
        ];
    }

    private function resultSheetStatusLabel(string $status): string
    {
        return match ($status) {
            ResultSheet::StatusSubmitted => 'Awaiting HOD Review',
            ResultSheet::StatusReturned => 'Returned for Correction',
            ResultSheet::StatusApproved => 'Approved',
            default => 'Draft',
        };
    }

    /**
     * @return array{
     *     department: array{name: string, abbreviation: string, minimumYearsToDegree: int}|null,
     *     assignedSets: array<int, array{entrySession: array{id: int, name: string, startsOn: string, endsOn: string, status: string}, currentLevel: array{id: int, title: string, slug: string}|null, advisorName: string|null}>,
     *     totalStudents: int,
     *     students: array<int, array{
     *         id: int,
     *         fullName: string,
     *         email: string,
     *         registrationNumber: string,
     *         avatar: string|null,
     *         currentLevel: array{id: int, title: string, slug: string}|null,
     *         entrySession: array{id: int, name: string, startsOn: string, endsOn: string, status: string}|null,
     *         advisorName: string,
     *         pendingCarryovers: array<int, string>,
     *         levelGpas: array<int, array{level: string, gpa: string}>,
     *         cgpa: string,
     *         profileUrl: string
     *     }>
     * }|null
     */
    private function staffStudentsPayload(User $user, string $role, string $section): ?array
    {
        if ($role !== 'staff' || $section !== 'students') {
            return null;
        }

        $staffProfile = $user->staffProfile;
        $department = $staffProfile?->department;

        if ($staffProfile === null || $department === null) {
            return [
                'department' => null,
                'assignedSets' => [],
                'totalStudents' => 0,
                'students' => [],
            ];
        }

        $assignedSets = $this->assignedEntrySessionsForStaff($staffProfile, $department);
        $students = $this->assignedStudentsForStaff($staffProfile, $department);

        return [
            'department' => $this->departmentPayload($department),
            'assignedSets' => $assignedSets
                ->map(fn (AcademicSession $academicSession): array => $this->studentSetContextPayload($department, $academicSession))
                ->values()
                ->all(),
            'totalStudents' => $students->count(),
            'students' => $students
                ->map(fn (StudentProfile $studentProfile): array => $this->staffStudentPayload($studentProfile, $department))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{
     *     id: int,
     *     fullName: string,
     *     email: string,
     *     registrationNumber: string,
     *     avatar: string|null,
     *     currentLevel: array{id: int, title: string, slug: string}|null,
     *     entrySession: array{id: int, name: string, startsOn: string, endsOn: string, status: string}|null,
     *     advisorName: string,
     *     pendingCarryovers: array<int, string>,
     *     levelGpas: array<int, array{level: string, gpa: string}>,
     *     cgpa: string,
     *     profileUrl: string
     * }
     */
    private function staffStudentPayload(StudentProfile $studentProfile, Department $department): array
    {
        $currentLevel = $studentProfile->currentLevel;
        $levels = $currentLevel === null
            ? collect()
            : AcademicYear::query()
                ->where('id', '<=', $currentLevel->id)
                ->orderBy('id')
                ->get();
        $levelResults = $levels
            ->map(fn (AcademicYear $level): array => [
                'level' => $this->shortLevelName($level),
                'courses' => $this->studentLevelResultCourses($department, $level, $studentProfile),
            ])
            ->values()
            ->all();
        $levelGpas = collect($levelResults)
            ->map(fn (array $levelResult): array => [
                'level' => $levelResult['level'],
                'gpa' => $this->gpaFor($levelResult['courses']),
            ])
            ->all();

        return [
            'id' => (int) $studentProfile->getKey(),
            'fullName' => $studentProfile->user->name,
            'email' => $studentProfile->user->email,
            'registrationNumber' => $studentProfile->registration_number,
            'avatar' => $studentProfile->user->profilePhotoUrl(),
            'currentLevel' => $currentLevel === null ? null : $this->levelPayload($currentLevel),
            'entrySession' => $studentProfile->entryAcademicSession === null
                ? null
                : $this->academicSessionPayload($studentProfile->entryAcademicSession),
            'advisorName' => $this->courseAdvisorNameForStudent($studentProfile, $department, 'Not assigned'),
            'pendingCarryovers' => collect($this->studentResits($currentLevel))
                ->pluck('code')
                ->values()
                ->all(),
            'levelGpas' => $levelGpas,
            'cgpa' => $this->gpaFor(
                collect($levelResults)
                    ->flatMap(fn (array $levelResult): array => $levelResult['courses'])
                    ->values()
                    ->all(),
            ),
            'profileUrl' => route('dashboard.students.show', [
                'registrationNumber' => $studentProfile->registration_number,
            ]),
        ];
    }

    /**
     * @return array<int, array{code: string, title: string, units: int, score: int, grade: string}>
     */
    private function studentLevelResultCourses(Department $department, AcademicYear $level, ?StudentProfile $studentProfile = null): array
    {
        $semesters = Semester::query()
            ->whereIn('slug', ['harmattan', 'rain'])
            ->orderBy('sort_order')
            ->get();

        return $semesters
            ->flatMap(fn (Semester $semester): array => $this->studentResultSemesterForProfile($department, $level, $semester, $studentProfile)['courses'])
            ->values()
            ->all();
    }

    private function shortLevelName(AcademicYear $level): string
    {
        if (preg_match('/^(?<level>\d+)00 Level$/', $level->name, $matches) === 1) {
            return $matches['level'].'00L';
        }

        return str_replace(' Level', 'L', $level->name);
    }

    private function hodPayload(Request $request, User $user, string $role): ?array
    {
        if ($role !== 'hod') {
            return null;
        }

        $staffProfile = $user->staffProfile;
        $department = $staffProfile?->department;

        if (! $staffProfile instanceof StaffProfile || ! $department instanceof Department) {
            return null;
        }

        $department->loadMissing('faculty');
        $levels = $department->visibleAcademicYears();
        $academicSessions = $this->academicSessionsForDepartment($department);
        $selectedAcademicSession = $this->selectedDepartmentAcademicSession($request, $department, $academicSessions);
        $selectedLevel = $this->selectedHodAcademicYear($request, $levels);
        $students = $department->studentProfiles()
            ->with(['currentLevel', 'entryAcademicSession', 'user'])
            ->get()
            ->sortBy(fn (StudentProfile $studentProfile): string => sprintf(
                '%02d-%s',
                $studentProfile->current_academic_year_id,
                $studentProfile->user->name,
            ))
            ->values();
        $staff = StaffProfile::query()
            ->with([
                'user',
                'studentSetAdvisorAssignments.entryAcademicSession',
                'headedDepartment',
                'headedFaculty',
            ])
            ->where('department_id', $department->id)
            ->whereHas('user', fn ($query) => $query
                ->where('school_id', $department->faculty?->school_id)
                ->where('account_type', AccountType::Staff))
            ->get()
            ->sortBy(fn (StaffProfile $profile): string => $profile->user?->name ?? '')
            ->values();
        $setAdvisors = $this->hodSetAdvisorPayload($department, $students);
        $resultSubmissions = $this->hodResultSubmissionPayload($department, $levels);
        $departmentResultSheets = ResultSheet::query()
            ->with($this->resultSheetPayloadRelations())
            ->where('department_id', $department->id)
            ->latest('updated_at')
            ->get();

        return [
            'department' => [
                'name' => $department->name,
                'abbreviation' => $department->abbreviation,
                'minimumYearsToDegree' => $department->minimum_years_to_degree,
            ],
            'faculty' => $department->faculty?->only(['name', 'abbreviation']),
            'metrics' => [
                'students' => $students->count(),
                'activeStaff' => $staff->filter(fn (StaffProfile $profile): bool => $profile->user?->activated_at !== null)->count(),
                'pendingSignups' => $staff->filter(fn (StaffProfile $profile): bool => $profile->user?->activated_at === null)->count(),
                'setsWithoutAdvisors' => collect($setAdvisors)->whereNull('advisor')->count(),
                'resultsAwaitingReview' => $departmentResultSheets
                    ->where('status', ResultSheet::StatusSubmitted)
                    ->count(),
                'missingResults' => collect($resultSubmissions)->where('status', 'Missing')->count(),
                'studentsWithCarryovers' => $students
                    ->filter(fn (StudentProfile $studentProfile): bool => $studentProfile->currentLevel !== null
                        && $this->studentResits($studentProfile->currentLevel) !== [])
                    ->count(),
            ],
            'staff' => $staff
                ->map(fn (StaffProfile $profile): array => $this->hodStaffPayload($profile, $department))
                ->values()
                ->all(),
            'students' => $students
                ->map(fn (StudentProfile $studentProfile): array => $this->staffStudentPayload($studentProfile, $department))
                ->values()
                ->all(),
            'levels' => $levels
                ->map(fn (AcademicYear $level): array => $this->levelPayload($level))
                ->values()
                ->all(),
            'setAdvisors' => $setAdvisors,
            'resultSubmissions' => $resultSubmissions,
            'carryoverSummaries' => $this->staffCarryoverSummaries($students),
            'academicSessions' => $academicSessions
                ->map(fn (AcademicSession $academicSession): array => $this->academicSessionPayload($academicSession))
                ->values()
                ->all(),
            'courseOfferings' => $this->hodCourseOfferingsPayload($department, $selectedAcademicSession, $selectedLevel),
            'studentResults' => $this->hodStudentResultsPayload($department, $students, $resultSubmissions, $departmentResultSheets),
            'settings' => [
                'hod' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->profilePhotoUrl(),
                ],
            ],
        ];
    }

    private function hodStaffPayload(StaffProfile $staffProfile, Department $department): array
    {
        $staffProfile->loadMissing(['user', 'studentSetAdvisorAssignments.entryAcademicSession', 'headedDepartment', 'headedFaculty']);
        $user = $staffProfile->user;

        $role = $this->staffRolePayload($staffProfile, $department);

        return [
            'id' => (int) $user?->id,
            'staffProfileId' => (int) $staffProfile->id,
            'name' => $user?->name ?? 'Staff Member',
            'email' => $user?->email,
            'avatar' => $user?->profilePhotoUrl(),
            'role' => $role['role'],
            'roleContext' => $role['roleContext'],
            'status' => $user?->activated_at === null ? 'Pending signup' : 'Active',
            'accessCode' => $user?->access_code,
            'profileUrl' => $this->staffProfileUrl($user),
            'assignedSets' => $staffProfile->studentSetAdvisorAssignments
                ->filter(fn ($assignment): bool => (int) $assignment->department_id === (int) $department->id && (int) $assignment->active_marker === 1)
                ->sortByDesc(fn ($assignment): mixed => $assignment->entryAcademicSession?->starts_on)
                ->map(fn ($assignment): array => $this->studentSetContextPayload($department, $assignment->entryAcademicSession))
                ->values()
                ->all(),
        ];
    }

    private function staffRecordPayload(StaffProfile $staffProfile): array
    {
        $staffProfile->loadMissing([
            'department.faculty',
            'headedDepartment',
            'headedFaculty',
            'studentSetAdvisorAssignments.department',
            'studentSetAdvisorAssignments.entryAcademicSession',
            'user',
        ]);

        $user = $staffProfile->user;
        $department = $staffProfile->department;
        $faculty = $department?->faculty;
        $role = $this->staffRolePayload($staffProfile, $department);
        $assignedSets = $staffProfile->studentSetAdvisorAssignments
            ->filter(fn ($assignment): bool => (int) $assignment->active_marker === 1
                && $assignment->department instanceof Department
                && $assignment->entryAcademicSession instanceof AcademicSession)
            ->sortByDesc(fn ($assignment): mixed => $assignment->entryAcademicSession?->starts_on)
            ->map(fn ($assignment): array => $this->studentSetContextPayload($assignment->department, $assignment->entryAcademicSession))
            ->values()
            ->all();

        return [
            'staff' => [
                'name' => $user?->name ?? 'Staff Member',
                'email' => $user?->email,
                'avatar' => $user?->profilePhotoUrl(),
                'role' => $role['role'],
                'roleContext' => $role['roleContext'],
                'status' => $user?->activated_at === null ? 'Pending signup' : 'Active',
                'accessCode' => $user?->access_code,
                'department' => $this->departmentPayload($department),
                'faculty' => $faculty === null ? null : $faculty->only(['name', 'abbreviation']),
                'assignedSets' => $assignedSets,
            ],
        ];
    }

    /**
     * @return array{role: string, roleContext: string|null}
     */
    private function staffRolePayload(?StaffProfile $staffProfile, ?Department $contextDepartment = null): array
    {
        if (! $staffProfile instanceof StaffProfile) {
            return [
                'role' => 'Staff',
                'roleContext' => null,
            ];
        }

        $staffProfile->loadMissing([
            'department',
            'headedDepartment',
            'headedFaculty',
            'studentSetAdvisorAssignments.entryAcademicSession',
        ]);

        $department = $contextDepartment ?? $staffProfile->department;

        if ($staffProfile->headedFaculty !== null) {
            return [
                'role' => 'Faculty Head',
                'roleContext' => $staffProfile->headedFaculty->abbreviation,
            ];
        }

        if ($staffProfile->headedDepartment !== null) {
            return [
                'role' => 'HOD',
                'roleContext' => $staffProfile->headedDepartment->abbreviation,
            ];
        }

        if ($department instanceof Department) {
            $activeSetAssignments = $staffProfile->studentSetAdvisorAssignments
                ->filter(fn ($assignment): bool => (int) $assignment->department_id === (int) $department->id && (int) $assignment->active_marker === 1);

            if ($activeSetAssignments->isNotEmpty()) {
                return [
                    'role' => 'Course Adviser',
                    'roleContext' => $this->courseAdviserRoleContext($department, $activeSetAssignments),
                ];
            }
        }

        return [
            'role' => 'Staff',
            'roleContext' => $department?->abbreviation,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $assignments
     */
    private function courseAdviserRoleContext(Department $department, Collection $assignments): string
    {
        $levels = $assignments
            ->sortByDesc(fn ($assignment): mixed => $assignment->entryAcademicSession?->starts_on)
            ->map(function ($assignment) use ($department): string {
                if (! $assignment->entryAcademicSession instanceof AcademicSession) {
                    return 'Level not set';
                }

                return $this->studentSetLevelLabel(
                    $department,
                    $this->academicYearForEntrySession($department, $assignment->entryAcademicSession),
                );
            })
            ->unique()
            ->values();

        if ($levels->isEmpty()) {
            return $department->abbreviation;
        }

        if ($levels->count() === 1) {
            return (string) $levels->first();
        }

        return $levels->first().' +'.($levels->count() - 1);
    }

    private function studentSetLevelLabel(Department $department, ?AcademicYear $level): string
    {
        if (! $level instanceof AcademicYear) {
            return 'Level not set';
        }

        if ((int) $level->id > (int) $department->minimum_years_to_degree) {
            return 'Graduated';
        }

        return $this->shortLevelName($level);
    }

    /**
     * @return EloquentCollection<int, AcademicSession>
     */
    private function academicSessionsForDepartment(Department $department): EloquentCollection
    {
        $department->loadMissing('faculty.school');

        if ($department->faculty?->school === null) {
            return new EloquentCollection;
        }

        return $department->faculty->school->academicSessions()
            ->orderByDesc('starts_on')
            ->get();
    }

    /**
     * @param  EloquentCollection<int, AcademicSession>  $academicSessions
     */
    private function selectedDepartmentAcademicSession(Request $request, Department $department, EloquentCollection $academicSessions): ?AcademicSession
    {
        $requestedAcademicSessionId = $request->integer('academic_session');

        if ($requestedAcademicSessionId > 0) {
            $requestedAcademicSession = $academicSessions->firstWhere('id', $requestedAcademicSessionId);

            if ($requestedAcademicSession instanceof AcademicSession) {
                return $requestedAcademicSession;
            }
        }

        return $this->currentAcademicSessionFor($department)
            ?? $academicSessions->sortByDesc('starts_on')->first();
    }

    /**
     * @param  EloquentCollection<int, AcademicYear>  $levels
     */
    private function selectedHodAcademicYear(Request $request, EloquentCollection $levels): ?AcademicYear
    {
        $requestedLevel = $request->string('level')->toString();

        if ($requestedLevel !== '') {
            $level = $levels->firstWhere('slug', $requestedLevel);

            if ($level instanceof AcademicYear) {
                return $level;
            }
        }

        return $levels->first();
    }

    /**
     * @param  EloquentCollection<int, AcademicSession>  $assignedSets
     */
    private function selectedStaffEntrySession(Request $request, EloquentCollection $assignedSets): ?AcademicSession
    {
        $requestedEntrySessionId = $request->integer('entry_session');

        if ($requestedEntrySessionId > 0) {
            $requestedEntrySession = $assignedSets->firstWhere('id', $requestedEntrySessionId);

            if ($requestedEntrySession instanceof AcademicSession) {
                return $requestedEntrySession;
            }
        }

        return $assignedSets->first();
    }

    /**
     * @return EloquentCollection<int, AcademicYear>
     */
    private function levelsForStaffCourseSchedule(Department $department, ?AcademicSession $entryAcademicSession): EloquentCollection
    {
        $visibleLevels = $department->visibleAcademicYears();

        if (! $entryAcademicSession instanceof AcademicSession) {
            return $visibleLevels;
        }

        $currentLevel = $this->academicYearForEntrySession($department, $entryAcademicSession);

        if (! $currentLevel instanceof AcademicYear || (int) $currentLevel->id > (int) $department->minimum_years_to_degree) {
            return $visibleLevels;
        }

        return AcademicYear::query()
            ->whereIn('id', $visibleLevels->pluck('id'))
            ->where('id', '<=', $currentLevel->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  EloquentCollection<int, AcademicYear>  $levels
     */
    private function selectedStaffCourseScheduleLevel(
        Request $request,
        Department $department,
        EloquentCollection $levels,
        ?AcademicSession $entryAcademicSession,
    ): ?AcademicYear {
        $requestedLevel = $request->string('level')->toString();

        if ($requestedLevel !== '') {
            $level = $levels->firstWhere('slug', $requestedLevel);

            if ($level instanceof AcademicYear) {
                return $level;
            }
        }

        if ($entryAcademicSession instanceof AcademicSession) {
            $currentLevel = $this->academicYearForEntrySession($department, $entryAcademicSession);

            if ($currentLevel instanceof AcademicYear && $levels->contains('id', $currentLevel->id)) {
                return $currentLevel;
            }
        }

        return $levels->last();
    }

    private function academicSessionForStudentSetLevel(
        Department $department,
        ?AcademicSession $entryAcademicSession,
        ?AcademicYear $level,
    ): ?AcademicSession {
        if (! $entryAcademicSession instanceof AcademicSession || ! $level instanceof AcademicYear) {
            return $this->currentAcademicSessionFor($department)
                ?? $this->academicSessionsForDepartment($department)->first();
        }

        $department->loadMissing('faculty.school');
        $school = $department->faculty?->school;

        if ($school === null) {
            return $this->currentAcademicSessionFor($department);
        }

        $sessions = $school->academicSessions()
            ->oldest('starts_on')
            ->get()
            ->values();

        if ($sessions->isEmpty()) {
            return null;
        }

        $entryIndex = $sessions->search(fn (AcademicSession $academicSession): bool => (int) $academicSession->id === (int) $entryAcademicSession->id);

        if ($entryIndex === false) {
            return $this->currentAcademicSessionFor($department)
                ?? $sessions->last();
        }

        $targetIndex = (int) $entryIndex + max((int) $level->id - 1, 0);
        $academicSession = $sessions->get($targetIndex);

        if ($academicSession instanceof AcademicSession) {
            return $academicSession;
        }

        return $this->currentAcademicSessionFor($department)
            ?? $sessions->last();
    }

    private function hodCourseOfferingsPayload(
        Department $department,
        ?AcademicSession $academicSession,
        ?AcademicYear $level,
    ): array {
        $semesters = Semester::query()
            ->whereIn('slug', ['harmattan', 'rain'])
            ->orderBy('sort_order')
            ->get();

        return [
            'academicSession' => $academicSession === null ? null : $this->academicSessionPayload($academicSession),
            'level' => $level === null ? null : $this->levelPayload($level),
            'semesters' => $level === null
                ? []
                : $semesters
                    ->map(fn (Semester $semester): array => $this->hodCourseOfferingSemesterPayload($department, $academicSession, $level, $semester))
                    ->values()
                    ->all(),
        ];
    }

    private function hodCourseOfferingSemesterPayload(
        Department $department,
        ?AcademicSession $academicSession,
        AcademicYear $level,
        Semester $semester,
    ): array {
        $offerings = $this->courseOfferingsForDepartment($department, $level, $semester, $academicSession);
        $courseTypeCounts = $this->courseTypeCounts($department, $offerings);

        return [
            'id' => $level->slug.'-'.$semester->slug,
            'name' => $semester->name,
            'slug' => $semester->slug,
            'totalUnits' => (int) $offerings->sum(fn (CourseOffering $offering): int => (int) $offering->course->credit_units),
            'facultyCourseCount' => $courseTypeCounts['Faculty Course'],
            'departmentalCourseCount' => $courseTypeCounts['Departmental Course'],
            'electiveCourseCount' => $courseTypeCounts['Elective Course'],
            'offerings' => $offerings
                ->map(fn (CourseOffering $offering): array => $this->staffCourseOfferingPayload($department, $offering))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, StudentProfile>  $students
     * @param  array<int, array{id: string, level: string, semester: string, course: string, status: string, submittedAt: string}>  $resultSubmissions
     * @param  EloquentCollection<int, ResultSheet>  $resultSheets
     */
    private function hodStudentResultsPayload(
        Department $department,
        Collection $students,
        array $resultSubmissions,
        EloquentCollection $resultSheets,
    ): array {
        $studentsWithCgpa = $students
            ->map(fn (StudentProfile $studentProfile): array => $this->staffStudentPayload($studentProfile, $department))
            ->values();
        $averageCgpa = $studentsWithCgpa->isEmpty()
            ? '0.00'
            : number_format($studentsWithCgpa->average(fn (array $student): float => (float) $student['cgpa']), 2);

        return [
            'averageCgpa' => $averageCgpa,
            'studentsCount' => $studentsWithCgpa->count(),
            'carryoverStudents' => $studentsWithCgpa
                ->filter(fn (array $student): bool => $student['pendingCarryovers'] !== [])
                ->count(),
            'pendingSubmissions' => $resultSheets
                ->where('status', ResultSheet::StatusSubmitted)
                ->count(),
            'sets' => $students
                ->pluck('entryAcademicSession')
                ->filter()
                ->unique('id')
                ->sortByDesc('starts_on')
                ->map(fn (AcademicSession $academicSession): array => $this->academicSessionPayload($academicSession))
                ->values()
                ->all(),
            'resultSheets' => $resultSheets
                ->where('status', ResultSheet::StatusApproved)
                ->map(fn (ResultSheet $sheet): array => $this->resultSheetPayload($sheet))
                ->values()
                ->all(),
            'approvalSheets' => $resultSheets
                ->where('status', ResultSheet::StatusSubmitted)
                ->map(fn (ResultSheet $sheet): array => $this->resultSheetPayload($sheet))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, StudentProfile>  $students
     * @return array<int, array{entrySession: array{id: int, name: string, startsOn: string, endsOn: string, status: string}, currentLevel: array{id: int, title: string, slug: string}|null, advisor: array{name: string, email: string|null, avatar: string|null}|null, studentsCount: int}>
     */
    private function hodSetAdvisorPayload(Department $department, Collection $students): array
    {
        $assignments = $department->studentSetAdvisorAssignments()
            ->with(['entryAcademicSession', 'staffProfile.user'])
            ->where('active_marker', 1)
            ->get()
            ->keyBy('entry_academic_session_id');
        $entrySessions = $students
            ->pluck('entryAcademicSession')
            ->filter()
            ->merge($assignments->pluck('entryAcademicSession')->filter())
            ->unique('id')
            ->sortByDesc('starts_on')
            ->values();

        return $entrySessions
            ->map(function (AcademicSession $entryAcademicSession) use ($assignments, $department, $students): array {
                $assignment = $assignments->get($entryAcademicSession->id);
                $advisor = $assignment?->staffProfile?->user;
                $currentLevel = $this->academicYearForEntrySession($department, $entryAcademicSession);

                return [
                    'entrySession' => $this->academicSessionPayload($entryAcademicSession),
                    'currentLevel' => $currentLevel === null ? null : $this->levelPayload($currentLevel),
                    'advisor' => $advisor === null ? null : [
                        'name' => $advisor->name,
                        'email' => $advisor->email,
                        'avatar' => $advisor->profilePhotoUrl(),
                    ],
                    'studentsCount' => $students
                        ->where('entry_academic_session_id', $entryAcademicSession->id)
                        ->count(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  EloquentCollection<int, AcademicYear>  $levels
     * @return array<int, array{id: string, level: string, semester: string, course: string, status: string, submittedAt: string}>
     */
    private function hodResultSubmissionPayload(Department $department, EloquentCollection $levels): array
    {
        $semesters = Semester::query()
            ->whereIn('slug', ['harmattan', 'rain'])
            ->orderBy('sort_order')
            ->get();
        $statusCycle = ['Awaiting review', 'Approved', 'Missing', 'Approved'];
        $rows = [];
        $index = 0;

        foreach ($levels as $level) {
            foreach ($semesters as $semester) {
                foreach ($this->courseOfferingsForDepartment($department, $level, $semester)->take(2) as $offering) {
                    $status = $statusCycle[$index % count($statusCycle)];

                    $rows[] = [
                        'id' => $level->slug.'-'.$semester->slug.'-'.$offering->id,
                        'level' => $level->name,
                        'semester' => $semester->name,
                        'course' => $offering->course->code.' '.$offering->course->title,
                        'status' => $status,
                        'submittedAt' => $status === 'Missing' ? '-' : now()->subDays($index + 1)->format('M j, Y'),
                    ];

                    $index++;
                }
            }
        }

        return array_slice($rows, 0, 8);
    }

    /**
     * @return array{
     *     assignedSets: array<int, array{entrySession: array{id: int, name: string, startsOn: string, endsOn: string, status: string}, currentLevel: array{id: int, title: string, slug: string}|null, advisorName: string|null}>,
     *     totalStudents: int,
     *     maximumCourseUnits: int,
     *     studentsWithCarryovers: int,
     *     pendingDuesByStudent: array<int, array{studentName: string, studentAvatar: string|null, registrationNumber: string, dues: array<int, string>, outstandingAmount: string}>,
     *     recentPayments: array<int, array{studentName: string, studentAvatar: string|null, dueName: string, amount: string, paidAt: string}>,
     *     carryoverSummaries: array<int, array{code: string, title: string, units: int, students: array<int, string>}>
     * }|null
     */
    private function staffHomePayload(User $user, string $role, string $section): ?array
    {
        if ($role !== 'staff' || $section !== 'home') {
            return null;
        }

        $staffProfile = $user->staffProfile;
        $department = $staffProfile?->department;

        if ($staffProfile === null || $department === null) {
            return $this->emptyStaffHomePayload();
        }

        $assignedSets = $this->assignedEntrySessionsForStaff($staffProfile, $department);
        $students = $this->assignedStudentsForStaff($staffProfile, $department);
        $carryoverSummaries = $this->staffCarryoverSummaries($students);

        return [
            'assignedSets' => $assignedSets
                ->map(fn (AcademicSession $academicSession): array => $this->studentSetContextPayload($department, $academicSession))
                ->values()
                ->all(),
            'levels' => $department->visibleAcademicYears()
                ->map(fn (AcademicYear $level): array => $this->levelPayload($level))
                ->values()
                ->all(),
            'students' => $students
                ->map(fn (StudentProfile $studentProfile): array => $this->staffStudentPayload($studentProfile, $department))
                ->values()
                ->all(),
            'totalStudents' => $students->count(),
            'maximumCourseUnits' => 24,
            'studentsWithCarryovers' => $students
                ->filter(fn ($studentProfile): bool => $studentProfile->currentLevel !== null
                    && $this->studentResits($studentProfile->currentLevel) !== [])
                ->count(),
            'pendingDuesByStudent' => $this->staffPendingDuesByStudent($students),
            'recentPayments' => $this->staffRecentPayments($students),
            'carryoverSummaries' => $carryoverSummaries,
        ];
    }

    /**
     * @return array{
     *     assignedSets: array<int, array{entrySession: array{id: int, name: string, startsOn: string, endsOn: string, status: string}, currentLevel: array{id: int, title: string, slug: string}|null, advisorName: string|null}>,
     *     totalStudents: int,
     *     maximumCourseUnits: int,
     *     studentsWithCarryovers: int,
     *     pendingDuesByStudent: array<int, array{studentName: string, studentAvatar: string|null, registrationNumber: string, dues: array<int, string>, outstandingAmount: string}>,
     *     recentPayments: array<int, array{studentName: string, studentAvatar: string|null, dueName: string, amount: string, paidAt: string}>,
     *     carryoverSummaries: array<int, array{code: string, title: string, units: int, students: array<int, string>}>
     * }
     */
    private function emptyStaffHomePayload(): array
    {
        return [
            'assignedSets' => [],
            'levels' => [],
            'students' => [],
            'totalStudents' => 0,
            'maximumCourseUnits' => 24,
            'studentsWithCarryovers' => 0,
            'pendingDuesByStudent' => [],
            'recentPayments' => [],
            'carryoverSummaries' => [],
        ];
    }

    /**
     * @return EloquentCollection<int, AcademicSession>
     */
    private function assignedEntrySessionsForStaff(StaffProfile $staffProfile, Department $department): EloquentCollection
    {
        return $staffProfile->advisedEntryAcademicSessions()
            ->wherePivot('department_id', $department->id)
            ->orderByDesc('academic_sessions.starts_on')
            ->get();
    }

    /**
     * @return Collection<int, int>
     */
    private function assignedEntrySessionIdsForStaff(StaffProfile $staffProfile, Department $department): Collection
    {
        return $this->assignedEntrySessionsForStaff($staffProfile, $department)
            ->pluck('id')
            ->map(fn (int $id): int => (int) $id)
            ->values();
    }

    /**
     * @return EloquentCollection<int, AcademicYear>
     */
    private function levelsForAssignedStudentSets(StaffProfile $staffProfile, Department $department): EloquentCollection
    {
        $assignedEntrySessions = $this->assignedEntrySessionsForStaff($staffProfile, $department);

        if ($assignedEntrySessions->isEmpty()) {
            return new EloquentCollection;
        }

        $studentLevelIds = $department->studentProfiles()
            ->whereIn('entry_academic_session_id', $assignedEntrySessions->pluck('id'))
            ->pluck('current_academic_year_id');
        $inferredLevelIds = $assignedEntrySessions
            ->map(fn (AcademicSession $entryAcademicSession): ?int => $this->academicYearForEntrySession($department, $entryAcademicSession)?->id)
            ->filter();
        $levelIds = $studentLevelIds
            ->merge($inferredLevelIds)
            ->filter()
            ->unique()
            ->values();

        if ($levelIds->isEmpty()) {
            return new EloquentCollection;
        }

        return AcademicYear::query()
            ->whereIn('id', $levelIds)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{entrySession: array{id: int, name: string, startsOn: string, endsOn: string, status: string}, currentLevel: array{id: int, title: string, slug: string}|null, advisorName: string|null, minimumYearsToDegree: int}
     */
    private function studentSetContextPayload(Department $department, AcademicSession $entryAcademicSession): array
    {
        $currentLevel = $this->academicYearForEntrySession($department, $entryAcademicSession);

        return [
            'entrySession' => $this->academicSessionPayload($entryAcademicSession),
            'currentLevel' => $currentLevel === null ? null : $this->levelPayload($currentLevel),
            'advisorName' => $this->courseAdvisorNameForSet($department, $entryAcademicSession, 'Not assigned'),
            'minimumYearsToDegree' => (int) $department->minimum_years_to_degree,
        ];
    }

    private function academicYearForEntrySession(Department $department, AcademicSession $entryAcademicSession): ?AcademicYear
    {
        $currentAcademicSession = $this->currentAcademicSessionFor($department);

        if ($currentAcademicSession === null) {
            return null;
        }

        $sessions = $department->faculty?->school?->academicSessions()
            ->oldest('starts_on')
            ->get();

        if ($sessions === null || $sessions->isEmpty()) {
            return null;
        }

        $entryIndex = $sessions->search(fn (AcademicSession $academicSession): bool => (int) $academicSession->id === (int) $entryAcademicSession->id);
        $currentIndex = $sessions->search(fn (AcademicSession $academicSession): bool => (int) $academicSession->id === (int) $currentAcademicSession->id);

        if ($entryIndex === false || $currentIndex === false || $currentIndex < $entryIndex) {
            return null;
        }

        return AcademicYear::query()->find((int) $currentIndex - (int) $entryIndex + 1);
    }

    /**
     * @return Collection<int, StudentProfile>
     */
    private function assignedStudentsForStaff(StaffProfile $staffProfile, Department $department): Collection
    {
        $assignedEntrySessionIds = $this->assignedEntrySessionIdsForStaff($staffProfile, $department);

        if ($assignedEntrySessionIds->isEmpty()) {
            return collect();
        }

        return $department->studentProfiles()
            ->with(['currentLevel', 'entryAcademicSession', 'user'])
            ->whereIn('entry_academic_session_id', $assignedEntrySessionIds)
            ->get()
            ->sortBy(fn (StudentProfile $studentProfile): string => sprintf(
                '%02d-%s',
                $studentProfile->current_academic_year_id,
                $studentProfile->user->name,
            ))
            ->values();
    }

    private function formatKoboAmount(int $amountKobo): string
    {
        $naira = $amountKobo / 100;

        return 'NGN '.number_format($naira, $amountKobo % 100 === 0 ? 0 : 2);
    }

    /**
     * @param  iterable<int, mixed>  $students
     * @return array<int, array{studentName: string, studentAvatar: string|null, registrationNumber: string, dues: array<int, string>, outstandingAmount: string}>
     */
    private function staffPendingDuesByStudent(iterable $students): array
    {
        $studentProfiles = collect($students)->values();
        $assignments = StudentDueAssignment::query()
            ->with('due')
            ->whereIn('student_profile_id', $studentProfiles->pluck('id'))
            ->where('status', StudentDueAssignment::StatusPending)
            ->whereHas('due', fn ($query) => $query->where('status', Due::StatusPublished))
            ->get()
            ->groupBy('student_profile_id');

        return $studentProfiles
            ->map(function ($studentProfile) use ($assignments): ?array {
                $studentAssignments = $assignments->get($studentProfile->id, collect());

                if ($studentAssignments->isEmpty()) {
                    return null;
                }

                return [
                    'studentName' => $studentProfile->user->name,
                    'studentAvatar' => $studentProfile->user->profilePhotoUrl(),
                    'registrationNumber' => $studentProfile->registration_number,
                    'dues' => $studentAssignments
                        ->pluck('due.title')
                        ->values()
                        ->all(),
                    'outstandingAmount' => $this->formatKoboAmount((int) $studentAssignments->sum('amount_kobo')),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  iterable<int, mixed>  $students
     * @return array<int, array{studentName: string, studentAvatar: string|null, dueName: string, amount: string, paidAt: string}>
     */
    private function staffRecentPayments(iterable $students): array
    {
        $studentProfiles = collect($students)->values();

        return DuePayment::query()
            ->with(['assignment.due', 'studentProfile.user'])
            ->whereIn('student_profile_id', $studentProfiles->pluck('id'))
            ->where('status', DuePayment::StatusSuccess)
            ->latest('paid_at')
            ->limit(5)
            ->get()
            ->map(fn (DuePayment $payment): array => [
                'studentName' => $payment->studentProfile->user->name,
                'studentAvatar' => $payment->studentProfile->user->profilePhotoUrl(),
                'dueName' => $payment->assignment->due->title,
                'amount' => $this->formatKoboAmount((int) $payment->amount_kobo),
                'paidAt' => $payment->paid_at?->toFormattedDateString() ?? 'Paid',
            ])
            ->all();
    }

    /**
     * @param  iterable<int, mixed>  $students
     * @return array<int, array{code: string, title: string, units: int, students: array<int, string>}>
     */
    private function staffCarryoverSummaries(iterable $students): array
    {
        $summaries = [];

        foreach ($students as $studentProfile) {
            foreach ($this->studentResits($studentProfile->currentLevel) as $resit) {
                $code = $resit['code'];

                $summaries[$code] ??= [
                    'code' => $code,
                    'title' => $resit['title'],
                    'units' => $resit['units'],
                    'students' => [],
                ];

                $summaries[$code]['students'][] = $studentProfile->user->name;
            }
        }

        ksort($summaries);

        return array_values($summaries);
    }

    /**
     * @return array<int, array{code: string, title: string, units: int, originalSemester: string, resitWindow: string, status: string}>
     */
    private function studentResits(?AcademicYear $level): array
    {
        if ($level === null || (int) $level->id <= 1) {
            return [];
        }

        $resits = [
            [
                'code' => 'MTH 201',
                'title' => 'Engineering Mathematics III',
                'units' => 3,
                'originalSemester' => '200 Level, Harmattan',
                'resitWindow' => 'Rain 2025/2026',
                'status' => 'Adviser review',
            ],
        ];

        if ((int) $level->id >= 4) {
            $resits[] = [
                'code' => 'CPE 302',
                'title' => 'Electronic Circuit Design',
                'units' => 3,
                'originalSemester' => '300 Level, Rain',
                'resitWindow' => 'Harmattan 2026/2027',
                'status' => 'Due next session',
            ];
        }

        return $resits;
    }

    /**
     * @return array{id: int, title: string, slug: string}
     */
    private function levelPayload(AcademicYear $level): array
    {
        return [
            'id' => $level->id,
            'title' => $level->name,
            'slug' => $level->slug,
        ];
    }
}
