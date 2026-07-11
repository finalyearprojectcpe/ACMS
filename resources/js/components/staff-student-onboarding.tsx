import { router, useForm } from '@inertiajs/react';
import { RiUserAddLine } from '@remixicon/react';
import type { FormEvent } from 'react';
import { useState } from 'react';

import {
    storeAdvisedStudent,
    storeImportedAdvisedStudents,
} from '@/actions/App/Http/Controllers/DashboardController';
import { SpreadsheetImportPreview } from '@/components/spreadsheet-import-preview';
import type {
    SpreadsheetImportBatch,
    SpreadsheetImportCallbacks,
} from '@/components/spreadsheet-import-preview';
import { StudentSetPicker } from '@/components/student-set-picker';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardForm,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    Field,
    FieldDescription,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import type { DashboardStaffStudentForm } from '@/types';

export function StaffStudentOnboarding({
    onboarding,
}: {
    onboarding: DashboardStaffStudentForm;
}) {
    return onboarding.mode === 'import' ? (
        <StudentSpreadsheetImport onboarding={onboarding} />
    ) : (
        <SingleStudentForm onboarding={onboarding} />
    );
}

function SingleStudentForm({
    onboarding,
}: {
    onboarding: DashboardStaffStudentForm;
}) {
    const form = useForm({
        entry_academic_session_id:
            onboarding.assignedSets[0]?.entrySession.id.toString() ?? '',
        registration_number: '',
        access_code: onboarding.suggestedAccessCode,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.submit(storeAdvisedStudent());
    }

    return (
        <div className="mx-auto flex w-full max-w-2xl flex-col gap-5">
            <PageHeading
                title="Add One Student"
                detail={`Add a student to a set you advise in ${onboarding.department.abbreviation}.`}
            />

            <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                <CardForm onSubmit={submit}>
                    <CardHeader className="border-b border-border">
                        <CardTitle>Student Account</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <FieldGroup>
                            <Field
                                data-invalid={Boolean(
                                    form.errors.entry_academic_session_id,
                                )}
                            >
                                <FieldLabel>Student Set</FieldLabel>
                                <StudentSetPicker
                                    value={form.data.entry_academic_session_id}
                                    onValueChange={(value) =>
                                        form.setData(
                                            'entry_academic_session_id',
                                            value,
                                        )
                                    }
                                    options={onboarding.assignedSets.map(
                                        (set) => ({
                                            value: set.entrySession.id.toString(),
                                            ...set,
                                        }),
                                    )}
                                    ariaLabel="Choose the student's set"
                                />
                                <FieldError>
                                    {form.errors.entry_academic_session_id}
                                </FieldError>
                            </Field>

                            <Field
                                data-invalid={Boolean(
                                    form.errors.registration_number,
                                )}
                            >
                                <FieldLabel htmlFor="registration-number">
                                    Registration Number
                                </FieldLabel>
                                <Input
                                    id="registration-number"
                                    inputMode="numeric"
                                    autoComplete="off"
                                    placeholder="2023001234"
                                    value={form.data.registration_number}
                                    onChange={(event) =>
                                        form.setData(
                                            'registration_number',
                                            event.target.value,
                                        )
                                    }
                                />
                                <FieldDescription>
                                    Enter the registration number supplied for
                                    this student.
                                </FieldDescription>
                                <FieldError>
                                    {form.errors.registration_number}
                                </FieldError>
                            </Field>

                            <Field
                                data-invalid={Boolean(form.errors.access_code)}
                            >
                                <FieldLabel htmlFor="student-access-code">
                                    Access Code
                                </FieldLabel>
                                <Input
                                    id="student-access-code"
                                    autoComplete="off"
                                    value={form.data.access_code}
                                    onChange={(event) =>
                                        form.setData(
                                            'access_code',
                                            event.target.value.toUpperCase(),
                                        )
                                    }
                                />
                                <FieldDescription>
                                    The student uses this once to activate the
                                    account and set a password.
                                </FieldDescription>
                                <FieldError>
                                    {form.errors.access_code}
                                </FieldError>
                            </Field>
                        </FieldGroup>
                    </CardContent>
                    <CardFooter className="justify-end bg-muted/20">
                        <Button
                            type="submit"
                            disabled={
                                form.processing ||
                                onboarding.assignedSets.length === 0
                            }
                        >
                            <RiUserAddLine />
                            {form.processing
                                ? 'Adding Student...'
                                : 'Add Student'}
                        </Button>
                    </CardFooter>
                </CardForm>
            </Card>
        </div>
    );
}

function StudentSpreadsheetImport({
    onboarding,
}: {
    onboarding: DashboardStaffStudentForm;
}) {
    const [entryAcademicSessionId, setEntryAcademicSessionId] = useState(
        onboarding.assignedSets[0]?.entrySession.id.toString() ?? '',
    );

    function submitRows(
        rows: Array<Record<string, string>>,
        fileName: string | null,
        batch: SpreadsheetImportBatch,
        callbacks: SpreadsheetImportCallbacks,
    ) {
        router.post(
            storeImportedAdvisedStudents.url(),
            {
                entry_academic_session_id: entryAcademicSessionId,
                file_name: fileName ?? '',
                import_key: batch.importKey,
                batch_number: batch.batchNumber,
                total_batches: batch.totalBatches,
                start_row: batch.startRow,
                total_rows: batch.totalRows,
                batch_size: batch.batchSize,
                mappings: batch.mappings,
                rows,
            },
            {
                preserveState: true,
                preserveScroll: true,
                onError: callbacks.onError,
                onSuccess: callbacks.onSuccess,
                onFinish: callbacks.onFinish,
            },
        );
    }

    return (
        <div className="mx-auto flex w-full max-w-5xl flex-col gap-5">
            <PageHeading
                title="Import Students From Spreadsheet"
                detail={`Add several students to one ${onboarding.department.abbreviation} set.`}
            />

            <div className="max-w-xl">
                <Field>
                    <FieldLabel>Student Set</FieldLabel>
                    <StudentSetPicker
                        value={entryAcademicSessionId}
                        onValueChange={setEntryAcademicSessionId}
                        options={onboarding.assignedSets.map((set) => ({
                            value: set.entrySession.id.toString(),
                            ...set,
                        }))}
                        ariaLabel="Choose the students' set"
                    />
                </Field>
            </div>

            <SpreadsheetImportPreview
                title="Students Spreadsheet"
                submitLabel="Import Students"
                submitDisabled={entryAcademicSessionId === ''}
                fields={[
                    {
                        key: 'registrationNumber',
                        label: 'Registration Number',
                        aliases: [
                            'registration number',
                            'reg number',
                            'reg no',
                            'matric number',
                        ],
                        defaultColumnIndex: 0,
                        required: true,
                    },
                    {
                        key: 'accessCode',
                        label: 'Access Code',
                        aliases: ['access code', 'code'],
                        defaultColumnIndex: 1,
                        description:
                            'Leave unmapped or blank to generate a different code for each student.',
                        emptyValueLabel: 'Auto-generated',
                    },
                ]}
                onSubmit={submitRows}
            />
        </div>
    );
}

function PageHeading({ title, detail }: { title: string; detail: string }) {
    return (
        <div className="space-y-1">
            <h1 className="text-xl font-semibold tracking-normal">{title}</h1>
            <p className="text-sm text-muted-foreground">{detail}</p>
        </div>
    );
}
