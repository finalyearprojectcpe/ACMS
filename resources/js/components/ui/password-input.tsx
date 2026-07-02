import { RiEyeLine, RiEyeOffLine } from '@remixicon/react';
import { useRef, useState } from 'react';
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
    const inputRef = useRef<HTMLInputElement | null>(null);
    const inputType = visible ? 'text' : 'password';

    function toggleVisibility() {
        inputRef.current?.focus({ preventScroll: true });
        setVisible((current) => !current);
    }

    return (
        <div className="relative">
            <Input
                {...props}
                ref={inputRef}
                type={inputType}
                className={cn('pr-9', className)}
                disabled={disabled}
            />
            <Button
                type="button"
                variant="ghost"
                size="icon-xs"
                className="absolute top-1/2 right-1 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                aria-label={visible ? 'Hide password' : 'Show password'}
                aria-pressed={visible}
                disabled={disabled}
                onClick={toggleVisibility}
            >
                {visible ? <RiEyeOffLine /> : <RiEyeLine />}
            </Button>
        </div>
    );
}

export { PasswordInput };
