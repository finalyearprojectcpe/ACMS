import { router, useForm } from '@inertiajs/react';
import { RiUserAddLine } from '@remixicon/react';
import type { FormEvent } from 'react';
import { useState } from 'react';

import {
    importMethod as storeImportedAdvisedStudents,
    store as storeAdvisedStudent,
} from '@/actions/App/Http/Controllers/AdviserStudentController';
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
import type { DashboardAdviserStudentForm } from '@/types';

export function AdviserStudentOnboarding({
    onboarding,
}: {
    onboarding: DashboardAdviserStudentForm;
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
    onboarding: DashboardAdviserStudentForm;
}) {
    const form = useForm({
        student_set_id: onboarding.assignedSets[0]?.id.toString() ?? '',
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
                detail={`Add a student to a ${onboarding.programme.abbreviation} set.`}
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
                                    form.errors.student_set_id,
                                )}
                            >
                                <FieldLabel>Student Set</FieldLabel>
                                <StudentSetPicker
                                    value={form.data.student_set_id}
                                    onValueChange={(value) =>
                                        form.setData('student_set_id', value)
                                    }
                                    options={onboarding.assignedSets.map(
                                        (set) => ({
                                            value: set.id.toString(),
                                            ...set,
                                        }),
                                    )}
                                    ariaLabel="Choose the student's set"
                                />
                                <FieldError>
                                    {form.errors.student_set_id}
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
    onboarding: DashboardAdviserStudentForm;
}) {
    const [studentSetId, setStudentSetId] = useState(
        onboarding.assignedSets[0]?.id.toString() ?? '',
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
                student_set_id: studentSetId,
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
                detail={`Add several students to one ${onboarding.programme.abbreviation} set.`}
            />

            <div className="max-w-xl">
                <Field>
                    <FieldLabel>Student Set</FieldLabel>
                    <StudentSetPicker
                        value={studentSetId}
                        onValueChange={setStudentSetId}
                        options={onboarding.assignedSets.map((set) => ({
                            value: set.id.toString(),
                            ...set,
                        }))}
                        ariaLabel="Choose the students' set"
                    />
                </Field>
            </div>

            <SpreadsheetImportPreview
                title="Students Spreadsheet"
                submitLabel="Import Students"
                submitDisabled={studentSetId === ''}
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
