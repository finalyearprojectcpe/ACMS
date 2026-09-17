import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { DashboardAdviserCourseOffering } from '@/types';

type CourseCategory = DashboardAdviserCourseOffering['category'];

const courseCategoryDetails: Record<
    CourseCategory,
    { label: string; className: string }
> = {
    normal: {
        label: 'Normal Course',
        className:
            'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-300',
    },
    elective: {
        label: 'Elective Course',
        className:
            'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-300',
    },
};

export function CourseCategoryBadge({
    category,
    className,
}: {
    category: CourseCategory;
    className?: string;
}) {
    const detail = courseCategoryDetails[category];

    return (
        <Badge variant="outline" className={cn(detail.className, className)}>
            {detail.label}
        </Badge>
    );
}
