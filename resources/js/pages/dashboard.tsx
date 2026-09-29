import { Head, Link, router, usePage } from '@inertiajs/react';
import { RiArrowLeftLine } from '@remixicon/react';
import { useEffect, useState } from 'react';

import {
    createStudentSet,
    section as dashboardSection,
} from '@/actions/App/Http/Controllers/DashboardController';
import { AdviserAcademicSessionForm } from '@/components/adviser-academic-session-form';
import {
    AdviserPaymentHistoryDashboard,
    AdviserRegistrationActivityDashboard,
} from '@/components/adviser-activity-dashboard';
import { AdviserAssistantDashboard } from '@/components/adviser-assistant-dashboard';
import { AdviserCourseRegistrationDashboard } from '@/components/adviser-course-registration-dashboard';
import { AdviserCourseOfferingForm } from '@/components/adviser-course-schedule-forms';
import { AdviserDueDetailDashboard } from '@/components/adviser-due-detail-dashboard';
import { AdviserDueFormDashboard } from '@/components/adviser-due-form-dashboard';
import { AdviserDueWaiverFormDashboard } from '@/components/adviser-due-waiver-form-dashboard';
import { AdviserDuesDashboard } from '@/components/adviser-dues-dashboard';
import { AdviserHomeDashboard } from '@/components/adviser-home-dashboard';
import {
    AdviserJoinRequestDashboard,
    AdviserJoinRequestsDashboard,
} from '@/components/adviser-join-requests-dashboard';
import { AdviserPaymentAccountFormDashboard } from '@/components/adviser-payment-account-form-dashboard';
import { AdviserGradeRoster } from '@/components/adviser-results-dashboard';
import { AdviserSemesterWorkspace } from '@/components/adviser-semester-workspace';
import { AdviserProfileDashboard } from '@/components/adviser-settings-dashboard';
import { AdviserStudentSetsDashboard } from '@/components/adviser-student-sets-dashboard';
import { AdviserStudentsDashboard } from '@/components/adviser-students-dashboard';
import { AppSidebar } from '@/components/app-sidebar';
import { DashboardPageSurface } from '@/components/dashboard-page-surface';
import { FlashToasts } from '@/components/flash-toasts';
import { StudentCourseRegistrationDashboard } from '@/components/student-course-registration-dashboard';
import { StudentDuesDashboard } from '@/components/student-dues-dashboard';
import { StudentHomeDashboard } from '@/components/student-home-dashboard';
import { StudentProfileDashboard } from '@/components/student-profile-dashboard';
import {
    StudentRecordDashboard,
    StudentRecordResultsDashboard,
} from '@/components/student-record-dashboard';
import { StudentResultStatementDashboard } from '@/components/student-result-statement-dashboard';
import { StudentResultsView } from '@/components/student-results-view';
import { StudentSetSettings } from '@/components/student-set-settings';
import { ThemeToggle } from '@/components/theme-toggle';
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyTitle,
} from '@/components/ui/empty';
import { Separator } from '@/components/ui/separator';
import {
    SidebarInset,
    SidebarProvider,
    SidebarTrigger,
} from '@/components/ui/sidebar';
import type { Auth, DashboardContext } from '@/types';

type DashboardPageProps = {
    dashboard: DashboardContext;
};

const initialDashboardUrl =
    typeof window === 'undefined'
        ? ''
        : window.location.pathname + window.location.search;
const hasInternalReferrer =
    typeof document !== 'undefined' &&
    document.referrer !== '' &&
    new URL(document.referrer).origin === window.location.origin;

export default function DashboardPage({ dashboard }: DashboardPageProps) {
    const { auth } = usePage<{
        auth: Auth;
    }>().props;
    const [hasInternalHistory, setHasInternalHistory] =
        useState(hasInternalReferrer);
    useEffect(
        () =>
            router.on('navigate', (event) => {
                setHasInternalHistory(
                    hasInternalReferrer ||
                        event.detail.page.url !== initialDashboardUrl,
                );
            }),
        [],
    );
    const canGoBack =
        dashboard.section !== 'home' &&
        hasInternalHistory &&
        typeof window !== 'undefined' &&
        window.history.length > 1;

    function goBack() {
        window.history.back();
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
    const adviserJoinRequests = dashboard.adviserJoinRequests;
    const adviserJoinRequest = dashboard.adviserJoinRequest;
    const adviserRegistrationActivity = dashboard.adviserRegistrationActivity;
    const adviserPaymentHistory = dashboard.adviserPaymentHistory;
    const adviserAssistant = dashboard.adviserAssistant;
    const adviserStudentSets =
        dashboard.role === 'adviser' &&
        dashboard.section === 'student-sets' &&
        dashboard.adviserStudentSets !== null
            ? dashboard.adviserStudentSets
            : null;
    const adviserStudents =
        dashboard.role === 'adviser' &&
        dashboard.section === 'students' &&
        dashboard.adviserStudents !== null
            ? dashboard.adviserStudents
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
    const adviserAcademicSessionForm =
        dashboard.role === 'adviser' &&
        dashboard.section === 'student-sets' &&
        dashboard.adviserAcademicSessionForm !== null
            ? dashboard.adviserAcademicSessionForm
            : null;
    const adviserCourseRegistration =
        dashboard.role === 'adviser' &&
        dashboard.section === 'course-registration' &&
        dashboard.adviserCourseRegistration !== null
            ? dashboard.adviserCourseRegistration
            : null;
    const adviserResults = dashboard.adviserResults;
    const adviserGradeRoster = dashboard.adviserGradeRoster;
    const adviserDues =
        dashboard.role === 'adviser' &&
        dashboard.section === 'student-dues' &&
        dashboard.adviserDues !== null
            ? dashboard.adviserDues
            : null;
    const adviserDueForm =
        dashboard.role === 'adviser' &&
        dashboard.section === 'student-dues' &&
        dashboard.adviserDueForm !== null
            ? dashboard.adviserDueForm
            : null;
    const adviserDueDetail =
        dashboard.role === 'adviser' &&
        dashboard.section === 'student-dues' &&
        dashboard.adviserDueDetail !== null
            ? dashboard.adviserDueDetail
            : null;
    const adviserDueWaiverForm =
        dashboard.role === 'adviser' &&
        dashboard.section === 'student-dues' &&
        dashboard.adviserDueWaiverForm !== null
            ? dashboard.adviserDueWaiverForm
            : null;
    const adviserPaymentSettings =
        dashboard.role === 'adviser' &&
        dashboard.section === 'settings' &&
        dashboard.adviserPaymentSettings !== null
            ? dashboard.adviserPaymentSettings
            : null;
    const adviserPaymentAccountForm =
        dashboard.role === 'adviser' &&
        dashboard.section === 'settings' &&
        dashboard.adviserPaymentAccountForm !== null
            ? dashboard.adviserPaymentAccountForm
            : null;
    const adviserProfile =
        dashboard.role === 'adviser' &&
        dashboard.section === 'settings' &&
        dashboard.adviserProfile !== null
            ? dashboard.adviserProfile
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
    const studentRecordResults = dashboard.studentRecordResults;
    const studentRecordStatement = dashboard.studentRecordStatement;
    const studentResults = dashboard.studentResults;

    return (
        <>
            <FlashToasts />
            <Head title={dashboard.sectionTitle} />
            <SidebarProvider>
                <AppSidebar dashboard={dashboard} user={auth.user} />
                <SidebarInset
                    className={
                        adviserAssistant
                            ? 'h-dvh min-h-0 min-w-0 overflow-hidden'
                            : 'min-w-0'
                    }
                >
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
                        {studentHeaderMeta ? (
                            <div className="absolute left-1/2 hidden max-w-[42vw] -translate-x-1/2 flex-col items-center text-center leading-tight sm:flex">
                                <div className="max-w-full truncate text-xs text-muted-foreground/80">
                                    {studentHeaderMeta}
                                </div>
                            </div>
                        ) : null}
                        <div className="ml-auto flex items-center gap-1">
                            <ThemeToggle />
                        </div>
                    </header>

                    <main
                        className={
                            adviserAssistant
                                ? 'flex min-h-0 flex-1 flex-col overflow-hidden bg-background'
                                : 'background-graph flex flex-1 flex-col p-4 sm:p-6'
                        }
                    >
                        {adviserAssistant ? (
                            <AdviserAssistantDashboard
                                key={adviserAssistant.selectedId ?? 'new'}
                                assistant={adviserAssistant}
                                role={dashboard.role}
                            />
                        ) : (
                            <DashboardPageSurface
                                bounded={
                                    !studentRecord &&
                                    !studentRecordResults &&
                                    !studentRecordStatement &&
                                    (!!adviserCourseSchedules ||
                                        !!adviserDues ||
                                        !!adviserStudents ||
                                        !!adviserResults ||
                                        !!adviserGradeRoster ||
                                        !!adviserCourseOfferingForm ||
                                        !!dashboard.studentSetSettings ||
                                        !!studentCourseRegistration ||
                                        (!!adviserCourseRegistration &&
                                            !adviserCourseRegistration.review))
                                }
                            >
                                {adviserRegistrationActivity ? (
                                    <AdviserRegistrationActivityDashboard
                                        activity={adviserRegistrationActivity}
                                    />
                                ) : adviserPaymentHistory ? (
                                    <AdviserPaymentHistoryDashboard
                                        history={adviserPaymentHistory}
                                    />
                                ) : adviserJoinRequest ? (
                                    <AdviserJoinRequestDashboard
                                        request={adviserJoinRequest}
                                    />
                                ) : adviserJoinRequests ? (
                                    <AdviserJoinRequestsDashboard
                                        listing={adviserJoinRequests}
                                    />
                                ) : dashboard.studentSetSettings ? (
                                    <StudentSetSettings
                                        key={
                                            dashboard.studentSetSettings.set.id
                                        }
                                        settings={dashboard.studentSetSettings}
                                    />
                                ) : adviserDueWaiverForm ? (
                                    <AdviserDueWaiverFormDashboard
                                        form={adviserDueWaiverForm}
                                    />
                                ) : adviserDueForm ? (
                                    <AdviserDueFormDashboard
                                        form={adviserDueForm}
                                    />
                                ) : adviserDueDetail ? (
                                    <AdviserDueDetailDashboard
                                        detail={adviserDueDetail}
                                    />
                                ) : adviserAcademicSessionForm ? (
                                    <AdviserAcademicSessionForm
                                        form={adviserAcademicSessionForm}
                                    />
                                ) : adviserCourseOfferingForm ? (
                                    <AdviserCourseOfferingForm
                                        form={adviserCourseOfferingForm}
                                    />
                                ) : adviserCourseRegistration?.review ? (
                                    <AdviserCourseRegistrationDashboard
                                        registration={adviserCourseRegistration}
                                    />
                                ) : adviserGradeRoster ? (
                                    <AdviserGradeRoster
                                        key={`${adviserGradeRoster.course.code}:${adviserGradeRoster.period.session}:${adviserGradeRoster.period.semester}`}
                                        roster={adviserGradeRoster}
                                    />
                                ) : adviserCourseSchedules ||
                                  adviserCourseRegistration ||
                                  adviserResults ? (
                                    <AdviserSemesterWorkspace
                                        view={
                                            adviserCourseSchedules
                                                ? 'courses'
                                                : adviserCourseRegistration
                                                  ? 'registrations'
                                                  : 'results'
                                        }
                                        schedules={adviserCourseSchedules}
                                        registrations={
                                            adviserCourseRegistration
                                        }
                                        results={adviserResults}
                                    />
                                ) : adviserDues ? (
                                    <AdviserDuesDashboard dues={adviserDues} />
                                ) : adviserPaymentAccountForm ? (
                                    <AdviserPaymentAccountFormDashboard
                                        form={adviserPaymentAccountForm}
                                    />
                                ) : adviserProfile && adviserPaymentSettings ? (
                                    <AdviserProfileDashboard
                                        profile={adviserProfile}
                                        settings={adviserPaymentSettings}
                                    />
                                ) : adviserStudentSets ? (
                                    <AdviserStudentSetsDashboard
                                        settings={adviserStudentSets}
                                    />
                                ) : studentRecordResults ? (
                                    <StudentRecordResultsDashboard
                                        record={studentRecordResults}
                                    />
                                ) : studentRecordStatement ? (
                                    <StudentResultStatementDashboard
                                        statement={studentRecordStatement}
                                    />
                                ) : studentRecord ? (
                                    <StudentRecordDashboard
                                        record={studentRecord}
                                    />
                                ) : adviserHome ? (
                                    <AdviserHomeDashboard home={adviserHome} />
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
                                        key={
                                            studentCourseRegistration.selectedSemesterId ??
                                            'none'
                                        }
                                        registration={studentCourseRegistration}
                                    />
                                ) : studentProfile ? (
                                    <StudentProfileDashboard
                                        profile={studentProfile}
                                    />
                                ) : studentResults ? (
                                    <div className="mx-auto w-full max-w-6xl">
                                        <StudentResultsView
                                            results={studentResults}
                                            overviewUrl={
                                                dashboardSection({
                                                    section: 'results',
                                                }).url
                                            }
                                        />
                                    </div>
                                ) : dashboard.role === 'adviser' &&
                                  dashboard.section === 'results' ? (
                                    <Empty className="flex-1">
                                        <EmptyHeader>
                                            <EmptyTitle>
                                                No student sets yet
                                            </EmptyTitle>
                                            <EmptyDescription>
                                                Add a student set before
                                                entering results.
                                            </EmptyDescription>
                                        </EmptyHeader>
                                        <Button asChild>
                                            <Link href={createStudentSet()}>
                                                Add a New Set
                                            </Link>
                                        </Button>
                                    </Empty>
                                ) : (
                                    <h1 className="text-2xl font-semibold tracking-normal">
                                        {dashboard.sectionTitle}
                                    </h1>
                                )}
                            </DashboardPageSurface>
                        )}
                    </main>
                </SidebarInset>
            </SidebarProvider>
        </>
    );
}
