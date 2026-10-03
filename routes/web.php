<?php

use App\Http\Controllers\AcademicSessionController;
use App\Http\Controllers\AdviserAccountController;
use App\Http\Controllers\AdviserJoinRequestController;
use App\Http\Controllers\AdviserStudentController;
use App\Http\Controllers\AssistantController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\StudentInvitationController;
use App\Http\Controllers\Auth\StudentJoinStatusController;
use App\Http\Controllers\CourseCatalogController;
use App\Http\Controllers\CourseOfferingController;
use App\Http\Controllers\CourseRegistrationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DueController;
use App\Http\Controllers\DueRosterExportController;
use App\Http\Controllers\ProfilePhotoController;
use App\Http\Controllers\ReceiptVerificationController;
use App\Http\Controllers\ResultController;
use App\Http\Controllers\SetResultsExportController;
use App\Http\Controllers\StudentResultStatementController;
use App\Http\Controllers\StudentRosterController;
use App\Http\Controllers\StudentSemesterPdfController;
use App\Http\Controllers\StudentSetController;
use App\Http\Controllers\StudentSetLogoController;
use App\Http\Middleware\EnsureStudentMembership;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function (): void {
    Route::get('/', [AuthenticatedSessionController::class, 'create'])->name('home');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->middleware('throttle:5,1')->name('login.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'createRequest'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'storeRequest'])->middleware('throttle:3,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'createReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'storeReset'])->middleware('throttle:5,1')->name('password.update');

    Route::redirect('/signup', '/signup/adviser')->name('signup');
    Route::get('/signup/adviser', [RegisteredUserController::class, 'createAdvisor'])->name('signup.advisor');
    Route::post('/signup/adviser', [RegisteredUserController::class, 'storeAdvisor'])->middleware('throttle:5,1')->name('signup.advisor.store');

    Route::get('/join/{studentSet:invite_token}', [StudentInvitationController::class, 'show'])
        ->where('studentSet', '[A-Za-z0-9]{48}')
        ->name('student-invitations.show');
    Route::post('/join/{studentSet:invite_token}/find', [StudentInvitationController::class, 'lookup'])
        ->where('studentSet', '[A-Za-z0-9]{48}')
        ->middleware('throttle:10,1')
        ->name('student-invitations.lookup');
    Route::delete('/join/{studentSet:invite_token}/find', [StudentInvitationController::class, 'clearLookup'])
        ->where('studentSet', '[A-Za-z0-9]{48}')
        ->name('student-invitations.lookup.clear');
    Route::post('/join/{studentSet:invite_token}', [StudentInvitationController::class, 'store'])
        ->where('studentSet', '[A-Za-z0-9]{48}')
        ->middleware('throttle:5,1')
        ->name('student-invitations.store');
});

Route::post('/paystack/webhook', [DueController::class, 'webhook'])->name('paystack.webhook');
Route::get('/receipts/{paystackTransactionId}/verify', [ReceiptVerificationController::class, 'show'])
    ->whereNumber('paystackTransactionId')
    ->name('receipts.verify');
Route::get('/receipts/{paystackTransactionId}/download', [ReceiptVerificationController::class, 'download'])
    ->whereNumber('paystackTransactionId')
    ->middleware('signed:relative')
    ->name('receipts.download');
Route::get('/join/verify/{user}/{hash}', [StudentJoinStatusController::class, 'verify'])
    ->whereUlid('user')->middleware('signed:relative')->name('student-joins.verify');
Route::get('/student-email/verify/{user}/{hash}', [StudentRosterController::class, 'verifyEmailChange'])
    ->whereUlid('user')->middleware('signed:relative')->name('student-email-changes.verify');

Route::middleware('auth')->group(function (): void {
    Route::get('/join/status', [StudentJoinStatusController::class, 'show'])->name('student-joins.status');
    Route::get('/join/status/edit', [StudentJoinStatusController::class, 'edit'])->name('student-joins.edit');
    Route::patch('/join/status', [StudentJoinStatusController::class, 'update'])->middleware('throttle:5,1')->name('student-joins.update');
    Route::post('/join/status/verification-email', [StudentJoinStatusController::class, 'resend'])->middleware('throttle:3,1')->name('student-joins.resend');
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware(EnsureStudentMembership::class)->group(function (): void {
        Route::get('/dashboard', [DashboardController::class, 'home'])->name('dashboard');
        Route::get('/profile-photos/{user}', [ProfilePhotoController::class, 'show'])
            ->whereUlid('user')->name('profile-photos.show');

        Route::get('/student-set/create', [DashboardController::class, 'createStudentSet'])
            ->name('student-sets.create');
        Route::post('/student-set', [StudentSetController::class, 'store'])
            ->name('student-sets.store');
        Route::get('/student-set/{departmentSlug}/{entrySession}', [DashboardController::class, 'showStudentSet'])
            ->where('departmentSlug', '[a-z0-9-]+')
            ->where('entrySession', '\\d{8}')
            ->name('student-sets.show');

        Route::get('/student-set/{departmentSlug}/{entrySession}/settings', [DashboardController::class, 'editStudentSet'])
            ->where('departmentSlug', '[a-z0-9-]+')
            ->where('entrySession', '\\d{8}')
            ->name('student-sets.edit');
        Route::patch('/student-set/{departmentSlug}/{entrySession}/settings', [StudentSetController::class, 'update'])
            ->where('departmentSlug', '[a-z0-9-]+')
            ->where('entrySession', '\\d{8}')
            ->name('student-sets.update');
        Route::get('/student-set/{departmentSlug}/{entrySession}/settings/logo', [StudentSetLogoController::class, 'show'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')
            ->name('student-sets.logo.show');
        Route::post('/student-set/{departmentSlug}/{entrySession}/settings/logo', [StudentSetLogoController::class, 'store'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')
            ->name('student-sets.logo.store');
        Route::delete('/student-set/{departmentSlug}/{entrySession}/settings/logo', [StudentSetLogoController::class, 'destroy'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')
            ->name('student-sets.logo.destroy');
        Route::get('/student-set/{departmentSlug}/{entrySession}/settings/session/create', [DashboardController::class, 'createAcademicSession'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')
            ->name('student-sets.sessions.create');
        Route::post('/student-set/{departmentSlug}/{entrySession}/settings/session', [AcademicSessionController::class, 'store'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')
            ->name('student-sets.sessions.store');
        Route::get('/student-set/{departmentSlug}/{entrySession}/settings/session/{sessionYears}/edit', [DashboardController::class, 'editAcademicSession'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')->where('sessionYears', '\\d{8}')
            ->name('student-sets.sessions.edit');
        Route::patch('/student-set/{departmentSlug}/{entrySession}/settings/session/{sessionYears}', [AcademicSessionController::class, 'update'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')->where('sessionYears', '\\d{8}')
            ->name('student-sets.sessions.update');
        Route::delete('/student-set/{departmentSlug}/{entrySession}/settings/session/{sessionYears}', [AcademicSessionController::class, 'destroy'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')->where('sessionYears', '\\d{8}')
            ->name('student-sets.sessions.destroy');

        Route::patch('/student-set/{departmentSlug}/{entrySession}/signup-link', [StudentSetController::class, 'rotateInvite'])
            ->where('departmentSlug', '[a-z0-9-]+')
            ->where('entrySession', '\\d{8}')
            ->name('student-sets.invite.rotate');

        Route::post('/student-set/{departmentSlug}/{entrySession}/students', [StudentRosterController::class, 'store'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')
            ->name('student-sets.students.store');
        Route::patch('/student-set/{departmentSlug}/{entrySession}/students/{registrationNumber}', [StudentRosterController::class, 'update'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')->where('registrationNumber', '[^/]+')
            ->name('student-sets.students.update');

        Route::get('/student-set/{departmentSlug}/{entrySession}/catalog', [DashboardController::class, 'showCourseCatalog'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')
            ->name('student-sets.catalog.show');
        Route::post('/student-set/{departmentSlug}/{entrySession}/catalog', [CourseCatalogController::class, 'store'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')
            ->name('student-sets.catalog.store');
        Route::get('/student-set/{departmentSlug}/{entrySession}/catalog/{courseCode}/edit', [DashboardController::class, 'editCatalogCourse'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')
            ->name('student-sets.catalog.edit');
        Route::patch('/student-set/{departmentSlug}/{entrySession}/catalog/{courseCode}', [CourseCatalogController::class, 'update'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')
            ->name('student-sets.catalog.update');
        Route::delete('/student-set/{departmentSlug}/{entrySession}/catalog/{courseCode}', [CourseCatalogController::class, 'destroy'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')
            ->name('student-sets.catalog.destroy');
        Route::get('/student-set/{departmentSlug}/{entrySession}/course-schedule', [DashboardController::class, 'showCourseSchedule'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')
            ->name('student-sets.courses.show');
        Route::get('/student-set/{departmentSlug}/{entrySession}/results', [DashboardController::class, 'showSetResults'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')
            ->name('student-sets.matrix.show');
        Route::get('/student-set/{departmentSlug}/{entrySession}/results/export', [SetResultsExportController::class, 'download'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')
            ->name('student-sets.matrix.export');
        Route::get('/student-set/{departmentSlug}/{entrySession}/session/{sessionYears}/semester/{semester}/students/{registrationNumber}/courses', [DashboardController::class, 'showCourseAssignment'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')->where('sessionYears', '\\d{8}')->whereIn('semester', ['first', 'second'])
            ->name('student-sets.assignments.show');
        Route::patch('/student-set/{departmentSlug}/{entrySession}/session/{sessionYears}/semester/{semester}/students/{registrationNumber}/courses', [CourseRegistrationController::class, 'store'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')->where('sessionYears', '\\d{8}')->whereIn('semester', ['first', 'second'])
            ->name('student-sets.assignments.store');
        Route::post('/student-set/{departmentSlug}/{entrySession}/session/{sessionYears}/semester/{semester}/results/course/{courseCode}/publish', [ResultController::class, 'publish'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')->where('sessionYears', '\\d{8}')->whereIn('semester', ['first', 'second'])->where('courseCode', '[a-z-]+[0-9]{2,4}[a-z]?')
            ->name('student-sets.results.publish');
        Route::get('/student-set/{departmentSlug}/{entrySession}/session/{sessionYears}/semester/{semester}/results/course/{courseCode}', [DashboardController::class, 'showCourseGradeRoster'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')->where('sessionYears', '\\d{8}')->whereIn('semester', ['first', 'second'])->where('courseCode', '[a-z-]+[0-9]{2,4}[a-z]?')
            ->name('student-sets.results.course');
        Route::patch('/student-set/{departmentSlug}/{entrySession}/session/{sessionYears}/semester/{semester}/results/course/{courseCode}', [ResultController::class, 'saveGrades'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')->where('sessionYears', '\\d{8}')->whereIn('semester', ['first', 'second'])->where('courseCode', '[a-z-]+[0-9]{2,4}[a-z]?')
            ->name('student-sets.results.course.save');
        Route::get('/student-set/{departmentSlug}/{entrySession}/dues', [DashboardController::class, 'showStudentSetDues'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')
            ->name('student-sets.dues.show');
        Route::get('/student-set/{departmentSlug}/{entrySession}/dues/create', [DashboardController::class, 'createStudentSetDue'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')
            ->name('student-sets.dues.create');
        Route::get('/student-set/{departmentSlug}/{entrySession}/session/{sessionYears}/dues', [DashboardController::class, 'showStudentSetSessionDues'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')->where('sessionYears', '\\d{8}')
            ->name('student-sets.dues.session');
        Route::get('/student-set/{departmentSlug}/{entrySession}/session/{sessionYears}/dues/create', [DashboardController::class, 'createStudentSetSessionDue'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')->where('sessionYears', '\\d{8}')
            ->name('student-sets.dues.session.create');
        Route::get('/student-set/{departmentSlug}/{entrySession}/session/{sessionYears}/semester/{semester}/dues', [DashboardController::class, 'showStudentSetSemesterDues'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')->where('sessionYears', '\\d{8}')->whereIn('semester', ['first', 'second'])
            ->name('student-sets.dues.semester');
        Route::get('/student-set/{departmentSlug}/{entrySession}/session/{sessionYears}/semester/{semester}/dues/create', [DashboardController::class, 'createStudentSetSemesterDue'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')->where('sessionYears', '\\d{8}')->whereIn('semester', ['first', 'second'])
            ->name('student-sets.dues.semester.create');
        Route::get('/student-set/{departmentSlug}/{entrySession}/course-schedule/session/{sessionYears}/semester/{semester}/course/create', [DashboardController::class, 'createCourseOffering'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')->where('sessionYears', '\\d{8}')->whereIn('semester', ['first', 'second'])
            ->name('student-sets.courses.create');
        Route::post('/student-set/{departmentSlug}/{entrySession}/course-schedule/session/{sessionYears}/semester/{semester}/course', [CourseOfferingController::class, 'store'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')->where('sessionYears', '\\d{8}')->whereIn('semester', ['first', 'second'])
            ->name('student-sets.courses.store');
        Route::get('/student-set/{departmentSlug}/{entrySession}/course-schedule/session/{sessionYears}/semester/{semester}/course/{courseCode}/edit', [DashboardController::class, 'editCourseOffering'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')->where('sessionYears', '\\d{8}')->whereIn('semester', ['first', 'second'])->where('courseCode', '[a-z-]+[0-9]{2,4}[a-z]?')
            ->name('student-sets.courses.edit');
        Route::patch('/student-set/{departmentSlug}/{entrySession}/course-schedule/session/{sessionYears}/semester/{semester}/course/{courseCode}', [CourseOfferingController::class, 'update'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')->where('sessionYears', '\\d{8}')->whereIn('semester', ['first', 'second'])->where('courseCode', '[a-z-]+[0-9]{2,4}[a-z]?')
            ->name('student-sets.courses.update');
        Route::delete('/student-set/{departmentSlug}/{entrySession}/course-schedule/session/{sessionYears}/semester/{semester}/course/{courseCode}', [CourseOfferingController::class, 'destroy'])
            ->where('departmentSlug', '[a-z0-9-]+')->where('entrySession', '\\d{8}')->where('sessionYears', '\\d{8}')->whereIn('semester', ['first', 'second'])->where('courseCode', '[a-z-]+[0-9]{2,4}[a-z]?')
            ->name('student-sets.courses.destroy');
        Route::get('/dashboard/students/classlist/export', [AdviserStudentController::class, 'exportClasslist'])->name('dashboard.students.classlist');
        Route::patch('/dashboard/join-requests/{studentJoinRequest}/approve', [AdviserJoinRequestController::class, 'approve'])
            ->whereUlid('studentJoinRequest')->name('student-joins.adviser.approve');
        Route::get('/dashboard/join-requests/{studentJoinRequest}/reject', [DashboardController::class, 'rejectJoinRequest'])
            ->whereUlid('studentJoinRequest')->name('student-joins.adviser.reject.create');
        Route::patch('/dashboard/join-requests/{studentJoinRequest}/reject', [AdviserJoinRequestController::class, 'reject'])
            ->whereUlid('studentJoinRequest')->name('student-joins.adviser.reject');
        Route::get('/dashboard/students/{registrationNumber}', [DashboardController::class, 'showStudentProfile'])
            ->where('registrationNumber', '[^/]+')
            ->name('dashboard.students.show');
        Route::get('/dashboard/students/{registrationNumber}/result-statement', [DashboardController::class, 'previewStudentResultStatement'])
            ->where('registrationNumber', '[^/]+')->name('dashboard.students.statement.preview');
        Route::get('/dashboard/students/{registrationNumber}/result-statement/download', [StudentResultStatementController::class, 'download'])
            ->where('registrationNumber', '[^/]+')->name('dashboard.students.statement.download');
        Route::get('/dashboard/students/{registrationNumber}/results/{sessionYears}/{semester}', [DashboardController::class, 'showStudentSemesterResults'])
            ->where('registrationNumber', '[^/]+')->where('sessionYears', '\\d{8}')->whereIn('semester', ['first', 'second'])
            ->name('dashboard.students.results.semester');
        Route::get('/dashboard/courses/{sessionYears}/{semester}/export.pdf', [StudentSemesterPdfController::class, 'courses'])
            ->where('sessionYears', '\\d{8}')->whereIn('semester', ['first', 'second'])
            ->name('dashboard.student-courses.pdf');
        Route::get('/dashboard/results/{sessionYears}/{semester}/export.pdf', [StudentSemesterPdfController::class, 'results'])
            ->where('sessionYears', '\\d{8}')->whereIn('semester', ['first', 'second'])
            ->name('dashboard.student-results.pdf');

        Route::post('/dashboard/assistant', [AssistantController::class, 'store'])->middleware('throttle:10,1')->name('dashboard.assistant.store');
        Route::get('/dashboard/assistant/{assistantConversation}', [DashboardController::class, 'assistantConversation'])->whereUlid('assistantConversation')->name('dashboard.assistant.show');
        Route::post('/dashboard/assistant/{assistantConversation}', [AssistantController::class, 'reply'])->whereUlid('assistantConversation')->middleware('throttle:10,1')->name('dashboard.assistant.reply');
        Route::post('/dashboard/assistant/{assistantConversation}/retry', [AssistantController::class, 'retry'])->whereUlid('assistantConversation')->middleware('throttle:10,1')->name('dashboard.assistant.retry');

        Route::get('/dashboard/student-dues/payment-history', [DashboardController::class, 'paymentHistory'])->name('dashboard.student-dues.payment-history');
        Route::post('/dashboard/dues', [DueController::class, 'store'])->name('dashboard.dues.store');
        Route::get('/dashboard/dues/{due}/edit', [DashboardController::class, 'editAdviserDue'])->whereUlid('due')->name('dashboard.dues.edit');
        Route::patch('/dashboard/dues/{due}', [DueController::class, 'update'])->whereUlid('due')->name('dashboard.dues.update');
        Route::patch('/dashboard/dues/{due}/archive', [DueController::class, 'archive'])->whereUlid('due')->name('dashboard.dues.archive');
        Route::patch('/dashboard/dues/{due}/restore', [DueController::class, 'restore'])->whereUlid('due')->name('dashboard.dues.restore');
        Route::get('/dashboard/dues/{due}/roster.docx', [DueRosterExportController::class, 'download'])->whereUlid('due')->name('dashboard.dues.roster.download');
        Route::get('/dashboard/dues/{due}/assignments/{studentDueAssignment}/waive', [DashboardController::class, 'createStudentDueWaiver'])->whereUlid('due')->whereUlid('studentDueAssignment')->name('dashboard.dues.assignments.waive.create');
        Route::patch('/dashboard/dues/{due}/assignments/{studentDueAssignment}/waive', [DueController::class, 'waive'])->whereUlid('due')->whereUlid('studentDueAssignment')->name('dashboard.dues.assignments.waive');
        Route::patch('/dashboard/dues/{due}/assignments/{studentDueAssignment}/restore', [DueController::class, 'restoreAssignment'])->whereUlid('due')->whereUlid('studentDueAssignment')->name('dashboard.dues.assignments.restore');
        Route::get('/dashboard/dues/{due}', [DashboardController::class, 'showAdviserDue'])->whereUlid('due')->name('dashboard.dues.show');

        Route::post('/dashboard/dues/assignments/{studentDueAssignment}/pay', [DueController::class, 'startPayment'])->whereUlid('studentDueAssignment')->name('dashboard.dues.payments.store');
        Route::get('/dashboard/dues/payments/{duePayment}/callback', [DueController::class, 'callback'])->whereUlid('duePayment')->name('dashboard.dues.payments.callback');
        Route::get('/dashboard/dues/payments/{paystackTransactionId}/receipt', [DueController::class, 'downloadReceipt'])->whereNumber('paystackTransactionId')->name('dashboard.dues.payments.receipt');
        Route::post('/dashboard/dues/payments/{paystackTransactionId}/receipt/retry', [DueController::class, 'retryReceipt'])->whereNumber('paystackTransactionId')->name('dashboard.dues.payments.receipt.retry');
        Route::post('/dashboard/dues/payments/{paystackTransactionId}/receipt/email', [DueController::class, 'resendReceiptEmail'])->whereNumber('paystackTransactionId')->name('dashboard.dues.payments.receipt.email');

        Route::get('/dashboard/payments/payout-account', [DashboardController::class, 'createAdviserPaymentAccount'])->name('dashboard.payments.account.create');
        Route::post('/dashboard/payments/payout-account', [AdviserAccountController::class, 'storePaymentAccount'])->name('dashboard.payments.account.store');
        Route::patch('/dashboard/profile', [AdviserAccountController::class, 'updateProfile'])->name('dashboard.profile.update');
        Route::patch('/dashboard/profile-settings', [AdviserAccountController::class, 'updateStudentProfile'])->name('dashboard.profile-settings.update');
        Route::get('/dashboard/{section}', [DashboardController::class, 'section'])
            ->whereIn('section', ['student-sets', 'students', 'courses', 'set-results', 'results', 'student-dues', 'settings', 'dues', 'profile-settings', 'assistant'])
            ->name('dashboard.section');

    });
});
