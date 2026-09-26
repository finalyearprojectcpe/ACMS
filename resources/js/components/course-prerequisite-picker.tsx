import { RiCloseLine } from '@remixicon/react';
import { useMemo, useState } from 'react';

import {
    LevelSemesterRail,
    LevelSemesterRailGroup,
    levelSemesterRailTabItemClassName,
} from '@/components/level-semester-rail';
import { Checkbox } from '@/components/ui/checkbox';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    academicLevelNumber,
    academicSemesterOrder,
    semesterLabel,
} from '@/lib/academic';

export type CoursePrerequisiteOption = {
    id: number;
    code: string;
    title: string;
    units: number;
    level: string;
    semester: string;
};

export function CoursePrerequisitePicker({
    options,
    selectedIds,
    onChange,
}: {
    options: CoursePrerequisiteOption[];
    selectedIds: number[];
    onChange: (courseId: number, checked: boolean) => void;
}) {
    const periods = useMemo(
        () => groupPrerequisitesByPeriod(options),
        [options],
    );
    const levels = useMemo(() => groupPeriodsByLevel(periods), [periods]);
    const selectedOptions = options.filter((option) =>
        selectedIds.includes(option.id),
    );
    const [activePeriod, setActivePeriod] = useState(periods[0]?.id ?? '');

    if (options.length === 0) {
        return (
            <div className="border border-border bg-muted/20 px-3 py-3 text-sm text-muted-foreground">
                No earlier courses are available.
            </div>
        );
    }

    return (
        <div className="border border-border bg-background">
            <SelectedPrerequisites
                options={selectedOptions}
                onRemove={(courseId) => onChange(courseId, false)}
            />
            <Tabs
                value={activePeriod}
                onValueChange={setActivePeriod}
                orientation="vertical"
                className="grid min-w-0 gap-0 md:grid-cols-[14rem_minmax(0,1fr)]"
            >
                <div className="border-b border-border md:border-r md:border-b-0">
                    <div className="p-3 md:hidden">
                        <Select
                            value={activePeriod}
                            onValueChange={setActivePeriod}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Choose an earlier semester" />
                            </SelectTrigger>
                            <SelectContent>
                                {periods.map((period) => (
                                    <SelectItem
                                        key={period.id}
                                        value={period.id}
                                    >
                                        {period.level} · {period.semester}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <ScrollArea className="hidden h-72 md:block">
                        <TabsList
                            variant="line"
                            className="grid! h-auto w-full items-stretch justify-stretch bg-transparent p-0"
                        >
                            <LevelSemesterRail>
                                {levels.map((level) => (
                                    <LevelSemesterRailGroup
                                        key={level.id}
                                        label={level.label}
                                    >
                                        {level.periods.map((period) => (
                                            <TabsTrigger
                                                key={period.id}
                                                value={period.id}
                                                className={
                                                    levelSemesterRailTabItemClassName
                                                }
                                            >
                                                {period.semester}
                                            </TabsTrigger>
                                        ))}
                                    </LevelSemesterRailGroup>
                                ))}
                            </LevelSemesterRail>
                        </TabsList>
                    </ScrollArea>
                </div>

                <div className="min-w-0 p-3 sm:p-4">
                    {periods.map((period) => (
                        <TabsContent
                            key={period.id}
                            value={period.id}
                            className="m-0"
                        >
                            <PrerequisiteCourseList
                                options={period.options}
                                selectedIds={selectedIds}
                                onChange={onChange}
                            />
                        </TabsContent>
                    ))}
                </div>
            </Tabs>
        </div>
    );
}

function PrerequisiteCourseList({
    options,
    selectedIds,
    onChange,
}: {
    options: CoursePrerequisiteOption[];
    selectedIds: number[];
    onChange: (courseId: number, checked: boolean) => void;
}) {
    return (
        <ScrollArea className="h-72">
            <div className="grid gap-2 pr-3">
                {options.length > 0 ? (
                    options.map((option) => (
                        <PrerequisiteOption
                            key={option.id}
                            option={option}
                            checked={selectedIds.includes(option.id)}
                            onChange={onChange}
                        />
                    ))
                ) : (
                    <div className="px-2 py-4 text-xs text-muted-foreground lg:col-span-2">
                        No courses.
                    </div>
                )}
            </div>
        </ScrollArea>
    );
}

function PrerequisiteOption({
    option,
    checked,
    onChange,
}: {
    option: CoursePrerequisiteOption;
    checked: boolean;
    onChange: (courseId: number, checked: boolean) => void;
}) {
    const id = `prerequisite-${option.id}`;

    return (
        <label
            htmlFor={id}
            className="flex min-h-20 items-start gap-3 border border-border bg-background p-3 hover:bg-muted/30"
        >
            <Checkbox
                id={id}
                checked={checked}
                onCheckedChange={(value) => onChange(option.id, value === true)}
            />
            <span className="min-w-0 flex-1">
                <span className="flex items-start justify-between gap-3">
                    <span className="text-sm font-semibold">{option.code}</span>
                    <span className="shrink-0 text-xs text-muted-foreground">
                        {option.units} units
                    </span>
                </span>
                <span className="mt-1 block text-xs text-muted-foreground">
                    {option.title}
                </span>
            </span>
        </label>
    );
}

function SelectedPrerequisites({
    options,
    onRemove,
}: {
    options: CoursePrerequisiteOption[];
    onRemove: (courseId: number) => void;
}) {
    return (
        <div className="h-24 border-b border-border bg-muted/10 p-3">
            <div className="mb-2 flex items-center justify-between gap-3">
                <div className="text-xs font-medium text-muted-foreground">
                    Selected Prerequisites
                </div>
                <div className="text-xs font-semibold text-muted-foreground tabular-nums">
                    {options.length} selected
                </div>
            </div>
            <ScrollArea className="h-12">
                <div className="flex flex-wrap gap-1 pr-3">
                    {options.length > 0 ? (
                        options.map((option) => (
                            <button
                                key={option.id}
                                type="button"
                                className="inline-flex h-6 max-w-full items-center gap-1 border border-border bg-background px-2 text-xs font-semibold text-foreground hover:bg-muted"
                                title={`${option.code} - ${option.title}`}
                                onClick={() => onRemove(option.id)}
                            >
                                <span className="truncate">{option.code}</span>
                                <RiCloseLine className="size-3 shrink-0" />
                            </button>
                        ))
                    ) : (
                        <div className="text-xs text-muted-foreground">
                            No prerequisites selected.
                        </div>
                    )}
                </div>
            </ScrollArea>
        </div>
    );
}

type PrerequisitePeriodGroup = {
    id: string;
    level: string;
    semester: string;
    options: CoursePrerequisiteOption[];
};

type PrerequisiteLevelGroup = {
    id: string;
    label: string;
    periods: PrerequisitePeriodGroup[];
};

function groupPrerequisitesByPeriod(
    options: CoursePrerequisiteOption[],
): PrerequisitePeriodGroup[] {
    const groups = new Map<string, PrerequisitePeriodGroup>();

    for (const option of options) {
        const semester = semesterLabel(option.semester);
        const id = `${option.level}-${semester}`;

        if (!groups.has(id)) {
            groups.set(id, {
                id,
                level: option.level,
                semester,
                options: [],
            });
        }

        const group = groups.get(id);

        if (!group) {
            continue;
        }

        group.options.push(option);
    }

    return [...groups.values()].sort(
        (first, second) =>
            academicLevelNumber(first.level) -
                academicLevelNumber(second.level) ||
            academicSemesterOrder(first.semester) -
                academicSemesterOrder(second.semester),
    );
}

function groupPeriodsByLevel(
    periods: PrerequisitePeriodGroup[],
): PrerequisiteLevelGroup[] {
    const groups = new Map<string, PrerequisiteLevelGroup>();

    for (const period of periods) {
        if (!groups.has(period.level)) {
            groups.set(period.level, {
                id: period.level,
                label: period.level,
                periods: [],
            });
        }

        groups.get(period.level)?.periods.push(period);
    }

    return [...groups.values()].sort(
        (first, second) =>
            academicLevelNumber(first.label) -
            academicLevelNumber(second.label),
    );
}
