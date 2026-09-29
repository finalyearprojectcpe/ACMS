import { useForm } from '@inertiajs/react';
import { RiSave3Line } from '@remixicon/react';
import type { FormEvent } from 'react';

import { updateStudentProfile } from '@/actions/App/Http/Controllers/AdviserAccountController';
import { EditableProfilePhoto } from '@/components/editable-profile-photo';
import { ProfilePasswordField } from '@/components/profile-password-field';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardForm } from '@/components/ui/card';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { useProfilePhotoInput } from '@/hooks/use-profile-photo-input';
import { fullNameFor } from '@/lib/person';
import type { DashboardStudentProfile } from '@/types';

type StudentProfileForm = {
    surname: string;
    other_names: string;
    profile_photo: File | null;
    current_password: string;
    password: string;
    password_confirmation: string;
};

export function StudentProfileDashboard({
    profile,
}: {
    profile: DashboardStudentProfile;
}) {
    const photo = useProfilePhotoInput(profile.avatar);
    const form = useForm<StudentProfileForm>({
        surname: profile.name.surname,
        other_names: profile.name.otherNames,
        profile_photo: null,
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    const fullName = fullNameFor(form.data.surname, form.data.other_names);

    function selectPhoto(file: File | undefined) {
        photo.select(file);
        form.setData('profile_photo', file ?? null);
    }

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform((data) => ({ ...data, _method: 'patch' }));
        form.post(updateStudentProfile.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                photo.reset();
                form.reset(
                    'profile_photo',
                    'current_password',
                    'password',
                    'password_confirmation',
                );
            },
        });
    }

    return (
        <div className="flex w-full flex-1 flex-col">
            <Card className="flex-1 border-0 bg-transparent py-0 shadow-none ring-0">
                <CardForm onSubmit={submit} className="flex-1">
                    <CardContent className="mx-auto w-full max-w-3xl space-y-8 px-5 py-6 sm:px-6">
                        <section className="space-y-5">
                            <h1 className="text-base font-semibold tracking-normal">
                                Personal Details
                            </h1>
                            <div className="grid gap-5 sm:grid-cols-[auto_minmax(0,1fr)] sm:items-start">
                                <EditableProfilePhoto
                                    avatar={photo.preview}
                                    name={fullName || profile.name.full}
                                    inputId="student-profile-photo"
                                    inputRef={photo.inputRef}
                                    onPhotoChange={selectPhoto}
                                    avatarClassName="size-24"
                                />

                                <div className="grid gap-4 sm:grid-cols-2">
                                    <Field
                                        data-invalid={Boolean(
                                            form.errors.surname,
                                        )}
                                    >
                                        <FieldLabel htmlFor="student-surname">
                                            Surname
                                        </FieldLabel>
                                        <Input
                                            id="student-surname"
                                            value={form.data.surname}
                                            autoComplete="family-name"
                                            aria-invalid={Boolean(
                                                form.errors.surname,
                                            )}
                                            onChange={(event) =>
                                                form.setData(
                                                    'surname',
                                                    event.target.value,
                                                )
                                            }
                                            required
                                        />
                                        <FieldError>
                                            {form.errors.surname}
                                        </FieldError>
                                    </Field>
                                    <Field
                                        data-invalid={Boolean(
                                            form.errors.other_names,
                                        )}
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
                            </div>
                            <FieldError>{form.errors.profile_photo}</FieldError>
                        </section>

                        <section className="space-y-5 border-t border-border pt-7">
                            <h2 className="text-base font-semibold tracking-normal">
                                Change Password
                            </h2>
                            <FieldGroup className="gap-5">
                                <ProfilePasswordField
                                    id="student-current-password"
                                    label="Current Password"
                                    value={form.data.current_password}
                                    error={form.errors.current_password}
                                    autoComplete="current-password"
                                    onChange={(value) =>
                                        form.setData('current_password', value)
                                    }
                                />
                                <div className="grid gap-5 sm:grid-cols-2">
                                    <ProfilePasswordField
                                        id="student-new-password"
                                        label="New Password"
                                        value={form.data.password}
                                        error={form.errors.password}
                                        autoComplete="new-password"
                                        onChange={(value) =>
                                            form.setData('password', value)
                                        }
                                    />
                                    <ProfilePasswordField
                                        id="student-confirm-password"
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

                    <CardFooter className="mt-auto border-0 bg-transparent px-0 pb-0">
                        <div className="mx-auto flex w-full max-w-3xl justify-end px-5 sm:px-6">
                            <Button type="submit" disabled={form.processing}>
                                <RiSave3Line />
                                {form.processing ? 'Saving...' : 'Save Changes'}
                            </Button>
                        </div>
                    </CardFooter>
                </CardForm>
            </Card>
        </div>
    );
}
