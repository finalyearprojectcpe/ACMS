import { Head, Link, usePage } from '@inertiajs/react';
import { RiArrowLeftLine, RiNotification3Line } from '@remixicon/react';
import { useEffect } from 'react';
import { toast } from 'sonner';

import {
    home as dashboardHome,
    level as dashboardLevel,
    section as dashboardSection,
} from '@/actions/App/Http/Controllers/DashboardController';
import { AppSidebar } from '@/components/app-sidebar';
import { FacultyHeadDashboard } from '@/components/faculty-head-dashboard';
import {
    HodDashboard,
    HodStaffImport,
    StudentRecordDashboard,
} from '@/components/hod-dashboard';
import { SchoolAdminDashboard } from '@/components/school-admin-dashboard';
import {
    StaffCourseOfferingForm,
    StaffCourseSchedulesDashboard,
} from '@/components/staff-course-schedules-dashboard';
import { StaffHomeDashboard } from '@/components/staff-home-dashboard';
import { StaffStudentsDashboard } from '@/components/staff-students-dashboard';
import { StudentCourseSchedulesDashboard } from '@/components/student-course-schedules-dashboard';
import { StudentDuesDashboard } from '@/components/student-dues-dashboard';
import { StudentHomeDashboard } from '@/components/student-home-dashboard';
import { StudentProfileDashboard } from '@/components/student-profile-dashboard';
import { StudentResultsDashboard } from '@/components/student-results-dashboard';
import { ThemeToggle } from '@/components/theme-toggle';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import {
    SidebarInset,
    SidebarProvider,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import { dashboardHeaderOrganizationLabel } from '@/lib/dashboard-labels';
import type { Auth, DashboardContext } from '@/types';

type DashboardPageProps = {
    dashboard: DashboardContext;
};

type DashboardFlash = {
    success?: string | null;
    error?: string | null;
};

export default function DashboardPage({ dashboard }: DashboardPageProps) {
    const { auth, flash } = usePage<{
        auth: Auth;
        flash?: DashboardFlash;
    }>().props;
    const headerOrganizationLabel = dashboardHeaderOrganizationLabel(dashboard);
    const backTarget = dashboardBackTarget(dashboard);
    const studentHeaderMeta =
        dashboard.role === 'student' && dashboard.student !== null
            ? [
                  dashboard.student.currentLevel?.title,
                  `Reg No. ${dashboard.student.registrationNumber}`,
              ]
                  .filter(Boolean)
                  .join(' • ')
            : null;
    const staffHome =
        dashboard.role === 'staff' &&
        dashboard.section === 'home' &&
        dashboard.staffHome !== null
            ? dashboard.staffHome
            : null;
    const facultyHead =
        dashboard.role === 'faculty_head' && dashboard.facultyHead !== null
            ? dashboard.facultyHead
            : null;
    const schoolAdmin =
        dashboard.role === 'school_admin' && dashboard.schoolAdmin !== null
            ? dashboard.schoolAdmin
            : null;
    const hod =
        dashboard.role === 'hod' && dashboard.hod !== null
            ? dashboard.hod
            : null;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }

        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash?.success, flash?.error]);

    const staffStudents =
        dashboard.role === 'staff' &&
        dashboard.section === 'students' &&
        dashboard.staffStudents !== null
            ? dashboard.staffStudents
            : null;
    const staffCourseSchedules =
        dashboard.role === 'staff' &&
        dashboard.section === 'courses' &&
        dashboard.staffCourseSchedules !== null
            ? dashboard.staffCourseSchedules
            : null;
    const staffCourseOfferingForm =
        dashboard.role === 'staff' &&
        dashboard.section === 'courses' &&
        dashboard.staffCourseOfferingForm !== null
            ? dashboard.staffCourseOfferingForm
            : null;
    const hodStaffForm =
        dashboard.role === 'hod' && dashboard.hodStaffForm !== null
            ? dashboard.hodStaffForm
            : null;
    const studentHome =
        dashboard.role === 'student' &&
        dashboard.section === 'home' &&
        dashboard.studentHome !== null
            ? dashboard.studentHome
            : null;
    const studentResults =
        dashboard.role === 'student' &&
        dashboard.section === 'results' &&
        dashboard.studentResults !== null
            ? dashboard.studentResults
            : null;
    const studentDues =
        dashboard.role === 'student' &&
        dashboard.section === 'dues' &&
        dashboard.studentDues !== null
            ? dashboard.studentDues
            : null;
    const studentCourseSchedules =
        dashboard.role === 'student' &&
        dashboard.section === 'course-schedules' &&
        dashboard.studentCourseSchedules !== null
            ? dashboard.studentCourseSchedules
            : null;
    const studentProfile =
        dashboard.role === 'student' &&
        dashboard.section === 'profile' &&
        dashboard.studentProfile !== null
            ? dashboard.studentProfile
            : null;
    const studentRecord =
        ['staff', 'hod'].includes(dashboard.role) &&
        dashboard.studentRecord !== null
            ? dashboard.studentRecord
            : null;

    return (
        <>
            <Head title={dashboard.sectionTitle} />
            <SidebarProvider>
                <AppSidebar dashboard={dashboard} user={auth.user} />
                <SidebarInset>
                    <header className="sticky top-0 z-40 flex h-14 shrink-0 items-center gap-2 border-b bg-background px-4">
                        <div className="flex min-w-0 items-center gap-2">
                            <SidebarTrigger />
                            {backTarget ? (
                                <Button variant="ghost" size="icon-sm" asChild>
                                    <Link
                                        href={backTarget.href}
                                        aria-label={backTarget.label}
                                    >
                                        <RiArrowLeftLine />
                                    </Link>
                                </Button>
                            ) : null}
                            <Separator
                                orientation="vertical"
                                className="mr-2 h-4"
                            />
                            <Breadcrumb>
                                <BreadcrumbList>
                                    <BreadcrumbItem>
                                        <BreadcrumbPage>
                                            {dashboard.roleLabel}
                                        </BreadcrumbPage>
                                    </BreadcrumbItem>
                                    <BreadcrumbSeparator />
                                    <BreadcrumbItem>
                                        <BreadcrumbPage>
                                            {dashboard.sectionTitle}
                                        </BreadcrumbPage>
                                    </BreadcrumbItem>
                                </BreadcrumbList>
                            </Breadcrumb>
                        </div>
                        {headerOrganizationLabel ? (
                            <div className="absolute left-1/2 hidden max-w-[42vw] -translate-x-1/2 flex-col items-center text-center leading-tight sm:flex">
                                <div className="max-w-full truncate text-sm font-medium text-muted-foreground">
                                    {headerOrganizationLabel}
                                </div>
                                {studentHeaderMeta ? (
                                    <div className="max-w-full truncate text-xs text-muted-foreground/80">
                                        {studentHeaderMeta}
                                    </div>
                                ) : null}
                            </div>
                        ) : null}
                        <div className="ml-auto flex items-center gap-1">
                            <ThemeToggle />
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon-sm"
                                aria-label="Notifications"
                            >
                                <RiNotification3Line />
                            </Button>
                        </div>
                    </header>

                    <main className="background-graph flex flex-1 flex-col p-4 sm:p-6">
                        {schoolAdmin ? (
                            <SchoolAdminDashboard
                                schoolAdmin={schoolAdmin}
                                academicSessionForm={
                                    dashboard.schoolAdminAcademicSessionForm
                                }
                                facultyForm={dashboard.schoolAdminFacultyForm}
                                departmentForm={
                                    dashboard.schoolAdminDepartmentForm
                                }
                                facultyHeadForm={
                                    dashboard.schoolAdminFacultyHeadForm
                                }
                                section={dashboard.section}
                                sectionTitle={dashboard.sectionTitle}
                            />
                        ) : staffCourseOfferingForm ? (
                            <StaffCourseOfferingForm
                                form={staffCourseOfferingForm}
                                role={dashboard.role}
                            />
                        ) : facultyHead ? (
                            <FacultyHeadDashboard
                                facultyHead={facultyHead}
                                hodForm={dashboard.facultyHeadHodForm}
                                staffForm={dashboard.facultyHeadStaffForm}
                                courses={dashboard.facultyHeadCourses}
                                courseForm={
                                    dashboard.facultyHeadCourseOfferingForm
                                }
                                section={dashboard.section}
                                sectionTitle={dashboard.sectionTitle}
                            />
                        ) : staffCourseSchedules ? (
                            <StaffCourseSchedulesDashboard
                                courseSchedules={staffCourseSchedules}
                                levels={dashboard.courseScheduleLevels}
                            />
                        ) : hodStaffForm ? (
                            <HodStaffImport form={hodStaffForm} />
                        ) : studentRecord ? (
                            <StudentRecordDashboard record={studentRecord} />
                        ) : hod ? (
                            <HodDashboard
                                hod={hod}
                                section={dashboard.section}
                            />
                        ) : staffHome ? (
                            <StaffHomeDashboard home={staffHome} />
                        ) : staffStudents ? (
                            <StaffStudentsDashboard students={staffStudents} />
                        ) : studentHome ? (
                            <StudentHomeDashboard home={studentHome} />
                        ) : studentResults ? (
                            <StudentResultsDashboard results={studentResults} />
                        ) : studentDues ? (
                            <StudentDuesDashboard dues={studentDues} />
                        ) : studentCourseSchedules ? (
                            <StudentCourseSchedulesDashboard
                                courseSchedules={studentCourseSchedules}
                            />
                        ) : studentProfile ? (
                            <StudentProfileDashboard profile={studentProfile} />
                        ) : (
                            <h1 className="text-2xl font-semibold tracking-normal">
                                {dashboard.sectionTitle}
                            </h1>
                        )}
                    </main>
                </SidebarInset>
            </SidebarProvider>
        </>
    );
}

function dashboardBackTarget(dashboard: DashboardContext) {
    if (dashboard.section === 'home') {
        return null;
    }

    if (dashboard.facultyHeadHodForm !== null) {
        return {
            href: dashboardSection({ section: 'departments' }),
            label: 'Back to Departments',
        };
    }

    if (dashboard.schoolAdminAcademicSessionForm !== null) {
        return {
            href: dashboardSection({ section: 'academic-sessions' }),
            label: 'Back to Academic Sessions',
        };
    }

    if (
        dashboard.schoolAdminFacultyForm !== null ||
        dashboard.schoolAdminDepartmentForm !== null
    ) {
        return {
            href: dashboardSection({ section: 'structure' }),
            label: 'Back to School Structure',
        };
    }

    if (dashboard.schoolAdminFacultyHeadForm !== null) {
        return {
            href: dashboardSection({ section: 'faculty-heads' }),
            label: 'Back to Faculty Heads',
        };
    }

    if (dashboard.facultyHeadStaffForm !== null) {
        return {
            href: dashboardSection({ section: 'staff' }),
            label: 'Back to Staff',
        };
    }

    if (dashboard.hodStaffForm !== null) {
        return {
            href: dashboardSection({ section: 'staff' }),
            label: 'Back to Staff',
        };
    }

    if (dashboard.facultyHeadCourseOfferingForm !== null) {
        return {
            href: dashboardSection(
                { section: 'sessions-courses' },
                {
                    query: {
                        academic_session:
                            dashboard.facultyHeadCourseOfferingForm
                                .academicSession.id,
                    },
                },
            ),
            label: 'Back to Sessions & Courses',
        };
    }

    if (dashboard.staffCourseOfferingForm !== null) {
        return {
            href: dashboardLevel({
                section: 'courses',
                level: dashboard.staffCourseOfferingForm.level.slug,
            }),
            label: 'Back to Course Offerings',
        };
    }

    if (dashboard.staffCourseSchedules !== null) {
        return {
            href: dashboardSection({ section: 'courses' }),
            label: 'Back to Course Offerings',
        };
    }

    if (dashboard.studentRecord !== null) {
        return {
            href: dashboardSection({ section: 'students' }),
            label: 'Back to Students',
        };
    }

    if (dashboard.studentResults !== null) {
        return {
            href: dashboardSection({ section: 'results' }),
            label: 'Back to Results',
        };
    }

    return {
        href: dashboardHome(),
        label: 'Back to Dashboard',
    };
}
