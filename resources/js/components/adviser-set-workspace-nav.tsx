import { Link } from '@inertiajs/react';
import { RiRefreshLine, RiSettings3Line } from '@remixicon/react';
import { useEffect, useRef } from 'react';
import type { ReactNode } from 'react';

import { editStudentSet } from '@/actions/App/Http/Controllers/DashboardController';
import { CopyableValue } from '@/components/copyable-value';
import { SetInviteStatus } from '@/components/set-invite-status';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import { Switch } from '@/components/ui/switch';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { studentSetRouteParameters } from '@/lib/student-sets';
import { cn } from '@/lib/utils';
import type { DashboardStudentSetContext } from '@/types';

type SetWorkspaceSection = 'students' | 'settings';

type InviteActions = {
    busy: boolean;
    onReplace: () => void;
    onToggle: (enabled: boolean) => void;
};

const sections: Array<{
    label: string;
    section: SetWorkspaceSection;
}> = [
    { label: 'Students', section: 'students' },
    { label: 'Settings', section: 'settings' },
];

export function AdviserSetWorkspace({
    studentSet,
    activeSection,
    inviteActions,
    children,
}: {
    studentSet: DashboardStudentSetContext;
    activeSection: SetWorkspaceSection;
    inviteActions?: InviteActions;
    children?: ReactNode;
}) {
    return (
        <Card className="min-h-0 flex-1 gap-0 overflow-hidden border-0 bg-transparent py-0 shadow-none ring-0">
            <CardContent className="flex min-h-0 flex-1 flex-col p-0">
                <div className="flex flex-col gap-4 p-4 sm:flex-row sm:items-start sm:justify-between sm:p-5">
                    <div className="min-w-0">
                        <h1 className="text-xl font-semibold break-words">
                            {studentSet.name}
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {studentSet.currentLevel
                                ? `${studentSet.currentLevel.title} · `
                                : ''}
                            {studentSet.studentCount}{' '}
                            {studentSet.studentCount === 1
                                ? 'student'
                                : 'students'}
                        </p>
                    </div>
                    <div className="w-full min-w-0 sm:w-80 sm:shrink-0 lg:w-96">
                        <div className="flex min-h-9 min-w-0 items-center gap-1.5">
                            <CopyableValue
                                value={studentSet.signupUrl}
                                fullWidth
                                className="h-9 min-w-0 flex-1"
                                valueClassName="font-sans font-medium"
                            />
                            {inviteActions ? (
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon-sm"
                                            aria-label="Replace invite link"
                                            disabled={inviteActions.busy}
                                            onClick={inviteActions.onReplace}
                                        >
                                            <RiRefreshLine />
                                        </Button>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        Replace invite link
                                    </TooltipContent>
                                </Tooltip>
                            ) : null}
                        </div>
                        <div className="mt-2 flex min-h-8 items-center justify-end gap-3">
                            {inviteActions ? (
                                <label className="mr-auto flex items-center gap-2 text-xs font-medium">
                                    <Switch
                                        checked={studentSet.signupEnabled}
                                        disabled={inviteActions.busy}
                                        onCheckedChange={inviteActions.onToggle}
                                    />
                                    Accepting invites
                                </label>
                            ) : null}
                            <SetInviteStatus open={studentSet.signupEnabled} />
                        </div>
                    </div>
                </div>
                <div className="border-y border-border bg-muted/20 px-4 sm:px-5">
                    <AdviserSetWorkspaceTabs
                        studentSet={studentSet}
                        activeSection={activeSection}
                    />
                </div>
                <div className="flex min-h-0 flex-1 flex-col">{children}</div>
            </CardContent>
        </Card>
    );
}

function AdviserSetWorkspaceTabs({
    studentSet,
    activeSection,
}: {
    studentSet: DashboardStudentSetContext;
    activeSection: SetWorkspaceSection;
}) {
    const parameters = studentSetRouteParameters(studentSet);
    const activeLink = useRef<HTMLAnchorElement>(null);
    const tabArea = useRef<HTMLDivElement>(null);

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
    }, [activeSection]);

    return (
        <ScrollArea ref={tabArea} className="w-full">
            <nav
                aria-label="Student set sections"
                className="flex w-full min-w-max items-center justify-between gap-5 sm:gap-6"
            >
                {sections.map((item) => {
                    const active = item.section === activeSection;

                    return (
                        <Link
                            ref={active ? activeLink : undefined}
                            key={item.section}
                            href={
                                item.section === 'students'
                                    ? studentSet.url
                                    : editStudentSet(parameters)
                            }
                            aria-current={active ? 'page' : undefined}
                            className={cn(
                                'inline-flex min-h-14 items-center gap-1.5 border-b-2 px-1 text-sm font-semibold transition-colors',
                                active
                                    ? 'border-foreground text-foreground'
                                    : 'border-transparent text-link hover:border-border',
                            )}
                        >
                            {item.section === 'settings' ? (
                                <RiSettings3Line className="size-4 shrink-0" />
                            ) : null}
                            {item.label}
                        </Link>
                    );
                })}
            </nav>
            <ScrollBar orientation="horizontal" />
        </ScrollArea>
    );
}
