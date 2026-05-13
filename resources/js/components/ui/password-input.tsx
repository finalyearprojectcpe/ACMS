import { RiEyeLine, RiEyeOffLine } from '@remixicon/react';
import { useState } from 'react';
import type { ComponentProps } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

function PasswordInput({
    className,
    disabled,
    ...props
}: Omit<ComponentProps<typeof Input>, 'type'>) {
    const [visible, setVisible] = useState(false);

    return (
        <div className="relative">
            <Input
                type={visible ? 'text' : 'password'}
                className={cn('pr-9', className)}
                disabled={disabled}
                {...props}
            />
            <Button
                type="button"
                variant="ghost"
                size="icon-xs"
                className="absolute top-1/2 right-1 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                aria-label={visible ? 'Hide password' : 'Show password'}
                disabled={disabled}
                onClick={() => setVisible((current) => !current)}
            >
                {visible ? <RiEyeOffLine /> : <RiEyeLine />}
            </Button>
        </div>
    );
}

export { PasswordInput };
