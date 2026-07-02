import { studentSetText } from '@/components/student-set-label';
import type {
    DashboardContext,
    DashboardDepartment,
    DashboardFaculty,
    DashboardStudentSetContext,
} from '@/types';

export function dashboardOrganization(
    dashboard: DashboardContext,
): DashboardDepartment | DashboardFaculty | null {
    if (dashboard.role === 'school_admin') {
        return dashboard.schoolAdmin?.school ?? null;
    }

    return dashboard.role === 'faculty_head'
        ? dashboard.faculty
        : dashboard.department;
}

export function dashboardSidebarRoleLabel(dashboard: DashboardContext): string {
    const organization = dashboardOrganization(dashboard);

    if (dashboard.role === 'staff') {
        const setLabel = staffSetLabel(
            dashboard.advisedSets,
            dashboard.department?.minimumYearsToDegree,
        );
        const advisorLabel = organization
            ? `${organization.abbreviation} Course Adviser`
            : 'Course Adviser';

        return setLabel ? `${setLabel} ${advisorLabel}` : advisorLabel;
    }

    if (dashboard.role === 'school_admin' && organization !== null) {
        return `${organization.abbreviation} School Admin`;
    }

    if (organization === null) {
        return dashboard.roleLabel;
    }

    return `${organization.abbreviation} ${dashboard.roleLabel}`;
}

export function dashboardHeaderOrganizationLabel(
    dashboard: DashboardContext,
): string | null {
    const organization = dashboardOrganization(dashboard);

    if (organization === null) {
        return null;
    }

    if (['faculty_head', 'school_admin'].includes(dashboard.role)) {
        return organization.abbreviation;
    }

    return organization.name;
}

function staffSetLabel(
    sets: DashboardStudentSetContext[],
    minimumYearsToDegree?: number,
): string | null {
    if (sets.length === 0) {
        return null;
    }

    return sets
        .map((set) => studentSetText({ ...set, minimumYearsToDegree }))
        .join(', ');
}
