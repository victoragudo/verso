import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { ConfirmStarterDialog } from '@/components/verso/easy/confirm-starter-dialog';
import { StarterCard } from '@/components/verso/easy/starter-card';
import { PageHeader } from '@/components/verso/ui';
import { t } from '@/lib/i18n';
import { plainVisitErrors } from '@/lib/easy/plain-errors';
import type { StarterSummary } from '@/lib/easy/starters';
import { store } from '@/routes/admin/easy/start';
import { TOUCH_TARGET } from '@/components/verso/easy/touch';

type Props = {
    starters: StarterSummary[];
    current: string | null;
    hasPages: boolean;
};

// Guided start: "What is your site for?" Each answer adds a complete starter site.
export default function GuidedStart({ starters, current, hasPages }: Props) {
    const { errors } = usePage<{ errors?: Record<string, string> }>().props;
    const [selected, setSelected] = useState<string>(current ?? '');
    const [confirming, setConfirming] = useState(false);
    const [processing, setProcessing] = useState(false);
    const [requestError, setRequestError] = useState<string | null>(null);
    const chosen = starters.find((starter) => starter.key === selected);
    const error = requestError ?? errors?.starter;

    const install = () => {
        setConfirming(false);
        setRequestError(null);
        router.post(
            store.url(),
            { starter: selected },
            {
                ...plainVisitErrors(setRequestError, {
                    retry: t('Build my site'),
                }),
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (!chosen || processing) {
            return;
        }

        if (hasPages) {
            setConfirming(true);

            return;
        }

        install();
    };

    return (
        <>
            <Head title={t('Guided start')} />
            <PageHeader
                icon="starter"
                title={t('What is your site for?')}
                description={t(
                    'Pick the closest match. We add pages, menus, sample text and pictures. You can change all of it later.',
                )}
            />

            <form onSubmit={submit} aria-busy={processing}>
                <fieldset
                    aria-describedby={error ? 'starter-error' : undefined}
                >
                    <legend className="sr-only">{t('Kind of site')}</legend>
                    <div className="auto-fit-grid gap-4 [--grid-min:17rem]">
                        {starters.map((starter) => (
                            <StarterCard
                                key={starter.key}
                                starter={starter}
                                checked={selected === starter.key}
                                current={current === starter.key}
                                disabled={processing}
                                onSelect={setSelected}
                            />
                        ))}
                    </div>
                </fieldset>

                {error && (
                    <p
                        id="starter-error"
                        role="alert"
                        className="mt-3 text-sm font-medium text-danger-text"
                    >
                        {error}
                    </p>
                )}

                <div className="sticky bottom-0 z-10 mt-6 flex flex-col gap-3 border-t bg-background/95 py-4 backdrop-blur-sm sm:flex-row sm:items-center sm:justify-between">
                    <p
                        className="text-sm text-muted-foreground"
                        aria-live="polite"
                    >
                        {processing
                            ? t('Building your site. This takes a few seconds.')
                            : chosen
                              ? t('You picked: {site}.', { site: chosen.label })
                              : t('Pick one kind of site above.')}
                    </p>
                    <Button
                        type="submit"
                        disabled={!chosen || processing}
                        className={TOUCH_TARGET}
                    >
                        {processing
                            ? t('Building your site')
                            : t('Build my site')}
                    </Button>
                </div>
            </form>

            <ConfirmStarterDialog
                open={confirming}
                starterLabel={chosen?.label ?? ''}
                onConfirm={install}
                onClose={() => setConfirming(false)}
            />
        </>
    );
}
