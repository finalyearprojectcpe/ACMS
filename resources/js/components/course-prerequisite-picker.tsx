import { RiCloseLine } from '@remixicon/react';
import { useMemo } from 'react';

import { Checkbox } from '@/components/ui/checkbox';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

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
    const groups = useMemo(
        () => groupPrerequisitesByPeriod(options),
        [options],
    );
    const selectedOptions = options.filter((option) =>
        selectedIds.includes(option.id),
    );
    const defaultGroup = groups[0]?.id;

    if (options.length === 0) {
        return (
            <div className="border border-border bg-muted/20 px-3 py-3 text-sm text-muted-foreground">
                No earlier courses are available.
            </div>
        );
    }

    return (
        <div className="rounded-none border border-border bg-background p-3">
            <Tabs
                defaultValue={defaultGroup}
                orientation="vertical"
                className="grid min-w-0 gap-0 md:grid-cols-[11rem_minmax(0,1fr)]"
            >
                <div className="border-b border-border pb-3 md:border-r md:border-b-0 md:pr-3 md:pb-0">
                    <TabsList
                        variant="line"
                        className="grid! h-auto w-full grid-cols-2 gap-1 bg-transparent p-0 sm:grid-cols-3 md:flex! md:flex-col md:items-stretch"
                    >
                        {groups.map((group) => (
                            <TabsTrigger
                                key={group.id}
                                value={group.id}
                                className="h-8 justify-center! px-2 md:justify-start! data-active:bg-primary! data-active:text-primary-foreground! data-active:after:opacity-0! data-active:hover:text-primary-foreground!"
                            >
                                {group.label}
                            </TabsTrigger>
                        ))}
                    </TabsList>
                </div>

                <div className="min-w-0 md:pl-3">
                    <SelectedPrerequisites
                        options={selectedOptions}
                        onRemove={(courseId) => onChange(courseId, false)}
                    />

                    {groups.map((group) => (
                        <TabsContent
                            key={group.id}
                            value={group.id}
                            className="m-0 mt-3"
                        >
                            <PrerequisiteCourseList
                                options={group.options}
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
        <div className="grid gap-2 border border-border bg-muted/10 p-2">
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
                <div className="px-2 py-4 text-xs text-muted-foreground">
                    No courses.
                </div>
            )}
        </div>
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
            className="flex items-start gap-3 border border-border bg-background p-3"
        >
            <Checkbox
                id={id}
                checked={checked}
                onCheckedChange={(value) => onChange(option.id, value === true)}
            />
            <span className="min-w-0">
                <span className="block text-sm font-semibold">
                    {option.code}
                </span>
                <span className="block truncate text-xs text-muted-foreground">
                    {option.title}
                </span>
                <span className="mt-1 block text-xs text-muted-foreground">
                    {option.units} units
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
        <div className="h-24 border border-border bg-muted/10 p-3">
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
    label: string;
    level: string;
    semester: string;
    options: CoursePrerequisiteOption[];
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
                label: `${shortLevelLabel(option.level)} ${semester}`,
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
            levelNumber(first.level) - levelNumber(second.level) ||
            semesterOrder(first.semester) - semesterOrder(second.semester),
    );
}

function shortLevelLabel(level: string): string {
    return level.replace(/\s*Level$/i, 'L');
}

function levelNumber(level: string): number {
    const match = level.match(/^(\d+)/);

    return match ? Number(match[1]) : 999;
}

function semesterLabel(semester: string): string {
    if (semester.toLowerCase().includes('rain')) {
        return 'Rain';
    }

    if (semester.toLowerCase().includes('harmattan')) {
        return 'Harmattan';
    }

    return semester.replace(/\s*Semester$/i, '');
}

function semesterOrder(semester: string): number {
    return semester === 'Harmattan' ? 0 : semester === 'Rain' ? 1 : 99;
}
