import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { RemoveExtensionDialog } from '@/components/verso/extensions/remove-extension-dialog';
import type { RemovableExtension } from '@/components/verso/extensions/remove-extension-dialog';
import { VerificationReportCard } from '@/components/verso/extensions/verification-report';
import {
    EmptyState,
    PageHeader,
    SectionCard,
    StatusBadge,
} from '@/components/verso/ui';
import { formatList, msg, t } from '@/lib/i18n';
import { disable, enable, index, verify } from '@/routes/admin/plugins';
import type { PluginDetail, VerificationReport } from '@/types';
import { STATUS_LABELS, STATUS_TONES } from './index';

type Props = {
    plugin: PluginDetail;
    verification: VerificationReport | null;
    capabilities: Record<string, string>;
    versoVersion: string;
};

// One plugin: its details, permissions and the newest verification report.
export default function PluginShow({
    plugin,
    verification,
    capabilities,
    versoVersion,
}: Props) {
    const [checking, setChecking] = useState(false);
    const [removing, setRemoving] = useState<RemovableExtension | null>(null);
    const canToggle = plugin.enabled || plugin.status === 'disabled';

    const runChecks = () =>
        router.post(
            verify.url(plugin.slug),
            {},
            {
                preserveScroll: true,
                onStart: () => setChecking(true),
                onFinish: () => setChecking(false),
            },
        );

    const toggle = () =>
        router.post(
            (plugin.enabled ? disable : enable).url(plugin.slug),
            {},
            { preserveScroll: true },
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
            <Head title={plugin.name} />
            <PageHeader
                icon="plugins"
                title={plugin.name}
                description={plugin.description || t('No description.')}
                actions={
                    (canToggle || plugin.removable) && (
                        <>
                            {canToggle && (
                                <Button
                                    onClick={toggle}
                                    variant={
                                        plugin.enabled ? 'outline' : 'default'
                                    }
                                    className="min-h-10 md:min-h-9"
                                >
                                    {plugin.enabled
                                        ? t('Disable')
                                        : t('Enable')}
                                </Button>
                            )}
                            {plugin.removable && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        setRemoving({
                                            kind: 'plugin',
                                            slug: plugin.slug,
                                            name: plugin.name,
                                        })
                                    }
                                    className="min-h-10 text-danger-text md:min-h-9"
                                >
                                    {t('Remove')}
                                </Button>
                            )}
                        </>
                    )
                }
            />

            <div className="grid gap-4 @3xl/page:grid-cols-[minmax(0,1fr)_clamp(20rem,24cqi,30rem)]">
                <div className="min-w-0">
                    {verification ? (
                        <VerificationReportCard
                            report={verification}
                            capabilities={capabilities}
                            actions={checkButton}
                        />
                    ) : (
                        <SectionCard>
                            <EmptyState
                                icon="shield"
                                title={t(
                                    'This plugin has not been checked yet.',
                                )}
                                description={t(
                                    'Run the checks to scan its code, compare its permissions and boot it in a sandbox.',
                                )}
                                action={checkButton}
                            />
                        </SectionCard>
                    )}
                </div>

                <SectionCard
                    title={t('Details')}
                    actions={
                        <StatusBadge tone={STATUS_TONES[plugin.status]}>
                            {t(STATUS_LABELS[plugin.status])}
                        </StatusBadge>
                    }
                >
                    <dl className="grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-2 text-sm">
                        <dt className="text-muted-foreground">
                            {t('Version')}
                        </dt>
                        <dd className="tabular-nums">
                            {plugin.version ?? '-'}
                        </dd>
                        <dt className="text-muted-foreground">{t('Slug')}</dt>
                        <dd className="font-mono text-xs break-all">
                            {plugin.slug}
                        </dd>
                        <dt className="text-muted-foreground">{t('Needs')}</dt>
                        <dd>
                            {t('Verso {requires} (this site runs {version})', {
                                requires: plugin.requires ?? '*',
                                version: versoVersion,
                            })}
                        </dd>
                        <dt className="text-muted-foreground">
                            {t('Permissions')}
                        </dt>
                        <dd className="flex flex-wrap gap-1.5">
                            {plugin.permissions.length === 0
                                ? t('None')
                                : plugin.permissions.map((permission) => (
                                      <span
                                          key={permission}
                                          className="rounded-md border bg-muted/50 px-2 py-0.5 text-xs text-body"
                                      >
                                          {capabilities[permission] ??
                                              permission}
                                      </span>
                                  ))}
                        </dd>
                        <dt className="text-muted-foreground">
                            {t('Network')}
                        </dt>
                        <dd className="break-all">
                            {plugin.network.length === 0
                                ? t('None')
                                : formatList(plugin.network, 'unit')}
                        </dd>
                    </dl>

                    {plugin.problems.length > 0 && (
                        <ul className="mt-4 list-disc rounded-md bg-danger-soft py-2 ps-7 pe-3 text-sm text-danger-text">
                            {plugin.problems.map((problem) => (
                                <li key={problem}>{problem}</li>
                            ))}
                        </ul>
                    )}

                    <p className="mt-4 text-sm">
                        <Link
                            href={index.url()}
                            className="text-brand hover:underline"
                        >
                            {t('Back to all plugins')}
                        </Link>
                    </p>
                </SectionCard>
            </div>

            <RemoveExtensionDialog
                extension={removing}
                onClose={() => setRemoving(null)}
            />
        </>
    );
}

PluginShow.layout = {
    breadcrumbs: [
        { title: msg('Plugins'), href: index() },
        { title: msg('Plugin'), href: index() },
    ],
};
