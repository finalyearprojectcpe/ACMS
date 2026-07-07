import { RiArrowLeftLine } from '@remixicon/react';
import type { ReactNode } from 'react';

import { section as dashboardSection } from '@/actions/App/Http/Controllers/DashboardController';
import { ActionLink } from '@/components/action-link';
import { CopyableValue } from '@/components/copyable-value';
import { RoleBadge } from '@/components/role-badge';
import {
    StudentSetLabel,
    studentSetLevelText,
} from '@/components/student-set-label';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { initialsFor } from '@/lib/person';
import type { DashboardStaffRecord } from '@/types';

type StaffProfileDashboardProps = {
    record: DashboardStaffRecord;
};

export function StaffProfileDashboard({ record }: StaffProfileDashboardProps) {
    const { staff } = record;
    const departmentName = staff.department?.name ?? 'Not assigned';
    const departmentAbbreviation = staff.department?.abbreviation ?? 'None';
    const facultyName = staff.faculty?.name ?? 'Not assigned';

    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <div className="flex flex-col gap-4 rounded-lg border border-border bg-background p-4 shadow-sm ring-1 ring-border/70 sm:flex-row sm:items-start sm:justify-between">
                <div className="flex min-w-0 items-start gap-4">
                    <Avatar className="size-16 rounded-none after:rounded-none">
                        <AvatarImage
                            src={staff.avatar ?? undefined}
                            alt={`${staff.name} avatar`}
                            className="rounded-none"
                        />
                        <AvatarFallback className="rounded-none text-base font-semibold">
                            {initialsFor(staff.name)}
                        </AvatarFallback>
                    </Avatar>
                    <div className="min-w-0">
                        <h1 className="truncate text-xl font-semibold tracking-normal">
                            {staff.name}
                        </h1>
                        <div className="mt-2 flex max-w-full flex-wrap items-center gap-2">
                            {staff.email ? (
                                <CopyableValue
                                    value={staff.email}
                                    className="max-w-full"
                                    valueClassName="font-sans text-xs"
                                />
                            ) : (
                                <span className="text-sm text-muted-foreground">
                                    No email
                                </span>
                            )}
                        </div>
                        <div className="mt-3 flex flex-wrap items-center gap-2">
                            <RoleBadge
                                role={staff.role}
                                context={staff.roleContext}
                            />
                            <StatusBadge status={staff.status} />
                        </div>
                    </div>
                </div>

                <ActionLink
                    href={dashboardSection({ section: 'staff' })}
                    icon={<RiArrowLeftLine className="size-4" />}
                >
                    Back to Staff
                </ActionLink>
            </div>

            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                <MetricBlock
                    label="Role"
                    value={
                        <RoleBadge
                            role={staff.role}
                            context={staff.roleContext}
                        />
                    }
                    detail={departmentAbbreviation}
                />
                <MetricBlock
                    label="Department"
                    value={departmentAbbreviation}
                    detail={departmentName}
                />
                <MetricBlock
                    label="Advised Sets"
                    value={staff.assignedSets.length}
                    detail={
                        staff.assignedSets.length === 1
                            ? 'Active set'
                            : 'Active sets'
                    }
                />
                <MetricBlock
                    label="Signup Status"
                    value={staff.status}
                    detail={
                        staff.accessCode
                            ? 'Access code available'
                            : 'Access code used'
                    }
                />
            </div>

            <div className="grid gap-4 xl:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
                <DashboardCard title="Profile Summary">
                    <div className="grid gap-3 p-4 text-sm">
                        <SummaryLine label="Faculty" value={facultyName} />
                        <SummaryLine
                            label="Department"
                            value={departmentName}
                        />
                        <SummaryLine
                            label="Department Code"
                            value={departmentAbbreviation}
                        />
                        <SummaryLine
                            label="Role"
                            value={
                                staff.roleContext
                                    ? `${staff.role} · ${staff.roleContext}`
                                    : staff.role
                            }
                        />
                    </div>
                </DashboardCard>

                <DashboardCard title="Onboarding">
                    <div className="grid gap-4 p-4 text-sm sm:grid-cols-2">
                        <div className="grid gap-1">
                            <span className="text-xs font-medium text-muted-foreground">
                                Email
                            </span>
                            <CopyableValue
                                value={staff.email}
                                emptyLabel="Not set"
                                className="max-w-full"
                                valueClassName="font-sans text-xs"
                            />
                        </div>
                        <div className="grid gap-1">
                            <span className="text-xs font-medium text-muted-foreground">
                                Access Code
                            </span>
                            <CopyableValue
                                value={staff.accessCode}
                                emptyLabel="Used"
                                className="max-w-full"
                            />
                        </div>
                        <SummaryLine label="Status" value={staff.status} />
                        <SummaryLine
                            label="Assigned Sets"
                            value={staff.assignedSets.length.toString()}
                        />
                    </div>
                </DashboardCard>
            </div>

            <DashboardCard
                title="Advised Sets"
                detail="Sets this staff member currently handles."
            >
                {staff.assignedSets.length > 0 ? (
                    <ScrollArea className="w-full">
                        <Table className="min-w-[640px] text-sm">
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-[38%] px-4">
                                        Set
                                    </TableHead>
                                    <TableHead>Current Level</TableHead>
                                    <TableHead>Department</TableHead>
                                    <TableHead className="pr-4">
                                        Status
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {staff.assignedSets.map((set) => (
                                    <TableRow key={set.entrySession.id}>
                                        <TableCell className="px-4 font-medium">
                                            <StudentSetLabel
                                                entrySession={set.entrySession}
                                                currentLevel={set.currentLevel}
                                                minimumYearsToDegree={
                                                    set.minimumYearsToDegree
                                                }
                                            />
                                        </TableCell>
                                        <TableCell>
                                            {studentSetLevelText(
                                                set.currentLevel,
                                                set.minimumYearsToDegree,
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {departmentAbbreviation}
                                        </TableCell>
                                        <TableCell className="pr-4">
                                            <Badge
                                                variant="outline"
                                                className="border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-400/30 dark:bg-teal-400/10 dark:text-teal-300"
                                            >
                                                Active Adviser
                                            </Badge>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                        <ScrollBar orientation="horizontal" />
                    </ScrollArea>
                ) : (
                    <div className="p-4 text-sm text-muted-foreground">
                        No advised set assigned.
                    </div>
                )}
            </DashboardCard>
        </div>
    );
}

function MetricBlock({
    label,
    value,
    detail,
}: {
    label: string;
    value: ReactNode;
    detail: string;
}) {
    return (
        <Card
            size="sm"
            className="border border-border bg-background shadow-sm ring-1 ring-border/70"
        >
            <CardHeader>
                <CardTitle className="text-xs text-muted-foreground">
                    {label}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="truncate text-xl font-semibold tracking-normal tabular-nums">
                    {value}
                </div>
                <div className="mt-1 truncate text-xs text-muted-foreground">
                    {detail}
                </div>
            </CardContent>
        </Card>
    );
}

function DashboardCard({
    title,
    detail,
    children,
}: {
    title: string;
    detail?: string;
    children: ReactNode;
}) {
    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardHeader className="border-b border-border">
                <CardTitle>{title}</CardTitle>
                {detail ? (
                    <div className="mt-1 text-sm text-muted-foreground">
                        {detail}
                    </div>
                ) : null}
            </CardHeader>
            <CardContent className="p-0">{children}</CardContent>
        </Card>
    );
}

function SummaryLine({ label, value }: { label: string; value: ReactNode }) {
    return (
        <div className="grid grid-cols-[8rem_minmax(0,1fr)] gap-3">
            <span className="text-muted-foreground">{label}</span>
            <span className="min-w-0 font-medium">{value}</span>
        </div>
    );
}

function StatusBadge({ status }: { status: string }) {
    const className =
        status === 'Active'
            ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-300'
            : 'border-border bg-muted/60 text-foreground';

    return (
        <Badge variant="outline" className={className}>
            {status}
        </Badge>
    );
}
