import { Link, useForm } from '@inertiajs/react';
import { RiArrowLeftLine, RiSave3Line } from '@remixicon/react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import {
    store,
    update,
} from '@/actions/App/Http/Controllers/AcademicSessionController';
import { editStudentSet } from '@/actions/App/Http/Controllers/DashboardController';
import { ActionLink } from '@/components/action-link';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardForm,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
    InputGroupText,
} from '@/components/ui/input-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    sessionRouteYears,
    studentSetRouteParameters,
} from '@/lib/student-sets';
import type { DashboardAdviserAcademicSessionForm } from '@/types';

export function AdviserAcademicSessionForm({
    form,
}: {
    form: DashboardAdviserAcademicSessionForm;
}) {
    const parameters = studentSetRouteParameters(form.set);
    const initialLevel =
        form.session?.academicYearId.toString() ??
        form.levels.find((level) => level.available)?.id.toString() ??
        '';
    const sessionForm = useForm({
        academic_year_id: initialLevel,
        start_year: form.startYear.toString(),
        end_year: (form.startYear + 1).toString(),
        first_semester_maximum_course_units:
            form.semesterMaximumCourseUnits.first.toString(),
        second_semester_maximum_course_units:
            form.semesterMaximumCourseUnits.second.toString(),
    });
    const [confirmingRelabel, setConfirmingRelabel] = useState(false);
    const endYear = /^\d{4}$/.test(sessionForm.data.start_year)
        ? Number(sessionForm.data.start_year) + 1
        : null;
    const selectedLevel = form.levels.find(
        (level) => level.id.toString() === sessionForm.data.academic_year_id,
    );

    function save() {
        sessionForm.transform((data) => ({
            ...data,
            end_year: endYear?.toString() ?? '',
        }));

        if (form.mode === 'edit' && form.session) {
            sessionForm.patch(
                update.url({
                    ...parameters,
                    sessionYears: sessionRouteYears(form.session),
                }),
                { onSuccess: () => setConfirmingRelabel(false) },
            );

            return;
        }

        sessionForm.post(store.url(parameters));
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (form.mode === 'edit' && form.session?.hasDependents) {
            setConfirmingRelabel(true);

            return;
        }

        save();
    }

    return (
        <div className="mx-auto flex w-full max-w-2xl flex-col gap-5">
            <ActionLink
                href={editStudentSet(parameters)}
                icon={<RiArrowLeftLine className="size-4" />}
                className="w-fit"
            >
                Go to Set Settings
            </ActionLink>
            <Card className="w-full border border-border bg-background">
                <CardForm onSubmit={submit}>
                    <CardHeader className="border-b border-border">
                        <CardTitle className="text-base">
                            {form.mode === 'edit'
                                ? 'Edit Academic Session'
                                : 'Add Academic Session'}
                        </CardTitle>
                        <p className="text-xs text-muted-foreground">
                            {form.set.name}
                        </p>
                    </CardHeader>
                    <CardContent className="grid gap-5 sm:grid-cols-2">
                        <Field
                            data-invalid={Boolean(
                                sessionForm.errors.academic_year_id,
                            )}
                        >
                            <FieldLabel htmlFor="academic-level">
                                Academic Level
                            </FieldLabel>
                            <Select
                                value={sessionForm.data.academic_year_id}
                                onValueChange={(value) =>
                                    sessionForm.setData(
                                        'academic_year_id',
                                        value,
                                    )
                                }
                            >
                                <SelectTrigger id="academic-level">
                                    <SelectValue placeholder="Choose a level" />
                                </SelectTrigger>
                                <SelectContent>
                                    {form.levels.map((level) => (
                                        <SelectItem
                                            key={level.id}
                                            value={level.id.toString()}
                                            disabled={!level.available}
                                        >
                                            <span>{level.title}</span>
                                            {level.unavailableReason ? (
                                                <span className="ml-2 text-xs text-muted-foreground">
                                                    {level.unavailableReason}
                                                </span>
                                            ) : null}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <FieldError>
                                {sessionForm.errors.academic_year_id}
                            </FieldError>
                        </Field>
                        <Field
                            data-invalid={Boolean(
                                sessionForm.errors.start_year,
                            )}
                        >
                            <FieldLabel htmlFor="session-start-year">
                                Session
                            </FieldLabel>
                            <InputGroup className="h-10 w-fit! border-foreground/35 bg-background shadow-xs">
                                <InputGroupInput
                                    id="session-start-year"
                                    aria-invalid={Boolean(
                                        sessionForm.errors.start_year,
                                    )}
                                    inputMode="numeric"
                                    maxLength={4}
                                    value={sessionForm.data.start_year}
                                    onChange={(event) =>
                                        sessionForm.setData(
                                            'start_year',
                                            event.target.value
                                                .replace(/\D/g, '')
                                                .slice(0, 4),
                                        )
                                    }
                                    className="w-[4.5rem] flex-none px-2 text-center tabular-nums"
                                />
                                <InputGroupAddon
                                    align="inline-end"
                                    className="pr-3"
                                >
                                    <InputGroupText
                                        className="gap-0 text-sm font-normal tabular-nums"
                                        aria-live="polite"
                                    >
                                        /{endYear ?? '----'}
                                    </InputGroupText>
                                </InputGroupAddon>
                            </InputGroup>
                            <FieldError>
                                {sessionForm.errors.start_year}
                            </FieldError>
                        </Field>
                        <div className="grid gap-4 border-t border-border pt-5 sm:col-span-2 sm:grid-cols-2">
                            <Field
                                data-invalid={Boolean(
                                    sessionForm.errors
                                        .first_semester_maximum_course_units,
                                )}
                            >
                                <FieldLabel htmlFor="first-semester-maximum-units">
                                    1st Semester Maximum Units
                                </FieldLabel>
                                <Input
                                    id="first-semester-maximum-units"
                                    type="number"
                                    min={1}
                                    max={60}
                                    value={
                                        sessionForm.data
                                            .first_semester_maximum_course_units
                                    }
                                    onChange={(event) =>
                                        sessionForm.setData(
                                            'first_semester_maximum_course_units',
                                            event.target.value,
                                        )
                                    }
                                    className="max-w-36"
                                />
                                <FieldError>
                                    {
                                        sessionForm.errors
                                            .first_semester_maximum_course_units
                                    }
                                </FieldError>
                            </Field>
                            <Field
                                data-invalid={Boolean(
                                    sessionForm.errors
                                        .second_semester_maximum_course_units,
                                )}
                            >
                                <FieldLabel htmlFor="second-semester-maximum-units">
                                    2nd Semester Maximum Units
                                </FieldLabel>
                                <Input
                                    id="second-semester-maximum-units"
                                    type="number"
                                    min={1}
                                    max={60}
                                    value={
                                        sessionForm.data
                                            .second_semester_maximum_course_units
                                    }
                                    onChange={(event) =>
                                        sessionForm.setData(
                                            'second_semester_maximum_course_units',
                                            event.target.value,
                                        )
                                    }
                                    className="max-w-36"
                                />
                                <FieldError>
                                    {
                                        sessionForm.errors
                                            .second_semester_maximum_course_units
                                    }
                                </FieldError>
                            </Field>
                        </div>
                        {form.mode === 'edit' && form.session?.hasDependents ? (
                            <p className="text-xs text-muted-foreground sm:col-span-2">
                                This session already contains courses or course
                                registrations. Saving changes will relabel those
                                records without deleting them.
                            </p>
                        ) : null}
                    </CardContent>
                    <CardFooter className="justify-end gap-2 border-t border-border">
                        <Button asChild type="button" variant="outline">
                            <Link href={editStudentSet(parameters)}>
                                Cancel
                            </Link>
                        </Button>
                        <Button
                            type="submit"
                            disabled={sessionForm.processing || !selectedLevel}
                        >
                            <RiSave3Line />
                            {sessionForm.processing
                                ? 'Saving...'
                                : form.mode === 'edit'
                                  ? 'Save Session'
                                  : 'Add Session'}
                        </Button>
                    </CardFooter>
                </CardForm>
            </Card>

            <AlertDialog
                open={confirmingRelabel}
                onOpenChange={(open) => {
                    if (!sessionForm.processing) {
                        setConfirmingRelabel(open);
                    }
                }}
            >
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            Update this academic session?
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            You are changing this session to{' '}
                            <strong>
                                {selectedLevel?.title}{' '}
                                {sessionForm.data.start_year}/{endYear}
                            </strong>
                            . Its existing courses and registrations will remain
                            attached and will use the new label.
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={sessionForm.processing}>
                            Cancel
                        </AlertDialogCancel>
                        <AlertDialogAction
                            disabled={sessionForm.processing}
                            onClick={(event) => {
                                event.preventDefault();
                                save();
                            }}
                        >
                            Save Session
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}
