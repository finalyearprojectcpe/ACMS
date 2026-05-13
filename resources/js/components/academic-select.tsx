import { RiArrowDownSLine, RiCheckLine } from '@remixicon/react';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

type AcademicSelectOption = {
    id: number;
    name: string;
    abbreviation: string;
};

type AcademicSelectProps = {
    id: string;
    name: string;
    value: string;
    options: AcademicSelectOption[];
    placeholder: string;
    onValueChange: (value: string) => void;
    disabled?: boolean;
    invalid?: boolean;
};

function AcademicSelect({
    id,
    name,
    value,
    options,
    placeholder,
    onValueChange,
    disabled,
    invalid,
}: AcademicSelectProps) {
    const selectedOption = options.find(
        (option) => option.id.toString() === value,
    );

    function selectOption(option: AcademicSelectOption) {
        onValueChange(option.id.toString());
    }

    return (
        <DropdownMenu>
            <input type="hidden" name={name} value={value} />
            <DropdownMenuTrigger asChild>
                <Button
                    id={id}
                    type="button"
                    variant="outline"
                    className={cn(
                        'h-10 w-full justify-between px-3 text-left text-sm font-semibold',
                        !selectedOption && 'font-normal text-muted-foreground',
                    )}
                    aria-invalid={invalid || undefined}
                    disabled={disabled}
                >
                    <span className="min-w-0 truncate">
                        {selectedOption ? (
                            <AcademicOptionLabel option={selectedOption} />
                        ) : (
                            placeholder
                        )}
                    </span>
                    <RiArrowDownSLine className="size-4 shrink-0 text-muted-foreground" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="max-h-60">
                {options.map((option) => {
                    const selected = option.id.toString() === value;

                    return (
                        <DropdownMenuItem
                            key={option.id}
                            className="h-10 text-sm"
                            onSelect={() => selectOption(option)}
                        >
                            <AcademicOptionLabel
                                option={option}
                                className="min-w-0 flex-1"
                            />
                            {selected && (
                                <RiCheckLine className="size-3.5 shrink-0 text-primary" />
                            )}
                        </DropdownMenuItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function AcademicOptionLabel({
    option,
    className,
}: {
    option: AcademicSelectOption;
    className?: string;
}) {
    return (
        <span className={cn('inline-flex min-w-0 items-center', className)}>
            <span className="shrink-0 font-semibold">
                {option.abbreviation}
            </span>
            <span className="mx-1.5 inline-block size-1 shrink-0 rounded-full bg-muted-foreground/40" />
            <span className="truncate">{option.name}</span>
        </span>
    );
}

export { AcademicSelect };
