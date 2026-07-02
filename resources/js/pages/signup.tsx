import { Head } from '@inertiajs/react';

import { SignupForm } from '@/components/signup-form';
import type { AccountType, SignupActivation } from '@/types';

export default function SignupPage({
    accountType,
    activation,
}: {
    accountType: AccountType;
    activation: SignupActivation;
}) {
    const title =
        accountType === 'student' ? 'Student account' : 'Staff account';

    return (
        <>
            <Head title={title} />
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
