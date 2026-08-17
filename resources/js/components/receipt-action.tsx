import { Link } from '@inertiajs/react';
import {
    RiCheckboxCircleLine,
    RiErrorWarningLine,
    RiMailLine,
} from '@remixicon/react';

import { DownloadLink } from '@/components/download-link';
import { Spinner } from '@/components/ui/spinner';

export type ReceiptActionData = {
    receiptStatus: string | null;
    receiptUrl: string | null;
    receiptRetryUrl: string | null;
    receiptEmailStatus: string | null;
    receiptEmailRetryUrl: string | null;
};

export function ReceiptAction({ receipt }: { receipt: ReceiptActionData }) {
    return (
        <div className="grid justify-items-end gap-1.5 text-xs">
            {receipt.receiptStatus === 'ready' && receipt.receiptUrl ? (
                <DownloadLink href={receipt.receiptUrl} className="text-xs">
                    Download receipt
                </DownloadLink>
            ) : receipt.receiptStatus === 'failed' &&
              receipt.receiptRetryUrl ? (
                <Link
                    href={receipt.receiptRetryUrl}
                    method="post"
                    as="button"
                    className="font-semibold text-link hover:underline"
                >
                    Retry receipt
                </Link>
            ) : (
                <span className="inline-flex items-center gap-1.5 text-muted-foreground">
                    <Spinner className="size-3.5" />
                    Preparing receipt
                </span>
            )}

            {['sent', 'delivered'].includes(
                receipt.receiptEmailStatus ?? '',
            ) ? (
                <span className="inline-flex items-center gap-1 text-muted-foreground">
                    <RiCheckboxCircleLine className="size-3.5 text-green-600 dark:text-green-400" />
                    Receipt emailed
                </span>
            ) : ['queued', 'processing'].includes(
                  receipt.receiptEmailStatus ?? '',
              ) ? (
                <span className="inline-flex items-center gap-1.5 text-muted-foreground">
                    <Spinner className="size-3.5" />
                    Sending email
                </span>
            ) : receipt.receiptEmailStatus === 'delayed' ? (
                <span className="inline-flex items-center gap-1 text-amber-700 dark:text-amber-400">
                    <RiErrorWarningLine className="size-3.5" />
                    Email delivery delayed
                </span>
            ) : ['bounced', 'suppressed', 'complained'].includes(
                  receipt.receiptEmailStatus ?? '',
              ) ? (
                <span className="inline-flex items-center gap-1 text-destructive">
                    <RiErrorWarningLine className="size-3.5" />
                    Email not delivered
                </span>
            ) : receipt.receiptEmailRetryUrl ? (
                <Link
                    href={receipt.receiptEmailRetryUrl}
                    method="post"
                    as="button"
                    className="inline-flex items-center gap-1 font-semibold text-link hover:underline"
                >
                    <RiMailLine className="size-3.5" />
                    Resend email
                </Link>
            ) : null}
        </div>
    );
}
