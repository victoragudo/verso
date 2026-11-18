import { Link } from '@inertiajs/react';
import {
    Check,
    CircleAlert,
    ExternalLink,
    LayoutDashboard,
    RotateCcw,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { formatPercent, t } from '@/lib/i18n';
import { cn } from '@/lib/utils';
import { InstallRequestError, installRequest } from './install-api';
import { StepFrame } from './step-frame';
import { useStepForm } from './use-step-form';

// Step 7: every choice in one place, secrets masked, then Install.
export function ReviewStep({
    summary,
    editSteps,
}: {
    summary: Record<string, Record<string, string>>;
    editSteps: Record<string, string>;
}) {
    const { form, formRef, submit } = useStepForm('review', {});

    return (
        <StepFrame
            title={t('Review')}
            description={t(
                'Check your choices. Passwords are hidden. Nothing changes on the server until you press Install.',
            )}
            back="content"
            formRef={formRef}
            onSubmit={submit}
            processing={form.processing}
            submitLabel={t('Install Verso')}
        >
            <div className="grid gap-4 sm:grid-cols-2">
                {Object.entries(summary).map(([section, rows]) => (
                    <section
                        key={section}
                        className="rounded-lg border border-zinc-200 dark:border-zinc-800"
                    >
                        <div className="flex items-center justify-between border-b border-zinc-200 px-3 py-2 dark:border-zinc-800">
                            <h3 className="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                {section}
                            </h3>
                            {editSteps[section] && (
                                <Link
                                    href={`/install/${editSteps[section]}`}
                                    className="rounded text-xs font-medium text-blue-600 hover:underline dark:text-blue-400"
                                >
                                    <span aria-hidden="true">{t('Edit')}</span>
                                    <span className="sr-only">
                                        {t('Edit {section}', { section })}
                                    </span>
                                </Link>
                            )}
                        </div>
                        <dl className="grid gap-1.5 px-3 py-2.5 text-sm">
                            {Object.entries(rows).map(([label, value]) => (
                                <div
                                    key={label}
                                    className="grid grid-cols-[minmax(0,2fr)_minmax(0,3fr)] gap-2"
                                >
                                    <dt className="text-muted-foreground">
                                        {label}
                                    </dt>
                                    <dd className="break-words text-zinc-900 dark:text-zinc-100">
                                        {value}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </section>
                ))}
            </div>
        </StepFrame>
    );
}

export type PipelineStep = {
    key: string;
    label: string;
    status: 'pending' | 'running' | 'done' | 'failed' | 'skipped';
};

export type PipelineStatus = {
    steps: PipelineStep[];
    error: { step: string; message: string } | null;
    finished: boolean;
    links?: { site: string; admin: string };
};

const RETRY_DELAY_MS = 1500;
const MAX_NETWORK_RETRIES = 8;
const CONFLICT = 409;

const wait = (ms: number) =>
    new Promise((resolve) => window.setTimeout(resolve, ms));

// Step 8: runs the install one step per request and shows live progress. A failed step can be retried.
export function InstallStep({ status: initial }: { status: PipelineStatus }) {
    const [status, setStatus] = useState<PipelineStatus>(initial);
    const [running, setRunning] = useState(false);
    const [notice, setNotice] = useState<string | null>(null);
    const started = useRef(false);

    const run = useCallback(async () => {
        setRunning(true);
        setNotice(null);
        let networkFailures = 0;

        while (true) {
            try {
                const next =
                    await installRequest<PipelineStatus>('/install/run');
                networkFailures = 0;
                setStatus(next);

                if (next.finished || next.error) {
                    break;
                }
            } catch (error) {
                networkFailures++;

                // 409: the wizard answers are gone, so trying again cannot help.
                if (
                    networkFailures > MAX_NETWORK_RETRIES ||
                    (error instanceof InstallRequestError &&
                        error.status === CONFLICT)
                ) {
                    setNotice((error as Error).message);
                    break;
                }

                setNotice(
                    t(
                        'The server is restarting with the new settings. Trying again...',
                    ),
                );
                await wait(RETRY_DELAY_MS);
                await installRequest<PipelineStatus>('/install/status', 'GET')
                    .then(setStatus)
                    .catch(() => undefined);
            }
        }

        setRunning(false);
    }, []);

    useEffect(() => {
        if (!started.current && !initial.finished) {
            started.current = true;
            void run();
        }
    }, [initial.finished, run]);

    const visible = status.steps.filter((step) => step.status !== 'skipped');
    const doneCount = visible.filter((step) => step.status === 'done').length;
    const percent = Math.round((doneCount / Math.max(1, visible.length)) * 100);

    if (status.finished && status.links) {
        return (
            <StepFrame
                title={t('Verso is installed')}
                description={t('Your site is ready. Sign in to start writing.')}
            >
                <div className="flex flex-col items-center gap-4 py-4 text-center">
                    <span className="flex size-12 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300">
                        <Check className="size-6" aria-hidden="true" />
                    </span>
                    <p className="max-w-md text-sm text-muted-foreground">
                        {t(
                            'The installer is now locked. The dashboard button signs you in once; after that, use your email and password.',
                        )}
                    </p>
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Button asChild>
                            <a href={status.links.admin}>
                                <LayoutDashboard aria-hidden="true" />
                                {t('Go to your dashboard')}
                            </a>
                        </Button>
                        <Button variant="outline" asChild>
                            <a href={status.links.site}>
                                <ExternalLink aria-hidden="true" />
                                {t('View your site')}
                            </a>
                        </Button>
                    </div>
                </div>
            </StepFrame>
        );
    }

    return (
        <StepFrame
            title={t('Installing Verso')}
            description={t('Keep this page open. It takes a few seconds.')}
        >
            <div className="grid gap-2">
                <div className="flex justify-between text-xs text-muted-foreground">
                    <span>
                        {t('{done} of {total} done', {
                            done: doneCount,
                            total: visible.length,
                        })}
                    </span>
                    <span className="tabular-nums">
                        {formatPercent(percent / 100)}
                    </span>
                </div>
                <progress
                    className="sr-only"
                    max={visible.length}
                    value={doneCount}
                    aria-label={t('Install progress')}
                />
                <div
                    className="h-1.5 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800"
                    aria-hidden="true"
                >
                    <div
                        className="h-full rounded-full bg-emerald-600 transition-[width] duration-300 motion-reduce:transition-none"
                        style={{ width: `${percent}%` }}
                    />
                </div>
            </div>
            <ol className="grid gap-1" aria-live="polite">
                {visible.map((step) => (
                    <li
                        key={step.key}
                        className="flex items-center gap-3 rounded-md px-2 py-1.5 text-sm"
                    >
                        <StepIcon
                            status={step.status}
                            active={
                                running &&
                                step.status === 'pending' &&
                                step.key ===
                                    visible.find(
                                        (item) => item.status === 'pending',
                                    )?.key
                            }
                        />
                        <span
                            className={cn(
                                step.status === 'pending'
                                    ? 'text-muted-foreground'
                                    : 'text-zinc-900 dark:text-zinc-100',
                            )}
                        >
                            {step.label}
                        </span>
                    </li>
                ))}
            </ol>
            {notice && (
                <p className="text-sm text-muted-foreground" role="status">
                    {notice}
                </p>
            )}
            {status.error && (
                <div
                    role="alert"
                    className="grid gap-3 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-900 dark:border-red-900 dark:bg-red-950 dark:text-red-100"
                >
                    <p className="font-medium">
                        {t('This step failed: {step}', {
                            step:
                                status.steps.find(
                                    (step) => step.key === status.error?.step,
                                )?.label ?? '',
                        })}
                    </p>
                    <p className="break-words">{status.error.message}</p>
                    <p>
                        {t(
                            'Fix the problem, then retry. Steps that are done stay done and your answers are kept.',
                        )}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            onClick={() => void run()}
                            disabled={running}
                        >
                            {running ? (
                                <Spinner />
                            ) : (
                                <RotateCcw aria-hidden="true" />
                            )}
                            {t('Retry this step')}
                        </Button>
                        <Button variant="outline" asChild>
                            <Link href="/install/review">
                                {t('Change my choices')}
                            </Link>
                        </Button>
                    </div>
                </div>
            )}
        </StepFrame>
    );
}

function StepIcon({
    status,
    active,
}: {
    status: PipelineStep['status'];
    active: boolean;
}) {
    if (status === 'done') {
        return (
            <Check className="size-4 text-emerald-600" aria-label={t('Done')} />
        );
    }

    if (status === 'failed') {
        return (
            <CircleAlert
                className="size-4 text-red-600"
                aria-label={t('Failed')}
            />
        );
    }

    if (status === 'running' || active) {
        return <Spinner className="size-4" aria-label={t('Running')} />;
    }

    return (
        <span
            className="mx-1 size-2 rounded-full bg-zinc-300 dark:bg-zinc-700"
            aria-label={t('Waiting')}
        />
    );
}
