import { studentSetListText } from '@/components/student-set-label';
import type {
    DashboardContext,
    DashboardProgramme,
    DashboardStudentSetContext,
} from '@/types';

export function dashboardOrganization(
    dashboard: DashboardContext,
): DashboardProgramme | null {
    return dashboard.programme;
}

export function dashboardSidebarRoleLabel(dashboard: DashboardContext): string {
    const organization = dashboardOrganization(dashboard);

    if (dashboard.role === 'adviser') {
        const setLabel = adviserSetLabel(
            dashboard.advisedSets,
            dashboard.programme?.minimumYearsToDegree,
        );
        const advisorLabel = organization
            ? `${organization.abbreviation} Course Adviser`
            : 'Course Adviser';

        return setLabel ? `${setLabel} ${advisorLabel}` : advisorLabel;
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

    return organization.name;
}

function adviserSetLabel(
    sets: DashboardStudentSetContext[],
    minimumYearsToDegree?: number,
): string | null {
    if (sets.length === 0) {
        return null;
    }

    return studentSetListText(
        sets.map((set) => ({ ...set, minimumYearsToDegree })),
    );
}
