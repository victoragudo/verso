import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { VerificationReportCard } from '@/components/verso/extensions/verification-report';
import {
    EmptyState,
    PageHeader,
    SectionCard,
    StatusBadge,
} from '@/components/verso/ui';
import { formatList, msg, t } from '@/lib/i18n';
import { activate, customize, index, verify } from '@/routes/admin/themes';
import type { ThemeDetail, VerificationReport } from '@/types';

type Props = {
    theme: ThemeDetail;
    verification: VerificationReport | null;
    versoVersion: string;
};

// One theme: its details and the newest verification report, with a button to check it again.
export default function ThemeShow({
    theme,
    verification,
    versoVersion,
}: Props) {
    const [checking, setChecking] = useState(false);
    const canActivate = !theme.active && theme.missingParent === null;

    const runChecks = () =>
        router.post(
            verify.url(theme.slug),
            {},
            {
                preserveScroll: true,
                onStart: () => setChecking(true),
                onFinish: () => setChecking(false),
            },
        );

    const [activating, setActivating] = useState(false);

    const activateTheme = () =>
        router.post(
            activate.url(theme.slug),
            {},
            {
                preserveScroll: true,
                onStart: () => setActivating(true),
                onFinish: () => setActivating(false),
            },
        );

    const checkButton = (
        <Button
            variant="outline"
            onClick={runChecks}
            disabled={checking}
            className="min-h-10 md:min-h-9"
        >
            {checking ? t('Checking…') : t('Run checks again')}
        </Button>
    );

    return (
        <>
            <Head title={theme.name} />
            <PageHeader
                icon="themes"
                title={theme.name}
                description={theme.description || t('No description.')}
                actions={
                    <>
                        {theme.customizable && (
                            <Button
                                asChild
                                variant="outline"
                                className="min-h-10 md:min-h-9"
                            >
                                <Link href={customize.url(theme.slug)}>
                                    {t('Customize')}
                                </Link>
                            </Button>
                        )}
                        {canActivate && (
                            <Button
                                onClick={activateTheme}
                                disabled={activating}
                                className="min-h-10 md:min-h-9"
                            >
                                {t('Activate')}
                            </Button>
                        )}
                    </>
                }
            />

            <div className="grid gap-4 @3xl/page:grid-cols-[minmax(0,1fr)_clamp(20rem,24cqi,30rem)]">
                <div className="min-w-0">
                    {verification ? (
                        <VerificationReportCard
                            report={verification}
                            actions={checkButton}
                        />
                    ) : (
                        <SectionCard>
                            <EmptyState
                                icon="shield"
                                title={t(
                                    'This theme has not been checked yet.',
                                )}
                                description={t(
                                    'Run the checks to look for missing views, risky code and quality problems, and to render it with demo posts in a sandbox.',
                                )}
                                action={checkButton}
                            />
                        </SectionCard>
                    )}
                </div>

                <SectionCard
                    title={t('Details')}
                    actions={
                        theme.active ? (
                            <StatusBadge tone="success">
                                {t('Active')}
                            </StatusBadge>
                        ) : (
                            <StatusBadge tone="neutral">
                                {t('Installed')}
                            </StatusBadge>
                        )
                    }
                >
                    <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-2 text-sm">
                        <dt className="text-muted-foreground">
                            {t('Version')}
                        </dt>
                        <dd className="tabular-nums">{theme.version}</dd>
                        <dt className="text-muted-foreground">{t('Slug')}</dt>
                        <dd className="font-mono text-xs break-all">
                            {theme.slug}
                        </dd>
                        {theme.author !== '' && (
                            <>
                                <dt className="text-muted-foreground">
                                    {t('Author')}
                                </dt>
                                <dd>{theme.author}</dd>
                            </>
                        )}
                        <dt className="text-muted-foreground">{t('Parent')}</dt>
                        <dd>{theme.parentName ?? theme.parent ?? t('None')}</dd>
                        <dt className="text-muted-foreground">{t('Site')}</dt>
                        <dd>Verso {versoVersion}</dd>
                    </dl>

                    {theme.missingParent !== null && (
                        <p className="mt-4 rounded-md bg-danger-soft px-3 py-2 text-sm text-danger-text">
                            {t('The parent theme {parent} is not installed.', {
                                parent: theme.missingParent,
                            })}
                        </p>
                    )}

                    {theme.missingViews.length > 0 && (
                        <div className="mt-4 rounded-md bg-warning-soft px-3 py-2 text-sm text-warning-text">
                            <p>
                                {t(
                                    'These views come from the fallback theme, because this theme does not have them:',
                                )}
                            </p>
                            <p className="mt-1 font-mono text-xs break-all">
                                {formatList(theme.missingViews, 'unit')}
                            </p>
                        </div>
                    )}

                    <p className="mt-4 text-sm">
                        <Link
                            href={index.url()}
                            className="text-brand hover:underline"
                        >
                            {t('Back to all themes')}
                        </Link>
                    </p>
                </SectionCard>
            </div>
        </>
    );
}

ThemeShow.layout = {
    breadcrumbs: [
        { title: msg('Themes'), href: index() },
        { title: msg('Theme'), href: index() },
    ],
};
