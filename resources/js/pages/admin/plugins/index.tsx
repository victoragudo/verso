import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { RemoveExtensionDialog } from '@/components/verso/extensions/remove-extension-dialog';
import type { RemovableExtension } from '@/components/verso/extensions/remove-extension-dialog';
import {
    EmptyState,
    FilterTabs,
    PageHeader,
    SectionCard,
    StatusBadge,
} from '@/components/verso/ui';
import type { StatusTone } from '@/components/verso/ui';
import {
    BrowseList,
    UploadCard,
    VerificationCard,
} from '@/components/verso/extensions/add-extension';
import type { VerificationOptions } from '@/components/verso/extensions/add-extension';
import { RiskBadge } from '@/components/verso/extensions/verification-report';
import { msg, t, Trans } from '@/lib/i18n';
import { disable, enable, index, show } from '@/routes/admin/plugins';
import { index as themesIndex, show as showTheme } from '@/routes/admin/themes';
import type {
    ExtensionKind,
    InstalledTheme,
    PluginInfo,
    PluginStatus,
    RegistryListing,
} from '@/types';

type Tab = 'plugins' | 'themes' | 'add' | 'browse';

type AddProps = {
    kinds: ExtensionKind[];
    maxUploadKb: number;
    verification: VerificationOptions;
};

type Props = {
    tab: Tab;
    tabs: Tab[];
    plugins: PluginInfo[];
    themes: InstalledTheme[];
    add: AddProps | null;
    registry: RegistryListing | null;
    capabilities: Record<string, string>;
    versoVersion: string;
};

// Badge tone for each plugin state.
export const STATUS_TONES: Record<PluginStatus, StatusTone> = {
    enabled: 'success',
    disabled: 'neutral',
    invalid: 'danger',
    incompatible: 'warning',
};

export const STATUS_LABELS: Record<PluginStatus, string> = {
    enabled: msg('Enabled'),
    disabled: msg('Disabled'),
    invalid: msg('Invalid'),
    incompatible: msg('Incompatible'),
};

const TAB_LABELS: Record<Tab, string> = {
    plugins: msg('Installed plugins'),
    themes: msg('Installed themes'),
    add: msg('Add new'),
    browse: msg('Browse'),
};

// One page for everything you install: plugins, themes, uploads and the registry.
export default function PluginsIndex({
    tab,
    tabs,
    plugins,
    themes,
    add,
    registry,
    capabilities,
    versoVersion,
}: Props) {
    const [removing, setRemoving] = useState<RemovableExtension | null>(null);

    return (
        <>
            <Head title={t('Plugins and themes')} />
            <PageHeader
                icon="plugins"
                title={t('Plugins and themes')}
                description={t(
                    'Plugins add features and themes change the look of your site. This site runs Verso {version}.',
                    { version: versoVersion },
                )}
                actions={
                    tab !== 'add' && (
                        <Button asChild className="min-h-10 md:min-h-9">
                            <Link href={index.url({ query: { tab: 'add' } })}>
                                {t('Add new')}
                            </Link>
                        </Button>
                    )
                }
            />

            <div className="mb-4">
                <FilterTabs
                    label={t('Plugins and themes')}
                    active={tab}
                    tabs={tabs.map((key) => ({
                        key,
                        label: t(TAB_LABELS[key]),
                        href: index.url({
                            query: { tab: key === 'plugins' ? undefined : key },
                        }),
                        count: key === 'plugins' ? plugins.length : undefined,
                    }))}
                />
            </div>

            {tab === 'plugins' && (
                <PluginList
                    plugins={plugins}
                    capabilities={capabilities}
                    onRemove={(plugin) =>
                        setRemoving({
                            kind: 'plugin',
                            slug: plugin.slug,
                            name: plugin.name,
                        })
                    }
                />
            )}

            {tab === 'themes' && (
                <ThemeList
                    themes={themes}
                    onRemove={(theme) =>
                        setRemoving({
                            kind: 'theme',
                            slug: theme.slug,
                            name: theme.name,
                        })
                    }
                />
            )}

            {tab === 'add' && add && (
                <UploadCard kinds={add.kinds} maxUploadKb={add.maxUploadKb} />
            )}

            {tab === 'browse' && <BrowseList listing={registry} />}

            {(tab === 'add' || tab === 'browse') &&
                add?.kinds.includes('plugin') && (
                    <VerificationCard options={add.verification} />
                )}

            <RemoveExtensionDialog
                extension={removing}
                onClose={() => setRemoving(null)}
            />
        </>
    );
}

// The installed plugins, or an empty state.
function PluginList({
    plugins,
    capabilities,
    onRemove,
}: {
    plugins: PluginInfo[];
    capabilities: Record<string, string>;
    onRemove: (plugin: PluginInfo) => void;
}) {
    const toggle = (plugin: PluginInfo) => {
        const action = plugin.enabled ? disable : enable;
        router.post(action.url(plugin.slug), {}, { preserveScroll: true });
    };

    if (plugins.length === 0) {
        return (
            <SectionCard>
                <EmptyState
                    icon="plugins"
                    title={t('No plugins found.')}
                    description={
                        <Trans
                            text={t('Create one with <code>{command}</code>.', {
                                command: 'php artisan verso:make-plugin',
                            })}
                            components={{
                                code: (command) => (
                                    <code className="rounded bg-muted px-1 py-0.5 text-xs text-foreground">
                                        {command}
                                    </code>
                                ),
                            }}
                        />
                    }
                />
            </SectionCard>
        );
    }

    return (
        <div className="auto-grid gap-4 [--grid-min:24rem]">
            {plugins.map((plugin) => (
                <PluginCard
                    key={plugin.slug}
                    plugin={plugin}
                    capabilities={capabilities}
                    onToggle={() => toggle(plugin)}
                    onRemove={() => onRemove(plugin)}
                />
            ))}
        </div>
    );
}

// The installed themes. Activating and customizing stay on Design, Themes.
function ThemeList({
    themes,
    onRemove,
}: {
    themes: InstalledTheme[];
    onRemove: (theme: InstalledTheme) => void;
}) {
    if (themes.length === 0) {
        return (
            <SectionCard>
                <EmptyState icon="themes" title={t('No themes found.')} />
            </SectionCard>
        );
    }

    return (
        <div className="auto-grid gap-4 [--grid-min:24rem]">
            {themes.map((theme) => (
                <SectionCard
                    key={theme.slug}
                    className="flex flex-col"
                    contentClassName="flex-1"
                    title={
                        <span className="flex flex-wrap items-baseline gap-x-2">
                            {theme.name}
                            <span className="text-xs font-normal text-muted-foreground tabular-nums">
                                v{theme.version}
                            </span>
                        </span>
                    }
                    description={theme.description || t('No description.')}
                    actions={
                        <span className="flex flex-wrap justify-end gap-1.5">
                            {theme.risk !== null && (
                                <RiskBadge risk={theme.risk} />
                            )}
                            {theme.active && (
                                <StatusBadge tone="success">
                                    {t('Active')}
                                </StatusBadge>
                            )}
                        </span>
                    }
                    footer={
                        <>
                            <span className="me-auto min-w-0 truncate text-xs text-muted-foreground">
                                {theme.parentName
                                    ? t('Child of {name}', {
                                          name: theme.parentName,
                                      })
                                    : theme.slug}
                            </span>
                            {theme.removable && (
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    className="min-h-10 md:min-h-8"
                                    onClick={() => onRemove(theme)}
                                >
                                    {t('Remove')}
                                </Button>
                            )}
                            <Button
                                asChild
                                size="sm"
                                variant="ghost"
                                className="min-h-10 md:min-h-8"
                            >
                                <Link href={showTheme.url(theme.slug)}>
                                    {theme.risk === null
                                        ? t('Check')
                                        : t('Details')}
                                </Link>
                            </Button>
                            <Button
                                asChild
                                size="sm"
                                variant="outline"
                                className="min-h-10 md:min-h-8"
                            >
                                <Link href={themesIndex.url()}>
                                    {theme.active
                                        ? t('Customize')
                                        : t('Activate')}
                                </Link>
                            </Button>
                        </>
                    }
                >
                    <p className="text-sm text-muted-foreground">
                        {t('Activate and customize themes in Design, Themes.')}
                    </p>
                </SectionCard>
            ))}
        </div>
    );
}

// One plugin: name, version, status, permissions and the toggle button.
function PluginCard({
    plugin,
    capabilities,
    onToggle,
    onRemove,
}: {
    plugin: PluginInfo;
    capabilities: Record<string, string>;
    onToggle: () => void;
    onRemove: () => void;
}) {
    const canToggle = plugin.enabled || plugin.status === 'disabled';

    return (
        <SectionCard
            className="flex flex-col"
            contentClassName="flex-1"
            title={
                <span className="flex flex-wrap items-baseline gap-x-2">
                    <Link
                        href={show.url(plugin.slug)}
                        className="hover:underline focus-visible:underline"
                    >
                        {plugin.name}
                    </Link>
                    {plugin.version && (
                        <span className="text-xs font-normal text-muted-foreground tabular-nums">
                            v{plugin.version}
                        </span>
                    )}
                </span>
            }
            description={plugin.description || t('No description.')}
            actions={
                <div className="flex flex-wrap items-center gap-1.5">
                    {plugin.risk && <RiskBadge risk={plugin.risk} />}
                    <StatusBadge tone={STATUS_TONES[plugin.status]}>
                        {t(STATUS_LABELS[plugin.status])}
                    </StatusBadge>
                </div>
            }
            footer={
                <>
                    <span className="me-auto min-w-0 truncate text-xs text-muted-foreground">
                        {plugin.requires
                            ? t('Needs Verso {version}', {
                                  version: plugin.requires,
                              })
                            : plugin.slug}
                    </span>
                    {plugin.removable && (
                        <Button
                            size="sm"
                            variant="ghost"
                            className="min-h-10 md:min-h-8"
                            onClick={onRemove}
                        >
                            {t('Remove')}
                        </Button>
                    )}
                    {canToggle && (
                        <Button
                            size="sm"
                            className="min-h-10 md:min-h-8"
                            variant={plugin.enabled ? 'outline' : 'default'}
                            onClick={onToggle}
                        >
                            {plugin.enabled ? t('Disable') : t('Enable')}
                        </Button>
                    )}
                </>
            }
        >
            <div className="grid gap-4">
                <div className="grid gap-2 text-sm">
                    <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                        {t('Permissions')}
                    </span>
                    {plugin.permissions.length === 0 ? (
                        <span className="text-muted-foreground">
                            {t('This plugin asks for no permissions.')}
                        </span>
                    ) : (
                        <div className="flex flex-wrap gap-1.5">
                            {plugin.permissions.map((permission) => (
                                <span
                                    key={permission}
                                    className="rounded-md border bg-muted/50 px-2 py-0.5 text-xs text-body"
                                >
                                    {capabilities[permission] ?? permission}
                                </span>
                            ))}
                        </div>
                    )}
                </div>

                {plugin.location && (
                    <p className="text-sm">
                        <span className="text-muted-foreground">
                            {t('Where it is:')}{' '}
                        </span>
                        {plugin.location.href ? (
                            <Link
                                href={plugin.location.href}
                                className="font-medium text-brand hover:underline"
                            >
                                {plugin.location.path.join(' > ')}
                            </Link>
                        ) : (
                            <span className="font-medium text-foreground">
                                {plugin.location.path.join(' > ')}
                            </span>
                        )}
                    </p>
                )}

                {plugin.problems.length > 0 && (
                    <ul className="list-disc rounded-md bg-danger-soft py-2 ps-7 pe-3 text-sm text-danger-text">
                        {plugin.problems.map((problem) => (
                            <li key={problem}>{problem}</li>
                        ))}
                    </ul>
                )}
            </div>
        </SectionCard>
    );
}

PluginsIndex.layout = {
    breadcrumbs: [
        {
            title: msg('Plugins and themes'),
            href: index(),
        },
    ],
};
