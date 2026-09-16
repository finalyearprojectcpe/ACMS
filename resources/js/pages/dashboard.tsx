import { Head, usePage } from '@inertiajs/react';
import { RiArrowLeftLine } from '@remixicon/react';

import { home as dashboardHome } from '@/actions/App/Http/Controllers/DashboardController';
import { AppSidebar } from '@/components/app-sidebar';
import { FlashToasts } from '@/components/flash-toasts';
import { StudentRecordDashboard } from '@/components/student-record-dashboard';
import { ResultSheetDetailDashboard } from '@/components/result-sheet-detail-dashboard';
import { StaffCourseRegistrationDashboard } from '@/components/staff-course-registration-dashboard';
import {
    StaffCourseOfferingForm,
    StaffCourseSchedulesDashboard,
} from '@/components/staff-course-schedules-dashboard';
import {
    StaffDueDetailDashboard,
    StaffDueFormDashboard,
    StaffDueWaiverFormDashboard,
    StaffDuesDashboard,
} from '@/components/staff-dues-dashboard';
import { StaffHomeDashboard } from '@/components/staff-home-dashboard';
import {
    StaffPaymentAccountFormDashboard,
    StaffPaymentSettingsDashboard,
} from '@/components/staff-payment-settings-dashboard';
import { StaffResultImportWizard } from '@/components/staff-result-import-wizard';
import { StaffResultsDashboard } from '@/components/staff-results-dashboard';
import { StaffSettingsDashboard } from '@/components/staff-settings-dashboard';
import { StaffStudentOnboarding } from '@/components/staff-student-onboarding';
import { StaffStudentsDashboard } from '@/components/staff-students-dashboard';
import { StudentCourseRegistrationDashboard } from '@/components/student-course-registration-dashboard';
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
    const { auth } = usePage<{
        auth: Auth;
    }>().props;
    const headerOrganizationLabel = dashboardHeaderOrganizationLabel(dashboard);
    const canGoBack = dashboard.section !== 'home';

    function goBack() {
        if (window.history.length > 1) {
            window.history.back();

            return;
        }

        window.location.assign(dashboardHome().url);
    }
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
    const staffStudentForm =
        dashboard.role === 'staff' && dashboard.staffStudentForm !== null
            ? dashboard.staffStudentForm
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
    const staffCourseRegistration =
        dashboard.role === 'staff' &&
        dashboard.section === 'course-registration' &&
        dashboard.staffCourseRegistration !== null
            ? dashboard.staffCourseRegistration
            : null;
    const staffResults =
        dashboard.role === 'staff' &&
        dashboard.section === 'result-management' &&
        dashboard.staffResults !== null
            ? dashboard.staffResults
            : null;
    const staffResultImportForm =
        dashboard.role === 'staff' &&
        dashboard.section === 'result-management' &&
        dashboard.staffResultImportForm !== null
            ? dashboard.staffResultImportForm
            : null;
    const staffDues =
        dashboard.role === 'staff' &&
        dashboard.section === 'dues' &&
        dashboard.staffDues !== null
            ? dashboard.staffDues
            : null;
    const staffDueForm =
        dashboard.role === 'staff' &&
        dashboard.section === 'dues' &&
        dashboard.staffDueForm !== null
            ? dashboard.staffDueForm
            : null;
    const staffDueDetail =
        dashboard.role === 'staff' &&
        dashboard.section === 'dues' &&
        dashboard.staffDueDetail !== null
            ? dashboard.staffDueDetail
            : null;
    const staffDueWaiverForm =
        dashboard.role === 'staff' &&
        dashboard.section === 'dues' &&
        dashboard.staffDueWaiverForm !== null
            ? dashboard.staffDueWaiverForm
            : null;
    const staffPaymentSettings =
        dashboard.role === 'staff' &&
        dashboard.section === 'payment-settings' &&
        dashboard.staffPaymentSettings !== null
            ? dashboard.staffPaymentSettings
            : null;
    const staffPaymentAccountForm =
        dashboard.role === 'staff' &&
        dashboard.section === 'payment-settings' &&
        dashboard.staffPaymentAccountForm !== null
            ? dashboard.staffPaymentAccountForm
            : null;
    const staffSettings =
        dashboard.role === 'staff' &&
        dashboard.section === 'profile-settings' &&
        dashboard.staffSettings !== null
            ? dashboard.staffSettings
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
    const studentCourseRegistration =
        dashboard.role === 'student' &&
        dashboard.section === 'course-registration' &&
        dashboard.studentCourseRegistration !== null
            ? dashboard.studentCourseRegistration
            : null;
    const studentProfile =
        dashboard.role === 'student' &&
        dashboard.section === 'profile-settings' &&
        dashboard.studentProfile !== null
            ? dashboard.studentProfile
            : null;
    const studentRecord =
        dashboard.role === 'staff' &&
        dashboard.studentRecord !== null
            ? dashboard.studentRecord
            : null;
    const resultSheetDetail =
        dashboard.resultSheetDetail !== null
            ? dashboard.resultSheetDetail
            : null;

    return (
        <>
            <FlashToasts />
            <Head title={dashboard.sectionTitle} />
            <SidebarProvider>
                <AppSidebar dashboard={dashboard} user={auth.user} />
                <SidebarInset>
                    <header className="sticky top-0 z-40 flex h-14 shrink-0 items-center gap-2 border-b bg-background px-4">
                        <div className="flex min-w-0 items-center gap-2">
                            <SidebarTrigger />
                            {canGoBack ? (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon-sm"
                                    aria-label="Go to previous page"
                                    onClick={goBack}
                                >
                                    <RiArrowLeftLine />
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
                        </div>
                    </header>

                    <main className="background-graph flex flex-1 flex-col p-4 sm:p-6">
                        {resultSheetDetail ? (
                            <ResultSheetDetailDashboard
                                detail={resultSheetDetail}
                            />
                        ) : staffResultImportForm ? (
                            <StaffResultImportWizard
                                form={staffResultImportForm}
                            />
                        ) : staffDueWaiverForm ? (
                            <StaffDueWaiverFormDashboard
                                form={staffDueWaiverForm}
                            />
                        ) : staffDueForm ? (
                            <StaffDueFormDashboard form={staffDueForm} />
                        ) : staffDueDetail ? (
                            <StaffDueDetailDashboard detail={staffDueDetail} />
                        ) : staffCourseOfferingForm ? (
                            <StaffCourseOfferingForm
                                form={staffCourseOfferingForm}
                                role={dashboard.role}
                            />
                        ) : staffCourseSchedules ? (
                            <StaffCourseSchedulesDashboard
                                courseSchedules={staffCourseSchedules}
                            />
                        ) : staffResults ? (
                            <StaffResultsDashboard results={staffResults} />
                        ) : staffCourseRegistration ? (
                            <StaffCourseRegistrationDashboard
                                registration={staffCourseRegistration}
                            />
                        ) : staffDues ? (
                            <StaffDuesDashboard dues={staffDues} />
                        ) : staffPaymentAccountForm ? (
                            <StaffPaymentAccountFormDashboard
                                form={staffPaymentAccountForm}
                            />
                        ) : staffPaymentSettings ? (
                            <StaffPaymentSettingsDashboard
                                settings={staffPaymentSettings}
                            />
                        ) : staffSettings ? (
                            <StaffSettingsDashboard settings={staffSettings} />
                        ) : studentRecord ? (
                            <StudentRecordDashboard record={studentRecord} />
                        ) : staffHome ? (
                            <StaffHomeDashboard home={staffHome} />
                        ) : staffStudentForm ? (
                            <StaffStudentOnboarding
                                onboarding={staffStudentForm}
                            />
                        ) : staffStudents ? (
                            <StaffStudentsDashboard students={staffStudents} />
                        ) : studentHome ? (
                            <StudentHomeDashboard home={studentHome} />
                        ) : studentResults ? (
                            <StudentResultsDashboard results={studentResults} />
                        ) : studentDues ? (
                            <StudentDuesDashboard dues={studentDues} />
                        ) : studentCourseRegistration ? (
                            <StudentCourseRegistrationDashboard
                                registration={studentCourseRegistration}
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
