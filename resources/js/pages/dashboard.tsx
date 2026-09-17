import { Head, usePage } from '@inertiajs/react';
import { RiArrowLeftLine } from '@remixicon/react';

import { home as dashboardHome } from '@/actions/App/Http/Controllers/DashboardController';
import { AdviserCourseRegistrationDashboard } from '@/components/adviser-course-registration-dashboard';
import {
    AdviserCourseOfferingForm,
    AdviserCourseSchedulesDashboard,
} from '@/components/adviser-course-schedules-dashboard';
import { AdviserDueDetailDashboard } from '@/components/adviser-due-detail-dashboard';
import { AdviserDueFormDashboard } from '@/components/adviser-due-form-dashboard';
import { AdviserDueWaiverFormDashboard } from '@/components/adviser-due-waiver-form-dashboard';
import { AdviserDuesDashboard } from '@/components/adviser-dues-dashboard';
import { AdviserHomeDashboard } from '@/components/adviser-home-dashboard';
import { AdviserPaymentAccountFormDashboard } from '@/components/adviser-payment-account-form-dashboard';
import { AdviserPaymentSettingsDashboard } from '@/components/adviser-payment-settings-dashboard';
import { AdviserSettingsDashboard } from '@/components/adviser-settings-dashboard';
import { AdviserStudentOnboarding } from '@/components/adviser-student-onboarding';
import { AdviserStudentsDashboard } from '@/components/adviser-students-dashboard';
import { AppSidebar } from '@/components/app-sidebar';
import { FlashToasts } from '@/components/flash-toasts';
import { StudentCourseRegistrationDashboard } from '@/components/student-course-registration-dashboard';
import { StudentDuesDashboard } from '@/components/student-dues-dashboard';
import { StudentHomeDashboard } from '@/components/student-home-dashboard';
import { StudentProfileDashboard } from '@/components/student-profile-dashboard';
import { StudentRecordDashboard } from '@/components/student-record-dashboard';
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
    const adviserHome =
        dashboard.role === 'adviser' &&
        dashboard.section === 'home' &&
        dashboard.adviserHome !== null
            ? dashboard.adviserHome
            : null;
    const adviserStudents =
        dashboard.role === 'adviser' &&
        dashboard.section === 'students' &&
        dashboard.adviserStudents !== null
            ? dashboard.adviserStudents
            : null;
    const adviserStudentForm =
        dashboard.role === 'adviser' && dashboard.adviserStudentForm !== null
            ? dashboard.adviserStudentForm
            : null;
    const adviserCourseSchedules =
        dashboard.role === 'adviser' &&
        dashboard.section === 'courses' &&
        dashboard.adviserCourseSchedules !== null
            ? dashboard.adviserCourseSchedules
            : null;
    const adviserCourseOfferingForm =
        dashboard.role === 'adviser' &&
        dashboard.section === 'courses' &&
        dashboard.adviserCourseOfferingForm !== null
            ? dashboard.adviserCourseOfferingForm
            : null;
    const adviserCourseRegistration =
        dashboard.role === 'adviser' &&
        dashboard.section === 'course-registration' &&
        dashboard.adviserCourseRegistration !== null
            ? dashboard.adviserCourseRegistration
            : null;
    const adviserDues =
        dashboard.role === 'adviser' &&
        dashboard.section === 'dues' &&
        dashboard.adviserDues !== null
            ? dashboard.adviserDues
            : null;
    const adviserDueForm =
        dashboard.role === 'adviser' &&
        dashboard.section === 'dues' &&
        dashboard.adviserDueForm !== null
            ? dashboard.adviserDueForm
            : null;
    const adviserDueDetail =
        dashboard.role === 'adviser' &&
        dashboard.section === 'dues' &&
        dashboard.adviserDueDetail !== null
            ? dashboard.adviserDueDetail
            : null;
    const adviserDueWaiverForm =
        dashboard.role === 'adviser' &&
        dashboard.section === 'dues' &&
        dashboard.adviserDueWaiverForm !== null
            ? dashboard.adviserDueWaiverForm
            : null;
    const adviserPaymentSettings =
        dashboard.role === 'adviser' &&
        dashboard.section === 'payment-settings' &&
        dashboard.adviserPaymentSettings !== null
            ? dashboard.adviserPaymentSettings
            : null;
    const adviserPaymentAccountForm =
        dashboard.role === 'adviser' &&
        dashboard.section === 'payment-settings' &&
        dashboard.adviserPaymentAccountForm !== null
            ? dashboard.adviserPaymentAccountForm
            : null;
    const adviserSettings =
        dashboard.role === 'adviser' &&
        dashboard.section === 'profile-settings' &&
        dashboard.adviserSettings !== null
            ? dashboard.adviserSettings
            : null;
    const studentHome =
        dashboard.role === 'student' &&
        dashboard.section === 'home' &&
        dashboard.studentHome !== null
            ? dashboard.studentHome
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
        dashboard.role === 'adviser' && dashboard.studentRecord !== null
            ? dashboard.studentRecord
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
                        {adviserDueWaiverForm ? (
                            <AdviserDueWaiverFormDashboard
                                form={adviserDueWaiverForm}
                            />
                        ) : adviserDueForm ? (
                            <AdviserDueFormDashboard form={adviserDueForm} />
                        ) : adviserDueDetail ? (
                            <AdviserDueDetailDashboard
                                detail={adviserDueDetail}
                            />
                        ) : adviserCourseOfferingForm ? (
                            <AdviserCourseOfferingForm
                                form={adviserCourseOfferingForm}
                            />
                        ) : adviserCourseSchedules ? (
                            <AdviserCourseSchedulesDashboard
                                courseSchedules={adviserCourseSchedules}
                            />
                        ) : adviserCourseRegistration ? (
                            <AdviserCourseRegistrationDashboard
                                registration={adviserCourseRegistration}
                            />
                        ) : adviserDues ? (
                            <AdviserDuesDashboard dues={adviserDues} />
                        ) : adviserPaymentAccountForm ? (
                            <AdviserPaymentAccountFormDashboard
                                form={adviserPaymentAccountForm}
                            />
                        ) : adviserPaymentSettings ? (
                            <AdviserPaymentSettingsDashboard
                                settings={adviserPaymentSettings}
                            />
                        ) : adviserSettings ? (
                            <AdviserSettingsDashboard
                                settings={adviserSettings}
                            />
                        ) : studentRecord ? (
                            <StudentRecordDashboard record={studentRecord} />
                        ) : adviserHome ? (
                            <AdviserHomeDashboard home={adviserHome} />
                        ) : adviserStudentForm ? (
                            <AdviserStudentOnboarding
                                onboarding={adviserStudentForm}
                            />
                        ) : adviserStudents ? (
                            <AdviserStudentsDashboard
                                students={adviserStudents}
                            />
                        ) : studentHome ? (
                            <StudentHomeDashboard home={studentHome} />
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
