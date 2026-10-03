import { Link } from '@inertiajs/react';
import { RiArrowDownSLine } from '@remixicon/react';

import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { ScrollArea } from '@/components/ui/scroll-area';

type Source = { title: string; url: string; records?: number };

export function AssistantSources({
    sources,
    answer,
}: {
    sources: Source[];
    answer: string;
}) {
    const additionalSources = sources.filter(
        (source) => !answer.includes(source.url),
    );

    if (additionalSources.length === 0) {
        return null;
    }

    const list = (
        <ul className="flex flex-col gap-1 py-2">
            {additionalSources.map((source) => (
                <li key={source.url} className="min-w-0 text-sm leading-5">
                    <Link
                        href={source.url}
                        className="block truncate text-link hover:underline"
                        title={source.title}
                    >
                        {source.title}
                    </Link>
                    {source.records !== undefined && (
                        <span className="text-xs text-muted-foreground">
                            {source.records} matching{' '}
                            {source.records === 1 ? 'record' : 'records'}
                        </span>
                    )}
                </li>
            ))}
        </ul>
    );

    return (
        <Collapsible className="mt-4 border-t pt-2">
            <CollapsibleTrigger className="group flex items-center gap-1 text-xs font-medium text-muted-foreground hover:text-foreground">
                Sources ({additionalSources.length})
                <RiArrowDownSLine className="size-4 transition-transform group-data-[state=open]:rotate-180" />
            </CollapsibleTrigger>
            <CollapsibleContent>
                {additionalSources.length > 4 ? (
                    <ScrollArea className="h-40">{list}</ScrollArea>
                ) : (
                    list
                )}
            </CollapsibleContent>
        </Collapsible>
    );
}
