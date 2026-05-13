import { Head } from '@inertiajs/react';

import { LoginForm } from '@/components/login-form';

export default function LoginPage() {
    return (
        <>
            <Head title="GradeManager" />
            <main className="flex min-h-svh flex-col items-center justify-center p-4 sm:p-6 lg:p-10">
                <div className="w-full max-w-5xl">
                    <LoginForm />
                </div>
            </main>
        </>
    );
}
