import { useForm } from '@inertiajs/react';
import { RiArrowLeftLine } from '@remixicon/react';
import { useState } from 'react';

import { ActionLink } from '@/components/action-link';
import { CopyableValue } from '@/components/copyable-value';
import { GradeLetter } from '@/components/grade-letter';
import { PersonCell } from '@/components/person-cell';
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
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyTitle,
} from '@/components/ui/empty';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { DashboardGradeRoster } from '@/types';

const gradeOptions = ['A', 'B', 'C', 'D', 'E', 'F'];

export function AdviserGradeRoster({
    roster,
}: {
    roster: DashboardGradeRoster;
}) {
    const [confirmOpen, setConfirmOpen] = useState(false);
    const form = useForm<{ grades: Record<string, string | null> }>({
        grades: Object.fromEntries(
            roster.students.map((student) => [student.itemId, student.grade]),
        ),
    });
    const changed = roster.students.some(
        (student) => form.data.grades[student.itemId] !== student.grade,
    );
    const save = () =>
        form.patch(roster.saveUrl, {
            preserveScroll: true,
            onSuccess: () => setConfirmOpen(false),
        });

    return (
        <div className="flex min-h-0 w-full flex-1 flex-col">
            <header className="shrink-0 border-b px-4 py-5 sm:px-6">
                <div className="mx-auto w-full max-w-3xl">
                    <ActionLink
                        href={roster.backUrl}
                        icon={<RiArrowLeftLine className="size-4" />}
                    >
                        Back to semester results
                    </ActionLink>
                    <div className="mt-4 flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h1 className="text-xl font-semibold">
                                {roster.course.code} · {roster.course.title}
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {roster.period.level} · {roster.period.session}{' '}
                                Session · {roster.period.semester} ·{' '}
                                {roster.course.units} units
                            </p>
                        </div>
                        {roster.period.publishedAt ? (
                            <Badge>Published</Badge>
                        ) : null}
                    </div>
                </div>
            </header>
            {roster.students.length === 0 ? (
                <Empty className="flex-1">
                    <EmptyHeader>
                        <EmptyTitle>
                            No approved students for this course
                        </EmptyTitle>
                        <EmptyDescription>
                            Approve course registrations before entering grades.
                        </EmptyDescription>
                    </EmptyHeader>
                </Empty>
            ) : (
                <>
                    <ScrollArea scrollRegion className="h-0 min-h-0 flex-1">
                        <div className="mx-auto w-full max-w-3xl min-w-[640px]">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12 pl-4 sm:pl-6">
                                            #
                                        </TableHead>
                                        <TableHead>Student</TableHead>
                                        <TableHead>
                                            Registration number
                                        </TableHead>
                                        <TableHead className="pr-4 text-right sm:pr-6">
                                            Grade
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {roster.students.map((student, index) => (
                                        <TableRow key={student.itemId}>
                                            <TableCell className="pl-4 text-muted-foreground tabular-nums sm:pl-6">
                                                {index + 1}
                                            </TableCell>
                                            <TableCell>
                                                <PersonCell
                                                    name={student.name}
                                                    avatar={student.avatar}
                                                    href={student.profileUrl}
                                                />
                                            </TableCell>
                                            <TableCell>
                                                <CopyableValue
                                                    value={
                                                        student.registrationNumber
                                                    }
                                                />
                                            </TableCell>
                                            <TableCell className="pr-4 sm:pr-6">
                                                <div className="ml-auto w-24">
                                                    <Select
                                                        value={
                                                            form.data.grades[
                                                                student.itemId
                                                            ] ?? 'none'
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            form.setData(
                                                                'grades',
                                                                {
                                                                    ...form.data
                                                                        .grades,
                                                                    [student.itemId]:
                                                                        value ===
                                                                        'none'
                                                                            ? null
                                                                            : value,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger
                                                            aria-label={`Grade for ${student.name}`}
                                                            className="w-full"
                                                        >
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {!roster.period
                                                                .publishedAt ? (
                                                                <SelectItem value="none">
                                                                    —
                                                                </SelectItem>
                                                            ) : null}
                                                            {gradeOptions.map(
                                                                (grade) => (
                                                                    <SelectItem
                                                                        key={
                                                                            grade
                                                                        }
                                                                        value={
                                                                            grade
                                                                        }
                                                                    >
                                                                        <GradeLetter
                                                                            grade={
                                                                                grade
                                                                            }
                                                                        />
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                {student.corrections.length >
                                                0 ? (
                                                    <Tooltip>
                                                        <TooltipTrigger className="block w-full pt-1 text-right text-xs text-link underline-offset-2 hover:underline">
                                                            Corrected
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            {student.corrections.map(
                                                                (
                                                                    change,
                                                                    index,
                                                                ) => (
                                                                    <div
                                                                        key={
                                                                            index
                                                                        }
                                                                    >
                                                                        {
                                                                            change.from
                                                                        }{' '}
                                                                        →{' '}
                                                                        {
                                                                            change.to
                                                                        }{' '}
                                                                        ·{' '}
                                                                        {new Date(
                                                                            change.at,
                                                                        ).toLocaleDateString()}
                                                                    </div>
                                                                ),
                                                            )}
                                                        </TooltipContent>
                                                    </Tooltip>
                                                ) : null}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                        <ScrollBar orientation="horizontal" />
                    </ScrollArea>
                    <footer className="flex shrink-0 flex-wrap items-center justify-end gap-3 border-t px-4 py-4 sm:px-6">
                        <div className="mx-auto flex w-full max-w-3xl justify-end">
                            {roster.period.publishedAt ? (
                                <AlertDialog
                                    open={confirmOpen}
                                    onOpenChange={setConfirmOpen}
                                >
                                    <AlertDialogTrigger asChild>
                                        <Button
                                            disabled={
                                                !changed || form.processing
                                            }
                                        >
                                            Save corrections
                                        </Button>
                                    </AlertDialogTrigger>
                                    <AlertDialogContent>
                                        <AlertDialogHeader>
                                            <AlertDialogTitle>
                                                Correct published grades?
                                            </AlertDialogTitle>
                                            <AlertDialogDescription>
                                                Students can already see these
                                                results. Your changes will
                                                update their grades and GPA, and
                                                each correction will be
                                                recorded.
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel>
                                                Keep current grades
                                            </AlertDialogCancel>
                                            <AlertDialogAction
                                                disabled={form.processing}
                                                onClick={save}
                                            >
                                                Save corrections
                                            </AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>
                            ) : (
                                <Button
                                    disabled={!changed || form.processing}
                                    onClick={save}
                                >
                                    Save grades
                                </Button>
                            )}
                        </div>
                    </footer>
                    {form.errors.grades ? (
                        <p className="px-6 pb-3 text-sm text-destructive">
                            {form.errors.grades}
                        </p>
                    ) : null}
                </>
            )}
        </div>
    );
}
