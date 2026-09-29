import { shortLevelLabel } from '@/lib/academic';
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

    return sets
        .map(
            (set) =>
                `${set.name} · ${set.currentLevel ? shortLevelLabel(set.currentLevel.title) : 'Level not set'}`,
        )
        .join(', ');
}
