import { Head, Link } from '@inertiajs/react';

import { AuthCard, AuthFormHeader } from '@/components/auth-card';
import { Button } from '@/components/ui/button';
import { home, logout } from '@/routes';

export default function StudentEmailVerificationPage({
    status,
    signedInAsAnotherAccount,
}: {
    status: 'verified' | 'invalid';
    signedInAsAnotherAccount: boolean;
}) {
    const verified = status === 'verified';

    return (
        <>
            <Head
                title={
                    verified ? 'Email verified' : 'Verification link expired'
                }
            />
            <main className="flex min-h-svh flex-col items-center justify-center p-4 sm:p-6 lg:p-10">
                <div className="w-full max-w-5xl">
                    <AuthCard>
                        <div className="flex flex-col gap-6">
                            <AuthFormHeader
                                title={
                                    verified
                                        ? 'Email verified'
                                        : 'Link unavailable'
                                }
                                description={
                                    verified
                                        ? 'Your request is ready for your course adviser to review.'
                                        : 'This verification link is invalid or has expired.'
                                }
                            />
                            <p className="text-sm leading-6 text-muted-foreground">
                                {verified
                                    ? 'You will receive an email when your course adviser responds.'
                                    : 'Sign in and request a new verification email from your request page.'}
                            </p>
                            <div className="flex border-t border-border pt-5">
                                {signedInAsAnotherAccount ? (
                                    <Button asChild>
                                        <Link
                                            href={logout()}
                                            method="post"
                                            as="button"
                                        >
                                            Log out to sign in
                                        </Link>
                                    </Button>
                                ) : (
                                    <Button asChild>
                                        <Link href={home()}>Sign in</Link>
                                    </Button>
                                )}
                            </div>
                        </div>
                    </AuthCard>
                </div>
            </main>
        </>
    );
}
