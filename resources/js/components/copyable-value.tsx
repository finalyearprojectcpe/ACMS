import { RiCheckLine, RiFileCopyLine } from '@remixicon/react';
import { useRef, useState } from 'react';

import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

type CopyableValueProps = {
    value: string | null | undefined;
    className?: string;
    valueClassName?: string;
    emptyLabel?: string;
    fullWidth?: boolean;
};

export function CopyableValue({
    value,
    className,
    valueClassName,
    emptyLabel = 'Not set',
    fullWidth = false,
}: CopyableValueProps) {
    const [copied, setCopied] = useState(false);
    const resetTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const copyValue = value?.trim() ?? '';
    const canCopy = copyValue.length > 0;

    async function copyToClipboard() {
        if (!canCopy) {
            return;
        }

        await writeClipboard(copyValue);
        setCopied(true);

        if (resetTimer.current !== null) {
            clearTimeout(resetTimer.current);
        }

        resetTimer.current = setTimeout(() => setCopied(false), 1600);
    }

    return (
        <div
            className={cn(
                'inline-flex h-8 w-fit max-w-full items-stretch overflow-hidden rounded-md border border-border bg-muted/60 align-middle text-foreground shadow-xs transition-colors',
                fullWidth && 'w-full',
                copied &&
                    'border-emerald-300 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300',
                className,
            )}
        >
            <span
                title={copyValue || undefined}
                className={cn(
                    'flex min-w-0 max-w-[min(18rem,calc(100vw-5rem))] items-center truncate px-2.5 font-mono text-sm font-semibold tracking-normal tabular-nums',
                    fullWidth && 'max-w-none flex-1',
                    !canCopy && 'text-muted-foreground',
                    valueClassName,
                )}
            >
                {copyValue || emptyLabel}
            </span>

            {canCopy ? (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon-xs"
                            className="h-full w-8 shrink-0 rounded-none border-l border-border bg-background/45 text-muted-foreground hover:bg-background hover:text-foreground"
                            aria-label={copied ? 'Copied' : 'Copy'}
                            onClick={() => void copyToClipboard()}
                        >
                            {copied ? (
                                <RiCheckLine className="size-3.5" />
                            ) : (
                                <RiFileCopyLine className="size-3.5" />
                            )}
                        </Button>
                    </TooltipTrigger>
                    <TooltipContent>
                        {copied ? 'Copied' : 'Copy'}
                    </TooltipContent>
                </Tooltip>
            ) : null}
        </div>
    );
}

async function writeClipboard(value: string): Promise<void> {
    if (navigator.clipboard?.writeText) {
        await navigator.clipboard.writeText(value);

        return;
    }

    const textArea = document.createElement('textarea');

    textArea.value = value;
    textArea.setAttribute('readonly', '');
    textArea.style.position = 'fixed';
    textArea.style.left = '-9999px';
    document.body.appendChild(textArea);
    textArea.select();
    document.execCommand('copy');
    document.body.removeChild(textArea);
}
