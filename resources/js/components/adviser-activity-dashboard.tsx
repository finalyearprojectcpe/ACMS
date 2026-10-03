import { router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { paymentHistory } from '@/actions/App/Http/Controllers/DashboardController';
import { ActionLink } from '@/components/action-link';
import { ActivityPaymentsTable } from '@/components/adviser-activity-tables';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { DashboardPageRows, DashboardPaymentHistory } from '@/types';

function PageFooter({ page }: { page: DashboardPageRows<unknown> }) {
    if (page.last_page <= 1) {
        return null;
    }

    const previous = page.links[0]?.url;
    const next = page.links[page.links.length - 1]?.url;

    return (
        <CardFooter className="flex items-center justify-between gap-3 border-t px-5 py-3 text-sm">
            <span className="text-muted-foreground">
                Page {page.current_page} of {page.last_page}
            </span>
            <div className="flex items-center gap-4">
                {previous ? (
                    <ActionLink href={previous}>Previous</ActionLink>
                ) : (
                    <span className="text-muted-foreground/50">Previous</span>
                )}
                {next ? (
                    <ActionLink href={next}>Next</ActionLink>
                ) : (
                    <span className="text-muted-foreground/50">Next</span>
                )}
            </div>
        </CardFooter>
    );
}

export function AdviserPaymentHistoryDashboard({
    history,
}: {
    history: DashboardPaymentHistory;
}) {
    const [from, setFrom] = useState(history.filters.from ?? '');
    const [to, setTo] = useState(history.filters.to ?? '');
    const set = history.filters.set;
    function submit(event: FormEvent) {
        event.preventDefault();
        router.get(
            paymentHistory({
                query: {
                    ...(set ? { set } : {}),
                    ...(from ? { from } : {}),
                    ...(to ? { to } : {}),
                },
            }).url,
        );
    }

    return (
        <Card className="mx-auto w-full max-w-6xl gap-0 overflow-hidden border-border bg-background py-0 shadow-sm">
            <CardHeader className="flex flex-col gap-4 border-b p-4 sm:px-5">
                <div className="flex items-start justify-between gap-4">
                    <div>
                        <CardTitle className="text-base">
                            Payment History
                        </CardTitle>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {history.payments.total} completed payments
                        </p>
                    </div>
                    <ActionLink href={paymentHistory()}>
                        Clear filters
                    </ActionLink>
                </div>
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <div className="sm:w-64">
                        <label className="mb-1 block text-xs text-muted-foreground">
                            Student set
                        </label>
                        <Select
                            value={set ? String(set) : 'all'}
                            onValueChange={(value) =>
                                router.get(
                                    paymentHistory({
                                        query: {
                                            ...(value !== 'all'
                                                ? { set: value }
                                                : {}),
                                            ...(from ? { from } : {}),
                                            ...(to ? { to } : {}),
                                        },
                                    }).url,
                                )
                            }
                        >
                            <SelectTrigger aria-label="Student set">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">All sets</SelectItem>
                                {history.sets.map((item) => (
                                    <SelectItem
                                        key={item.id}
                                        value={String(item.id)}
                                    >
                                        {item.name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <form
                        onSubmit={submit}
                        className="flex flex-wrap items-end gap-2"
                    >
                        <div>
                            <label
                                htmlFor="payment-from"
                                className="mb-1 block text-xs text-muted-foreground"
                            >
                                From
                            </label>
                            <Input
                                id="payment-from"
                                type="date"
                                value={from}
                                onChange={(event) =>
                                    setFrom(event.target.value)
                                }
                                className="w-40"
                            />
                        </div>
                        <div>
                            <label
                                htmlFor="payment-to"
                                className="mb-1 block text-xs text-muted-foreground"
                            >
                                To
                            </label>
                            <Input
                                id="payment-to"
                                type="date"
                                value={to}
                                min={from || undefined}
                                onChange={(event) => setTo(event.target.value)}
                                className="w-40"
                            />
                        </div>
                        <Button variant="outline" type="submit">
                            Apply
                        </Button>
                    </form>
                </div>
            </CardHeader>
            <CardContent className="p-0">
                <ActivityPaymentsTable
                    payments={history.payments.data}
                    showSet={!set}
                    startNumber={history.payments.from ?? 1}
                />
            </CardContent>
            <PageFooter page={history.payments} />
        </Card>
    );
}
