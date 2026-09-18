import type { ReactNode } from 'react';

import { TableCell, TableRow } from '@/components/ui/table';
import { cn } from '@/lib/utils';

export function TableEmptyRow({
    children,
    colSpan,
    className,
}: {
    children: ReactNode;
    colSpan: number;
    className?: string;
}) {
    return (
        <TableRow>
            <TableCell
                colSpan={colSpan}
                className={cn('px-4 py-6 text-muted-foreground', className)}
            >
                {children}
            </TableCell>
        </TableRow>
    );
}
