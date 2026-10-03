import { RiImageAddLine } from '@remixicon/react';
import { useRef } from 'react';

import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

export function SetLogoPicker({
    id,
    src,
    disabled = false,
    loading = false,
    onSelect,
    onRemove,
}: {
    id: string;
    src: string | null;
    disabled?: boolean;
    loading?: boolean;
    onSelect: (file: File) => void;
    onRemove?: () => void;
}) {
    const input = useRef<HTMLInputElement>(null);

    return (
        <div className="flex flex-col items-start gap-2">
            <input
                ref={input}
                id={id}
                type="file"
                accept="image/png,image/jpeg"
                className="sr-only"
                onChange={(event) => {
                    const file = event.target.files?.[0];
                    event.target.value = '';

                    if (file) {
                        onSelect(file);
                    }
                }}
            />
            <button
                type="button"
                disabled={disabled}
                aria-label={src ? 'Replace set logo' : 'Choose set logo'}
                className={cn(
                    'relative flex size-40 items-center justify-center overflow-hidden rounded-sm border text-muted-foreground transition-colors hover:border-foreground/50 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50',
                    src
                        ? 'bg-background'
                        : 'border-dashed bg-muted/20 hover:bg-muted/30',
                )}
                onClick={() => input.current?.click()}
            >
                {src ? (
                    <img
                        src={src}
                        alt="Set logo preview"
                        className="size-full object-contain p-3 pb-9"
                    />
                ) : (
                    <RiImageAddLine
                        className="mb-6 size-7"
                        aria-hidden="true"
                    />
                )}
                {loading && (
                    <span className="absolute inset-0 grid place-items-center bg-background/75">
                        <Spinner className="size-5" />
                    </span>
                )}
                <span className="absolute inset-x-0 bottom-0 border-t bg-background/95 px-2 py-1.5 text-center text-xs font-medium text-foreground">
                    {loading
                        ? 'Saving logo'
                        : src
                          ? 'Replace logo'
                          : 'Choose image'}
                </span>
            </button>
            {src && onRemove && (
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    disabled={disabled}
                    onClick={onRemove}
                >
                    Remove logo
                </Button>
            )}
        </div>
    );
}
