import type {
    DashboardAcademicSession,
    DashboardStudentSetContext,
} from '@/types';

export type StudentSetRouteParameters = {
    departmentSlug: string;
    entrySession: string;
};

export function sessionRouteYears(
    session: Pick<DashboardAcademicSession, 'startYear' | 'endYear'>,
): string {
    return `${session.startYear}${session.endYear}`;
}

export function studentSetRouteParameters(
    studentSet: DashboardStudentSetContext,
): StudentSetRouteParameters {
    return {
        departmentSlug: studentSet.entrySession.departmentSlug,
        entrySession: sessionRouteYears(studentSet.entrySession),
    };
}
