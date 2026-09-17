import { RiCheckboxCircleLine, RiErrorWarningLine } from '@remixicon/react';

import { CopyableValue } from '@/components/copyable-value';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { DashboardPaymentAccount } from '@/types';

export function PaymentAccountSummary({
    accounts,
    compact = false,
    title = 'Connected Payout Account',
}: {
    accounts: DashboardPaymentAccount[];
    compact?: boolean;
    title?: string;
}) {
    return (
        <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
            <CardHeader className="border-b border-border">
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent className="grid gap-3">
                {accounts.length > 0 ? (
                    accounts.map((account) => (
                        <PaymentAccountCard
                            key={account.id}
                            account={account}
                            compact={compact}
                        />
                    ))
                ) : (
                    <div className="border border-dashed border-border p-4 text-sm text-muted-foreground">
                        No payout account has been connected yet.
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

function PaymentAccountCard({
    account,
    compact,
}: {
    account: DashboardPaymentAccount;
    compact: boolean;
}) {
    return (
        <div
            className={cn(
                'grid gap-4 border border-border bg-muted/20 p-4',
                compact ? '' : 'sm:p-5',
            )}
        >
            <div
                className={cn(
                    'grid min-w-0 gap-3',
                    compact
                        ? ''
                        : 'sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start',
                )}
            >
                <div className="min-w-0">
                    <div className="truncate text-base font-semibold text-foreground">
                        {account.label}
                    </div>
                    <div className="mt-1 truncate text-sm text-muted-foreground">
                        {account.set}
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <PaymentAccountStatusBadge status={account.status} />
                    <VerificationBadge verifiedAt={account.verifiedAt} />
                </div>
            </div>

            <div
                className={cn(
                    'grid gap-3 text-sm',
                    compact ? '' : 'sm:grid-cols-3',
                )}
            >
                <AccountDetail label="Bank" value={account.bankName} />
                <AccountDetail
                    label="Account Name"
                    value={account.accountName}
                />
                <AccountDetail
                    label="Account Number"
                    value={account.accountNumber}
                />
            </div>

            <div
                className={cn(
                    'grid gap-2 border-t border-border pt-3',
                    compact
                        ? ''
                        : 'sm:grid-cols-[8rem_minmax(0,1fr)] sm:items-center',
                )}
            >
                <span className="text-xs font-medium text-muted-foreground">
                    Copy Bank Info
                </span>
                <CopyableValue
                    value={`${account.bankName} · ${account.accountName} · ${account.accountNumber}`}
                    className="h-9 w-full max-w-full bg-background"
                    valueClassName="font-sans text-xs sm:text-sm"
                    fullWidth
                />
            </div>
        </div>
    );
}

function AccountDetail({ label, value }: { label: string; value: string }) {
    return (
        <div className="grid gap-1">
            <span className="text-xs font-medium text-muted-foreground">
                {label}
            </span>
            <span className="truncate text-foreground">{value}</span>
        </div>
    );
}

function VerificationBadge({ verifiedAt }: { verifiedAt: string | null }) {
    const verified = verifiedAt !== null;

    return (
        <Badge
            variant="outline"
            className={cn(
                'gap-1.5',
                verified
                    ? 'border-green-200 bg-green-50 text-green-700 dark:border-green-400/30 dark:bg-green-400/10 dark:text-green-300'
                    : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-300',
            )}
        >
            {verified ? (
                <RiCheckboxCircleLine className="size-3.5 shrink-0" />
            ) : (
                <RiErrorWarningLine className="size-3.5 shrink-0" />
            )}
            <span className="truncate">
                {verified ? `Verified ${verifiedAt}` : 'Pending verification'}
            </span>
        </Badge>
    );
}

function PaymentAccountStatusBadge({ status }: { status: string }) {
    const className =
        status === 'Active'
            ? 'border-green-200 bg-green-50 text-green-700 dark:border-green-400/30 dark:bg-green-400/10 dark:text-green-300'
            : 'border-slate-200 bg-slate-50 text-slate-700 dark:border-slate-400/30 dark:bg-slate-400/10 dark:text-slate-300';

    return (
        <Badge variant="outline" className={className}>
            {status}
        </Badge>
    );
}
