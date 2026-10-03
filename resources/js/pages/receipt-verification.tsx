import { Head } from '@inertiajs/react';
import {
    RiCheckboxCircleFill,
    RiCloseCircleFill,
    RiErrorWarningFill,
} from '@remixicon/react';

import { CopyableValue } from '@/components/copyable-value';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

type ReceiptVerification =
    | { status: 'invalid' }
    | {
          status: 'valid' | 'reversed';
          receiptNumber: string;
          transactionId: string;
          courseAdviser: string;
          due: string;
          amount: string;
          paidAt: string | null;
          registrationNumber: string;
      };

export default function ReceiptVerificationPage({
    receipt,
}: {
    receipt: ReceiptVerification;
}) {
    const isInvalid = receipt.status === 'invalid';
    const isReversed = receipt.status === 'reversed';

    return (
        <>
            <Head title="Verify Payment Receipt" />
            <main className="background-graph grid min-h-screen place-items-center bg-background p-4 text-foreground sm:p-8">
                <Card className="w-full max-w-xl border border-border bg-background shadow-sm ring-1 ring-border/70">
                    <CardHeader className="border-b border-border">
                        <div className="flex items-center gap-3">
                            <span className="grid size-11 shrink-0 place-items-center bg-background ring-1 ring-border dark:bg-foreground">
                                <img
                                    src="/logo.png"
                                    alt="ACMS"
                                    className="size-8 object-contain"
                                />
                            </span>
                            <div className="min-w-0">
                                <CardTitle>
                                    Payment Receipt Verification
                                </CardTitle>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    ACMS payment record
                                </p>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="grid gap-5 p-5 sm:p-6">
                        <div
                            className={
                                isInvalid || isReversed
                                    ? 'flex items-start gap-3 border-l-4 border-red-600 bg-red-50 p-4 text-red-900 dark:bg-red-500/10 dark:text-red-200'
                                    : 'flex items-start gap-3 border-l-4 border-green-600 bg-green-50 p-4 text-green-900 dark:bg-green-500/10 dark:text-green-200'
                            }
                        >
                            {isInvalid ? (
                                <RiCloseCircleFill className="mt-0.5 size-5 shrink-0" />
                            ) : isReversed ? (
                                <RiErrorWarningFill className="mt-0.5 size-5 shrink-0" />
                            ) : (
                                <RiCheckboxCircleFill className="mt-0.5 size-5 shrink-0" />
                            )}
                            <div>
                                <p className="font-semibold">
                                    {isInvalid
                                        ? 'This receipt could not be verified'
                                        : isReversed
                                          ? 'This payment was reversed'
                                          : 'This payment is valid'}
                                </p>
                                <p className="mt-1 text-sm opacity-80">
                                    {isInvalid
                                        ? 'The verification link is invalid or has been changed.'
                                        : isReversed
                                          ? 'The transaction was previously confirmed but has since been reversed.'
                                          : 'The details below match a confirmed Paystack transaction.'}
                                </p>
                            </div>
                        </div>

                        {!isInvalid ? (
                            <dl className="grid gap-4 text-sm sm:grid-cols-2">
                                <ReceiptDetail
                                    label="Course Adviser"
                                    value={receipt.courseAdviser}
                                />
                                <ReceiptDetail
                                    label="Amount Paid"
                                    value={receipt.amount}
                                    emphasis
                                />
                                <ReceiptDetail
                                    label="Payment For"
                                    value={receipt.due}
                                />
                                <ReceiptDetail
                                    label="Paid On"
                                    value={receipt.paidAt ?? 'Not recorded'}
                                />
                                <ReceiptDetail
                                    label="Student Reg. No."
                                    value={receipt.registrationNumber}
                                />
                                <div className="grid gap-1.5">
                                    <dt className="text-xs text-muted-foreground">
                                        Receipt Number
                                    </dt>
                                    <dd>
                                        <CopyableValue
                                            value={receipt.receiptNumber}
                                            className="h-9 w-fit max-w-full"
                                        />
                                    </dd>
                                </div>
                                <div className="grid gap-1.5 sm:col-span-2">
                                    <dt className="text-xs text-muted-foreground">
                                        Paystack Transaction ID
                                    </dt>
                                    <dd>
                                        <CopyableValue
                                            value={receipt.transactionId}
                                            className="h-9 w-fit max-w-full"
                                        />
                                    </dd>
                                </div>
                            </dl>
                        ) : null}
                    </CardContent>
                </Card>
            </main>
        </>
    );
}

function ReceiptDetail({
    label,
    value,
    emphasis = false,
}: {
    label: string;
    value: string;
    emphasis?: boolean;
}) {
    return (
        <div className="grid gap-1">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd
                className={
                    emphasis
                        ? 'text-lg font-semibold tabular-nums'
                        : 'font-medium'
                }
            >
                {value}
            </dd>
        </div>
    );
}
