import {
    RiImageEditLine,
    RiResetLeftLine,
    RiSave3Line,
} from '@remixicon/react';
import { useEffect, useMemo, useRef, useState } from 'react';

import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { fullNameFor, initialsFor } from '@/lib/person';
import { cn } from '@/lib/utils';
import type { DashboardStudentProfile } from '@/types';

type StudentProfileDashboardProps = {
    profile: DashboardStudentProfile;
};

export function StudentProfileDashboard({
    profile,
}: StudentProfileDashboardProps) {
    const [avatarPreview, setAvatarPreview] = useState<string | null>(
        profile.avatar,
    );
    const [savedAvatar, setSavedAvatar] = useState<string | null>(
        profile.avatar,
    );
    const [savedName, setSavedName] = useState(profile.name);
    const [surname, setSurname] = useState(profile.name.surname);
    const [otherNames, setOtherNames] = useState(profile.name.otherNames);
    const objectUrls = useRef<string[]>([]);
    const draftFullName = useMemo(
        () => fullNameFor(surname, otherNames),
        [surname, otherNames],
    );
    const isNameDirty =
        surname !== savedName.surname || otherNames !== savedName.otherNames;
    const isAvatarDirty = avatarPreview !== savedAvatar;
    const hasProfileChanges = isNameDirty || isAvatarDirty;

    useEffect(() => {
        const objectUrlList = objectUrls.current;

        return () => {
            objectUrlList.forEach((url) => URL.revokeObjectURL(url));
        };
    }, []);

    function changeAvatar(file: File | undefined) {
        if (!file) {
            return;
        }

        const nextAvatar = URL.createObjectURL(file);

        objectUrls.current.push(nextAvatar);
        setAvatarPreview(nextAvatar);
    }

    function saveProfile() {
        setSavedName({
            full: draftFullName,
            surname,
            otherNames,
        });
        setSavedAvatar(avatarPreview);
    }

    function resetProfile() {
        setSurname(savedName.surname);
        setOtherNames(savedName.otherNames);
        setAvatarPreview(savedAvatar);
    }

    return (
        <div className="mx-auto grid w-full max-w-6xl gap-4 xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)]">
            <ProfileIdentityCard
                avatar={avatarPreview}
                name={draftFullName || savedName.full}
                email={profile.email}
                registrationNumber={profile.registrationNumber}
                onAvatarChange={changeAvatar}
            />

            <NameEditorCard
                surname={surname}
                otherNames={otherNames}
                onSurnameChange={setSurname}
                onOtherNamesChange={setOtherNames}
            />

            <AcademicSummaryCard profile={profile} />

            <div className="flex flex-col gap-2 sm:flex-row sm:justify-end xl:col-span-2">
                <Button
                    type="button"
                    variant="outline"
                    disabled={!hasProfileChanges}
                    onClick={resetProfile}
                >
                    <RiResetLeftLine />
                    Reset
                </Button>
                <Button
                    type="button"
                    disabled={!hasProfileChanges}
                    onClick={saveProfile}
                >
                    <RiSave3Line />
                    Save Profile
                </Button>
            </div>
        </div>
    );
}

function ProfileIdentityCard({
    avatar,
    name,
    email,
    registrationNumber,
    onAvatarChange,
}: {
    avatar: string | null;
    name: string;
    email: string;
    registrationNumber: string;
    onAvatarChange: (file: File | undefined) => void;
}) {
    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardContent className="flex flex-col gap-5 p-4 sm:flex-row sm:items-center">
                <Avatar className="size-20">
                    <AvatarImage
                        src={avatar ?? undefined}
                        alt={`${name} avatar`}
                    />
                    <AvatarFallback className="text-xl font-semibold">
                        {initialsFor(name)}
                    </AvatarFallback>
                </Avatar>

                <div className="min-w-0 flex-1">
                    <div className="truncate text-xl font-semibold tracking-normal">
                        {name}
                    </div>
                    <div className="mt-1 truncate text-sm text-muted-foreground">
                        {email}
                    </div>
                    <div className="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                        <span className="font-medium">Reg. No.</span>
                        <span className="font-medium tabular-nums">
                            {registrationNumber}
                        </span>
                    </div>
                </div>

                <div className="shrink-0">
                    <input
                        id="student-avatar"
                        type="file"
                        accept="image/*"
                        className="sr-only"
                        onChange={(event) =>
                            onAvatarChange(event.target.files?.[0])
                        }
                    />
                    <Button type="button" variant="outline" asChild>
                        <label htmlFor="student-avatar">
                            <RiImageEditLine />
                            Change picture
                        </label>
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}

function NameEditorCard({
    surname,
    otherNames,
    onSurnameChange,
    onOtherNamesChange,
}: {
    surname: string;
    otherNames: string;
    onSurnameChange: (value: string) => void;
    onOtherNamesChange: (value: string) => void;
}) {
    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardHeader className="border-b border-border">
                <CardTitle>Edit Name</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="student-surname">Surname</Label>
                        <Input
                            id="student-surname"
                            value={surname}
                            onChange={(event) =>
                                onSurnameChange(event.target.value)
                            }
                        />
                    </div>
                    <div className="space-y-2">
                        <Label htmlFor="student-other-names">Other Names</Label>
                        <Input
                            id="student-other-names"
                            value={otherNames}
                            onChange={(event) =>
                                onOtherNamesChange(event.target.value)
                            }
                        />
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

function AcademicSummaryCard({
    profile,
}: {
    profile: DashboardStudentProfile;
}) {
    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70 xl:col-span-2">
            <CardHeader className="border-b border-border">
                <CardTitle>Profile Summary</CardTitle>
            </CardHeader>
            <CardContent>
                <div className="grid gap-x-8 gap-y-4 md:grid-cols-[minmax(7rem,0.7fr)_minmax(18rem,1.4fr)] xl:grid-cols-[minmax(7rem,0.6fr)_minmax(22rem,1.8fr)_minmax(14rem,1fr)]">
                    <SummaryItem label="CGPA" value={profile.cgpa} />
                    <SummaryItem
                        label="Faculty"
                        value={facultyLabel(profile.faculty)}
                        className="md:col-span-1"
                    />
                    <SummaryItem
                        label="Department"
                        value={departmentLabel(profile.department)}
                    />
                    <SummaryItem
                        label="Current Level"
                        value={profile.currentLevel?.title ?? 'Unassigned'}
                    />
                    <SummaryItem
                        label="Course Advisor"
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
            <div className="mt-1 text-sm font-semibold">{value}</div>
        </div>
    );
}

function facultyLabel(faculty: DashboardStudentProfile['faculty']): string {
    if (faculty === null) {
        return 'Unassigned';
    }

    return `${faculty.abbreviation} - ${faculty.name}`;
}

function departmentLabel(
    department: DashboardStudentProfile['department'],
): string {
    if (department === null) {
        return 'Unassigned';
    }

    return `${department.abbreviation} - ${department.name}`;
}
