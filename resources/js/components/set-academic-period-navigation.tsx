import { Link, router } from '@inertiajs/react';
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

type NavigationSession = {
    id: number;
    publicId: string;
    name: string;
    level: { title: string };
    semesters: Array<{ id: number; publicId: string; name: string }>;
};

type SetAcademicPeriodNavigationProps = {
    sessions: NavigationSession[];
    selectedSemesterId: number | null;
    buildHref: (
        sessionPublicId: string,
        semesterPublicId: string | null,
    ) => string;
    ariaLabel: string;
};

export function SetAcademicPeriodNavigation({
    sessions,
    selectedSemesterId,
    buildHref,
    ariaLabel,
}: SetAcademicPeriodNavigationProps) {
    const selectedLink = useRef<HTMLAnchorElement>(null);

    useEffect(() => {
        const link = selectedLink.current;
        const viewport = link?.closest<HTMLElement>(
            '[data-slot="scroll-area-viewport"]',
        );

        if (!link || !viewport) {
            return;
        }

        const linkTop =
            link.getBoundingClientRect().top -
            viewport.getBoundingClientRect().top +
            viewport.scrollTop;

        if (
            linkTop < viewport.scrollTop ||
            linkTop + link.clientHeight >
                viewport.scrollTop + viewport.clientHeight
        ) {
            viewport.scrollTo({
                top: Math.max(
                    0,
                    linkTop - viewport.clientHeight / 2 + link.clientHeight / 2,
                ),
            });
        }
    }, [selectedSemesterId]);

    const options = sessions.flatMap((session) =>
        session.semesters.map((semester) => ({
            value: `${session.id}:${semester.id}`,
            sessionId: session.id,
            semesterId: semester.id,
            sessionPublicId: session.publicId,
            semesterPublicId: semester.publicId,
            label: `${session.level.title} · ${session.name} Session · ${semester.name}`,
        })),
    );
    const selectedOption =
        options.find((option) => option.semesterId === selectedSemesterId) ??
        null;

    function selectPeriod(value: string) {
        const option = options.find((item) => item.value === value);

        if (!option) {
            return;
        }

        router.visit(
            buildHref(option.sessionPublicId, option.semesterPublicId),
            { preserveScroll: true },
        );
    }

    return (
        <nav aria-label={ariaLabel} className="flex h-full min-h-0 flex-col">
            <div className="p-4 md:hidden">
                <Select
                    value={selectedOption?.value ?? ''}
                    onValueChange={selectPeriod}
                >
                    <SelectTrigger aria-label={ariaLabel} className="w-full">
                        <SelectValue placeholder="Select a semester" />
                    </SelectTrigger>
                    <SelectContent>
                        {options.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <ScrollArea
                scrollRegion
                className="hidden h-0 min-h-0 flex-1 md:block"
            >
                <LevelSemesterRail>
                    {sessions.map((session) => (
                        <LevelSemesterRailGroup
                            key={session.id}
                            label={
                                <span className="grid gap-0.5">
                                    <span>{session.level.title}</span>
                                    <span className="text-xs font-normal text-muted-foreground">
                                        {session.name} Session
                                    </span>
                                </span>
                            }
                        >
                            {session.semesters.length === 0 ? (
                                <Link
                                    preserveScroll
                                    href={buildHref(session.publicId, null)}
                                    className="px-3 py-2 text-sm text-muted-foreground transition-colors hover:text-foreground"
                                >
                                    Semester setup unavailable
                                </Link>
                            ) : (
                                session.semesters.map((semester) => (
                                    <Link
                                        preserveScroll
                                        key={semester.id}
                                        ref={
                                            semester.id === selectedSemesterId
                                                ? selectedLink
                                                : undefined
                                        }
                                        href={buildHref(
                                            session.publicId,
                                            semester.publicId,
                                        )}
                                        aria-current={
                                            semester.id === selectedSemesterId
                                                ? 'page'
                                                : undefined
                                        }
                                        className={levelSemesterRailItemClassName(
                                            semester.id === selectedSemesterId,
                                        )}
                                    >
                                        {semester.name}
                                    </Link>
                                ))
                            )}
                        </LevelSemesterRailGroup>
                    ))}
                </LevelSemesterRail>
            </ScrollArea>
        </nav>
    );
}
