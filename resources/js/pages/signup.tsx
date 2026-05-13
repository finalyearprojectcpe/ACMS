import { Head } from '@inertiajs/react';

import { SignupForm } from '@/components/signup-form';
import type { AccountTypeOption, DepartmentGroup } from '@/types';

export default function SignupPage({
    accountTypes,
    departments,
}: {
    accountTypes: AccountTypeOption[];
    departments: DepartmentGroup[];
}) {
    return (
        <>
            <Head title="Create account" />
            <main className="flex min-h-svh flex-col items-center justify-center p-4 sm:p-6 lg:p-10">
                <div className="w-full max-w-5xl">
                    <SignupForm
                        accountTypes={accountTypes}
                        departments={departments}
                    />
                </div>
            </main>
        </>
    );
}
