import { Link, router } from '@inertiajs/react';
import {
    RiArrowLeftLine,
    RiFileExcel2Line,
    RiSendPlaneLine,
} from '@remixicon/react';
import { useState } from 'react';

import {
    createResultSheetImport,
    reviewResultSheet,
    section as dashboardSection,
} from '@/actions/App/Http/Controllers/DashboardController';
import { ActionLink } from '@/components/action-link';
import { qualifiedSemesterLabel } from '@/components/qualified-semester';
import { ResultMatrixTable } from '@/components/result-matrix-table';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Field, FieldLabel } from '@/components/ui/field';
import { Textarea } from '@/components/ui/textarea';
import type { DashboardResultSheet, DashboardResultSheetDetail } from '@/types';

type ResultSheetDetailDashboardProps = {
    detail: DashboardResultSheetDetail;
};

const correctionReasons = [
    { value: 'incomplete_data', label: 'Incomplete data' },
    { value: 'incorrect_grades', label: 'Incorrect grades' },
    {
        value: 'missing_students_or_courses',
        label: 'Missing students or courses',
    },
] as const;

export function ResultSheetDetailDashboard({
    detail,
}: ResultSheetDetailDashboardProps) {
    const { sheet } = detail;
    const title = qualifiedSemesterLabel(
        sheet.level.title,
        sheet.semester.name,
    );
    const [correctionNote, setCorrectionNote] = useState('');
    const [correctionReason, setCorrectionReason] = useState<string | null>(
        null,
    );
    const summary = `${sheet.academicSession.name} Session · ${sheet.studentCount} students · ${sheet.courseCount} courses · Avg GPA ${sheet.averageGpa}`;

    function review(action: 'approve' | 'return') {
        router.patch(reviewResultSheet.url({ resultSheet: sheet.id }), {
            action,
            correction_reason:
                action === 'return' ? correctionReason : undefined,
            correction_note: action === 'return' ? correctionNote : undefined,
        });
    }

    return (
        <div className="mx-auto flex w-full max-w-7xl flex-col gap-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <ActionLink
                        href={dashboardSection({
                            section: detail.returnSection,
                        })}
                        icon={<RiArrowLeftLine className="size-4" />}
                    >
                        {detail.returnLabel}
                    </ActionLink>
                    <h1 className="mt-3 text-xl font-semibold tracking-normal">
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

                {detail.canEdit ? (
                    <div className="flex flex-wrap items-center gap-2">
                        <Button asChild variant="outline">
                            <Link href={createResultSheetImport()}>
                                <RiFileExcel2Line />
                                Replace Spreadsheet
                            </Link>
                        </Button>
                    </div>
                ) : null}
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
                    {detail.canReview ? (
                        <div className="flex flex-wrap gap-2">
                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button variant="outline" type="button">
                                        Return for Correction
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>
                                            Return {title} results?
                                        </AlertDialogTitle>
                                        <AlertDialogDescription>
                                            You can send this sheet back to the
                                            course adviser with an optional
                                            reason. Students will not see these
                                            results while corrections are
                                            pending.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <div className="grid gap-4">
                                        <div className="grid gap-2">
                                            <div className="text-sm font-medium">
                                                Common reasons
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                {correctionReasons.map(
                                                    (reason) => (
                                                        <Button
                                                            key={reason.value}
                                                            type="button"
                                                            variant={
                                                                correctionReason ===
                                                                reason.value
                                                                    ? 'default'
                                                                    : 'outline'
                                                            }
                                                            size="sm"
                                                            onClick={() =>
                                                                setCorrectionReason(
                                                                    correctionReason ===
                                                                        reason.value
                                                                        ? null
                                                                        : reason.value,
                                                                )
                                                            }
                                                        >
                                                            {reason.label}
                                                        </Button>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                        <Field>
                                            <FieldLabel>
                                                Note for the course adviser
                                            </FieldLabel>
                                            <Textarea
                                                value={correctionNote}
                                                onChange={(event) =>
                                                    setCorrectionNote(
                                                        event.target.value,
                                                    )
                                                }
                                                placeholder="Optional. Add any detail that will help the course adviser correct the sheet."
                                            />
                                        </Field>
                                    </div>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>
                                            Keep Reviewing
                                        </AlertDialogCancel>
                                        <AlertDialogAction
                                            onClick={() => review('return')}
                                        >
                                            Return Results
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                            <AlertDialog>
                                <AlertDialogTrigger asChild>
                                    <Button type="button">
                                        <RiSendPlaneLine />
                                        Approve Results
                                    </Button>
                                </AlertDialogTrigger>
                                <AlertDialogContent>
                                    <AlertDialogHeader>
                                        <AlertDialogTitle>
                                            Approve {title} results?
                                        </AlertDialogTitle>
                                        <AlertDialogDescription>
                                            You are approving this result sheet
                                            for public view. Students and
                                            authorised staff will be able to see
                                            the grades and GPA from this sheet.
                                        </AlertDialogDescription>
                                    </AlertDialogHeader>
                                    <AlertDialogFooter>
                                        <AlertDialogCancel>
                                            Keep Reviewing
                                        </AlertDialogCancel>
                                        <AlertDialogAction
                                            onClick={() => review('approve')}
                                        >
                                            Approve Results
                                        </AlertDialogAction>
                                    </AlertDialogFooter>
                                </AlertDialogContent>
                            </AlertDialog>
                        </div>
                    ) : null}
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
