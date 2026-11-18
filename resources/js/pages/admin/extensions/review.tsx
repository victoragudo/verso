import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { VerificationReportCard } from '@/components/verso/extensions/verification-report';
import { PageHeader, SectionCard, StatusBadge } from '@/components/verso/ui';
import { formatBytes, formatList, msg, t, Trans } from '@/lib/i18n';
import { discard, install } from '@/routes/admin/extensions';
import { index } from '@/routes/admin/plugins';
import type { StagedExtension, VerificationReport } from '@/types';

const SOURCE_LABELS: Record<StagedExtension['source'], string> = {
    upload: msg('Upload'),
    registry: msg('Registry'),
};

type Props = {
    staged: StagedExtension;
    capabilities: Record<string, string>;
    verification: VerificationReport | null;
    blockHighRisk: boolean;
};

// Consent screen: shows what the package is, what it may do and what the checks found before it is installed.
export default function ExtensionReview({
    staged,
    capabilities,
    verification,
    blockHighRisk,
}: Props) {
    const { package: pkg } = staged;
    const [activate, setActivate] = useState(staged.isUpdate);
    const [processing, setProcessing] = useState(false);
    const [confirmSlug, setConfirmSlug] = useState('');
    const [error, setError] = useState<string | undefined>();
    const isPlugin = pkg.kind === 'plugin';
    const blockingRules = verification?.blocking ?? [];
    const isHighRisk = verification?.risk === 'high';
    // The "block high risk" switch only covers plugins. Blocking findings stop any install.
    const isBlocked =
        blockingRules.length > 0 || (isHighRisk && blockHighRisk && isPlugin);
    const needsSlug = isHighRisk && !isBlocked;
    const canAccept =
        !processing &&
        !staged.isDowngrade &&
        !isBlocked &&
        (!needsSlug || confirmSlug.trim() === pkg.slug);

    const accept = () =>
        router.post(
            install.url(staged.token),
            { activate, confirm_slug: confirmSlug },
            {
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onError: (errors) => setError(errors.confirm_slug),
            },
        );

    const cancel = () => router.delete(discard.url(staged.token));

    return (
        <>
            <Head title={t('Review {name}', { name: pkg.name })} />
            <PageHeader
                icon={isPlugin ? 'plugins' : 'themes'}
                title={t('Review {name}', { name: pkg.name })}
                description={t(
                    'Check the details and permissions. Nothing is installed until you accept.',
                )}
            />

            <div className="grid gap-4 @3xl/page:grid-cols-[minmax(0,1fr)_clamp(20rem,24cqi,30rem)]">
                <div className="flex min-w-0 flex-col gap-4">
                    {verification && (
                        <VerificationReportCard
                            report={verification}
                            capabilities={capabilities}
                        />
                    )}

                    <SectionCard
                        title={t('Permissions')}
                        description={
                            isPlugin
                                ? t(
                                      'This plugin asks to act with these capabilities.',
                                  )
                                : t(
                                      'Themes only change how the public site looks.',
                                  )
                        }
                    >
                        {pkg.permissions.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                {t('No permissions requested.')}
                            </p>
                        ) : (
                            <ul className="grid gap-2 sm:grid-cols-2">
                                {pkg.permissions.map((permission) => (
                                    <li
                                        key={permission}
                                        className="rounded-md border bg-muted/50 px-3 py-2 text-sm text-body"
                                    >
                                        {capabilities[permission] ?? permission}
                                    </li>
                                ))}
                            </ul>
                        )}
                        {isPlugin && (
                            <p className="mt-3 text-sm text-muted-foreground">
                                {pkg.network.length === 0
                                    ? t('Network hosts: none declared.')
                                    : t('Network hosts: {hosts}', {
                                          hosts: formatList(
                                              pkg.network,
                                              'unit',
                                          ),
                                      })}
                            </p>
                        )}
                    </SectionCard>

                    {isPlugin && (
                        <SectionCard>
                            <p className="text-sm text-body">
                                {t(
                                    'Plugins run PHP code on your server with full access to your site. Only install plugins from people you trust.',
                                )}
                            </p>
                        </SectionCard>
                    )}
                </div>

                <SectionCard
                    title={pkg.name}
                    description={pkg.description || t('No description.')}
                    actions={
                        <StatusBadge
                            tone={staged.isUpdate ? 'info' : 'neutral'}
                        >
                            {staged.isUpdate ? t('Update') : t('New')}
                        </StatusBadge>
                    }
                    footer={
                        <>
                            <Button
                                variant="outline"
                                onClick={cancel}
                                disabled={processing}
                                className="min-h-10 md:min-h-9"
                            >
                                {t('Cancel')}
                            </Button>
                            <Button
                                onClick={accept}
                                disabled={!canAccept}
                                variant={isHighRisk ? 'destructive' : 'default'}
                                className="min-h-10 md:min-h-9"
                            >
                                {staged.isUpdate
                                    ? t('Accept and update')
                                    : t('Accept and install')}
                            </Button>
                        </>
                    }
                >
                    <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-2 text-sm">
                        <dt className="text-muted-foreground">
                            {t('Version')}
                        </dt>
                        <dd className="tabular-nums">
                            {staged.installedVersion !== null &&
                                `${staged.installedVersion} → `}
                            {pkg.version}
                        </dd>
                        {pkg.author !== '' && (
                            <>
                                <dt className="text-muted-foreground">
                                    {t('Author')}
                                </dt>
                                <dd>{pkg.author}</dd>
                            </>
                        )}
                        <dt className="text-muted-foreground">{t('Needs')}</dt>
                        <dd>
                            {t('Verso {requires}', { requires: pkg.requires })}
                        </dd>
                        <dt className="text-muted-foreground">{t('Files')}</dt>
                        <dd className="tabular-nums">
                            {t(
                                '{count, plural, one {# file} other {# files}} ({size})',
                                {
                                    count: pkg.fileCount,
                                    size: formatBytes(pkg.unpackedBytes),
                                },
                            )}
                        </dd>
                        <dt className="text-muted-foreground">{t('Source')}</dt>
                        <dd>{t(SOURCE_LABELS[staged.source])}</dd>
                        <dt className="text-muted-foreground">sha256</dt>
                        <dd className="font-mono text-xs break-all">
                            {staged.sha256}
                        </dd>
                    </dl>

                    {isBlocked && (
                        <p className="mt-4 rounded-md bg-danger-soft px-3 py-2 text-sm text-danger-text">
                            {blockingRules.length > 0
                                ? t(
                                      '{kind, select, theme {This theme cannot be installed: {rules}. Fix the findings and upload it again.} other {This plugin cannot be installed: {rules}. Fix the findings and upload it again.}}',
                                      {
                                          kind: pkg.kind,
                                          rules: blockingRules.join(', '),
                                      },
                                  )
                                : t(
                                      'This plugin is high risk, and this site blocks high risk plugins.',
                                  )}
                        </p>
                    )}

                    {needsSlug && (
                        <div className="mt-4 grid gap-2">
                            <p className="rounded-md bg-danger-soft px-3 py-2 text-sm text-danger-text">
                                {t(
                                    '{kind, select, theme {This theme is high risk. Read the findings before you install it.} other {This plugin is high risk. Read the findings before you install it.}}',
                                    { kind: pkg.kind },
                                )}
                            </p>
                            <Label htmlFor="confirm-slug">
                                <Trans
                                    text={t(
                                        'Type <code>{slug}</code> to confirm',
                                        {
                                            slug: pkg.slug,
                                        },
                                    )}
                                    components={{
                                        code: (slug) => (
                                            <code className="rounded bg-muted px-1 py-0.5 text-xs text-foreground">
                                                {slug}
                                            </code>
                                        ),
                                    }}
                                />
                            </Label>
                            <Input
                                id="confirm-slug"
                                value={confirmSlug}
                                autoComplete="off"
                                spellCheck={false}
                                onChange={(event) =>
                                    setConfirmSlug(event.target.value)
                                }
                            />
                            <InputError message={error} />
                        </div>
                    )}

                    {staged.isDowngrade ? (
                        <p className="mt-4 rounded-md bg-danger-soft px-3 py-2 text-sm text-danger-text">
                            {t('This version is older than the installed one.')}
                        </p>
                    ) : (
                        !staged.isUpdate && (
                            <div className="mt-4 flex items-center gap-2">
                                <Checkbox
                                    id="activate"
                                    checked={activate}
                                    onCheckedChange={(value) =>
                                        setActivate(value === true)
                                    }
                                />
                                <Label htmlFor="activate">
                                    {isPlugin
                                        ? t('Enable after install')
                                        : t('Activate after install')}
                                </Label>
                            </div>
                        )
                    )}
                </SectionCard>
            </div>
        </>
    );
}

ExtensionReview.layout = {
    breadcrumbs: [
        {
            title: msg('Plugins and themes'),
            href: index({ query: { tab: 'add' } }),
        },
        { title: msg('Review'), href: index({ query: { tab: 'add' } }) },
    ],
};
