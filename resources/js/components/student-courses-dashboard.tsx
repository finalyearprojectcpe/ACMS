import { section as dashboardSection } from '@/actions/App/Http/Controllers/DashboardController';
import { AcademicPeriodLayout } from '@/components/academic-period-layout';
import { AcademicPeriodNavigation } from '@/components/academic-period-navigation';
import { CourseRegistrationCourseTable } from '@/components/course-registration-course-table';
import { DownloadButton } from '@/components/download-button';
import { QualifiedSemester } from '@/components/qualified-semester';
import { ScrollArea } from '@/components/ui/scroll-area';
import { pdf as courseListPdf } from '@/routes/dashboard/student-courses';
import type { DashboardStudentCourses } from '@/types';

export function StudentCoursesDashboard({
    courses,
}: {
    courses: DashboardStudentCourses;
}) {
    const period = courses.currentSemester;
    const totalUnits = courses.selectedCourses.reduce(
        (total, course) => total + course.units,
        0,
    );

    return (
        <AcademicPeriodLayout
            navigation={(collapsed) => (
                <AcademicPeriodNavigation
                    collapsed={collapsed}
                    ariaLabel="Course semesters"
                    groups={courses.sessions.map((session) => ({
                        key: session.publicId,
                        label: session.level.title,
                        detail: `${session.name} Session`,
                        compactLabel: `${session.level.yearNumber}00L`,
                        links: session.semesters.map((semester) => ({
                            key: `${session.publicId}:${semester.slug}`,
                            href: dashboardSection.url(
                                { section: 'courses' },
                                {
                                    query: {
                                        session: session.publicId,
                                        semester: semester.slug,
                                    },
                                },
                            ),
                            label: semester.name,
                            compactLabel:
                                semester.slug === 'first' ? '1st' : '2nd',
                            selected:
                                semester.id === courses.selectedSemesterId,
                        })),
                    }))}
                />
            )}
        >
            <ScrollArea className="min-h-0 min-w-0">
                <div className="mx-auto w-full max-w-4xl py-5 md:px-5 md:py-6">
                    {period ? (
                        <>
                            <header className="flex flex-wrap items-start justify-between gap-4 border-b px-3 pb-5 md:px-0">
                                <div>
                                    <h1 className="text-lg font-semibold">
                                        <QualifiedSemester
                                            level={period.level}
                                            semester={period.name}
                                        />
                                    </h1>
                                    <p className="text-sm text-muted-foreground">
                                        {period.session} Session
                                    </p>
                                </div>
                            </header>
                            <div className="mt-5 border-y">
                                <CourseRegistrationCourseTable
                                    courses={courses.selectedCourses}
                                    selectedKeys={
                                        new Set(
                                            courses.selectedCourses.map(
                                                (course) =>
                                                    String(
                                                        course.courseOfferingId,
                                                    ),
                                            ),
                                        )
                                    }
                                    selectable={false}
                                    totalUnits={totalUnits}
                                    emptyLabel="Your course adviser has not assigned courses for this semester."
                                />
                            </div>
                            {courses.selectedCourses.length > 0 && (
                                <div className="flex justify-end pt-4">
                                    <DownloadButton
                                        href={courseListPdf.url({
                                            sessionYears: period.sessionYears,
                                            semester: period.slug,
                                        })}
                                    >
                                        Download as PDF
                                    </DownloadButton>
                                </div>
                            )}
                        </>
                    ) : (
                        <p className="px-4 py-12 text-center text-sm text-muted-foreground">
                            {courses.sessions.length
                                ? 'Choose a semester to see your courses.'
                                : 'Your course adviser has not added any sessions yet.'}
                        </p>
                    )}
                </div>
            </ScrollArea>
        </AcademicPeriodLayout>
    );
}
