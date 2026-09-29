import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

export function DashboardPageSurface({
    children,
    bounded = false,
}: {
    children: ReactNode;
    bounded?: boolean;
}) {
    return (
        <section
            className={cn(
                'flex min-h-[calc(100dvh-5.5rem)] w-full flex-col border border-border bg-background p-4 shadow-sm sm:min-h-[calc(100dvh-6.5rem)] sm:p-5',
                bounded
                    ? 'h-[calc(100dvh-5.5rem)] sm:h-[calc(100dvh-6.5rem)]'
                    : 'shrink-0',
            )}
        >
            {children}
        </section>
    );
}
