import * as React from 'react';

import { cn } from '@/lib/utils';

function Select({
    className,
    children,
    ...props
}: React.ComponentProps<'select'>) {
    return (
        <select
            data-slot="select"
            className={cn(
                'h-10 w-full border border-input bg-background px-3 py-2 text-sm font-semibold transition-colors outline-none focus-visible:border-ring focus-visible:ring-1 focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:bg-input/50 disabled:opacity-50 dark:bg-input/30',
                className,
            )}
            {...props}
        >
            {children}
        </select>
    );
}

function SelectOption(props: React.ComponentProps<'option'>) {
    return <option data-slot="select-option" {...props} />;
}

export { Select, SelectOption };
