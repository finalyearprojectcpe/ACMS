import { Link } from '@inertiajs/react';
import { RiDownload2Line, RiFileExcel2Line } from '@remixicon/react';

import {
    createResultSheetImport,
    exportResultSheet,
} from '@/actions/App/Http/Controllers/DashboardController';
import { qualifiedSemesterLabel } from '@/components/qualified-semester';
import { ResultMatrixTable } from '@/components/result-matrix-table';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import type { DashboardResultSheet, DashboardResultSheetDetail } from '@/types';

type ResultSheetDetailDashboardProps = {
    detail: DashboardResultSheetDetail;
};

export function ResultSheetDetailDashboard({
    detail,
}: ResultSheetDetailDashboardProps) {
    const { sheet } = detail;
    const title = qualifiedSemesterLabel(
        sheet.level.title,
        sheet.semester.name,
    );
    const summary = `${sheet.academicSession.name} Session · ${sheet.studentCount} students · ${sheet.courseCount} courses · Avg GPA ${sheet.averageGpa}`;

    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <h1 className="text-xl font-semibold tracking-normal">
                        {title}
                    </h1>
                    <div className="mt-1 flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
                        <span>{sheet.entrySession.name} Set</span>
                        <span>{sheet.academicSession.name} Session</span>
                        {sheet.submittedBy ? (
                            <span className="inline-flex items-center gap-1">
                                Submitted by{' '}
                                <SubmittedByLink person={sheet.submittedBy} />
                            </span>
                        ) : null}
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <Button asChild variant="outline">
                        <a href={exportResultSheet(sheet.id).url}>
                            <RiFileExcel2Line />
                            Export Result Sheet
                        </a>
                    </Button>
                    {detail.canEdit || detail.sourceFileUrl ? (
                        <>
                            {detail.sourceFileUrl ? (
                                <Button asChild variant="outline">
                                    <a href={detail.sourceFileUrl}>
                                        <RiDownload2Line />
                                        Download Source
                                    </a>
                                </Button>
                            ) : null}
                            {detail.canEdit ? (
                                <Button asChild variant="outline">
                                    <Link href={createResultSheetImport()}>
                                        <RiFileExcel2Line />
                                        Replace Spreadsheet
                                    </Link>
                                </Button>
                            ) : null}
                        </>
                    ) : null}
                </div>
            </div>

            {sheet.correctionNote ? (
                <Card className="border border-destructive/35 bg-destructive/5 text-destructive shadow-sm ring-1 ring-destructive/20">
                    <CardContent className="p-4 text-sm">
                        <span className="font-semibold">
                            Returned for correction:{' '}
                        </span>
                        <span className="text-destructive/90">
                            {sheet.correctionNote}
                        </span>
                    </CardContent>
                </Card>
            ) : null}

            <Card className="gap-0 border border-border bg-background pb-0 shadow-sm ring-1 ring-border/70">
                <CardHeader className="flex-col items-start justify-between gap-3 border-b border-border sm:flex-row sm:items-center">
                    <div className="min-w-0">
                        <CardTitle>{title} Results</CardTitle>
                        <div className="mt-1 text-sm text-muted-foreground">
                            {summary}
                        </div>
                    </div>
                </CardHeader>
                <CardContent className="p-0">
                    <ResultMatrixTable
                        sheet={sheet}
                        className="border-0 shadow-none ring-0"
                    />
                </CardContent>
            </Card>
        </div>
    );
}

function SubmittedByLink({
    person,
}: {
    person: NonNullable<DashboardResultSheet['submittedBy']>;
}) {
    if (person.profileUrl === null) {
        return (
            <span className="font-medium text-foreground">{person.name}</span>
        );
    }

    return (
        <Link
            href={person.profileUrl}
            className="font-semibold text-link underline-offset-4 hover:underline"
        >
            {person.name}
        </Link>
    );
}
