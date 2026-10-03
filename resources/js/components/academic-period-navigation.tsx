import { Link, router } from '@inertiajs/react';
import { RiLockLine } from '@remixicon/react';
import { useEffect, useRef } from 'react';

import {
    LevelSemesterRail,
    LevelSemesterRailGroup,
    levelSemesterRailItemClassName,
} from '@/components/level-semester-rail';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

export type AcademicPeriodLink = {
    key: string;
    href: string;
    label: string;
    compactLabel: string;
    selected: boolean;
    locked?: boolean;
};

export type AcademicPeriodGroup = {
    key: string;
    label: string;
    detail: string;
    compactLabel: string;
    links: AcademicPeriodLink[];
};

export function AcademicPeriodNavigation({
    groups,
    overview,
    collapsed,
    ariaLabel,
}: {
    groups: AcademicPeriodGroup[];
    overview?: AcademicPeriodLink;
    collapsed: boolean;
    ariaLabel: string;
}) {
    const selectedLink = useRef<HTMLAnchorElement>(null);
    const links = [overview, ...groups.flatMap((group) => group.links)].filter(
        (link): link is AcademicPeriodLink => Boolean(link),
    );
    const selected = links.find((link) => link.selected);

    useEffect(() => {
        const link = selectedLink.current;
        const viewport = link?.closest<HTMLElement>(
            '[data-slot="scroll-area-viewport"]',
        );

        if (!link || !viewport) {
            return;
        }

        const top =
            link.getBoundingClientRect().top -
            viewport.getBoundingClientRect().top +
            viewport.scrollTop;

        if (
            top < viewport.scrollTop ||
            top + link.clientHeight > viewport.scrollTop + viewport.clientHeight
        ) {
            viewport.scrollTo({
                top: Math.max(
                    0,
                    top - viewport.clientHeight / 2 + link.clientHeight / 2,
                ),
            });
        }
    }, [selected?.key, collapsed]);

    function navigate(key: string) {
        const link = links.find((item) => item.key === key);

        if (link) {
            router.visit(link.href, { preserveScroll: true });
        }
    }

    function compactLink(link: AcademicPeriodLink, description: string) {
        return (
            <Tooltip key={link.key}>
                <TooltipTrigger asChild>
                    <Link
                        preserveScroll
                        href={link.href}
                        ref={link.selected ? selectedLink : undefined}
                        aria-current={link.selected ? 'page' : undefined}
                        aria-label={description}
                        className={`flex h-9 items-center justify-center text-xs font-medium ${link.selected ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`}
                    >
                        {link.compactLabel}
                    </Link>
                </TooltipTrigger>
                <TooltipContent side="right">{description}</TooltipContent>
            </Tooltip>
        );
    }

    return (
        <nav aria-label={ariaLabel} className="flex h-full min-h-0 flex-col">
            <div className="p-4 md:hidden">
                <Select value={selected?.key ?? ''} onValueChange={navigate}>
                    <SelectTrigger aria-label={ariaLabel} className="w-full">
                        <SelectValue placeholder="Select a period" />
                    </SelectTrigger>
                    <SelectContent>
                        {overview && (
                            <SelectItem value={overview.key}>
                                {overview.label}
                            </SelectItem>
                        )}
                        {groups.flatMap((group) =>
                            group.links.map((link) => (
                                <SelectItem key={link.key} value={link.key}>
                                    {group.label} · {group.detail} ·{' '}
                                    {link.label}
                                    {link.locked ? ' · Locked' : ''}
                                </SelectItem>
                            )),
                        )}
                    </SelectContent>
                </Select>
            </div>
            <ScrollArea
                scrollRegion
                className="hidden h-0 min-h-0 flex-1 md:block"
            >
                {collapsed ? (
                    <div className="grid gap-5 px-1.5 pb-3">
                        {overview && compactLink(overview, overview.label)}
                        {groups.map((group) => (
                            <div key={group.key} className="grid gap-1">
                                <div
                                    className="text-center text-xs font-semibold"
                                    title={`${group.label} · ${group.detail}`}
                                >
                                    {group.compactLabel}
                                </div>
                                {group.links.map((link) =>
                                    compactLink(
                                        link,
                                        `${group.label} · ${group.detail} · ${link.label}${link.locked ? ' · Locked' : ''}`,
                                    ),
                                )}
                            </div>
                        ))}
                    </div>
                ) : (
                    <LevelSemesterRail>
                        {overview && (
                            <Link
                                preserveScroll
                                href={overview.href}
                                ref={
                                    overview.selected ? selectedLink : undefined
                                }
                                aria-current={
                                    overview.selected ? 'page' : undefined
                                }
                                className={levelSemesterRailItemClassName(
                                    overview.selected,
                                )}
                            >
                                {overview.label}
                            </Link>
                        )}
                        {groups.map((group) => (
                            <LevelSemesterRailGroup
                                key={group.key}
                                label={
                                    <span className="grid gap-0.5">
                                        <span>{group.label}</span>
                                        <span className="text-xs font-normal text-muted-foreground">
                                            {group.detail}
                                        </span>
                                    </span>
                                }
                            >
                                {group.links.map((link) => (
                                    <Link
                                        preserveScroll
                                        key={link.key}
                                        href={link.href}
                                        ref={
                                            link.selected
                                                ? selectedLink
                                                : undefined
                                        }
                                        aria-current={
                                            link.selected ? 'page' : undefined
                                        }
                                        className={levelSemesterRailItemClassName(
                                            link.selected,
                                        )}
                                    >
                                        {link.label}
                                        {link.locked && (
                                            <RiLockLine
                                                className="ml-auto size-3.5"
                                                aria-label="Locked"
                                            />
                                        )}
                                    </Link>
                                ))}
                            </LevelSemesterRailGroup>
                        ))}
                    </LevelSemesterRail>
                )}
            </ScrollArea>
        </nav>
    );
}
