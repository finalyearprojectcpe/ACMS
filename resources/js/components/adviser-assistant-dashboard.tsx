import { Link, useForm } from '@inertiajs/react';
import { RiAddLine, RiHistoryLine, RiSendPlane2Line } from '@remixicon/react';
import { useEffect, useRef, useState } from 'react';
import type { FormEvent, KeyboardEvent } from 'react';

import {
    reply,
    store,
} from '@/actions/App/Http/Controllers/AssistantController';
import {
    assistantConversation,
    section,
} from '@/actions/App/Http/Controllers/DashboardController';
import { Button } from '@/components/ui/button';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupButton,
    InputGroupTextarea,
} from '@/components/ui/input-group';
import { ScrollArea } from '@/components/ui/scroll-area';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { DashboardAssistant } from '@/types';

const exampleQuestions = [
    'Which registrations need my review?',
    'How many students are in my set?',
    'Who has paid their dues?',
    'What courses are in this semester?',
];

export function AdviserAssistantDashboard({
    assistant,
}: {
    assistant: DashboardAssistant;
}) {
    const form = useForm({ question: '' });
    const messagesEnd = useRef<HTMLDivElement>(null);
    const [exampleIndex, setExampleIndex] = useState(0);
    const [composerFocused, setComposerFocused] = useState(false);

    useEffect(() => {
        if (
            assistant.messages.length > 0 ||
            composerFocused ||
            form.data.question ||
            window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ) {
            return;
        }

        const interval = window.setInterval(() => {
            setExampleIndex((index) => (index + 1) % exampleQuestions.length);
        }, 5000);

        return () => window.clearInterval(interval);
    }, [assistant.messages.length, composerFocused, form.data.question]);

    useEffect(() => {
        if (assistant.messages.length > 0) {
            messagesEnd.current?.scrollIntoView({ block: 'end' });
        }
    }, [assistant.messages.length, assistant.selectedId]);

    function send(event?: FormEvent) {
        event?.preventDefault();

        if (
            form.processing ||
            !assistant.available ||
            form.data.question.trim().length < 3
        ) {
            return;
        }

        const url =
            assistant.selectedId === null
                ? store().url
                : reply({ assistantConversation: assistant.selectedId }).url;
        form.post(url, { onSuccess: () => form.reset() });
    }

    function onKeyDown(event: KeyboardEvent<HTMLTextAreaElement>) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            send();
        }
    }

    const hasMessages = assistant.messages.length > 0;
    const composer = (
        <form onSubmit={send} className="w-full">
            <InputGroup className="relative items-end rounded-md bg-background shadow-xs dark:bg-background">
                <InputGroupTextarea
                    aria-label="Ask AI"
                    value={form.data.question}
                    onChange={(event) =>
                        form.setData('question', event.target.value)
                    }
                    onKeyDown={onKeyDown}
                    onFocus={() => setComposerFocused(true)}
                    onBlur={() => setComposerFocused(false)}
                    placeholder={
                        composerFocused ? 'Ask about your records' : ''
                    }
                    rows={1}
                    maxLength={1500}
                    disabled={!assistant.available || form.processing}
                    className="field-sizing-content max-h-32 min-h-12 px-4 py-3 text-sm leading-6"
                />
                {!form.data.question && !composerFocused && (
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-y-0 right-16 left-4 flex items-center overflow-hidden text-sm text-muted-foreground"
                    >
                        <span
                            key={exampleIndex}
                            className="block w-full animate-[prompt-roll_5s_ease-in-out_both] truncate motion-reduce:animate-none"
                        >
                            {exampleQuestions[exampleIndex]}
                        </span>
                    </div>
                )}
                <InputGroupAddon
                    align="inline-end"
                    className="self-end pr-3 pb-1.5"
                >
                    <InputGroupButton
                        type="submit"
                        variant="default"
                        size="icon-sm"
                        aria-label="Send question"
                        disabled={
                            !assistant.available ||
                            form.processing ||
                            form.data.question.trim().length < 3
                        }
                        className="size-9 rounded-sm"
                    >
                        {form.processing ? <Spinner /> : <RiSendPlane2Line />}
                    </InputGroupButton>
                </InputGroupAddon>
            </InputGroup>
            {!assistant.available && (
                <p className="mt-3 text-center text-sm text-destructive">
                    AI is temporarily unavailable. Please try again later.
                </p>
            )}
            {form.errors.question && (
                <p className="mt-2 text-center text-sm text-destructive">
                    {form.errors.question}
                </p>
            )}
        </form>
    );

    return (
        <section className="flex min-h-0 w-full flex-1 flex-col bg-background">
            <div
                className={`flex h-14 shrink-0 items-center justify-between gap-3 px-4 sm:px-6 ${hasMessages ? 'border-b' : ''}`}
            >
                <div className="min-w-0">
                    {hasMessages && (
                        <h1 className="truncate text-base font-semibold">
                            {assistant.selectedId
                                ? (assistant.conversations.find(
                                      (item) =>
                                          item.id === assistant.selectedId,
                                  )?.title ?? 'Conversation')
                                : 'Conversation'}
                        </h1>
                    )}
                </div>
                <div className="flex items-center gap-1">
                    {assistant.selectedId !== null && (
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <Button asChild variant="ghost" size="icon-sm">
                                    <Link
                                        href={section({ section: 'assistant' })}
                                        aria-label="New conversation"
                                    >
                                        <RiAddLine />
                                    </Link>
                                </Button>
                            </TooltipTrigger>
                            <TooltipContent>New conversation</TooltipContent>
                        </Tooltip>
                    )}
                    <Sheet>
                        <Tooltip>
                            <TooltipTrigger asChild>
                                <SheetTrigger asChild>
                                    <Button
                                        variant="ghost"
                                        size="icon-sm"
                                        aria-label="Conversation history"
                                    >
                                        <RiHistoryLine />
                                    </Button>
                                </SheetTrigger>
                            </TooltipTrigger>
                            <TooltipContent>
                                Conversation history
                            </TooltipContent>
                        </Tooltip>
                        <SheetContent className="sm:max-w-md">
                            <SheetHeader className="border-b px-5 py-5">
                                <SheetTitle>Conversation History</SheetTitle>
                                <SheetDescription className="sr-only">
                                    Open an earlier conversation.
                                </SheetDescription>
                            </SheetHeader>
                            <ScrollArea className="min-h-0 flex-1">
                                <nav
                                    className="flex flex-col p-2"
                                    aria-label="Conversation history"
                                >
                                    {assistant.conversations.length ? (
                                        assistant.conversations.map(
                                            (conversation) => (
                                                <Link
                                                    key={conversation.id}
                                                    href={assistantConversation(
                                                        {
                                                            assistantConversation:
                                                                conversation.id,
                                                        },
                                                    )}
                                                    className={`flex flex-col gap-1 border-b px-4 py-3 text-sm hover:bg-muted/60 ${conversation.id === assistant.selectedId ? 'bg-muted font-semibold' : ''}`}
                                                >
                                                    <span className="line-clamp-2">
                                                        {conversation.title}
                                                    </span>
                                                    <span className="text-xs font-normal text-muted-foreground">
                                                        {conversation.updatedAt}
                                                    </span>
                                                </Link>
                                            ),
                                        )
                                    ) : (
                                        <p className="p-4 text-sm text-muted-foreground">
                                            No conversations yet.
                                        </p>
                                    )}
                                </nav>
                            </ScrollArea>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>
            {hasMessages ? (
                <>
                    <ScrollArea className="min-h-0 flex-1">
                        <div className="mx-auto flex w-full max-w-3xl flex-col gap-7 px-4 py-7 sm:px-6">
                            {assistant.messages.map((message) => (
                                <div
                                    key={message.id}
                                    className={
                                        message.role === 'user'
                                            ? 'ml-auto max-w-[85%] border border-border bg-muted px-4 py-3 text-sm leading-6'
                                            : 'max-w-[95%] text-sm leading-7'
                                    }
                                >
                                    <p className="whitespace-pre-wrap">
                                        {message.content}
                                    </p>
                                    {message.role === 'assistant' &&
                                        message.sources.length > 0 && (
                                            <div className="mt-4 border-t pt-3">
                                                <div className="mb-1 text-xs font-medium text-muted-foreground">
                                                    Sources
                                                </div>
                                                <div className="flex flex-wrap gap-x-4 gap-y-1">
                                                    {message.sources
                                                        .slice(0, 6)
                                                        .map((source) => (
                                                            <Link
                                                                key={source.url}
                                                                href={
                                                                    source.url
                                                                }
                                                                className="text-link hover:underline"
                                                            >
                                                                {source.title}
                                                            </Link>
                                                        ))}
                                                </div>
                                            </div>
                                        )}
                                </div>
                            ))}
                            {form.processing && (
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <Spinner /> Checking your records...
                                </div>
                            )}
                            <div ref={messagesEnd} />
                        </div>
                    </ScrollArea>
                    <div className="shrink-0 border-t bg-background px-4 py-3 sm:px-6">
                        <div className="mx-auto w-full max-w-3xl">
                            {composer}
                        </div>
                    </div>
                </>
            ) : (
                <ScrollArea className="min-h-0 flex-1">
                    <div className="flex min-h-[calc(100dvh-7rem)] justify-center px-4 pt-[clamp(4rem,14dvh,11rem)] pb-6 sm:px-6">
                        <div className="flex w-full max-w-3xl flex-col gap-5">
                            <h1 className="text-center text-xl font-semibold sm:text-2xl">
                                What would you like to know?
                            </h1>
                            {composer}
                        </div>
                    </div>
                </ScrollArea>
            )}
        </section>
    );
}
