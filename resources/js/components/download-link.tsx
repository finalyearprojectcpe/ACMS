import type { ComponentProps, ReactNode } from 'react';

import { cn } from '@/lib/utils';

type DownloadLinkProps = ComponentProps<'a'> & {
    icon?: ReactNode;
};

export function DownloadLink({
    children,
    className,
    icon,
    ...props
}: DownloadLinkProps) {
    return (
        <a
            className={cn(
                'inline-flex items-center gap-1 text-sm font-semibold text-link underline-offset-4 transition-colors hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background focus-visible:outline-hidden',
                className,
            )}
            {...props}
        >
            {icon}
            <span>{children}</span>
        </a>
    );
}
