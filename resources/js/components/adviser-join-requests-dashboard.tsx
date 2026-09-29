import { Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { ActionLink } from '@/components/action-link';
import { CopyableValue } from '@/components/copyable-value';
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
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Field, FieldError, FieldLabel } from '@/components/ui/field';
import { ScrollArea, ScrollBar } from '@/components/ui/scroll-area';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import { initialsFor } from '@/lib/person';
import { approve, index, reject } from '@/routes/student-joins/adviser';
import { create as rejectCreate } from '@/routes/student-joins/adviser/reject';
import type {
    DashboardJoinRequestDetail,
    DashboardJoinRequestList,
    DashboardJoinRequestRow,
} from '@/types';

export function JoinRequestsTable({
    requests,
    compact = false,
}: {
    requests: DashboardJoinRequestRow[];
    compact?: boolean;
}) {
    return (
        <ScrollArea className="w-full">
            <Table className="min-w-[620px] text-sm">
                <TableHeader>
                    <TableRow>
                        <TableHead>Student</TableHead>
                        <TableHead>Set</TableHead>
                        <TableHead className="w-36">Requested</TableHead>
                        {!compact ? (
                            <TableHead className="w-44">Review</TableHead>
                        ) : null}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {requests.length === 0 ? (
                        <TableRow>
                            <TableCell
                                colSpan={compact ? 3 : 4}
                                className="py-10 text-center text-muted-foreground"
                            >
                                No student requests in this view.
                            </TableCell>
                        </TableRow>
                    ) : (
                        requests.map((request) => (
                            <TableRow key={request.id}>
                                <TableCell>
                                    <PersonCell
                                        name={request.name}
                                        href={request.url}
                                    />
                                </TableCell>
                                <TableCell>{request.set}</TableCell>
                                <TableCell className="text-muted-foreground">
                                    {request.requestedAt}
                                    {compact && !request.emailVerified ? (
                                        <span className="block text-xs">
                                            Email unverified
                                        </span>
                                    ) : null}
                                </TableCell>
                                {!compact ? (
                                    <TableCell>
                                        {request.status === 'pending' ? (
                                            request.emailVerified ? (
                                                <span className="text-sm text-foreground">
                                                    Ready to review
                                                </span>
                                            ) : (
                                                <span className="text-sm text-muted-foreground">
                                                    Email unverified
                                                </span>
                                            )
                                        ) : (
                                            <Badge
                                                variant={
                                                    request.status ===
                                                    'approved'
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {request.status === 'approved'
                                                    ? 'Accepted'
                                                    : 'Returned'}
                                            </Badge>
                                        )}
                                    </TableCell>
                                ) : null}
                            </TableRow>
                        ))
                    )}
                </TableBody>
            </Table>
            <ScrollBar orientation="horizontal" />
        </ScrollArea>
    );
}

export function AdviserJoinRequestsDashboard({
    listing,
}: {
    listing: DashboardJoinRequestList;
}) {
    const page = listing.requests;
    const previous = page.links[0]?.url;
    const next = page.links[page.links.length - 1]?.url;

    return (
        <section className="mx-auto flex w-full max-w-5xl flex-col gap-5">
            <div className="flex flex-wrap items-end justify-between gap-4 border-b border-border pb-4">
                <div>
                    <h1 className="text-lg font-semibold">
                        Student Join Requests
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {page.total}{' '}
                        {listing.status === 'pending'
                            ? 'pending'
                            : 'reviewed'}
                    </p>
                </div>
                <div className="flex gap-4 text-sm font-medium">
                    <Link
                        href={index()}
                        aria-current={
                            listing.status === 'pending' ? 'page' : undefined
                        }
                        className={
                            listing.status === 'pending'
                                ? 'border-b-2 border-foreground pb-2'
                                : 'pb-2 text-muted-foreground hover:text-foreground'
                        }
                    >
                        Awaiting review
                    </Link>
                    <Link
                        href={index({ query: { status: 'reviewed' } })}
                        aria-current={
                            listing.status === 'reviewed' ? 'page' : undefined
                        }
                        className={
                            listing.status === 'reviewed'
                                ? 'border-b-2 border-foreground pb-2'
                                : 'pb-2 text-muted-foreground hover:text-foreground'
                        }
                    >
                        Reviewed
                    </Link>
                </div>
            </div>
            <JoinRequestsTable requests={page.data} />
            {page.last_page > 1 ? (
                <div className="flex items-center justify-between border-t border-border pt-4 text-sm">
                    <span className="text-muted-foreground">
                        Page {page.current_page} of {page.last_page}
                    </span>
                    <div className="flex gap-5">
                        {previous ? (
                            <ActionLink href={previous}>Previous</ActionLink>
                        ) : null}
                        {next ? (
                            <ActionLink href={next}>Next</ActionLink>
                        ) : null}
                    </div>
                </div>
            ) : null}
        </section>
    );
}

export function AdviserJoinRequestDashboard({
    request,
}: {
    request: DashboardJoinRequestDetail;
}) {
    if (request.rejecting) {
        return <ReturnRequestForm request={request} />;
    }

    return (
        <section className="mx-auto flex w-full max-w-4xl flex-col gap-6">
            <ActionLink href={index()}>All student requests</ActionLink>
            <div className="flex flex-col gap-5 border-b border-border pb-6 sm:flex-row sm:items-center">
                <Avatar className="size-24 shrink-0 rounded-md sm:size-28">
                    <AvatarFallback className="rounded-md text-2xl font-semibold">
                        {initialsFor(request.name)}
                    </AvatarFallback>
                </Avatar>
                <div className="min-w-0 flex-1">
                    <h1 className="text-xl font-semibold sm:text-2xl">
                        {request.name}
                    </h1>
                    <p className="mt-1 text-sm break-all text-muted-foreground">
                        {request.email}
                    </p>
                    <div className="mt-4 grid gap-x-8 gap-y-3 text-sm sm:grid-cols-2">
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Registration number
                            </p>
                            <CopyableValue
                                value={request.registrationNumber}
                                className="mt-1"
                            />
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Student set
                            </p>
                            <p className="mt-1 font-medium">{request.set}</p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Request received
                            </p>
                            <p className="mt-1">{request.requestedAt}</p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">
                                Email
                            </p>
                            <p className="mt-1">
                                {request.emailVerified
                                    ? 'Verified'
                                    : 'Not verified'}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            {request.conflict ? (
                <div className="border-l-2 border-destructive bg-destructive/5 px-4 py-3 text-sm text-destructive">
                    This registration number already belongs to an accepted
                    student. Ask the applicant to correct their request.
                </div>
            ) : null}
            {request.status === 'rejected' ? (
                <div className="border-l-2 border-destructive bg-destructive/5 px-4 py-3 text-sm">
                    <p className="font-medium text-destructive">
                        Returned for correction
                    </p>
                    <p className="mt-1">{request.reason}</p>
                    <p className="mt-2 text-xs text-muted-foreground">
                        {request.reviewer} · {request.reviewedAt}
                    </p>
                </div>
            ) : request.status === 'approved' ? (
                <div className="flex flex-wrap items-center gap-4 text-sm">
                    <Badge>Accepted</Badge>
                    {request.profileUrl ? (
                        <ActionLink href={request.profileUrl}>
                            View student profile
                        </ActionLink>
                    ) : null}
                </div>
            ) : !request.emailVerified ? (
                <p className="text-sm text-muted-foreground">
                    The applicant must verify their email before you can accept
                    this request.
                </p>
            ) : null}
            {request.status === 'pending' ? (
                <div className="flex flex-wrap justify-end gap-4 border-t border-border pt-5">
                    <Button asChild variant="outline">
                        <Link href={rejectCreate(request.id)}>
                            Return for correction
                        </Link>
                    </Button>
                    <AlertDialog>
                        <AlertDialogTrigger asChild>
                            <Button disabled={!request.canApprove}>
                                Accept Student
                            </Button>
                        </AlertDialogTrigger>
                        <AlertDialogContent>
                            <AlertDialogHeader>
                                <AlertDialogTitle>
                                    Accept {request.name}?
                                </AlertDialogTitle>
                                <AlertDialogDescription>
                                    {request.name} will join {request.set} under
                                    registration number{' '}
                                    {request.registrationNumber}. Their student
                                    dashboard and assigned dues will become
                                    available.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <AlertDialogFooter>
                                <AlertDialogCancel>
                                    Keep pending
                                </AlertDialogCancel>
                                <AlertDialogAction
                                    onClick={() =>
                                        router.patch(approve(request.id).url)
                                    }
                                >
                                    Accept Student
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </AlertDialogContent>
                    </AlertDialog>
                </div>
            ) : null}
        </section>
    );
}

function ReturnRequestForm({
    request,
}: {
    request: DashboardJoinRequestDetail;
}) {
    const form = useForm({ reason: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.submit(reject(request.id));
    }

    return (
        <form
            onSubmit={submit}
            className="mx-auto flex w-full max-w-2xl flex-col gap-6"
        >
            <ActionLink href={request.url}>Back to request</ActionLink>
            <div>
                <h1 className="text-xl font-semibold">
                    Return {request.name}'s request
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    {request.set} · {request.registrationNumber}
                </p>
            </div>
            <Field data-invalid={Boolean(form.errors.reason)}>
                <FieldLabel htmlFor="join-reason">
                    What should the applicant correct?
                </FieldLabel>
                <Textarea
                    id="join-reason"
                    rows={5}
                    maxLength={500}
                    value={form.data.reason}
                    onChange={(event) =>
                        form.setData('reason', event.target.value)
                    }
                    placeholder="For example, the registration number does not match our class list."
                    required
                />
                <FieldError>{form.errors.reason}</FieldError>
            </Field>
            <div className="flex justify-end gap-4 border-t border-border pt-5">
                <Button asChild variant="outline">
                    <Link href={request.url}>Cancel</Link>
                </Button>
                <Button type="submit" disabled={form.processing}>
                    Return Request
                </Button>
            </div>
        </form>
    );
}
