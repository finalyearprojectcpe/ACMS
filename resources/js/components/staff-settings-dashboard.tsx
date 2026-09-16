import { useForm } from '@inertiajs/react';
import { RiLockPasswordLine, RiSave3Line } from '@remixicon/react';
import type { FormEvent } from 'react';

import { updateStaffPassword } from '@/actions/App/Http/Controllers/DashboardController';
import { ProfilePhotoSettingsCard } from '@/components/profile-photo-settings-card';
import { RoleBadge } from '@/components/role-badge';
import { StudentSetChoiceLabel } from '@/components/student-set-label';
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
import { PasswordInput } from '@/components/ui/password-input';
import type { DashboardStaffSettings } from '@/types';

type PasswordFormData = {
    current_password: string;
    password: string;
    password_confirmation: string;
};

export function StaffSettingsDashboard({
    settings,
}: {
    settings: DashboardStaffSettings;
}) {
    return (
        <div className="mx-auto flex w-full max-w-6xl flex-col gap-4">
            <div className="min-w-0">
                <h1 className="text-xl font-semibold tracking-normal">
                    Profile Settings
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    Manage your profile photo and account password.
                </p>
            </div>

            <div className="grid gap-4 lg:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
                <div className="grid content-start gap-4">
                    <ProfilePhotoSettingsCard
                        inputId="staff-profile-photo"
                        user={settings.staff}
                    />
                    <StaffPasswordSettingsCard />
                </div>

                <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                    <CardHeader className="border-b border-border">
                        <CardTitle>Profile Summary</CardTitle>
                    </CardHeader>
                    <CardContent className="grid gap-4 p-4 text-sm">
                        <div className="flex flex-wrap items-center gap-2">
                            <RoleBadge
                                role="Course Adviser"
                                context={settings.programme.abbreviation}
                            />
                        </div>
                        <SummaryLine
                            label="Programme"
                            value={settings.programme.name}
                        />
                        <SummaryLine
                            label="Institution"
                            value={settings.institution ?? 'Not set'}
                        />
                        <div className="grid gap-2">
                            <span className="text-xs font-medium tracking-normal text-muted-foreground">
                                Assigned Sets
                            </span>
                            <div className="grid gap-2">
                                {settings.assignedSets.length > 0 ? (
                                    settings.assignedSets.map((set) => (
                                        <div
                                            key={set.entrySession.id}
                                            className="text-foreground"
                                        >
                                            <StudentSetChoiceLabel
                                                entrySession={set.entrySession}
                                                currentLevel={set.currentLevel}
                                                minimumYearsToDegree={
                                                    set.minimumYearsToDegree
                                                }
                                            />
                                        </div>
                                    ))
                                ) : (
                                    <span className="text-muted-foreground">
                                        No assigned set yet.
                                    </span>
                                )}
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

function StaffPasswordSettingsCard() {
    const form = useForm<PasswordFormData>({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        form.submit(updateStaffPassword(), {
            onSuccess: () => form.reset(),
        });
    }

    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardForm onSubmit={submit}>
                <CardHeader className="gap-2">
                    <CardTitle className="flex items-center gap-2 text-base">
                        <RiLockPasswordLine className="size-4 text-muted-foreground" />
                        Change Password
                    </CardTitle>
                </CardHeader>
                <CardContent className="px-5 pb-6">
                    <FieldGroup className="gap-6">
                        <Field
                            data-invalid={Boolean(form.errors.current_password)}
                        >
                            <FieldLabel htmlFor="staff-current-password">
                                Current Password
                            </FieldLabel>
                            <PasswordInput
                                id="staff-current-password"
                                value={form.data.current_password}
                                autoComplete="current-password"
                                aria-invalid={Boolean(
                                    form.errors.current_password,
                                )}
                                onChange={(event) =>
                                    form.setData(
                                        'current_password',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                            <FieldError>
                                {form.errors.current_password}
                            </FieldError>
                        </Field>
                        <Field data-invalid={Boolean(form.errors.password)}>
                            <FieldLabel htmlFor="staff-new-password">
                                New Password
                            </FieldLabel>
                            <PasswordInput
                                id="staff-new-password"
                                value={form.data.password}
                                autoComplete="new-password"
                                aria-invalid={Boolean(form.errors.password)}
                                onChange={(event) =>
                                    form.setData('password', event.target.value)
                                }
                                required
                            />
                            <FieldError>{form.errors.password}</FieldError>
                        </Field>
                        <Field
                            data-invalid={Boolean(
                                form.errors.password_confirmation,
                            )}
                        >
                            <FieldLabel htmlFor="staff-confirm-password">
                                Confirm Password
                            </FieldLabel>
                            <PasswordInput
                                id="staff-confirm-password"
                                value={form.data.password_confirmation}
                                autoComplete="new-password"
                                aria-invalid={Boolean(
                                    form.errors.password_confirmation,
                                )}
                                onChange={(event) =>
                                    form.setData(
                                        'password_confirmation',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                            <FieldError>
                                {form.errors.password_confirmation}
                            </FieldError>
                        </Field>
                    </FieldGroup>
                </CardContent>
                <CardFooter className="justify-end border-t bg-muted/20 px-5 py-4">
                    <Button type="submit" disabled={form.processing}>
                        <RiSave3Line />
                        {form.processing ? 'Saving...' : 'Save Password'}
                    </Button>
                </CardFooter>
            </CardForm>
        </Card>
    );
}

function SummaryLine({ label, value }: { label: string; value: string }) {
    return (
        <div className="grid gap-1">
            <span className="text-xs font-medium tracking-normal text-muted-foreground">
                {label}
            </span>
            <span className="text-foreground">{value}</span>
        </div>
    );
}
