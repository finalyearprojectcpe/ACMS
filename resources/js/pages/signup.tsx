import { Head } from '@inertiajs/react';

import { FlashToasts } from '@/components/flash-toasts';
import { SignupForm } from '@/components/signup-form';
import type { AccountType, SignupActivation } from '@/types';

export default function SignupPage({
    accountType,
    activation,
}: {
    accountType: AccountType;
    activation: SignupActivation | null;
}) {
    const title =
        accountType === 'student'
            ? 'Student account'
            : 'Course adviser account';

    return (
        <>
            <Head title={title} />
            <FlashToasts />
            <main className="flex min-h-svh flex-col items-center justify-center p-4 sm:p-6 lg:p-10">
                <div className="w-full max-w-5xl">
                    <SignupForm
                        accountType={accountType}
                        activation={activation}
                    />
                </div>
            </main>
        </>
    );
}
