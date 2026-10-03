<?php

namespace App\Http\Controllers;

use App\Models\StudentProfile;
use App\Services\CourseDashboardData;
use App\Services\DashboardAccess;
use App\Services\ResultDashboardData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class StudentSemesterPdfController extends Controller
{
    public function __construct(
        private readonly DashboardAccess $access,
        private readonly CourseDashboardData $courses,
        private readonly ResultDashboardData $results,
    ) {}

    public function courses(Request $request, string $sessionYears, string $semester): Response
    {
        $student = $this->access->student($request)->loadMissing('studentSet');
        $data = $this->courses->studentCourses($student, $sessionYears, $semester);
        abort_if($data['selectedCourses'] === [], 404);

        return Pdf::loadView('student-semester-pdf', [
            'student' => $student,
            'title' => 'Semester Course List',
            'period' => $data['currentSemester']['level'].' '.$data['currentSemester']['name'].' · '.$data['currentSemester']['session'].' Session',
            'courses' => collect($data['selectedCourses'])->sortBy('code')->values()->all(),
            'results' => false,
            'gpa' => null,
        ])->setPaper('a4')->download($this->filename($student, $sessionYears, $semester, 'courses'));
    }

    public function results(Request $request, string $sessionYears, string $semester): Response
    {
        $student = $this->access->student($request)->loadMissing('studentSet');
        $selected = $this->results->studentResults($student, $sessionYears, $semester, false)['selected'];
        abort_if($selected === null, 404);
        abort_if($selected['locked'], 403);
        abort_unless(collect($selected['courses'])->contains(fn (array $course): bool => ! $course['pending']), 404);

        return Pdf::loadView('student-semester-pdf', [
            'student' => $student,
            'title' => 'Semester Results',
            'period' => $selected['label'],
            'courses' => $selected['courses'],
            'results' => true,
            'gpa' => $selected['gpa'],
        ])->setPaper('a4')->download($this->filename($student, $sessionYears, $semester, 'results'));
    }

    private function filename(StudentProfile $student, string $sessionYears, string $semester, string $subject): string
    {
        return Str::slug($student->official_name).'-'.Str::slug($student->registration_number).'-'.$sessionYears.'-'.$semester.'-'.$subject.'.pdf';
    }
}
