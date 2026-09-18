import type { DashboardAcademicSession } from '@/types';

export function shortLevelLabel(level: string): string {
    return level.replace(/\s*Level$/i, 'L');
}

export function academicLevelNumber(level: string): number {
    const match = level.match(/^(\d+)/);

    return match ? Number(match[1]) : 999;
}

export function semesterName(semester: string): string {
    if (semester.toLowerCase().includes('rain')) {
        return 'Rain';
    }

    if (semester.toLowerCase().includes('harmattan')) {
        return 'Harmattan';
    }

    return semester.replace(/\s*Semester$/i, '');
}

export function semesterLabel(semester: string): string {
    return `${semesterName(semester)} Semester`;
}

export function academicSemesterOrder(semester: string): number {
    const name = semesterName(semester);

    return name === 'Harmattan' ? 0 : name === 'Rain' ? 1 : 99;
}

export function qualifiedSemesterLabel(
    level: string,
    semester: string,
): string {
    return `${shortLevelLabel(level)} ${semesterLabel(semester)}`;
}

export function sessionDisplayName(
    session: Pick<DashboardAcademicSession, 'name'> | null | undefined,
): string {
    if (!session) {
        return 'No active session';
    }

    return /session$/i.test(session.name)
        ? session.name
        : `${session.name} Session`;
}
