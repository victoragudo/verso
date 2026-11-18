import { formatNumber, t } from '@/lib/i18n';
import { usePage } from '@inertiajs/react';
import { useEffect, useId, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { HelpIllustration } from '@/components/verso/easy/help-illustrations';
import { SimpleModeSwitch } from '@/components/verso/easy/simple-mode-switch';
import {
    isTopicForRoute,
    loadHelpTopics,
    orderTopics,
    resultsMessage,
    searchTopics,
} from '@/lib/easy/help-topics';
import type { HelpTopic } from '@/lib/easy/help-topics';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCloseAutoFocus?: (event: Event) => void;
};

type LoadState =
    | { status: 'loading' }
    | { status: 'ready'; topics: HelpTopic[] }
    | { status: 'failed' };

// The help panel: short illustrated steps, the topics for the current page first, a search box and the simple mode switch.
export function HelpPanel({ open, onOpenChange, onCloseAutoFocus }: Props) {
    const { routeName, simpleMode } = usePage().props;
    const [state, setState] = useState<LoadState>({ status: 'loading' });
    const [query, setQuery] = useState('');
    const [attempt, setAttempt] = useState(0);
    const searchId = useId();

    useEffect(() => {
        if (!open) {
            return;
        }

        let active = true;

        loadHelpTopics().then(
            (topics) => active && setState({ status: 'ready', topics }),
            () => active && setState({ status: 'failed' }),
        );

        return () => {
            active = false;
        };
    }, [open, attempt]);

    const topics = useMemo(
        () =>
            state.status === 'ready'
                ? searchTopics(orderTopics(state.topics, routeName), query)
                : [],
        [state, routeName, query],
    );

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="right"
                onCloseAutoFocus={onCloseAutoFocus}
                className="w-full gap-0 overflow-y-auto overscroll-contain sm:max-w-md"
            >
                <SheetHeader className="border-b px-5 pt-5 pb-4">
                    <SheetTitle>{t('Help')}</SheetTitle>
                    <SheetDescription>
                        {t(
                            'Short steps for the things people do most. Help for this page comes first.',
                        )}
                    </SheetDescription>
                    <search className="mt-3">
                        <label htmlFor={searchId} className="sr-only">
                            {t('Search help')}
                        </label>
                        <Input
                            id={searchId}
                            type="search"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder={t(
                                'Search help, like “menu” or “logo”',
                            )}
                            autoComplete="off"
                            enterKeyHint="search"
                        />
                    </search>
                    <p role="status" className="sr-only">
                        {state.status === 'ready'
                            ? resultsMessage(topics.length, query)
                            : ''}
                    </p>
                </SheetHeader>

                <div className="flex-1 px-5 py-4">
                    {state.status === 'loading' && (
                        <p className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Spinner /> {t('Loading help')}
                        </p>
                    )}

                    {state.status === 'failed' && (
                        <div role="alert" className="grid gap-3 text-sm">
                            <p>
                                {t(
                                    'Help could not be loaded. Check your connection and try again.',
                                )}
                            </p>
                            <Button
                                variant="outline"
                                className="min-h-11 w-fit pointer-fine:md:min-h-9"
                                onClick={() => {
                                    setState({ status: 'loading' });
                                    setAttempt((value) => value + 1);
                                }}
                            >
                                {t('Try again')}
                            </Button>
                        </div>
                    )}

                    {state.status === 'ready' && topics.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            {t(
                                'Nothing found. Try another word, like “page”, “photo” or “colors”.',
                            )}
                        </p>
                    )}

                    {state.status === 'ready' && topics.length > 0 && (
                        <ul
                            className="grid gap-2"
                            aria-label={t('Help topics')}
                        >
                            {topics.map((topic, index) => (
                                <li key={topic.key}>
                                    <HelpTopicItem
                                        topic={topic}
                                        forThisPage={isTopicForRoute(
                                            topic,
                                            routeName,
                                        )}
                                        defaultOpen={
                                            index === 0 &&
                                            (query !== '' ||
                                                isTopicForRoute(
                                                    topic,
                                                    routeName,
                                                ))
                                        }
                                    />
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                {simpleMode != null && (
                    <div className="border-t px-5 py-4">
                        <SimpleModeSwitch enabled={simpleMode} />
                    </div>
                )}
            </SheetContent>
        </Sheet>
    );
}

// One topic as a disclosure: the title and summary, then the drawing and the steps.
export function HelpTopicItem({
    topic,
    forThisPage,
    defaultOpen,
}: {
    topic: HelpTopic;
    forThisPage: boolean;
    defaultOpen: boolean;
}) {
    return (
        <details
            open={defaultOpen}
            className="group rounded-lg border bg-card shadow-xs open:pb-4"
        >
            <summary className="flex min-h-12 cursor-pointer list-none items-start gap-2 rounded-lg px-4 py-3 outline-none hover:bg-muted/50 focus-visible:ring-[3px] focus-visible:ring-ring/50 [&::-webkit-details-marker]:hidden">
                <svg
                    viewBox="0 0 20 20"
                    className="mt-0.5 size-4 shrink-0 -rotate-90 text-muted-foreground transition-transform group-open:rotate-0 motion-reduce:transition-none rtl:rotate-90"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth={1.5}
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    aria-hidden="true"
                >
                    <path d="M5 8l5 5 5-5" />
                </svg>
                <span className="min-w-0 flex-1">
                    <span className="block text-sm font-medium text-foreground">
                        {topic.title}
                    </span>
                    <span className="mt-0.5 block text-sm text-muted-foreground">
                        {topic.summary}
                    </span>
                </span>
                {forThisPage && (
                    <span className="shrink-0 rounded-full bg-brand-soft px-2 py-0.5 text-xs font-medium text-brand">
                        {t('This page')}
                    </span>
                )}
            </summary>
            <div className="px-4">
                <HelpIllustration
                    name={topic.illustration}
                    className="mx-auto my-2 h-auto w-full max-w-72 text-muted-foreground"
                />
                <ol className="grid list-none gap-2 text-sm text-foreground">
                    {topic.steps.map((step, index) => (
                        <li key={step} className="flex gap-2.5">
                            <span
                                aria-hidden="true"
                                className="flex size-6 shrink-0 items-center justify-center rounded-full border text-xs font-medium text-muted-foreground"
                            >
                                {formatNumber(index + 1)}
                            </span>
                            <span className="pt-0.5">{step}</span>
                        </li>
                    ))}
                </ol>
                {topic.video && (
                    <a
                        href={topic.video}
                        target="_blank"
                        rel="noreferrer noopener"
                        className="mt-3 inline-flex min-h-11 items-center text-sm font-medium text-brand hover:underline pointer-fine:md:min-h-8"
                    >
                        {t('Watch a short video (opens in a new tab)')}
                    </a>
                )}
            </div>
        </details>
    );
}
