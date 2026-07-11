import { RiArrowDownSLine } from '@remixicon/react';
import { useState } from 'react';

import {
    StudentSetChoiceLabel,
    studentSetName,
    studentSetText,
} from '@/components/student-set-label';
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
import type { DashboardAcademicSession, DashboardLevel } from '@/types';

export type StudentSetPickerOption = {
    value: string;
    entrySession: DashboardAcademicSession | null;
    currentLevel: DashboardLevel | null;
    minimumYearsToDegree?: number | null;
    isGraduated?: boolean;
    meta?: string;
    searchText?: string;
};

type StudentSetPickerAllOption = {
    value: string;
    label: string;
    meta?: string;
    searchText?: string;
};

type StudentSetPickerProps = {
    value: string;
    onValueChange: (value: string) => void;
    options: StudentSetPickerOption[];
    allOption?: StudentSetPickerAllOption;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyLabel?: string;
    ariaLabel?: string;
    disabled?: boolean;
    className?: string;
    contentClassName?: string;
};

export function StudentSetPicker({
    value,
    onValueChange,
    options,
    allOption,
    placeholder = 'Select set',
    searchPlaceholder = 'Search student sets...',
    emptyLabel = 'No set found.',
    ariaLabel = 'Select student set',
    disabled = false,
    className,
    contentClassName,
}: StudentSetPickerProps) {
    const [open, setOpen] = useState(false);
    const selectedOption = options.find((option) => option.value === value);
    const selectedAllOption =
        allOption && allOption.value === value ? allOption : null;

    function select(nextValue: string) {
        onValueChange(nextValue);
        setOpen(false);
    }

    return (
        <Popover
            open={disabled ? false : open}
            onOpenChange={(nextOpen) => !disabled && setOpen(nextOpen)}
        >
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    aria-label={ariaLabel}
                    disabled={disabled}
                    className={cn(
                        'w-full justify-between gap-3 px-3 text-left font-semibold',
                        className,
                    )}
                >
                    {selectedOption ? (
                        <StudentSetChoiceLabel
                            entrySession={selectedOption.entrySession}
                            currentLevel={selectedOption.currentLevel}
                            minimumYearsToDegree={
                                selectedOption.minimumYearsToDegree
                            }
                            isGraduated={selectedOption.isGraduated}
                            meta={selectedOption.meta}
                        />
                    ) : selectedAllOption ? (
                        <PlainChoiceLabel
                            label={selectedAllOption.label}
                            meta={selectedAllOption.meta}
                        />
                    ) : (
                        <span className="min-w-0 flex-1 truncate text-muted-foreground">
                            {placeholder}
                        </span>
                    )}
                    <RiArrowDownSLine className="size-4 shrink-0 text-muted-foreground" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className={cn(
                    'w-[min(28rem,calc(100vw-2rem))] p-0 sm:w-[var(--radix-popover-trigger-width)] sm:min-w-[22rem]',
                    contentClassName,
                )}
            >
                <Command>
                    <CommandInput placeholder={searchPlaceholder} />
                    <CommandList>
                        <CommandEmpty>{emptyLabel}</CommandEmpty>
                        <CommandGroup>
                            {allOption ? (
                                <CommandItem
                                    value={
                                        allOption.searchText ?? allOption.label
                                    }
                                    data-checked={value === allOption.value}
                                    onSelect={() => select(allOption.value)}
                                    className="px-3 py-2.5"
                                >
                                    <PlainChoiceLabel
                                        label={allOption.label}
                                        meta={allOption.meta}
                                    />
                                </CommandItem>
                            ) : null}

                            {options.map((option) => (
                                <CommandItem
                                    key={option.value}
                                    value={searchTextForSet(option)}
                                    data-checked={value === option.value}
                                    onSelect={() => select(option.value)}
                                    className="px-3 py-2.5"
                                >
                                    <StudentSetChoiceLabel
                                        entrySession={option.entrySession}
                                        currentLevel={option.currentLevel}
                                        minimumYearsToDegree={
                                            option.minimumYearsToDegree
                                        }
                                        isGraduated={option.isGraduated}
                                        meta={option.meta}
                                    />
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
    );
}

function PlainChoiceLabel({ label, meta }: { label: string; meta?: string }) {
    return (
        <span className="flex min-w-0 flex-1 items-center justify-between gap-3 text-left">
            <span className="min-w-0 truncate font-medium text-foreground">
                {label}
            </span>
            {meta ? (
                <span className="shrink-0 text-xs font-medium text-muted-foreground">
                    {meta}
                </span>
            ) : null}
        </span>
    );
}

function searchTextForSet(option: StudentSetPickerOption): string {
    if (option.searchText) {
        return option.searchText;
    }

    return [
        studentSetName(option.entrySession),
        studentSetText(option),
        option.meta,
    ]
        .filter(Boolean)
        .join(' ');
}
