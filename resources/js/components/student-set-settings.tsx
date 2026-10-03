import { Link, router } from '@inertiajs/react';
import { RiAddLine, RiDeleteBinLine, RiEditLine } from '@remixicon/react';
import { useRef, useState } from 'react';

import { destroy as destroyAcademicSession } from '@/actions/App/Http/Controllers/AcademicSessionController';
import {
    createAcademicSession,
    editAcademicSession,
} from '@/actions/App/Http/Controllers/DashboardController';
import {
    rotateInvite,
    update,
} from '@/actions/App/Http/Controllers/StudentSetController';
import {
    destroy as removeSetLogo,
    store as uploadSetLogo,
} from '@/actions/App/Http/Controllers/StudentSetLogoController';
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
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyTitle,
} from '@/components/ui/empty';
import { Input } from '@/components/ui/input';
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
    | {
          type: 'current';
          session: DashboardStudentSetSession;
          semesterId: number;
      }
    | { type: 'link' }
    | { type: 'remove'; session: DashboardStudentSetSession }
    | { type: 'logo' }
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
    const [savingLogo, setSavingLogo] = useState(false);
    const [logoError, setLogoError] = useState<string | null>(null);
    const logoInput = useRef<HTMLInputElement>(null);
    const busy =
        updatingSettings || replacingLink || removingSession || savingLogo;

    function uploadLogo(file: File) {
        setSavingLogo(true);
        setLogoError(null);
        router.post(
            uploadSetLogo.url(parameters),
            { logo: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onError: (errors) =>
                    setLogoError(
                        errors.logo ?? 'The logo could not be uploaded.',
                    ),
                onFinish: () => setSavingLogo(false),
            },
        );
    }

    function removeLogo() {
        setSavingLogo(true);
        router.delete(removeSetLogo.url(parameters), {
            preserveScroll: true,
            onSuccess: () => setConfirmation(null),
            onFinish: () => setSavingLogo(false),
        });
    }

    function updateSettings(data: {
        student_signup_enabled?: boolean;
        current_academic_year_id?: number;
        current_semester_id?: number;
    }) {
        setUpdatingSettings(true);
        router.patch(update.url(parameters), data, {
            preserveScroll: true,
            onSuccess: () => setConfirmation(null),
            onFinish: () => setUpdatingSettings(false),
        });
    }

    function changeCurrentPeriod(
        session: DashboardStudentSetSession,
        semesterId: number,
    ) {
        if (session.isCurrent && session.currentSemesterId === semesterId) {
            return;
        }

        if (currentSession?.currentSemesterId) {
            setConfirmation({ type: 'current', session, semesterId });

            return;
        }

        updateSettings({
            current_academic_year_id: session.level.id,
            current_semester_id: semesterId,
        });
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
                            <Table className="min-w-[660px] table-fixed">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-28 px-4 sm:px-5">
                                            Level
                                        </TableHead>
                                        <TableHead className="w-40">
                                            Session
                                        </TableHead>
                                        <TableHead className="w-56">
                                            Current Period
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
                                            <TableCell className="px-4 font-medium sm:px-5">
                                                {session.level.title}
                                            </TableCell>
                                            <TableCell className="tabular-nums">
                                                {session.name} Session
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex items-center gap-1.5">
                                                    {session.semesters.map(
                                                        (semester) => {
                                                            const selected =
                                                                session.isCurrent &&
                                                                session.currentSemesterId ===
                                                                    semester.id;

                                                            return (
                                                                <button
                                                                    key={
                                                                        semester.id
                                                                    }
                                                                    type="button"
                                                                    disabled={
                                                                        busy
                                                                    }
                                                                    aria-pressed={
                                                                        selected
                                                                    }
                                                                    aria-label={
                                                                        selected
                                                                            ? `${session.level.title}, ${semester.name} (${session.name} Session) is current`
                                                                            : `Make ${session.level.title}, ${semester.name} (${session.name} Session) current`
                                                                    }
                                                                    onClick={() =>
                                                                        changeCurrentPeriod(
                                                                            session,
                                                                            semester.id,
                                                                        )
                                                                    }
                                                                    className={`inline-flex min-h-9 min-w-14 items-center justify-center border-2 px-2 text-sm font-bold transition-colors disabled:opacity-50 ${selected ? 'border-emerald-700 bg-emerald-700 text-white dark:border-emerald-500 dark:bg-emerald-600' : 'border-border bg-background text-foreground hover:border-emerald-600 hover:text-emerald-700 dark:hover:text-emerald-300'}`}
                                                                >
                                                                    {semester.slug ===
                                                                    'first'
                                                                        ? '1st'
                                                                        : '2nd'}
                                                                </button>
                                                            );
                                                        },
                                                    )}
                                                </div>
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
                    <section className="shrink-0 border-t px-4 py-4 sm:px-5">
                        <div className="flex flex-wrap items-center justify-between gap-4">
                            <div className="flex min-w-0 items-center gap-3">
                                {settings.logoUrl ? (
                                    <img
                                        src={settings.logoUrl}
                                        alt={`${set.name} logo`}
                                        className="size-16 shrink-0 border bg-white object-contain p-1"
                                    />
                                ) : (
                                    <div className="flex size-16 shrink-0 items-center justify-center border bg-muted/25 text-xs text-muted-foreground">
                                        No logo
                                    </div>
                                )}
                                <div>
                                    <h3 className="text-sm font-semibold">
                                        Receipt Logo
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        Used on new receipts.
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-3">
                                <Input
                                    ref={logoInput}
                                    type="file"
                                    accept="image/png,image/jpeg"
                                    className="sr-only"
                                    aria-label="Choose receipt logo"
                                    onChange={(event) => {
                                        const file = event.target.files?.[0];
                                        event.target.value = '';

                                        if (file) {
                                            uploadLogo(file);
                                        }
                                    }}
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    disabled={busy}
                                    onClick={() => logoInput.current?.click()}
                                >
                                    <RiEditLine />{' '}
                                    {settings.logoUrl
                                        ? 'Replace Logo'
                                        : 'Upload Logo'}
                                </Button>
                                {settings.logoUrl && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        disabled={busy}
                                        onClick={() =>
                                            setConfirmation({ type: 'logo' })
                                        }
                                    >
                                        Remove
                                    </Button>
                                )}
                            </div>
                        </div>
                        {logoError && (
                            <p
                                role="alert"
                                className="mt-2 text-xs text-destructive"
                            >
                                {logoError}
                            </p>
                        )}
                    </section>
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
                                : confirmation?.type === 'logo'
                                  ? 'Remove the receipt logo?'
                                  : confirmation?.type === 'remove'
                                    ? `Remove ${confirmation.session.name} Session?`
                                    : 'Change the current period?'}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {confirmation?.type === 'logo' ? (
                                <>
                                    New receipts for <strong>{set.name}</strong>{' '}
                                    will have no logo. Existing receipts will
                                    stay unchanged.
                                </>
                            ) : confirmation?.type === 'link' ? (
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
                                        {confirmation.session.level.title},{' '}
                                        {
                                            confirmation.session.semesters.find(
                                                (item) =>
                                                    item.id ===
                                                    confirmation.semesterId,
                                            )?.name
                                        }{' '}
                                        ({confirmation.session.name} Session)
                                    </strong>{' '}
                                    current instead of{' '}
                                    <strong>
                                        {currentSession?.level.title},{' '}
                                        {
                                            currentSession?.semesters.find(
                                                (item) =>
                                                    item.id ===
                                                    currentSession.currentSemesterId,
                                            )?.name
                                        }{' '}
                                        ({currentSession?.name} Session)
                                    </strong>
                                    ? Students will see the new period on Home.
                                    Registration and results stay unchanged.
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
                                confirmation?.type === 'remove' ||
                                confirmation?.type === 'logo'
                                    ? 'destructive'
                                    : 'default'
                            }
                            disabled={busy}
                            onClick={(event) => {
                                event.preventDefault();

                                if (confirmation?.type === 'logo') {
                                    removeLogo();
                                } else if (confirmation?.type === 'link') {
                                    replaceLink();
                                } else if (confirmation?.type === 'remove') {
                                    removeSession(confirmation.session);
                                } else if (confirmation?.type === 'current') {
                                    updateSettings({
                                        current_academic_year_id:
                                            confirmation.session.level.id,
                                        current_semester_id:
                                            confirmation.semesterId,
                                    });
                                }
                            }}
                        >
                            {confirmation?.type === 'link'
                                ? 'Replace Link'
                                : confirmation?.type === 'logo'
                                  ? 'Remove Logo'
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
