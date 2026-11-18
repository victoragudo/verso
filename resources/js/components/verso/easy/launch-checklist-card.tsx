import { t } from '@/lib/i18n';
import { Link, router } from '@inertiajs/react';
import { useId, useState } from 'react';
import { Button } from '@/components/ui/button';
import { SectionCard } from '@/components/verso/ui';
import {
    isComplete,
    nextStep,
    progressLabel,
} from '@/lib/easy/launch-checklist';
import type {
    LaunchCheckItem,
    LaunchChecklistData,
} from '@/lib/easy/launch-checklist';
import { cn } from '@/lib/utils';
import { TOUCH_TARGET } from './touch';

// The launch checklist on the dashboard: a progress bar and one row per step, each with a "Do it" link.
export function LaunchChecklistCard({ data }: { data: LaunchChecklistData }) {
    const progressId = useId();
    const [busy, setBusy] = useState<string | null>(null);
    const complete = isComplete(data);
    const next = nextStep(data.items);

    const post = (key: string, href: string) => {
        router.post(
            href,
            {},
            {
                preserveScroll: true,
                onStart: () => setBusy(key),
                onFinish: () => setBusy(null),
            },
        );
    };

    return (
        <SectionCard
            title={t('Launch checklist')}
            icon="launch"
            description={
                complete
                    ? t('Every step is done. You can hide this list now.')
                    : t('A few steps to make your site ready for visitors.')
            }
            className="mb-6"
            actions={
                complete && (
                    <Button
                        variant="outline"
                        className={TOUCH_TARGET}
                        disabled={busy !== null}
                        onClick={() => post('dismiss', data.dismissUrl)}
                    >
                        {t('Hide checklist')}
                    </Button>
                )
            }
            contentClassName="p-0 sm:p-0"
        >
            <div className="flex flex-wrap items-center gap-x-4 gap-y-2 border-b px-4 py-3 sm:px-5">
                <label
                    htmlFor={progressId}
                    className="text-sm font-medium text-foreground"
                >
                    {progressLabel(data.done, data.total)}
                </label>
                <progress
                    id={progressId}
                    value={data.done}
                    max={Math.max(1, data.total)}
                    className="h-2 min-w-40 flex-1 appearance-none overflow-clip rounded-full border-0 bg-muted [&::-moz-progress-bar]:rounded-full [&::-moz-progress-bar]:bg-brand [&::-webkit-progress-bar]:bg-transparent [&::-webkit-progress-value]:rounded-full [&::-webkit-progress-value]:bg-brand [&::-webkit-progress-value]:transition-[inline-size] motion-reduce:[&::-webkit-progress-value]:transition-none"
                />
            </div>
            <ol className="divide-y" aria-label={t('Steps')}>
                {data.items.map((item) => (
                    <li key={item.key}>
                        <CheckRow
                            item={item}
                            isNext={next?.key === item.key}
                            busy={busy === item.key}
                            disabled={busy !== null}
                            onPost={() => post(item.key, item.href)}
                        />
                    </li>
                ))}
            </ol>
        </SectionCard>
    );
}

function CheckRow({
    item,
    isNext,
    busy,
    disabled,
    onPost,
}: {
    item: LaunchCheckItem;
    isNext: boolean;
    busy: boolean;
    disabled: boolean;
    onPost: () => void;
}) {
    const actionLabel =
        item.done && item.method === 'get' ? t('Change') : item.action;
    const variant = isNext ? 'default' : 'outline';

    return (
        <div className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:px-5">
            <div className="flex min-w-0 flex-1 items-start gap-3">
                <CheckMark done={item.done} />
                <div className="min-w-0">
                    <p
                        className={cn(
                            'text-sm font-medium',
                            item.done
                                ? 'text-muted-foreground'
                                : 'text-foreground',
                        )}
                    >
                        {item.label}
                        <span className="sr-only">
                            {' '}
                            {item.done ? t('(done)') : t('(not done yet)')}
                        </span>
                    </p>
                    <p className="mt-0.5 max-w-prose text-sm text-muted-foreground">
                        {item.description}
                    </p>
                </div>
            </div>
            {!(item.done && item.method === 'post') && (
                <div className="shrink-0 ps-9 sm:ps-0">
                    {item.method === 'post' ? (
                        <Button
                            variant={variant}
                            className={TOUCH_TARGET}
                            disabled={disabled}
                            aria-busy={busy}
                            onClick={onPost}
                        >
                            {busy ? t('Working on it') : actionLabel}
                        </Button>
                    ) : (
                        <Button
                            asChild
                            variant={item.done ? 'ghost' : variant}
                            className={TOUCH_TARGET}
                        >
                            <Link
                                href={item.href}
                                aria-label={t('{action}: {step}', {
                                    action: actionLabel,
                                    step: item.label,
                                })}
                            >
                                {actionLabel}
                            </Link>
                        </Button>
                    )}
                </div>
            )}
        </div>
    );
}

function CheckMark({ done }: { done: boolean }) {
    return (
        <svg
            viewBox="0 0 24 24"
            className={cn(
                'mt-px size-6 shrink-0',
                done ? 'text-success-text' : 'text-muted-foreground/60',
            )}
            fill="none"
            stroke="currentColor"
            strokeWidth={1.5}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
            focusable="false"
        >
            <circle cx="12" cy="12" r="9.25" />
            {done && <path d="M8 12.5l2.75 2.75L16 9.75" />}
        </svg>
    );
}
