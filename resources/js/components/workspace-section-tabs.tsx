import { Link } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import { cn } from '@/lib/utils';

type WorkspaceSectionTab = {
    key: string;
    label: string;
    mobileLabel?: string;
    href: string;
};

export function WorkspaceSectionTabs({
    label,
    tabs,
    activeTab,
    className,
}: {
    label: string;
    tabs: WorkspaceSectionTab[];
    activeTab: string;
    className?: string;
}) {
    const tabArea = useRef<HTMLDivElement>(null);
    const activeLink = useRef<HTMLAnchorElement>(null);

    useEffect(() => {
        const viewport = tabArea.current?.querySelector<HTMLElement>(
            '[data-slot="scroll-area-viewport"]',
        );
        const link = activeLink.current;

        if (!viewport || !link) {
            return;
        }

        const viewportBounds = viewport.getBoundingClientRect();
        const linkBounds = link.getBoundingClientRect();

        viewport.scrollTo({
            left:
                viewport.scrollLeft +
                linkBounds.left -
                viewportBounds.left -
                (viewport.clientWidth - linkBounds.width) / 2,
        });
    }, [activeTab]);

    return (
        <ScrollArea ref={tabArea} className={cn('min-w-0', className)}>
            <nav aria-label={label} className="flex w-max items-center gap-1">
                {tabs.map((tab) => {
                    const active = tab.key === activeTab;

                    return (
                        <Link
                            ref={active ? activeLink : undefined}
                            key={tab.key}
                            href={tab.href}
                            aria-label={tab.mobileLabel ? tab.label : undefined}
                            aria-current={active ? 'page' : undefined}
                            className={cn(
                                'inline-flex min-h-12 items-center border-b-[3px] px-2 text-[15px] font-semibold whitespace-nowrap transition-colors focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-ring sm:px-4 sm:text-base',
                                active
                                    ? 'border-link bg-muted/50 text-foreground'
                                    : 'border-transparent text-muted-foreground hover:bg-muted/30 hover:text-foreground',
                            )}
                        >
                            {tab.mobileLabel ? (
                                <>
                                    <span className="sm:hidden">
                                        {tab.mobileLabel}
                                    </span>
                                    <span className="hidden sm:inline">
                                        {tab.label}
                                    </span>
                                </>
                            ) : (
                                tab.label
                            )}
                        </Link>
                    );
                })}
            </nav>
            <ScrollBar orientation="horizontal" />
        </ScrollArea>
    );
}
