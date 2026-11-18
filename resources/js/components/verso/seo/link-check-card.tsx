import { router } from '@inertiajs/react';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { SectionCard, SiteDate } from '@/components/verso/ui';
import { t, Trans } from '@/lib/i18n';
import { linkCheck } from '@/routes/admin/seo';
import type { LinkCheckState } from './types';

// How often the page asks for news while a link check runs.
const POLL_MS = 5000;

// State of the background link check, with a button to start it. Reloads itself while it runs.
export function LinkCheckCard({
    state,
    canManage,
}: {
    state: LinkCheckState;
    canManage: boolean;
}) {
    useEffect(() => {
        if (!state.running) {
            return;
        }

        const timer = window.setInterval(
            () => router.reload({ only: ['linkCheck', 'checks', 'score'] }),
            POLL_MS,
        );

        return () => window.clearInterval(timer);
    }, [state.running]);

    const percent =
        state.total > 0
            ? Math.min(100, Math.round((state.checked / state.total) * 100))
            : 0;
    const finishedAt = state.finished_at;

    return (
        <SectionCard
            title={t('Link check')}
            icon="redirects"
            description={t(
                'Reads your published pages to find broken links, pages with no links to them and slow pages.',
            )}
            contentClassName="grid gap-3"
        >
            {state.running ? (
                <div className="grid gap-2">
                    <label
                        className="text-sm"
                        htmlFor="seo-link-check-progress"
                    >
                        {t(
                            'Checking {checked, number} of {total, plural, one {# page} other {# pages}}',
                            { checked: state.checked, total: state.total },
                        )}
                    </label>
                    <progress
                        id="seo-link-check-progress"
                        className="h-2 w-full overflow-hidden rounded-full accent-foreground"
                        max={100}
                        value={percent}
                    />
                </div>
            ) : (
                <p className="text-sm text-muted-foreground">
                    {finishedAt ? (
                        <>
                            <Trans
                                text={t(
                                    'Last check: <date></date> ({count, plural, one {# page} other {# pages}})',
                                    { count: state.checked },
                                )}
                                components={{
                                    date: () => <SiteDate iso={finishedAt} />,
                                }}
                            />
                        </>
                    ) : (
                        t('Links were never checked.')
                    )}
                </p>
            )}
            {canManage && (
                <div>
                    <Button
                        type="button"
                        variant="outline"
                        className="min-h-10 md:min-h-9"
                        disabled={state.running}
                        onClick={() =>
                            router.post(
                                linkCheck.url(),
                                {},
                                { preserveScroll: true },
                            )
                        }
                    >
                        {state.running ? t('Checking…') : t('Check links now')}
                    </Button>
                </div>
            )}
        </SectionCard>
    );
}
