import { studentSetListText } from '@/components/student-set-label';
import type { DashboardContext, DashboardStudentSetContext } from '@/types';

export function dashboardSidebarRoleLabel(dashboard: DashboardContext): string {
    if (dashboard.role === 'adviser') {
        const setLabel = adviserSetLabel(dashboard.advisedSets);

        return setLabel ? `${setLabel} Course Adviser` : 'Course Adviser';
    }

    return dashboard.roleLabel;
}

function adviserSetLabel(sets: DashboardStudentSetContext[]): string | null {
    if (sets.length === 0) {
        return null;
    }

    return studentSetListText(sets);
}
