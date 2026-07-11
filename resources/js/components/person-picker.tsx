import { RiArrowDownSLine } from '@remixicon/react';
import { useState } from 'react';

import { PersonCell } from '@/components/person-cell';
import { Button } from '@/components/ui/button';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';

export type PersonPickerOption = {
    value: string;
    name: string;
    secondary: string | null;
    avatar: string | null;
    unavailableReason?: string | null;
};

export function PersonPicker({
    value,
    options,
    onValueChange,
    placeholder = 'Select staff member',
    searchPlaceholder = 'Search by name or email...',
    emptyLabel = 'No matching staff member.',
    ariaLabel = 'Select staff member',
    className,
}: {
    value: string;
    options: PersonPickerOption[];
    onValueChange: (value: string) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyLabel?: string;
    ariaLabel?: string;
    className?: string;
}) {
    const [open, setOpen] = useState(false);
    const selected = options.find((option) => option.value === value);

    function select(option: PersonPickerOption) {
        if (option.unavailableReason) {
            return;
        }

        onValueChange(option.value);
        setOpen(false);
    }

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    aria-label={ariaLabel}
                    className={cn(
                        'h-auto min-h-11 w-full justify-between gap-3 px-3 py-2 text-left',
                        className,
                    )}
                >
                    {selected ? (
                        <PersonCell
                            name={selected.name}
                            secondary={selected.secondary}
                            avatar={selected.avatar}
                        />
                    ) : (
                        <span className="text-muted-foreground">
                            {placeholder}
                        </span>
                    )}
                    <RiArrowDownSLine className="size-4 shrink-0 text-muted-foreground" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className="w-[min(30rem,calc(100vw-2rem))] p-0 sm:w-[var(--radix-popover-trigger-width)]"
            >
                <Command>
                    <CommandInput placeholder={searchPlaceholder} />
                    <CommandList>
                        <CommandEmpty>{emptyLabel}</CommandEmpty>
                        <CommandGroup>
                            {options.map((option) => (
                                <CommandItem
                                    key={option.value}
                                    value={`${option.name} ${option.secondary ?? ''}`}
                                    disabled={Boolean(option.unavailableReason)}
                                    onSelect={() => select(option)}
                                    className="items-center gap-3 px-3 py-2.5"
                                >
                                    <PersonCell
                                        name={option.name}
                                        secondary={option.secondary}
                                        avatar={option.avatar}
                                    />
                                    {option.unavailableReason ? (
                                        <span className="ml-auto shrink-0 text-xs text-muted-foreground">
                                            {option.unavailableReason}
                                        </span>
                                    ) : null}
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}
