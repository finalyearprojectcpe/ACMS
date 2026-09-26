import { Link, router } from '@inertiajs/react';
import { RiAddLine, RiDeleteBinLine, RiEditLine } from '@remixicon/react';
import { useState } from 'react';

import { destroy as destroyAcademicSession } from '@/actions/App/Http/Controllers/AcademicSessionController';
import {
    createAcademicSession,
    editAcademicSession,
} from '@/actions/App/Http/Controllers/DashboardController';
import {
    rotateInvite,
    update,
} from '@/actions/App/Http/Controllers/StudentSetController';
import { ActionLink } from '@/components/action-link';
import { AdviserSetWorkspace } from '@/components/adviser-set-workspace-nav';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyTitle,
} from '@/components/ui/empty';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    sessionRouteYears,
    studentSetRouteParameters,
} from '@/lib/student-sets';
import type {
    DashboardStudentSetSession,
    DashboardStudentSetSettings,
} from '@/types';

type Confirmation =
    | { type: 'current'; session: DashboardStudentSetSession }
    | { type: 'link' }
    | { type: 'remove'; session: DashboardStudentSetSession }
    | null;

export function StudentSetSettings({
    settings,
}: {
    settings: DashboardStudentSetSettings;
}) {
    const { set, sessions } = settings;
    const parameters = studentSetRouteParameters(set);
    const currentSession = sessions.find((session) => session.isCurrent);
    const [confirmation, setConfirmation] = useState<Confirmation>(null);
    const [updatingSettings, setUpdatingSettings] = useState(false);
    const [replacingLink, setReplacingLink] = useState(false);
    const [removingSession, setRemovingSession] = useState(false);
    const busy = updatingSettings || replacingLink || removingSession;

    function updateSettings(data: {
        student_signup_enabled?: boolean;
        current_academic_year_id?: number | null;
    }) {
        setUpdatingSettings(true);
        router.patch(update.url(parameters), data, {
            preserveScroll: true,
            onSuccess: () => setConfirmation(null),
            onFinish: () => setUpdatingSettings(false),
        });
    }

    function changeCurrentSession(
        session: DashboardStudentSetSession,
        checked: boolean,
    ) {
        if (!checked) {
            if (session.isCurrent) {
                updateSettings({ current_academic_year_id: null });
            }

            return;
        }

        if (session.isCurrent) {
            return;
        }

        if (currentSession) {
            setConfirmation({ type: 'current', session });

            return;
        }

        updateSettings({ current_academic_year_id: session.level.id });
    }

    function replaceLink() {
        setReplacingLink(true);
        router.patch(
            rotateInvite.url(parameters),
            {},
            {
                preserveScroll: true,
                onSuccess: () => setConfirmation(null),
                onFinish: () => setReplacingLink(false),
            },
        );
    }

    function removeSession(session: DashboardStudentSetSession) {
        setRemovingSession(true);
        router.delete(
            destroyAcademicSession.url({
                ...parameters,
                sessionYears: sessionRouteYears(session),
            }),
            {
                preserveScroll: true,
                onSuccess: () => setConfirmation(null),
                onFinish: () => setRemovingSession(false),
            },
        );
    }

    return (
        <div className="flex min-h-0 w-full flex-1 flex-col">
            <AdviserSetWorkspace
                studentSet={set}
                activeSection="settings"
                inviteActions={{
                    busy,
                    onReplace: () => setConfirmation({ type: 'link' }),
                    onToggle: (student_signup_enabled) =>
                        updateSettings({ student_signup_enabled }),
                }}
            >
                <div className="mx-auto flex min-h-0 w-full max-w-3xl flex-1 flex-col">
                    <div className="border-b border-border p-4 sm:p-5">
                        <h2 className="text-base font-semibold">
                            Academic Sessions
                        </h2>
                    </div>
                    {sessions.length === 0 ? (
                        <Empty className="py-10">
                            <EmptyHeader>
                                <EmptyTitle>
                                    No academic sessions yet
                                </EmptyTitle>
                                <EmptyDescription>
                                    Add the first session for this set.
                                </EmptyDescription>
                            </EmptyHeader>
                        </Empty>
                    ) : (
                        <ScrollArea className="h-0 min-h-0 w-full flex-1">
                            <Table className="min-w-[600px] table-fixed">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-24 px-4 text-center sm:px-5">
                                            Current
                                        </TableHead>
                                        <TableHead className="w-28">
                                            Level
                                        </TableHead>
                                        <TableHead className="w-40">
                                            Session
                                        </TableHead>
                                        <TableHead className="w-44 pr-4 text-right sm:pr-5">
                                            Actions
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {sessions.map((session) => (
                                        <TableRow
                                            key={session.id}
                                            className="odd:bg-background even:bg-muted/25"
                                        >
                                            <TableCell className="px-4 text-center sm:px-5">
                                                <Checkbox
                                                    checked={session.isCurrent}
                                                    disabled={busy}
                                                    aria-label={`${session.isCurrent ? 'Unset' : 'Set'} ${session.level.title} ${session.name} Session as current`}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        changeCurrentSession(
                                                            session,
                                                            checked === true,
                                                        )
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className="font-medium">
                                                {session.level.title}
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                {session.name} Session
                                            </TableCell>
                                            <TableCell className="pr-4 text-right sm:pr-5">
                                                <div className="inline-flex items-center gap-3">
                                                    <ActionLink
                                                        href={editAcademicSession(
                                                            {
                                                                ...parameters,
                                                                sessionYears:
                                                                    sessionRouteYears(
                                                                        session,
                                                                    ),
                                                            },
                                                        )}
                                                        icon={
                                                            <RiEditLine className="size-4" />
                                                        }
                                                    >
                                                        Edit
                                                    </ActionLink>
                                                    <button
                                                        type="button"
                                                        className="inline-flex items-center gap-1 text-sm font-semibold text-destructive underline-offset-4 hover:underline disabled:cursor-not-allowed disabled:opacity-50"
                                                        disabled={
                                                            session.isCurrent ||
                                                            session.hasDependents
                                                        }
                                                        title={
                                                            session.isCurrent
                                                                ? 'Unset the current session first.'
                                                                : session.hasDependents
                                                                  ? 'This session contains courses or course registrations.'
                                                                  : undefined
                                                        }
                                                        onClick={() =>
                                                            setConfirmation({
                                                                type: 'remove',
                                                                session,
                                                            })
                                                        }
                                                    >
                                                        <RiDeleteBinLine className="size-4" />
                                                        Remove
                                                    </button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                            <ScrollBar orientation="horizontal" />
                        </ScrollArea>
                    )}
                    <footer className="flex shrink-0 justify-end border-t border-border pt-4">
                        <Button asChild size="sm">
                            <Link href={createAcademicSession(parameters)}>
                                <RiAddLine />
                                Add Session
                            </Link>
                        </Button>
                    </footer>
                </div>
            </AdviserSetWorkspace>

            <AlertDialog
                open={confirmation !== null}
                onOpenChange={(open) => {
                    if (!open && !busy) {
                        setConfirmation(null);
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {confirmation?.type === 'link'
                                ? 'Replace the invite link?'
                                : confirmation?.type === 'remove'
                                  ? `Remove ${confirmation.session.name} Session?`
                                  : 'Change the current session?'}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {confirmation?.type === 'link' ? (
                                <>
                                    The current link for{' '}
                                    <strong>{set.name}</strong> will stop
                                    working. Students who already joined will
                                    remain in the set.
                                </>
                            ) : confirmation?.type === 'remove' ? (
                                <>
                                    You are removing{' '}
                                    <strong>
                                        {confirmation.session.level.title}{' '}
                                        {confirmation.session.name} Session
                                    </strong>{' '}
                                    from <strong>{set.name}</strong>.
                                </>
                            ) : confirmation?.type === 'current' ? (
                                <>
                                    Make{' '}
                                    <strong>
                                        {confirmation.session.level.title} (
                                        {confirmation.session.name} Session)
                                    </strong>{' '}
                                    current instead of{' '}
                                    <strong>
                                        {currentSession?.level.title} (
                                        {currentSession?.name} Session)
                                    </strong>
                                    ? Existing registrations stay unchanged.
                                </>
                            ) : null}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={busy}>
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction
                            variant={
                                confirmation?.type === 'remove'
                                    ? 'destructive'
                                    : 'default'
                            }
                            disabled={busy}
                            onClick={(event) => {
                                event.preventDefault();

                                if (confirmation?.type === 'link') {
                                    replaceLink();
                                } else if (confirmation?.type === 'remove') {
                                    removeSession(confirmation.session);
                                } else if (confirmation?.type === 'current') {
                                    updateSettings({
                                        current_academic_year_id:
                                            confirmation.session.level.id,
                                    });
                                }
                            }}
                        >
                            {confirmation?.type === 'link'
                                ? 'Replace Link'
                                : confirmation?.type === 'remove'
                                  ? 'Remove Session'
                                  : 'Make Current'}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
