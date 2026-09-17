import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { TableCell, TableRow } from '@/components/ui/table';

export function DueMetric({
    label,
    value,
}: {
    label: string;
    value: string | number;
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
                <div className="text-2xl font-semibold tracking-normal tabular-nums">
                    {value}
                </div>
            </CardContent>
        </Card>
    );
}

export function DueStatusBadge({ status }: { status: string }) {
    const className =
        status === 'Published'
            ? 'border-green-200 bg-green-50 text-green-700 dark:border-green-400/30 dark:bg-green-400/10 dark:text-green-300'
            : status === 'Archived'
              ? 'border-border bg-muted text-muted-foreground'
              : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-300';

    return (
        <Badge variant="outline" className={className}>
            {status}
        </Badge>
    );
}

export function PaymentStatusBadge({ status }: { status: string }) {
    const className =
        status === 'Paid'
            ? 'border-green-200 bg-green-50 text-green-700 dark:border-green-400/30 dark:bg-green-400/10 dark:text-green-300'
            : status === 'Waived'
              ? 'border-border bg-muted text-muted-foreground'
              : 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-300';

    return (
        <Badge variant="outline" className={className}>
            {status}
        </Badge>
    );
}

export function DueEmptyRow({
    colSpan,
    children,
}: {
    colSpan: number;
    children: string;
}) {
    return (
        <TableRow>
            <TableCell
                colSpan={colSpan}
                className="px-4 py-8 text-muted-foreground"
            >
                {children}
            </TableCell>
        </TableRow>
    );
}
