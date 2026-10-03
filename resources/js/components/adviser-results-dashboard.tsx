import { router, useForm } from '@inertiajs/react';
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
import { Textarea } from '@/components/ui/textarea';
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
    const [publishing, setPublishing] = useState(false);
    const form = useForm<{
        grades: Record<string, string | null>;
        reason: string;
    }>({
        grades: Object.fromEntries(
            roster.students.map((student) => [
                student.itemId,
                student.missingScript ? 'missing_script' : student.grade,
            ]),
        ),
        reason: '',
    });
    const changed = roster.students.some(
        (student) =>
            form.data.grades[student.itemId] !==
            (student.missingScript ? 'missing_script' : student.grade),
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
                    <ActionLink href={roster.semesterResultsUrl}>
                        Go to Semester Results
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
                        {roster.publishedAt ? <Badge>Published</Badge> : null}
                    </div>
                </div>
            </header>
            {roster.students.length === 0 ? (
                <div className="mx-auto w-full max-w-3xl">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-12 text-center">
                                    S/N
                                </TableHead>
                                <TableHead>Student</TableHead>
                                <TableHead>Registration number</TableHead>
                                <TableHead className="text-right">
                                    Grade
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow>
                                <TableCell
                                    colSpan={4}
                                    className="py-8 text-center text-muted-foreground"
                                >
                                    No approved students for this course.
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            ) : (
                <>
                    <ScrollArea scrollRegion className="h-0 min-h-0 flex-1">
                        <div className="mx-auto w-full max-w-3xl min-w-[640px]">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12 text-center">
                                            S/N
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
                                            <TableCell className="text-center text-muted-foreground tabular-nums">
                                                {index + 1}
                                            </TableCell>
                                            <TableCell>
                                                <PersonCell
                                                    name={student.name}
                                                    avatar={student.avatar}
                                                    secondary={
                                                        student.assignmentOrigin ===
                                                        'carryover'
                                                            ? 'Carryover'
                                                            : student.assignmentOrigin ===
                                                                'deferred'
                                                              ? 'Deferred course'
                                                              : undefined
                                                    }
                                                    href={
                                                        student.profileUrl ??
                                                        undefined
                                                    }
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
                                                <div className="ml-auto w-36">
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
                                                            {!roster.publishedAt ? (
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
                                                            <SelectItem value="missing_script">
                                                                Missing script
                                                            </SelectItem>
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
                                                                        <span className="block">
                                                                            {
                                                                                change.reason
                                                                            }
                                                                        </span>
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
                    {roster.publishedAt && changed ? (
                        <div className="mx-auto w-full max-w-3xl px-4 py-4 sm:px-6">
                            <label
                                htmlFor="correction-reason"
                                className="mb-2 block text-sm font-medium"
                            >
                                Reason for correction
                            </label>
                            <Textarea
                                id="correction-reason"
                                value={form.data.reason}
                                onChange={(event) =>
                                    form.setData('reason', event.target.value)
                                }
                                rows={2}
                            />
                            {form.errors.reason ? (
                                <p className="mt-1 text-sm text-destructive">
                                    {form.errors.reason}
                                </p>
                            ) : null}
                        </div>
                    ) : null}
                    <footer className="flex shrink-0 flex-wrap items-center justify-between gap-3 border-t px-4 py-4 sm:px-6">
                        {!roster.publishedAt && roster.publication.reason ? (
                            <p className="text-sm text-muted-foreground">
                                {roster.publication.reason}
                            </p>
                        ) : null}
                        <div className="ml-auto flex w-full max-w-3xl justify-end gap-3">
                            {roster.publishedAt ? (
                                <AlertDialog
                                    open={confirmOpen}
                                    onOpenChange={setConfirmOpen}
                                >
                                    <AlertDialogTrigger asChild>
                                        <Button
                                            disabled={
                                                !changed ||
                                                !form.data.reason.trim() ||
                                                form.processing
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
                                                update their results and any
                                                affected GPA. Each correction
                                                will be recorded.
                                            </AlertDialogDescription>
                                        </AlertDialogHeader>
                                        <AlertDialogFooter>
                                            <AlertDialogCancel>
                                                Keep current grades
                                            </AlertDialogCancel>
                                            <AlertDialogAction
                                                disabled={
                                                    !form.data.reason.trim() ||
                                                    form.processing
                                                }
                                                onClick={save}
                                            >
                                                Save corrections
                                            </AlertDialogAction>
                                        </AlertDialogFooter>
                                    </AlertDialogContent>
                                </AlertDialog>
                            ) : (
                                <>
                                    <Button
                                        variant="outline"
                                        disabled={!changed || form.processing}
                                        onClick={save}
                                    >
                                        Save results
                                    </Button>
                                    <AlertDialog>
                                        <AlertDialogTrigger asChild>
                                            <Button
                                                disabled={
                                                    !roster.publication
                                                        .canPublish ||
                                                    changed ||
                                                    publishing
                                                }
                                            >
                                                Publish {roster.course.code}{' '}
                                                results
                                            </Button>
                                        </AlertDialogTrigger>
                                        <AlertDialogContent>
                                            <AlertDialogHeader>
                                                <AlertDialogTitle>
                                                    Publish {roster.course.code}{' '}
                                                    results?
                                                </AlertDialogTitle>
                                                <AlertDialogDescription>
                                                    The {roster.students.length}{' '}
                                                    students registered for{' '}
                                                    {roster.course.code} in{' '}
                                                    {roster.set.name} will see
                                                    their grades. Other subjects
                                                    in {roster.period.semester}{' '}
                                                    remain unpublished.
                                                </AlertDialogDescription>
                                            </AlertDialogHeader>
                                            <AlertDialogFooter>
                                                <AlertDialogCancel>
                                                    Keep unpublished
                                                </AlertDialogCancel>
                                                <AlertDialogAction
                                                    disabled={publishing}
                                                    onClick={() => {
                                                        setPublishing(true);
                                                        router.post(
                                                            roster.publishUrl,
                                                            {},
                                                            {
                                                                onFinish: () =>
                                                                    setPublishing(
                                                                        false,
                                                                    ),
                                                            },
                                                        );
                                                    }}
                                                >
                                                    Publish subject results
                                                </AlertDialogAction>
                                            </AlertDialogFooter>
                                        </AlertDialogContent>
                                    </AlertDialog>
                                </>
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
