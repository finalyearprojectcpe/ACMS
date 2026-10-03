import { RiDownloadLine } from '@remixicon/react';
import { useState } from 'react';
import type { ComponentProps, MouseEvent, ReactNode } from 'react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';

type DownloadButtonProps = {
    href: string;
    children: ReactNode;
    variant?: ComponentProps<typeof Button>['variant'];
    size?: ComponentProps<typeof Button>['size'];
    className?: string;
};

function downloadName(disposition: string | null): string {
    const encoded = disposition?.match(/filename\*=UTF-8''([^;]+)/i)?.[1];

    if (encoded) {
        try {
            return decodeURIComponent(encoded);
        } catch {
            return encoded;
        }
    }

    return disposition?.match(/filename="([^"]+)"/i)?.[1] ?? 'download';
}

export function DownloadButton({
    href,
    children,
    variant = 'outline',
    size = 'sm',
    className,
}: DownloadButtonProps) {
    const [preparing, setPreparing] = useState(false);

    async function start(event: MouseEvent<HTMLAnchorElement>) {
        if (
            event.button !== 0 ||
            event.metaKey ||
            event.ctrlKey ||
            event.shiftKey ||
            event.altKey
        ) {
            return;
        }

        event.preventDefault();

        if (preparing) {
            return;
        }

        setPreparing(true);

        try {
            const response = await fetch(href, { credentials: 'same-origin' });
            const type = response.headers.get('content-type') ?? '';

            if (
                !response.ok ||
                type.includes('text/html') ||
                type.includes('application/json')
            ) {
                throw new Error('The file could not be prepared.');
            }

            const file = URL.createObjectURL(await response.blob());
            const link = document.createElement('a');
            link.href = file;
            link.download = downloadName(
                response.headers.get('content-disposition'),
            );
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.setTimeout(() => URL.revokeObjectURL(file), 60_000);
        } catch {
            toast.error('The download could not be prepared. Try again.');
        } finally {
            setPreparing(false);
        }
    }

    return (
        <Button asChild variant={variant} size={size} className={className}>
            <a
                href={href}
                onClick={start}
                aria-busy={preparing}
                aria-disabled={preparing}
            >
                {preparing ? (
                    <Spinner className="size-4" />
                ) : (
                    <RiDownloadLine className="size-4" />
                )}
                {preparing ? 'Preparing…' : children}
            </a>
        </Button>
    );
}
