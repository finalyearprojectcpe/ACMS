import { Link } from '@inertiajs/react';
import {
    RiBankCardLine,
    RiCheckboxCircleLine,
    RiErrorWarningLine,
    RiFileCopyLine,
} from '@remixicon/react';
import { toast } from 'sonner';

import { ActionLink } from '@/components/action-link';
import { writeClipboard } from '@/components/copyable-value';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import type { DashboardPaymentAccount } from '@/types';

export function PaymentAccountSummary({
    accounts,
    actionUrl,
    compact = false,
    embedded = false,
    title = 'Connected Payout Account',
    className,
}: {
    accounts: DashboardPaymentAccount[];
    actionUrl?: string;
    compact?: boolean;
    embedded?: boolean;
    title?: string;
    className?: string;
}) {
    const Container = embedded ? 'section' : Card;

    return (
        <Container
            className={cn(
                embedded
                    ? 'min-w-0 border-t border-border'
                    : 'gap-0 border border-border bg-background py-0 shadow-sm',
                className,
            )}
        >
            <CardHeader
                className={cn(
                    'border-b border-border p-5',
                    embedded && 'border-b-0 px-0 pt-6 pb-2',
                )}
            >
                <CardTitle>{title}</CardTitle>
            </CardHeader>
            <CardContent
                className={cn('grid gap-4 p-5', embedded && 'px-0 pt-2 pb-4')}
            >
                {accounts.length > 0 ? (
                    <>
                        {accounts.map((account) => (
                            <PaymentAccountCard
                                key={account.id}
                                account={account}
                                compact={compact}
                            />
                        ))}
                        <div className="flex flex-wrap items-center justify-end gap-x-8 gap-y-3 pt-2">
                            <Button
                                type="button"
                                variant="link"
                                className="h-auto gap-1 px-0 py-0"
                                onClick={() =>
                                    void copyAccountDetails(accounts[0])
                                }
                            >
                                <RiFileCopyLine />
                                Copy Account Details
                            </Button>
                            {actionUrl ? (
                                <ActionLink href={actionUrl}>
                                    Change Account
                                </ActionLink>
                            ) : null}
                        </div>
                    </>
                ) : (
                    <div className="grid justify-items-start gap-4">
                        <p className="text-sm text-muted-foreground">
                            Connect the bank account that will receive student
                            dues payments.
                        </p>
                        {actionUrl ? (
                            <Button asChild>
                                <Link href={actionUrl}>
                                    <RiBankCardLine />
                                    Connect Payout Account
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                )}
            </CardContent>
        </Container>
    );
}

async function copyAccountDetails(account: DashboardPaymentAccount) {
    try {
        await writeClipboard(
            [account.accountNumber, account.bankName, account.accountName].join(
                '\n',
            ),
        );
        toast.success('Account details copied.');
    } catch {
        toast.error('Could not copy the account details.');
    }
}

function PaymentAccountCard({
    account,
    compact,
}: {
    account: DashboardPaymentAccount;
    compact: boolean;
}) {
    return (
        <div className={cn('grid gap-4', compact ? '' : 'py-1')}>
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
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    {account.status !== 'Active' ? (
                        <PaymentAccountStatusBadge status={account.status} />
                    ) : null}
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
