import type { DashboardContext } from '@/types';

export function dashboardSidebarRoleLabel(dashboard: DashboardContext): string {
    if (dashboard.role === 'adviser') {
        return 'Course Advisor';
    }

    return dashboard.roleLabel;
}
