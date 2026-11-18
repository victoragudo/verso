import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { EmptyState, SectionCard, StatusBadge } from '@/components/verso/ui';
import { formatBytes, msg, t } from '@/lib/i18n';
import { registry, settings, upload } from '@/routes/admin/extensions';
import type {
    ExtensionKind,
    RegistryExtension,
    RegistryListing,
} from '@/types';

// The plugin check settings shown under the "Add new" and "Browse" tabs.
export type VerificationOptions = {
    blockHighRisk: boolean;
    canChange: boolean;
    sandbox: boolean;
};

type UploadForm = {
    kind: ExtensionKind;
    package: File | null;
};

// Labels for each kind of package.
const KIND_LABELS: Record<ExtensionKind, string> = {
    plugin: msg('Plugin'),
    theme: msg('Theme'),
};

// Explains the plugin checks and holds the switch that blocks high risk plugins.
export function VerificationCard({
    options,
}: {
    options: VerificationOptions;
}) {
    const toggle = (checked: boolean) =>
        router.put(
            settings.url(),
            { block_high_risk: checked },
            { preserveScroll: true },
        );

    return (
        <SectionCard
            className="mt-4"
            icon="shield"
            title={t('Plugin checks')}
            description={
                options.sandbox
                    ? t(
                          'Every plugin is scanned and booted in a sandbox before the consent screen.',
                      )
                    : t(
                          'Every plugin is scanned before the consent screen. The sandbox boot is turned off.',
                      )
            }
        >
            <div className="flex items-start gap-3">
                <Checkbox
                    id="block-high-risk"
                    checked={options.blockHighRisk}
                    disabled={!options.canChange}
                    onCheckedChange={(value) => toggle(value === true)}
                    className="mt-0.5"
                />
                <div className="grid gap-1">
                    <Label htmlFor="block-high-risk">
                        {t('Block high risk plugins')}
                    </Label>
                    <p className="text-sm text-muted-foreground">
                        {options.blockHighRisk
                            ? t('High risk plugins cannot be installed.')
                            : t(
                                  'High risk plugins can be installed after you type their slug to confirm.',
                              )}
                        {!options.canChange &&
                            ` ${t('Only people who manage settings can change this.')}`}
                    </p>
                </div>
            </div>
        </SectionCard>
    );
}

// Form to send a zip file. The server checks it and opens the consent screen.
export function UploadCard({
    kinds,
    maxUploadKb,
}: {
    kinds: ExtensionKind[];
    maxUploadKb: number;
}) {
    const form = useForm<UploadForm>({ kind: kinds[0], package: null });
    const { data, setData, errors, processing } = form;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(upload.url(), { forceFormData: true });
    };

    return (
        <form onSubmit={submit}>
            <SectionCard
                icon="importer"
                title={t('Upload a zip file')}
                description={t(
                    'The zip must hold a verso.json (plugin) or theme.json (theme) file. Up to {size}.',
                    { size: formatBytes(maxUploadKb * 1024) },
                )}
                contentClassName="grid gap-4 md:grid-cols-2"
                footer={
                    <Button
                        type="submit"
                        disabled={processing || data.package === null}
                        className="min-h-10 md:min-h-9"
                    >
                        {t('Check package')}
                    </Button>
                }
            >
                <div className="grid gap-2">
                    <Label htmlFor="kind">{t('Type')}</Label>
                    <Select
                        value={data.kind}
                        onValueChange={(value) =>
                            setData('kind', value as ExtensionKind)
                        }
                    >
                        <SelectTrigger id="kind" className="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {kinds.map((kind) => (
                                <SelectItem key={kind} value={kind}>
                                    {t(KIND_LABELS[kind])}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.kind} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="package">{t('Zip file')}</Label>
                    <Input
                        id="package"
                        type="file"
                        accept=".zip,application/zip"
                        onChange={(event) =>
                            setData('package', event.target.files?.[0] ?? null)
                        }
                    />
                    {errors.package && (
                        <ul className="list-disc rounded-md bg-danger-soft py-2 ps-7 pe-3 text-sm text-danger-text">
                            {errors.package.split('\n').map((problem) => (
                                <li key={problem}>{problem}</li>
                            ))}
                        </ul>
                    )}
                </div>
            </SectionCard>
        </form>
    );
}

// Registry entries with install or update buttons.
export function BrowseList({ listing }: { listing: RegistryListing | null }) {
    if (listing === null || !listing.configured) {
        return (
            <SectionCard>
                <EmptyState
                    icon="extensions"
                    title={t('No registry is set up.')}
                    description={t(
                        'Set {variable} in your .env file to browse plugins and themes.',
                        { variable: 'VERSO_REGISTRY_URL' },
                    )}
                />
            </SectionCard>
        );
    }

    if (listing.error !== null) {
        return (
            <SectionCard>
                <EmptyState
                    icon="extensions"
                    title={t('The registry could not be loaded.')}
                    description={listing.error}
                />
            </SectionCard>
        );
    }

    if (listing.entries.length === 0) {
        return (
            <SectionCard>
                <EmptyState
                    icon="extensions"
                    title={t('The registry has no packages yet.')}
                />
            </SectionCard>
        );
    }

    return (
        <div className="auto-grid gap-4 [--grid-min:24rem]">
            {listing.entries.map((entry) => (
                <RegistryCard
                    key={`${entry.kind}:${entry.slug}`}
                    entry={entry}
                />
            ))}
        </div>
    );
}

// One registry package and its install state.
function RegistryCard({ entry }: { entry: RegistryExtension }) {
    const isInstalled = entry.installedVersion !== null;
    const isCurrent = entry.installedVersion === entry.version;
    const label = isInstalled ? t('Update') : t('Install');

    const stage = () =>
        router.post(registry.url({ kind: entry.kind, slug: entry.slug }));

    return (
        <SectionCard
            className="flex flex-col"
            contentClassName="flex-1"
            title={
                <span className="flex flex-wrap items-baseline gap-x-2">
                    {entry.name}
                    <span className="text-xs font-normal text-muted-foreground tabular-nums">
                        v{entry.version}
                    </span>
                </span>
            }
            description={entry.description || t('No description.')}
            actions={
                <StatusBadge
                    tone={entry.kind === 'plugin' ? 'info' : 'neutral'}
                >
                    {t(KIND_LABELS[entry.kind])}
                </StatusBadge>
            }
            footer={
                <>
                    <span className="me-auto min-w-0 truncate text-xs text-muted-foreground tabular-nums">
                        {isInstalled
                            ? t('Installed: v{version}', {
                                  version: entry.installedVersion,
                              })
                            : t('Not installed')}
                    </span>
                    {!isCurrent && (
                        <Button
                            size="sm"
                            className="min-h-10 md:min-h-8"
                            onClick={stage}
                        >
                            {label}
                        </Button>
                    )}
                </>
            }
        >
            <p className="font-mono text-xs break-all text-muted-foreground">
                sha256 {entry.sha256}
            </p>
        </SectionCard>
    );
}
