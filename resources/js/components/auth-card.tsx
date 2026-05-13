import type { ComponentProps, ReactNode } from 'react';

import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

function AuthCard({ className, children, ...props }: ComponentProps<'div'>) {
    return (
        <div className={cn('flex w-full flex-col gap-4', className)} {...props}>
            <Card className="overflow-hidden p-0 shadow-sm">
                <CardContent className="grid min-h-[620px] p-0 lg:grid-cols-[minmax(0,0.95fr)_minmax(360px,1.05fr)]">
                    <div className="flex min-h-full flex-col justify-center p-6 sm:p-8 lg:p-10">
                        {children}
                    </div>

                    <div className="relative hidden min-h-full overflow-hidden border-l bg-muted lg:block">
                        <img
                            src="/banner.jpg"
                            alt="GradeManager banner"
                            className="absolute inset-0 h-full w-full object-cover"
                        />
                    </div>
                </CardContent>
            </Card>
        </div>
    );
}

function AuthFormHeader({
    title,
    description,
}: {
    title: string;
    description: string;
}) {
    return (
        <div className="flex items-start gap-3">
            <span className="flex size-10 shrink-0 items-center justify-center border bg-background dark:bg-foreground">
                <img
                    src="/logo.png"
                    alt=""
                    className="size-5"
                    aria-hidden="true"
                />
            </span>

            <div className="flex min-w-0 flex-col gap-1">
                <h2 className="font-sans text-2xl font-semibold">{title}</h2>
                <p className="text-sm text-muted-foreground">{description}</p>
            </div>
        </div>
    );
}

function AuthFormActions({ children }: { children: ReactNode }) {
    return <div className="flex flex-col gap-3">{children}</div>;
}

export { AuthCard, AuthFormActions, AuthFormHeader };
