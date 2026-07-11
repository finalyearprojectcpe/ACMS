import { useForm } from '@inertiajs/react';
import { RiSave3Line } from '@remixicon/react';
import type { FormEvent } from 'react';

import { updateStudentProfile } from '@/actions/App/Http/Controllers/DashboardController';
import { ProfilePhotoSettingsCard } from '@/components/profile-photo-settings-card';
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
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { fullNameFor } from '@/lib/person';
import { cn } from '@/lib/utils';
import type { DashboardStudentProfile } from '@/types';

type StudentProfileDashboardProps = {
    profile: DashboardStudentProfile;
};

type NameFormData = {
    surname: string;
    other_names: string;
};

export function StudentProfileDashboard({
    profile,
}: StudentProfileDashboardProps) {
    return (
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-4">
            <div className="grid gap-4 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
                <div className="grid content-start gap-4">
                    <ProfilePhotoSettingsCard
                        inputId="student-profile-photo"
                        user={{
                            name: profile.name.full,
                            email: profile.email,
                            avatar: profile.avatar,
                        }}
                    />
                    <NameSettingsCard profile={profile} />
                </div>

                <ProfileSummaryCard profile={profile} />
            </div>
        </div>
    );
}

function NameSettingsCard({ profile }: { profile: DashboardStudentProfile }) {
    const form = useForm<NameFormData>({
        surname: profile.name.surname,
        other_names: profile.name.otherNames,
    });
    const fullName = fullNameFor(form.data.surname, form.data.other_names);
    const hasChanges =
        form.data.surname !== profile.name.surname ||
        form.data.other_names !== profile.name.otherNames;

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!hasChanges) {
            return;
        }

        form.submit(updateStudentProfile(), {
            preserveScroll: true,
        });
    }

    function resetName() {
        form.setData({
            surname: profile.name.surname,
            other_names: profile.name.otherNames,
        });
        form.clearErrors();
    }

    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardForm onSubmit={submit}>
                <CardHeader className="gap-1">
                    <CardTitle className="text-base">Name</CardTitle>
                    <p className="text-sm text-muted-foreground">
                        This is how your name appears on your student profile.
                    </p>
                </CardHeader>
                <CardContent className="px-5 pb-6">
                    <FieldGroup className="gap-6">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field data-invalid={Boolean(form.errors.surname)}>
                                <FieldLabel htmlFor="student-surname">
                                    Surname
                                </FieldLabel>
                                <Input
                                    id="student-surname"
                                    value={form.data.surname}
                                    autoComplete="family-name"
                                    aria-invalid={Boolean(form.errors.surname)}
                                    onChange={(event) =>
                                        form.setData(
                                            'surname',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                                <FieldError>{form.errors.surname}</FieldError>
                            </Field>
                            <Field
                                data-invalid={Boolean(form.errors.other_names)}
                            >
                                <FieldLabel htmlFor="student-other-names">
                                    Other Names
                                </FieldLabel>
                                <Input
                                    id="student-other-names"
                                    value={form.data.other_names}
                                    autoComplete="given-name"
                                    aria-invalid={Boolean(
                                        form.errors.other_names,
                                    )}
                                    onChange={(event) =>
                                        form.setData(
                                            'other_names',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                                <FieldError>
                                    {form.errors.other_names}
                                </FieldError>
                            </Field>
                        </div>

                        <div className="rounded-md border border-border bg-muted/30 px-3 py-2 text-sm">
                            <span className="text-muted-foreground">
                                Display name
                            </span>
                            <span className="ml-2 font-medium text-foreground">
                                {fullName || profile.name.full}
                            </span>
                        </div>
                    </FieldGroup>
                </CardContent>
                <CardFooter className="justify-end gap-2 border-t bg-muted/20 px-5 py-4">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={!hasChanges || form.processing}
                        onClick={resetName}
                    >
                        Reset
                    </Button>
                    <Button
                        type="submit"
                        disabled={!hasChanges || form.processing}
                    >
                        <RiSave3Line />
                        {form.processing ? 'Saving...' : 'Save Name'}
                    </Button>
                </CardFooter>
            </CardForm>
        </Card>
    );
}

function ProfileSummaryCard({ profile }: { profile: DashboardStudentProfile }) {
    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardHeader className="border-b border-border">
                <CardTitle>Profile Summary</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-5 p-5 text-sm">
                <div className="grid gap-x-8 gap-y-4 sm:grid-cols-2">
                    <SummaryItem
                        label="Registration Number"
                        value={profile.registrationNumber}
                    />
                    <SummaryItem
                        label="CGPA"
                        value={profile.cgpa ?? 'Not available'}
                    />
                    <SummaryItem
                        label="Department"
                        value={departmentLabel(profile.department)}
                    />
                    <SummaryItem
                        label="Current Level"
                        value={profile.currentLevel?.title ?? 'Unassigned'}
                    />
                </div>

                <div className="grid gap-4 rounded-md border border-border bg-muted/20 p-4">
                    <SummaryItem
                        label="Faculty"
                        value={facultyLabel(profile.faculty)}
                    />
                    <SummaryItem
                        label="Course Adviser"
                        value={profile.courseAdvisor}
                    />
                </div>
            </CardContent>
        </Card>
    );
}

function SummaryItem({
    label,
    value,
    className,
}: {
    label: string;
    value: string;
    className?: string;
}) {
    return (
        <div className={cn('min-w-0', className)}>
            <div className="text-xs font-medium text-muted-foreground">
                {label}
            </div>
            <div className="mt-1 text-sm font-semibold break-words">
                {value}
            </div>
        </div>
    );
}

function facultyLabel(faculty: DashboardStudentProfile['faculty']): string {
    if (faculty === null) {
        return 'Unassigned';
    }

    return `${faculty.name} (${faculty.abbreviation})`;
}

function departmentLabel(
    department: DashboardStudentProfile['department'],
): string {
    if (department === null) {
        return 'Unassigned';
    }

    return `${department.name} (${department.abbreviation})`;
}
