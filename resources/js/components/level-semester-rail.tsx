import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

const levelSemesterRailItemBaseClassName =
    'relative flex min-h-11 w-full items-center justify-start! rounded-sm border border-transparent bg-transparent px-3 py-2.5 text-left text-base font-medium text-muted-foreground transition-colors before:absolute before:top-1/2 before:-left-[1.0625rem] before:h-px before:w-3 before:bg-border hover:bg-muted/70 hover:text-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none';

export function LevelSemesterRail({ children }: { children: ReactNode }) {
    return <div className="grid w-full gap-7 p-3">{children}</div>;
}

export function LevelSemesterRailGroup({
    label,
    children,
}: {
    label: ReactNode;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2.5">
            <div className="px-1.5 text-base font-semibold text-foreground">
                {label}
            </div>
            <div className="relative ml-3 grid gap-1.5 border-l border-border/80 pl-4">
                {children}
            </div>
        </div>
    );
}

export function levelSemesterRailItemClassName(isActive: boolean): string {
    return cn(
        levelSemesterRailItemBaseClassName,
        isActive &&
            'bg-primary text-primary-foreground shadow-sm before:bg-primary hover:bg-primary hover:text-primary-foreground',
    );
}

export const levelSemesterRailTabItemClassName = cn(
    levelSemesterRailItemBaseClassName,
    'data-active:bg-primary! data-active:text-primary-foreground! data-active:shadow-sm! data-active:before:bg-primary! data-active:after:opacity-0! data-active:hover:bg-primary! data-active:hover:text-primary-foreground!',
);
