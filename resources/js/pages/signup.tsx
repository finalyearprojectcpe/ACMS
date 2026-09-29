import { Head } from '@inertiajs/react';

import { FlashToasts } from '@/components/flash-toasts';
import { SignupForm } from '@/components/signup-form';
import type { HonorificOption } from '@/types';

export default function SignupPage({
    honorifics,
}: {
    honorifics: HonorificOption[];
}) {
    return (
        <>
            <Head title="Course adviser account" />
            <FlashToasts />
            <main className="flex min-h-svh flex-col items-center justify-center p-4 sm:p-6 lg:p-10">
                <div className="w-full max-w-5xl">
                    <SignupForm honorifics={honorifics} />
                </div>
            </main>
        </>
    );
}
