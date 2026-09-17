import { RiArrowLeftLine } from '@remixicon/react';

import { section as dashboardSection } from '@/actions/App/Http/Controllers/DashboardController';
import { ActionLink } from '@/components/action-link';
import { CopyableValue } from '@/components/copyable-value';
import { PersonCell } from '@/components/person-cell';
import { studentSetLevelText } from '@/components/student-set-label';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { DashboardStudentRecord } from '@/types';

export function StudentRecordDashboard({
    record,
}: {
    record: DashboardStudentRecord;
}) {
    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-5">
            <ActionLink
                href={dashboardSection({ section: 'students' })}
                icon={<RiArrowLeftLine className="size-4" />}
                className="w-fit"
            >
                Back to Students
            </ActionLink>

            <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <PersonCell
                        name={record.student.fullName}
                        secondary={record.student.email}
                        avatar={record.student.avatar}
                        size="md"
                    />
                    <div className="mt-2 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                        <span>Reg. No.</span>
                        <CopyableValue
                            value={record.student.registrationNumber}
                            className="h-7"
                            valueClassName="text-xs"
                        />
                    </div>
                </div>
            </div>

            <div className="grid gap-3 sm:grid-cols-3">
                <Summary
                    label="Current Level"
                    value={studentSetLevelText(
                        record.student.currentLevel,
                        record.programme.minimumYearsToDegree,
                    )}
                />
                <Summary
                    label="Student Set"
                    value={record.student.studentSet.name}
                />
                <Summary label="Course Adviser" value={record.courseAdvisor} />
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Profile Summary</CardTitle>
                </CardHeader>
                <CardContent className="grid gap-3 text-sm sm:grid-cols-2">
                    <SummaryLine
                        label="Institution"
                        value={record.institution ?? 'Not set'}
                    />
                    <SummaryLine
                        label="Programme"
                        value={record.programme.name}
                    />
                    <SummaryLine
                        label="Entry Session"
                        value={`${record.entrySession.name} Session`}
                    />
                    <SummaryLine
                        label="Registration Number"
                        value={record.student.registrationNumber}
                    />
                </CardContent>
            </Card>
        </div>
    );
}

function Summary({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="border bg-background p-4 shadow-sm">
            <div className="text-xs font-medium text-muted-foreground">
                {label}
            </div>
            <div className="mt-1 text-lg font-semibold">{value}</div>
        </div>
    );
}

function SummaryLine({ label, value }: { label: string; value: string }) {
    return (
        <div className="grid grid-cols-[8rem_minmax(0,1fr)] gap-3 border-b py-2 last:border-b-0">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium">{value}</span>
        </div>
    );
}
