import { Head, Link } from '@inertiajs/react';

import { AuthCard, AuthFormHeader } from '@/components/auth-card';
import { FlashToasts } from '@/components/flash-toasts';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';
import { edit, resend } from '@/routes/student-joins';

type JoinStatus = {
    set: string;
    status: 'pending' | 'approved' | 'rejected';
    emailVerified: boolean;
    email: string;
    reason: string | null;
    canResubmit: boolean;
};

export default function StudentJoinStatusPage({
    request,
}: {
    request: JoinStatus;
}) {
    const needsEmail = request.status === 'pending' && !request.emailVerified;
    const rejected = request.status === 'rejected';

    return (
        <>
            <Head title="Student request" />
            <FlashToasts />
            <main className="flex min-h-svh flex-col items-center justify-center p-4 sm:p-6 lg:p-10">
                <div className="w-full max-w-5xl">
                    <AuthCard>
                        <div className="flex flex-col gap-6">
                            <AuthFormHeader
                                title={
                                    needsEmail
                                        ? 'Check your email'
                                        : rejected
                                          ? 'Changes requested'
                                          : 'Request submitted'
                                }
                                description={request.set}
                            />
                            {needsEmail ? (
                                <p className="text-sm leading-6 text-muted-foreground">
                                    Check{' '}
                                    <strong className="font-semibold break-all text-foreground">
                                        {request.email}
                                    </strong>{' '}
                                    for a verification link. Your course adviser
                                    can review your request after you confirm
                                    this address.
                                </p>
                            ) : rejected ? (
                                <div className="border-l-2 border-destructive bg-destructive/5 px-4 py-3 text-sm">
                                    <p className="font-medium text-destructive">
                                        Adviser feedback
                                    </p>
                                    <p className="mt-1">{request.reason}</p>
                                    {!request.canResubmit ? (
                                        <p className="mt-3 text-muted-foreground">
                                            Contact your course adviser to
                                            update this request.
                                        </p>
                                    ) : null}
                                </div>
                            ) : (
                                <p className="text-sm leading-6 text-muted-foreground">
                                    Your course adviser will review your
                                    request. You will receive an email when they
                                    respond.
                                </p>
                            )}
                            <div className="flex flex-wrap items-center gap-3 border-t border-border pt-5">
                                {needsEmail ? (
                                    <>
                                        <Button asChild>
                                            <Link
                                                href={resend()}
                                                method="post"
                                                as="button"
                                            >
                                                Resend verification email
                                            </Link>
                                        </Button>
                                        {request.canResubmit ? (
                                            <Button asChild variant="outline">
                                                <Link href={edit()}>
                                                    Correct details
                                                </Link>
                                            </Button>
                                        ) : null}
                                    </>
                                ) : rejected && request.canResubmit ? (
                                    <Button asChild>
                                        <Link href={edit()}>
                                            Correct and resubmit
                                        </Link>
                                    </Button>
                                ) : null}
                                <Button
                                    asChild
                                    variant="ghost"
                                    className="ml-auto"
                                >
                                    <Link
                                        href={logout()}
                                        method="post"
                                        as="button"
                                    >
                                        Log out
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </AuthCard>
                </div>
            </main>
        </>
    );
}
