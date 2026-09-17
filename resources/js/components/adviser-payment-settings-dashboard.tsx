import { Link } from '@inertiajs/react';
import { RiBankCardLine, RiExternalLinkLine } from '@remixicon/react';

import { PaymentAccountSummary } from '@/components/payment-account-summary';
import { Button } from '@/components/ui/button';
import type { DashboardAdviserPaymentSettings } from '@/types';

export function AdviserPaymentSettingsDashboard({
    settings,
}: {
    settings: DashboardAdviserPaymentSettings;
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

            <PaymentAccountSummary accounts={settings.accounts} />
        </div>
    );
}
