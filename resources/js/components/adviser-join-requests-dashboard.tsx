import { Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { ActionLink } from '@/components/action-link';
import { PersonCell } from '@/components/person-cell';
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
import { reject } from '@/routes/student-joins/adviser';
import type {
    DashboardJoinRequestDetail,
    DashboardJoinRequestRow,
} from '@/types';

export function JoinRequestsTable({
    requests,
}: {
    requests: DashboardJoinRequestRow[];
}) {
    return (
        <ScrollArea className="w-full">
            <Table className="min-w-[520px] text-sm">
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-12 text-center">S/N</TableHead>
                        <TableHead>Student</TableHead>
                        <TableHead>Set</TableHead>
                        <TableHead className="w-36">Requested</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {requests.length === 0 ? (
                        <TableRow>
                            <TableCell
                                colSpan={4}
                                className="py-10 text-center text-muted-foreground"
                            >
                                No student requests to review.
                            </TableCell>
                        </TableRow>
                    ) : (
                        requests.map((request, index) => (
                            <TableRow key={request.id}>
                                <TableCell className="text-center text-muted-foreground tabular-nums">
                                    {index + 1}
                                </TableCell>
                                <TableCell>
                                    <PersonCell
                                        name={request.name}
                                        secondary={request.registrationNumber}
                                        href={request.url}
                                    />
                                </TableCell>
                                <TableCell>{request.set}</TableCell>
                                <TableCell className="text-muted-foreground">
                                    {request.requestedAt}
                                </TableCell>
                            </TableRow>
                        ))
                    )}
                </TableBody>
            </Table>
            <ScrollBar orientation="horizontal" />
        </ScrollArea>
    );
}

export function DeclineJoinRequestDashboard({
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
            <ActionLink href={request.url}>Go to Students</ActionLink>
            <div>
                <h1 className="text-xl font-semibold">
                    Decline {request.name}'s request
                </h1>
                <p className="mt-1 text-sm text-muted-foreground">
                    {request.set} · {request.registrationNumber}
                </p>
            </div>
            <Field data-invalid={Boolean(form.errors.reason)}>
                <FieldLabel htmlFor="join-reason">
                    Reason for declining
                </FieldLabel>
                <Textarea
                    id="join-reason"
                    rows={4}
                    maxLength={500}
                    value={form.data.reason}
                    onChange={(event) =>
                        form.setData('reason', event.target.value)
                    }
                    required
                />
                <FieldError>{form.errors.reason}</FieldError>
            </Field>
            <div className="flex justify-end gap-3">
                <Button asChild variant="outline">
                    <Link href={request.url}>Cancel</Link>
                </Button>
                <Button type="submit" disabled={form.processing}>
                    Decline request
                </Button>
            </div>
        </form>
    );
}
