import * as React from 'react';

import { cn } from '@/lib/utils';

function RadioCard({
    className,
    label,
    ...props
}: React.ComponentProps<'input'> & {
    label: React.ReactNode;
}) {
    return (
        <label
            htmlFor={props.id}
            className={cn(
                'flex h-10 cursor-pointer items-center justify-center border border-input bg-background px-3 text-sm font-medium transition-colors has-checked:border-primary has-checked:bg-primary/8 has-checked:text-primary hover:bg-muted',
                className,
            )}
        >
            <input
                type="radio"
                data-slot="radio-card"
                className="sr-only"
                {...props}
            />
            {label}
        </label>
    );
}

export { RadioCard };
