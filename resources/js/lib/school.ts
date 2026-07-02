import { usePage } from '@inertiajs/react';

import type { SchoolContext } from '@/types';

export function useCurrentSchool(): SchoolContext | null {
    return usePage<{ school: SchoolContext | null }>().props.school;
}

export function schoolEmailPlaceholder(
    school: SchoolContext | null | undefined,
): string {
    const domainKey = school?.domainKey || school?.abbreviation.toLowerCase();

    return domainKey ? `name@${domainKey}.edu` : 'name@school.edu';
}
