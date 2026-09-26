import { useForm } from '@inertiajs/react';
import { RiSave3Line } from '@remixicon/react';
import type { FormEvent } from 'react';

import { updateProfile as updateAdviserProfile } from '@/actions/App/Http/Controllers/AdviserAccountController';
import { createAdviserPaymentAccount } from '@/actions/App/Http/Controllers/DashboardController';
import { EditableProfilePhoto } from '@/components/editable-profile-photo';
import { PaymentAccountSummary } from '@/components/payment-account-summary';
import { ProfilePasswordField } from '@/components/profile-password-field';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useProfilePhotoInput } from '@/hooks/use-profile-photo-input';
import type {
    DashboardAdviserPaymentSettings,
    DashboardAdviserProfile,
} from '@/types';

type ProfileFormData = {
    title: string;
    name: string;
    profile_photo: File | null;
    current_password: string;
    password: string;
    password_confirmation: string;
};

export function AdviserProfileDashboard({
    profile,
    settings,
}: {
    profile: DashboardAdviserProfile;
    settings: DashboardAdviserPaymentSettings;
}) {
    const form = useForm<ProfileFormData>({
        title: profile.adviser.title ?? '',
        name: profile.adviser.name,
        profile_photo: null,
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    const photo = useProfilePhotoInput(profile.adviser.avatar);

    function selectPhoto(file: File | undefined) {
        if (!file) {
            return;
        }

        photo.select(file);
        form.setData('profile_photo', file);
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();

        if (!form.isDirty) {
            return;
        }

        form.transform((data) => ({ ...data, _method: 'patch' }));
        form.post(updateAdviserProfile.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                const savedData: ProfileFormData = {
                    title: form.data.title,
                    name: form.data.name.trim().replace(/\s+/g, ' '),
                    profile_photo: null,
                    current_password: '',
                    password: '',
                    password_confirmation: '',
                };

                photo.reset();
                form.setDefaults(savedData);
                form.setData(savedData);
            },
        });
    }

    return (
        <div className="mx-auto w-full max-w-3xl">
            <Card className="min-w-0 border-0 bg-transparent py-0 shadow-none ring-0">
                <CardForm onSubmit={submit}>
                    <CardHeader className="px-0 pt-4">
                        <CardTitle
                            id="personal-details-heading"
                            className="text-base font-semibold"
                        >
                            Personal Details
                        </CardTitle>
                    </CardHeader>

                    <CardContent className="grid w-full gap-6 px-0 pb-6">
                        <section
                            aria-labelledby="personal-details-heading"
                            className="grid grid-cols-[6rem_minmax(0,1fr)] items-start gap-5"
                        >
                            <div className="grid gap-2">
                                <EditableProfilePhoto
                                    avatar={photo.preview}
                                    name={profile.adviser.displayName}
                                    inputId="adviser-profile-photo"
                                    inputRef={photo.inputRef}
                                    avatarClassName="size-24"
                                    onPhotoChange={selectPhoto}
                                />
                                <FieldError>
                                    {form.errors.profile_photo}
                                </FieldError>
                            </div>

                            <div className="min-w-0">
                                <FieldGroup>
                                    <Field
                                        data-invalid={Boolean(
                                            form.errors.title,
                                        )}
                                    >
                                        <FieldLabel htmlFor="adviser-title">
                                            Honorific
                                        </FieldLabel>
                                        <Select
                                            value={form.data.title}
                                            onValueChange={(value) =>
                                                form.setData('title', value)
                                            }
                                        >
                                            <SelectTrigger
                                                id="adviser-title"
                                                aria-invalid={Boolean(
                                                    form.errors.title,
                                                )}
                                            >
                                                <SelectValue placeholder="Select" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {profile.honorifics.map(
                                                    (honorific) => (
                                                        <SelectItem
                                                            key={
                                                                honorific.value
                                                            }
                                                            value={
                                                                honorific.value
                                                            }
                                                        >
                                                            {honorific.label}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                        <FieldError>
                                            {form.errors.title}
                                        </FieldError>
                                    </Field>
                                    <Field
                                        data-invalid={Boolean(form.errors.name)}
                                    >
                                        <FieldLabel htmlFor="adviser-name">
                                            Full Name
                                        </FieldLabel>
                                        <Input
                                            id="adviser-name"
                                            value={form.data.name}
                                            autoComplete="name"
                                            aria-invalid={Boolean(
                                                form.errors.name,
                                            )}
                                            onChange={(event) =>
                                                form.setData(
                                                    'name',
                                                    event.target.value,
                                                )
                                            }
                                            required
                                        />
                                        <FieldError>
                                            {form.errors.name}
                                        </FieldError>
                                    </Field>
                                </FieldGroup>
                            </div>
                        </section>

                        <section
                            aria-labelledby="change-password-heading"
                            className="grid gap-4 border-t border-border pt-6"
                        >
                            <div>
                                <h2
                                    id="change-password-heading"
                                    className="text-base font-semibold"
                                >
                                    Change Password
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Leave these fields blank to keep your
                                    current password.
                                </p>
                            </div>
                            <FieldGroup>
                                <div className="grid gap-4 lg:grid-cols-3">
                                    <ProfilePasswordField
                                        id="adviser-current-password"
                                        label="Current Password"
                                        value={form.data.current_password}
                                        error={form.errors.current_password}
                                        autoComplete="current-password"
                                        onChange={(value) =>
                                            form.setData(
                                                'current_password',
                                                value,
                                            )
                                        }
                                    />
                                    <ProfilePasswordField
                                        id="adviser-new-password"
                                        label="New Password"
                                        value={form.data.password}
                                        error={form.errors.password}
                                        autoComplete="new-password"
                                        onChange={(value) =>
                                            form.setData('password', value)
                                        }
                                    />
                                    <ProfilePasswordField
                                        id="adviser-confirm-password"
                                        label="Confirm Password"
                                        value={form.data.password_confirmation}
                                        error={
                                            form.errors.password_confirmation
                                        }
                                        autoComplete="new-password"
                                        onChange={(value) =>
                                            form.setData(
                                                'password_confirmation',
                                                value,
                                            )
                                        }
                                    />
                                </div>
                            </FieldGroup>
                        </section>
                    </CardContent>

                    <CardFooter className="justify-end border-0 px-0 py-0">
                        <Button
                            type="submit"
                            disabled={!form.isDirty || form.processing}
                        >
                            <RiSave3Line />
                            {form.processing ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </CardFooter>
                </CardForm>
                <PaymentAccountSummary
                    accounts={settings.accounts}
                    title="Payout Account"
                    actionUrl={createAdviserPaymentAccount().url}
                    className="mt-4"
                    embedded
                />
            </Card>
        </div>
    );
}
