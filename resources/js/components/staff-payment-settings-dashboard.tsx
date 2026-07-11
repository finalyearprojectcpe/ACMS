import { Link, useForm } from '@inertiajs/react';
import {
    RiArrowDownSLine,
    RiBankCardLine,
    RiCheckboxCircleLine,
    RiErrorWarningLine,
    RiExternalLinkLine,
    RiSave3Line,
} from '@remixicon/react';
import type { FormEvent } from 'react';
import { useState } from 'react';

import { storeStaffPaymentAccount } from '@/actions/App/Http/Controllers/DashboardController';
import { CopyableValue } from '@/components/copyable-value';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
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
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Field,
    FieldError,
    FieldGroup,
    FieldLabel,
} from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import { cn } from '@/lib/utils';
import type {
    DashboardPaymentAccount,
    DashboardPaystackBankOption,
    DashboardStaffPaymentAccountForm,
    DashboardStaffPaymentSettings,
} from '@/types';

type PaymentAccountFormData = {
    label: string;
    business_name: string;
    bank_code: string;
    account_number: string;
    payment_account?: string;
};

export function StaffPaymentSettingsDashboard({
    settings,
}: {
    settings: DashboardStaffPaymentSettings;
}) {
    const hasAccounts = settings.accounts.length > 0;

    return (
        <div className="mx-auto flex w-full max-w-5xl flex-col gap-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div className="min-w-0">
                    <h1 className="text-xl font-semibold tracking-normal">
                        Paystack Settings
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Manage the payout account used when students pay dues.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Button variant="outline" asChild>
                        <a
                            href={settings.dashboardUrl}
                            target="_blank"
                            rel="noreferrer"
                        >
                            <RiExternalLinkLine />
                            Open Paystack
                        </a>
                    </Button>
                    <Button asChild>
                        <Link href={settings.createUrl}>
                            <RiBankCardLine />
                            {hasAccounts
                                ? 'Change Payout Account'
                                : 'Connect Payout Account'}
                        </Link>
                    </Button>
                </div>
            </div>

            <ConnectedAccounts accounts={settings.accounts} />
        </div>
    );
}

export function StaffPaymentAccountFormDashboard({
    form: accountForm,
}: {
    form: DashboardStaffPaymentAccountForm;
}) {
    const [confirmOpen, setConfirmOpen] = useState(false);
    const currentAccount = accountForm.accounts[0] ?? null;
    const form = useForm<PaymentAccountFormData>({
        label: currentAccount?.label ?? 'Student dues account',
        business_name: currentAccount?.businessName ?? 'GradeManager Dues',
        bank_code: '',
        account_number: '',
    });

    const bankOptionsLoaded = accountForm.bankOptions.length > 0;

    function requestSubmit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        setConfirmOpen(true);
    }

    function submitConfirmed() {
        form.submit(storeStaffPaymentAccount(), {
            preserveScroll: true,
        });
    }

    return (
        <div className="mx-auto flex w-full max-w-5xl flex-col gap-4">
            <div className="flex flex-col gap-2">
                <h1 className="text-xl font-semibold tracking-normal">
                    {accountForm.accounts.length > 0
                        ? 'Change Payout Account'
                        : 'Connect Payout Account'}
                </h1>
                <p className="max-w-2xl text-sm text-muted-foreground">
                    Enter the bank account that should receive every student due
                    you collect through Paystack.
                </p>
            </div>

            <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <Card className="border border-border bg-background shadow-sm ring-1 ring-border/70">
                    <CardForm onSubmit={requestSubmit}>
                        <CardHeader className="gap-2 border-b border-border">
                            <CardTitle className="flex items-center gap-2">
                                <RiBankCardLine className="size-4 text-muted-foreground" />
                                Payout Account Details
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="grid gap-5">
                            <Alert className="border-border bg-muted/30 text-foreground">
                                <RiCheckboxCircleLine className="size-4" />
                                <AlertTitle>
                                    What happens after you save
                                </AlertTitle>
                                <AlertDescription>
                                    All unpaid and future dues will use this
                                    account. Completed payments and receipts
                                    stay unchanged.
                                </AlertDescription>
                            </Alert>

                            <FieldGroup className="grid gap-5 sm:grid-cols-2">
                                <Field
                                    data-invalid={Boolean(form.errors.label)}
                                >
                                    <FieldLabel htmlFor="payment-account-label">
                                        Account Label
                                    </FieldLabel>
                                    <Input
                                        id="payment-account-label"
                                        value={form.data.label}
                                        onChange={(event) =>
                                            form.setData(
                                                'label',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Student dues account"
                                    />
                                    <FieldError>{form.errors.label}</FieldError>
                                </Field>

                                <Field
                                    data-invalid={Boolean(
                                        form.errors.business_name,
                                    )}
                                >
                                    <FieldLabel htmlFor="payment-business-name">
                                        Paystack Business Name
                                    </FieldLabel>
                                    <Input
                                        id="payment-business-name"
                                        value={form.data.business_name}
                                        onChange={(event) =>
                                            form.setData(
                                                'business_name',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="CPE Student Dues"
                                    />
                                    <FieldError>
                                        {form.errors.business_name}
                                    </FieldError>
                                </Field>
                            </FieldGroup>

                            {accountForm.bankOptionsError ? (
                                <Alert className="border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100">
                                    <RiErrorWarningLine className="size-4" />
                                    <AlertTitle>
                                        Bank list unavailable
                                    </AlertTitle>
                                    <AlertDescription>
                                        {accountForm.bankOptionsError}
                                    </AlertDescription>
                                </Alert>
                            ) : null}

                            <FieldGroup className="grid gap-5 sm:grid-cols-2">
                                <Field
                                    data-invalid={Boolean(
                                        form.errors.bank_code,
                                    )}
                                >
                                    <FieldLabel>Bank</FieldLabel>
                                    <BankPicker
                                        value={form.data.bank_code}
                                        options={accountForm.bankOptions}
                                        disabled={!bankOptionsLoaded}
                                        onValueChange={(value) =>
                                            form.setData('bank_code', value)
                                        }
                                    />
                                    <FieldError>
                                        {form.errors.bank_code}
                                    </FieldError>
                                </Field>

                                <Field
                                    data-invalid={Boolean(
                                        form.errors.account_number,
                                    )}
                                >
                                    <FieldLabel htmlFor="payment-account-number">
                                        Account Number
                                    </FieldLabel>
                                    <Input
                                        id="payment-account-number"
                                        inputMode="numeric"
                                        value={form.data.account_number}
                                        onChange={(event) =>
                                            form.setData(
                                                'account_number',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="0123456789"
                                    />
                                    <FieldError>
                                        {form.errors.account_number}
                                    </FieldError>
                                </Field>
                            </FieldGroup>

                            <FieldError>
                                {form.errors.payment_account}
                            </FieldError>
                        </CardContent>
                        <CardFooter className="justify-end bg-muted/20">
                            <Button
                                type="submit"
                                disabled={form.processing || !bankOptionsLoaded}
                            >
                                <RiSave3Line />
                                {form.processing ? 'Saving...' : 'Save Account'}
                            </Button>
                        </CardFooter>
                    </CardForm>
                </Card>

                <ConnectedAccounts
                    accounts={accountForm.accounts}
                    compact
                    title="Current Payout Account"
                />
            </div>

            <AlertDialog open={confirmOpen} onOpenChange={setConfirmOpen}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {currentAccount
                                ? 'Replace payout account?'
                                : 'Save payout account?'}
                        </AlertDialogTitle>
                        <AlertDialogDescription asChild>
                            <div className="grid gap-2">
                                <p>
                                    {currentAccount ? (
                                        <>
                                            You are replacing{' '}
                                            <strong className="font-semibold text-foreground">
                                                {currentAccount.label}
                                            </strong>
                                            , the account currently used for all
                                            your student dues.
                                        </>
                                    ) : (
                                        <>
                                            You are connecting the payout
                                            account used for all your student
                                            dues.
                                        </>
                                    )}
                                </p>
                                <p>
                                    Unpaid and future dues will use this
                                    account. Any checkout already open will be
                                    replaced; completed payments and receipts
                                    stay unchanged.
                                </p>
                            </div>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel>Cancel</AlertDialogCancel>
                        <AlertDialogAction onClick={submitConfirmed}>
                            Save Account
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </div>
    );
}

function ConnectedAccounts({
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
                <CopyableBankValue account={account} />
            </div>
        </div>
    );
}

function CopyableBankValue({ account }: { account: DashboardPaymentAccount }) {
    return (
        <div className="min-w-0">
            <CopyableValue
                value={bankInfoText(account)}
                className="h-9 w-full max-w-full bg-background"
                valueClassName="font-sans text-xs sm:text-sm"
                fullWidth
            />
        </div>
    );
}

function bankInfoText(account: DashboardPaymentAccount): string {
    return `${account.bankName} · ${account.accountName} · ${account.accountNumber}`;
}

function BankPicker({
    disabled,
    onValueChange,
    options,
    value,
}: {
    disabled: boolean;
    onValueChange: (value: string) => void;
    options: DashboardPaystackBankOption[];
    value: string;
}) {
    const [open, setOpen] = useState(false);
    const selectedBank = options.find((bank) => bank.code === value);

    function selectBank(nextValue: string) {
        onValueChange(nextValue);
        setOpen(false);
    }

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    role="combobox"
                    aria-expanded={open}
                    disabled={disabled}
                    className="w-full justify-between gap-3 px-3 text-left font-semibold"
                >
                    {selectedBank ? (
                        <span className="min-w-0 flex-1 truncate">
                            {selectedBank.name}
                        </span>
                    ) : (
                        <span className="min-w-0 flex-1 truncate text-muted-foreground">
                            {disabled ? 'Banks unavailable' : 'Select bank'}
                        </span>
                    )}
                    <RiArrowDownSLine className="size-4 shrink-0 text-muted-foreground" />
                </Button>
            </PopoverTrigger>
            <PopoverContent
                align="start"
                className="w-[min(28rem,calc(100vw-2rem))] p-0 sm:w-[var(--radix-popover-trigger-width)] sm:min-w-[22rem]"
            >
                <Command>
                    <CommandInput placeholder="Search banks..." />
                    <CommandList>
                        <CommandEmpty>No bank found.</CommandEmpty>
                        <CommandGroup>
                            {options.map((bank) => (
                                <CommandItem
                                    key={bank.code}
                                    value={`${bank.name} ${bank.code}`}
                                    data-checked={bank.code === value}
                                    onSelect={() => selectBank(bank.code)}
                                    className="px-3 py-2.5"
                                >
                                    <span className="flex min-w-0 flex-1 items-center justify-between gap-3 text-left">
                                        <span className="min-w-0 truncate font-medium text-foreground">
                                            {bank.name}
                                        </span>
                                        <span className="shrink-0 text-xs font-medium text-muted-foreground">
                                            {bank.code}
                                        </span>
                                    </span>
                                </CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </PopoverContent>
        </Popover>
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
