import { Head, usePage } from '@inertiajs/react';
import { RiNotification3Line } from '@remixicon/react';

import { AppSidebar } from '@/components/app-sidebar';
import {
    StaffCourseOfferingForm,
    StaffCourseSchedulesDashboard,
    StaffCourseTimetableDashboard,
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

export default function DashboardPage({ dashboard }: DashboardPageProps) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const headerOrganizationLabel = dashboardHeaderOrganizationLabel(dashboard);
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
    const staffStudents =
        dashboard.role === 'staff' &&
        dashboard.section === 'students' &&
        dashboard.staffStudents !== null
            ? dashboard.staffStudents
            : null;
    const staffCourseSchedules =
        ['staff', 'hod'].includes(dashboard.role) &&
        dashboard.section === 'courses' &&
        dashboard.staffCourseSchedules !== null
            ? dashboard.staffCourseSchedules
            : null;
    const staffCourseOfferingForm =
        ['staff', 'hod'].includes(dashboard.role) &&
        dashboard.section === 'courses' &&
        dashboard.staffCourseOfferingForm !== null
            ? dashboard.staffCourseOfferingForm
            : null;
    const staffCourseTimetable =
        ['staff', 'hod'].includes(dashboard.role) &&
        dashboard.section === 'courses' &&
        dashboard.staffCourseTimetable !== null
            ? dashboard.staffCourseTimetable
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

    return (
        <>
            <Head title={dashboard.sectionTitle} />
            <SidebarProvider>
                <AppSidebar dashboard={dashboard} user={auth.user} />
                <SidebarInset>
                    <header className="relative flex h-14 shrink-0 items-center gap-2 border-b bg-background px-4">
                        <div className="flex min-w-0 items-center gap-2">
                            <SidebarTrigger />
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
                        {staffCourseOfferingForm ? (
                            <StaffCourseOfferingForm
                                form={staffCourseOfferingForm}
                            />
                        ) : staffCourseTimetable ? (
                            <StaffCourseTimetableDashboard
                                timetable={staffCourseTimetable}
                            />
                        ) : staffCourseSchedules ? (
                            <StaffCourseSchedulesDashboard
                                courseSchedules={staffCourseSchedules}
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
