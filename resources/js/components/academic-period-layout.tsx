import { RiSideBarLine } from '@remixicon/react';
import { useSyncExternalStore } from 'react';
import type { ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

function subscribe(onChange: () => void): () => void {
    window.addEventListener('storage', onChange);
    window.addEventListener('academic-period-rail-change', onChange);

    return () => {
        window.removeEventListener('storage', onChange);
        window.removeEventListener('academic-period-rail-change', onChange);
    };
}

function railCollapsed(): boolean {
    return window.localStorage.getItem('academic-period-rail') === 'collapsed';
}

export function AcademicPeriodLayout({
    navigation,
    children,
    className,
}: {
    navigation: (collapsed: boolean) => ReactNode;
    children: ReactNode;
    className?: string;
}) {
    const collapsed = useSyncExternalStore(
        subscribe,
        railCollapsed,
        () => false,
    );

    function toggle() {
        window.localStorage.setItem(
            'academic-period-rail',
            collapsed ? 'open' : 'collapsed',
        );
        window.dispatchEvent(new Event('academic-period-rail-change'));
    }

    return (
        <div
            className={cn(
                'flex min-h-0 min-w-0 flex-1 flex-col md:flex-row',
                className,
            )}
        >
            <div
                className={`flex min-h-0 shrink-0 flex-col border-b md:border-r md:border-b-0 ${collapsed ? 'md:w-16' : 'md:w-52'}`}
            >
                <div
                    className={`hidden shrink-0 py-2 md:flex ${collapsed ? 'justify-center' : 'justify-end pr-2'}`}
                >
                    <Tooltip>
                        <TooltipTrigger asChild>
                            <Button
                                size="icon-sm"
                                variant="ghost"
                                type="button"
                                onClick={toggle}
                                aria-label={
                                    collapsed
                                        ? 'Expand semester navigation'
                                        : 'Collapse semester navigation'
                                }
                                aria-expanded={!collapsed}
                            >
                                <RiSideBarLine className="size-4" />
                            </Button>
                        </TooltipTrigger>
                        <TooltipContent>
                            {collapsed
                                ? 'Expand semester navigation'
                                : 'Collapse semester navigation'}
                        </TooltipContent>
                    </Tooltip>
                </div>
                <div className="min-h-0 flex-1">{navigation(collapsed)}</div>
            </div>
            <div className="flex min-h-0 min-w-0 flex-1 flex-col">
                {children}
            </div>
        </div>
    );
}
