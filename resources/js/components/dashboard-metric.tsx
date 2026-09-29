import type { ReactNode } from 'react';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export function DashboardMetric({
    label,
    value,
    detail,
    valueClassName,
    truncateDetail = false,
}: {
    label: string;
    value: ReactNode;
    detail?: ReactNode;
    valueClassName?: string;
    truncateDetail?: boolean;
}) {
    return (
        <Card
            size="sm"
            className="border border-border bg-background shadow-sm ring-1 ring-border/70"
        >
            <CardHeader>
                <CardTitle className="text-xs text-muted-foreground">
                    {label}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div
                    className={cn(
                        'font-semibold tracking-normal tabular-nums',
                        valueClassName ?? 'text-3xl',
                    )}
                >
                    {value}
                </div>
                {detail ? (
                    <div
                        className={cn(
                            'mt-1 text-xs text-muted-foreground',
                            truncateDetail && 'truncate',
                        )}
                    >
                        {detail}
                    </div>
                ) : null}
            </CardContent>
        </Card>
    );
}
