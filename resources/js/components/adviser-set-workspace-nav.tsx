import { RiRefreshLine } from '@remixicon/react';
import type { ReactNode } from 'react';

import { editStudentSet } from '@/actions/App/Http/Controllers/DashboardController';
import { CopyableValue } from '@/components/copyable-value';
import { SetInviteStatus } from '@/components/set-invite-status';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Switch } from '@/components/ui/switch';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { WorkspaceSectionTabs } from '@/components/workspace-section-tabs';
import { studentSetRouteParameters } from '@/lib/student-sets';
import type { DashboardStudentSetContext } from '@/types';

type SetWorkspaceSection = 'students' | 'settings';

type InviteActions = {
    busy: boolean;
    onReplace: () => void;
    onToggle: (enabled: boolean) => void;
};

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
                <header className="flex flex-col gap-4 border-b border-border p-4 lg:flex-row lg:items-start lg:justify-between lg:p-5">
                    <div className="min-w-0 flex-1">
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
                        <WorkspaceSectionTabs
                            label="Student set sections"
                            activeTab={activeSection}
                            className="mt-4 w-full"
                            tabs={[
                                {
                                    key: 'students',
                                    label: 'Students',
                                    href: studentSet.url,
                                },
                                {
                                    key: 'settings',
                                    label: 'Settings',
                                    href: editStudentSet(
                                        studentSetRouteParameters(studentSet),
                                    ).url,
                                },
                            ]}
                        />
                    </div>
                    <div className="w-full min-w-0 lg:w-96 lg:shrink-0">
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
                </header>
                <div className="flex min-h-0 flex-1 flex-col">{children}</div>
            </CardContent>
        </Card>
    );
}
