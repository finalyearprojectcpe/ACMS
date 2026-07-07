import type { ReactNode } from 'react';
import {
    Bar,
    BarChart,
    CartesianGrid,
    Cell,
    Line,
    LineChart,
    Pie,
    PieChart,
    XAxis,
    YAxis,
} from 'recharts';

import { studentSetText } from '@/components/student-set-label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent,
} from '@/components/ui/chart';
import type { ChartConfig } from '@/components/ui/chart';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    DashboardAcademicSession,
    DashboardLevel,
    DashboardStaffStudent,
} from '@/types';

export type ChartSelectOption = {
    value: string;
    label: string;
};

export type GpaChartRow = {
    level: string;
    average: number | null;
};

export type ClassificationChartRow = {
    classification: string;
    students: number;
    fill: string;
};

export type CountChartRow = {
    label: string;
    value: number;
};

type StudentGpaRecord = Pick<
    DashboardStaffStudent,
    'cgpa' | 'entrySession' | 'levelGpas'
>;

export function ChartCard({
    title,
    detail,
    control,
    children,
}: {
    title: string;
    detail?: string;
    control?: ReactNode;
    children: ReactNode;
}) {
    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardHeader className="flex flex-col gap-3 border-b border-border lg:flex-row lg:items-start lg:justify-between">
                <div className="min-w-0">
                    <CardTitle>{title}</CardTitle>
                    {detail ? (
                        <p className="mt-1 text-sm text-muted-foreground">
                            {detail}
                        </p>
                    ) : null}
                </div>
                {control ? <div className="w-full lg:w-80">{control}</div> : null}
            </CardHeader>
            <CardContent className="p-4">{children}</CardContent>
        </Card>
    );
}

export function ChartSelect({
    value,
    options,
    label,
    onChange,
}: {
    value: string;
    options: ChartSelectOption[];
    label: string;
    onChange: (value: string) => void;
}) {
    return (
        <Select value={value} onValueChange={onChange}>
            <SelectTrigger aria-label={label}>
                <SelectValue placeholder={label} />
            </SelectTrigger>
            <SelectContent>
                {options.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                        {option.label}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

export function AverageGpaByLevelBarChart({ rows }: { rows: GpaChartRow[] }) {
    return <GpaBarChart rows={rows} />;
}

export function SetGpaProgressBarChart({ rows }: { rows: GpaChartRow[] }) {
    return <GpaBarChart rows={rows} emptyText="No GPA records for this set." />;
}

export function StudentGpaTrendLineChart({ rows }: { rows: GpaChartRow[] }) {
    return (
        <GpaLineChart rows={rows} emptyText="No GPA records to display yet." />
    );
}

function GpaBarChart({
    rows,
    emptyText = 'No GPA records to display.',
}: {
    rows: GpaChartRow[];
    emptyText?: string;
}) {
    const chartConfig = {
        average: { label: 'Average GPA', color: 'var(--chart-1)' },
    } satisfies ChartConfig;
    const hasValues = rows.some((row) => row.average !== null);

    if (!hasValues) {
        return <EmptyChartState>{emptyText}</EmptyChartState>;
    }

    return (
        <ChartContainer
            config={chartConfig}
            className="min-h-64 w-full"
            initialDimension={{ width: 520, height: 260 }}
        >
            <BarChart data={rows} margin={{ left: 8, right: 12, top: 8 }}>
                <CartesianGrid vertical={false} />
                <XAxis
                    dataKey="level"
                    tickLine={false}
                    axisLine={false}
                    tickMargin={8}
                />
                <YAxis
                    domain={[0, 5]}
                    tickLine={false}
                    axisLine={false}
                    tickMargin={8}
                />
                <ChartTooltip content={<ChartTooltipContent />} />
                <Bar
                    dataKey="average"
                    fill="var(--color-average)"
                    radius={[3, 3, 0, 0]}
                />
            </BarChart>
        </ChartContainer>
    );
}

function GpaLineChart({
    rows,
    emptyText = 'No GPA records to display.',
}: {
    rows: GpaChartRow[];
    emptyText?: string;
}) {
    const chartConfig = {
        average: { label: 'GPA', color: 'var(--chart-2)' },
    } satisfies ChartConfig;
    const hasValues = rows.some((row) => row.average !== null);

    if (!hasValues) {
        return <EmptyChartState>{emptyText}</EmptyChartState>;
    }

    return (
        <ChartContainer
            config={chartConfig}
            className="min-h-64 w-full"
            initialDimension={{ width: 520, height: 260 }}
        >
            <LineChart data={rows} margin={{ left: 8, right: 12, top: 8 }}>
                <CartesianGrid vertical={false} />
                <XAxis
                    dataKey="level"
                    tickLine={false}
                    axisLine={false}
                    tickMargin={8}
                />
                <YAxis
                    domain={[0, 5]}
                    tickLine={false}
                    axisLine={false}
                    tickMargin={8}
                />
                <ChartTooltip content={<ChartTooltipContent />} />
                <Line
                    type="monotone"
                    dataKey="average"
                    stroke="var(--color-average)"
                    strokeWidth={2.5}
                    dot={{ r: 4, strokeWidth: 2 }}
                    activeDot={{ r: 5 }}
                    connectNulls={false}
                />
            </LineChart>
        </ChartContainer>
    );
}

export function ResultClassificationPieChart({
    rows,
}: {
    rows: ClassificationChartRow[];
}) {
    const chartConfig = {
        students: { label: 'Students' },
        first: { label: 'First Class', color: 'var(--chart-1)' },
        secondUpper: { label: 'Second Class Upper', color: 'var(--chart-2)' },
        secondLower: { label: 'Second Class Lower', color: 'var(--chart-3)' },
        third: { label: 'Third Class', color: 'var(--chart-4)' },
        below: { label: 'Below Third Class', color: 'var(--chart-5)' },
    } satisfies ChartConfig;
    const hasStudents = rows.some((row) => row.students > 0);

    if (!hasStudents) {
        return <EmptyChartState>No students to classify.</EmptyChartState>;
    }

    return (
        <div className="grid justify-items-center gap-4 lg:grid-cols-[minmax(0,1fr)_14rem] lg:items-center lg:justify-items-stretch">
            <ChartContainer
                config={chartConfig}
                className="mx-auto min-h-64 w-full max-w-sm lg:max-w-none"
                initialDimension={{ width: 420, height: 260 }}
            >
                <PieChart>
                    <ChartTooltip content={<ChartTooltipContent />} />
                    <Pie
                        data={rows}
                        dataKey="students"
                        nameKey="classification"
                        innerRadius={56}
                        outerRadius={92}
                        paddingAngle={2}
                    >
                        {rows.map((row) => (
                            <Cell key={row.classification} fill={row.fill} />
                        ))}
                    </Pie>
                </PieChart>
            </ChartContainer>
            <div className="grid w-full max-w-sm content-center gap-2 text-sm lg:max-w-none">
                {rows.map((row) => (
                    <div
                        key={row.classification}
                        className="flex items-center justify-between gap-3"
                    >
                        <span className="flex min-w-0 items-center gap-2 text-muted-foreground">
                            <span
                                aria-hidden="true"
                                className="size-2.5 shrink-0 rounded-[2px] border border-border"
                                style={{ backgroundColor: row.fill }}
                            />
                            <span className="truncate">
                                {row.classification}
                            </span>
                        </span>
                        <span className="font-semibold tabular-nums">
                            {row.students}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

export function CountBarChart({
    rows,
    dataLabel = 'Total',
}: {
    rows: CountChartRow[];
    dataLabel?: string;
}) {
    const chartConfig = {
        value: { label: dataLabel, color: 'var(--chart-2)' },
    } satisfies ChartConfig;
    const hasValues = rows.some((row) => row.value > 0);

    if (!hasValues) {
        return <EmptyChartState>No records to display.</EmptyChartState>;
    }

    return (
        <ChartContainer
            config={chartConfig}
            className="min-h-64 w-full"
            initialDimension={{ width: 520, height: 260 }}
        >
            <BarChart data={rows} margin={{ left: 8, right: 12, top: 8 }}>
                <CartesianGrid vertical={false} />
                <XAxis
                    dataKey="label"
                    tickLine={false}
                    axisLine={false}
                    tickMargin={8}
                />
                <YAxis tickLine={false} axisLine={false} tickMargin={8} />
                <ChartTooltip content={<ChartTooltipContent />} />
                <Bar
                    dataKey="value"
                    fill="var(--color-value)"
                    radius={[3, 3, 0, 0]}
                />
            </BarChart>
        </ChartContainer>
    );
}

export function classificationRowsForStudents(
    students: StudentGpaRecord[],
): ClassificationChartRow[] {
    return classificationRowsForGpas(students.map((student) => student.cgpa));
}

export function classificationRowsForGpas(
    values: Array<string | number>,
): ClassificationChartRow[] {
    const labels = [
        ['First Class', 'var(--chart-1)'],
        ['Second Class Upper', 'var(--chart-2)'],
        ['Second Class Lower', 'var(--chart-3)'],
        ['Third Class', 'var(--chart-4)'],
        ['Below Third Class', 'var(--chart-5)'],
    ] as const;

    return labels.map(([classification, fill]) => ({
        classification,
        fill,
        students: values.filter(
            (value) => classificationFor(Number(value)) === classification,
        ).length,
    }));
}

export function averageGpaByLevelRows(
    students: StudentGpaRecord[],
    levels: DashboardLevel[],
): GpaChartRow[] {
    return levels.map((academicLevel) => {
        const level = shortLevelTitle(academicLevel.title);
        const gpas = students
            .map((student) =>
                student.levelGpas.find((row) => row.level === level),
            )
            .filter((row) => row !== undefined)
            .map((row) => Number(row.gpa))
            .filter((gpa) => !Number.isNaN(gpa));

        return {
            level,
            average: gpas.length === 0 ? null : roundedAverage(gpas),
        };
    });
}

export function gpaRowsForLevels(
    rows: GpaChartRow[],
    levels: DashboardLevel[],
): GpaChartRow[] {
    return levels.map((academicLevel) => {
        const level = shortLevelTitle(academicLevel.title);
        const row = rows.find(
            (candidate) =>
                candidate.level === level ||
                candidate.level === academicLevel.title,
        );
        const average =
            row?.average === null || row?.average === undefined
                ? null
                : Number(row.average);

        return {
            level,
            average: average === null || Number.isNaN(average) ? null : average,
        };
    });
}

export function studentsForEntrySession<T extends StudentGpaRecord>(
    students: T[],
    entrySessionName: string,
): T[] {
    return students.filter(
        (student) => student.entrySession?.name === entrySessionName,
    );
}

export function setChartOptions(
    sets: Array<{
        entrySession: DashboardAcademicSession;
        currentLevel: DashboardLevel | null;
        minimumYearsToDegree?: number | null;
    }>,
    minimumYearsToDegree?: number | null,
): ChartSelectOption[] {
    return sets.map((set) => ({
        value: set.entrySession.name,
        label: studentSetText({
            entrySession: set.entrySession,
            currentLevel: set.currentLevel,
            minimumYearsToDegree:
                set.minimumYearsToDegree ?? minimumYearsToDegree,
        }),
    }));
}

export function averageGpa(values: Array<string | number>): string {
    const validValues = values
        .map((value) => Number(value))
        .filter((value) => !Number.isNaN(value));

    if (validValues.length === 0) {
        return '0.00';
    }

    return roundedAverage(validValues).toFixed(2);
}

export function shortLevelTitle(level: string): string {
    const match = level.match(/^(\d+)00 Level$/);

    if (match) {
        return `${match[1]}00L`;
    }

    return level.replace(' Level', 'L');
}

export function classificationFor(cgpa: number): string {
    if (cgpa >= 4.5) {
        return 'First Class';
    }

    if (cgpa >= 3.5) {
        return 'Second Class Upper';
    }

    if (cgpa >= 2.4) {
        return 'Second Class Lower';
    }

    if (cgpa >= 1.5) {
        return 'Third Class';
    }

    return 'Below Third Class';
}

function roundedAverage(values: number[]): number {
    return Number(
        (
            values.reduce((total, value) => total + value, 0) / values.length
        ).toFixed(2),
    );
}

function EmptyChartState({ children }: { children: ReactNode }) {
    return (
        <div className="flex min-h-64 items-center justify-center border border-dashed border-border bg-muted/20 px-4 text-center text-sm text-muted-foreground">
            {children}
        </div>
    );
}
