import type {
    DashboardContext,
    DashboardDepartment,
    DashboardFaculty,
    DashboardLevel,
} from '@/types';

export function dashboardOrganization(
    dashboard: DashboardContext,
): DashboardDepartment | DashboardFaculty | null {
    return dashboard.role === 'faculty_head'
        ? dashboard.faculty
        : dashboard.department;
}

export function dashboardSidebarRoleLabel(dashboard: DashboardContext): string {
    const organization = dashboardOrganization(dashboard);

    if (dashboard.role === 'staff') {
        const levelLabel = staffLevelLabel(dashboard.levels);
        const advisorLabel = organization
            ? `${organization.abbreviation} Course Advisor`
            : 'Course Advisor';

        return levelLabel ? `${levelLabel} ${advisorLabel}` : advisorLabel;
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

    if (dashboard.role === 'faculty_head') {
        return organization.abbreviation;
    }

    return organization.name;
}

function staffLevelLabel(levels: DashboardLevel[]): string | null {
    if (levels.length === 0) {
        return null;
    }

    return levels.map((level) => shortLevel(level.title)).join(', ');
}

function shortLevel(level: string): string {
    const match = level.match(/^(\d{3})\s+Level$/i);

    return match ? `${match[1]}L` : level;
}
